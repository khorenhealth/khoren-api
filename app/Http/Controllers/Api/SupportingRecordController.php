<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UploadSupportingRecordRequest;
use App\Http\Resources\SupportingRecordResource;
use App\Models\PatientProfile;
use App\Models\SupportingRecord;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

#[Group('Profile', weight: 3)]
class SupportingRecordController extends Controller
{
    /**
     * Store one supporting record for the authenticated patient.
     */
    #[Endpoint(
        title: 'Upload supporting record',
        description: 'Uploads one step 4 supporting record. Requires a Sanctum Bearer token for a verified account, and personal information must already be saved. Send multipart/form-data with a PDF, JPG, or PNG up to 10 MB in the file field. Each upload is added to the list. Files are stored privately and can be viewed only by this account through GET /api/profile/records/{id}.',
    )]
    #[Response(201, description: 'Supporting record stored.')]
    #[Response(401, description: 'Missing or invalid token.')]
    #[Response(403, description: 'The email address is not verified.')]
    #[Response(422, description: 'The file is missing, is not a PDF, JPG, or PNG, is larger than 10 MB, or personal information has not been saved.')]
    public function store(UploadSupportingRecordRequest $request): JsonResponse
    {
        $user = $this->verifiedUser($request);
        $profile = $user->patientProfile;

        if (! $profile instanceof PatientProfile) {
            throw ValidationException::withMessages([
                'file' => 'Save your personal information before uploading a supporting record.',
            ]);
        }

        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => 'Supporting record could not be saved.',
            ]);
        }

        $storedPath = $file->store(SupportingRecord::DIRECTORY, SupportingRecord::DISK);

        if (! is_string($storedPath)) {
            throw ValidationException::withMessages([
                'file' => 'Supporting record could not be saved.',
            ]);
        }

        try {
            $size = $file->getSize();
            $mimeType = $file->getMimeType();

            $record = $profile->supportingRecords()->create([
                'original_name' => $this->displayName($file),
                'path' => $storedPath,
                'mime_type' => is_string($mimeType) ? $mimeType : null,
                'size' => is_int($size) ? $size : 0,
            ]);
        } catch (Throwable $exception) {
            Storage::disk(SupportingRecord::DISK)->delete($storedPath);

            throw $exception;
        }

        return SupportingRecordResource::make($record)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Stream one supporting record to its owner.
     */
    #[Endpoint(
        title: 'View supporting record',
        description: 'Streams a supporting record inline. Requires a Sanctum Bearer token for the verified account that uploaded it. Another patient receives 404.',
    )]
    #[Response(200, description: 'The file. Content-Type matches the uploaded PDF, JPG, or PNG.', mediaType: 'application/octet-stream')]
    #[Response(401, description: 'Missing or invalid token.')]
    #[Response(403, description: 'The email address is not verified.')]
    #[Response(404, description: 'The record does not exist for this account.')]
    public function show(Request $request, SupportingRecord $supportingRecord): StreamedResponse
    {
        $this->ownedRecord($request, $supportingRecord);

        $headers = [];

        if (is_string($supportingRecord->mime_type) && $supportingRecord->mime_type !== '') {
            $headers['Content-Type'] = $supportingRecord->mime_type;
        }

        return Storage::disk(SupportingRecord::DISK)->response(
            $supportingRecord->path,
            $supportingRecord->original_name,
            $headers,
        );
    }

    /**
     * Delete one supporting record.
     */
    #[Endpoint(
        title: 'Remove supporting record',
        description: 'Deletes one supporting record and its file. Requires a Sanctum Bearer token for the verified account that uploaded it. Another patient receives 404.',
    )]
    #[Response(200, description: 'Supporting record removed.')]
    #[Response(401, description: 'Missing or invalid token.')]
    #[Response(403, description: 'The email address is not verified.')]
    #[Response(404, description: 'The record does not exist for this account.')]
    public function destroy(Request $request, SupportingRecord $supportingRecord): JsonResponse
    {
        $profile = $this->ownedRecord($request, $supportingRecord);
        $path = $supportingRecord->path;

        $supportingRecord->delete();
        Storage::disk(SupportingRecord::DISK)->delete($path);

        $profile->unsetRelation('supportingRecords');

        return SupportingRecordResource::collection($profile->supportingRecords()->orderBy('id')->get())
            ->response();
    }

    private function ownedRecord(Request $request, SupportingRecord $supportingRecord): PatientProfile
    {
        $user = $this->verifiedUser($request);
        $profile = $user->patientProfile;

        if (! $profile instanceof PatientProfile || $supportingRecord->patient_profile_id !== $profile->id) {
            abort(404);
        }

        return $profile;
    }

    private function verifiedUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        if (! $user->hasVerifiedEmail()) {
            throw new AuthorizationException('Email address is not verified.');
        }

        return $user;
    }

    private function displayName(UploadedFile $file): string
    {
        $name = str_replace('\\', '/', $file->getClientOriginalName());
        $name = basename($name);
        $name = str_replace(["\0", "\r", "\n", '"'], '', $name);
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..') {
            $extension = $file->guessExtension() ?: 'bin';
            $name = 'record.'.$extension;
        }

        if (mb_strlen($name) > 255) {
            $name = mb_substr($name, 0, 255);
        }

        return $name;
    }
}
