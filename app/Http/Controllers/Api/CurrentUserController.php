<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group('User', weight: 2)]
class CurrentUserController extends Controller
{
    /**
     * Return the authenticated user for the given Sanctum token.
     */
    #[Endpoint(title: 'Current user', description: 'Requires a Sanctum Bearer token from Google authentication.')]
    public function __invoke(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
