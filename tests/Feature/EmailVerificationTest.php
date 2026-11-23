<?php

use App\Mail\EmailVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('sign up sends a six digit verification code', function () {
    Mail::fake();
    $this->travelTo('2026-11-23 08:00:00');

    $response = $this->postJson('/api/sign-up', [
        'email' => 'youremail@gmail.com',
        'password' => 'Secret1!',
        'password_confirmation' => 'Secret1!',
        'terms' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('email', 'youremail@gmail.com')
        ->assertJsonPath('expires_at', '2026-11-23T08:10:00.000000Z')
        ->assertJsonPath('resend_available_at', '2026-11-23T08:01:00.000000Z')
        ->assertJsonMissingPath('token')
        ->assertJsonMissingPath('code');

    $code = null;

    Mail::assertSent(EmailVerificationCode::class, function (EmailVerificationCode $mail) use (&$code): bool {
        $code = $mail->code;

        return $mail->hasTo('youremail@gmail.com');
    });

    expect($code)->toMatch('/^\d{6}$/');

    $user = User::query()->where('email', 'youremail@gmail.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->email_verification_code)->not->toBe($code);
});

test('verify returns a token and marks the email as verified', function () {
    Mail::fake();
    $code = registerPendingUser();

    $response = $this->postJson('/api/email/verify', [
        'email' => 'youremail@gmail.com',
        'code' => $code,
    ]);

    $response->assertOk()
        ->assertJsonPath('user.email', 'youremail@gmail.com')
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'email_verified_at']]);

    expect($response->json('token'))->toBeString()->not->toBeEmpty()
        ->and($response->json('user.email_verified_at'))->not->toBeNull();

    $user = User::query()->where('email', 'youremail@gmail.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->email_verification_code)->toBeNull();

    $this->withToken($response->json('token'))
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.email', 'youremail@gmail.com');
});

test('verify returns 422 when the code is wrong', function () {
    Mail::fake();
    $code = registerPendingUser();
    $wrong = $code === '000000' ? '111111' : '000000';

    $this->postJson('/api/email/verify', [
        'email' => 'youremail@gmail.com',
        'code' => $wrong,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'The verification code is incorrect.');

    $user = User::query()->where('email', 'youremail@gmail.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->email_verification_attempts)->toBe(1)
        ->and($user->email_verification_code)->not->toBeNull();
});

test('verify returns 422 when the code is expired', function () {
    Mail::fake();
    $this->travelTo('2026-11-23 08:00:00');
    $code = registerPendingUser();

    $this->travel(11)->minutes();

    $this->postJson('/api/email/verify', [
        'email' => 'youremail@gmail.com',
        'code' => $code,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'The verification code has expired.');

    $user = User::query()->where('email', 'youremail@gmail.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->email_verification_code)->toBeNull();
});

test('verify returns 422 when the code is locked after five attempts', function () {
    Mail::fake();
    $code = registerPendingUser();
    $wrong = $code === '000000' ? '111111' : '000000';

    for ($attempt = 1; $attempt <= 4; $attempt++) {
        $this->postJson('/api/email/verify', [
            'email' => 'youremail@gmail.com',
            'code' => $wrong,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The verification code is incorrect.');
    }

    $this->postJson('/api/email/verify', [
        'email' => 'youremail@gmail.com',
        'code' => $wrong,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'The verification code is no longer valid. Request a new one.');

    $user = User::query()->where('email', 'youremail@gmail.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->email_verification_code)->toBeNull();
});

test('verify returns 422 when the email and code are missing', function () {
    $this->postJson('/api/email/verify', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'The email field is required.')
        ->assertJsonPath('errors.code.0', 'The code field is required.');
});

test('verify returns 422 when the code is not 6 digits', function () {
    $this->postJson('/api/email/verify', [
        'email' => 'youremail@gmail.com',
        'code' => '123',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'The code field must be 6 digits.');
});

test('resend returns 429 until the cooldown ends', function () {
    Mail::fake();
    $this->travelTo('2026-11-23 08:00:00');
    registerPendingUser();

    $this->postJson('/api/email/resend', [
        'email' => 'youremail@gmail.com',
    ])->assertTooManyRequests()
        ->assertJsonPath('message', 'Please wait before requesting a new code.')
        ->assertJsonPath('resend_available_at', '2026-11-23T08:01:00.000000Z');

    $this->travel(61)->seconds();

    $this->postJson('/api/email/resend', [
        'email' => 'youremail@gmail.com',
    ])->assertOk()
        ->assertJsonPath('email', 'youremail@gmail.com')
        ->assertJsonPath('expires_at', '2026-11-23T08:11:01.000000Z')
        ->assertJsonPath('resend_available_at', '2026-11-23T08:02:01.000000Z')
        ->assertJsonMissingPath('code');

    Mail::assertSentTimes(EmailVerificationCode::class, 2);
});

test('resend returns 200 and sends no mail when the email is unknown', function () {
    Mail::fake();

    $this->postJson('/api/email/resend', [
        'email' => 'missing@example.com',
    ])->assertOk()
        ->assertJsonPath('message', 'If an unverified account exists for that email, a new code is on the way.')
        ->assertJsonMissingPath('email');

    Mail::assertNothingSent();
});

test('resend returns 200 and sends no mail when the email is already verified', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
    ]);

    Mail::fake();

    $this->postJson('/api/email/resend', [
        'email' => $user->email,
    ])->assertOk()
        ->assertJsonPath('message', 'If an unverified account exists for that email, a new code is on the way.')
        ->assertJsonMissingPath('expires_at');

    Mail::assertNothingSent();
});

test('change email returns 422 when the password is wrong', function () {
    Mail::fake();
    registerPendingUser();

    $this->postJson('/api/email/change', [
        'email' => 'youremail@gmail.com',
        'password' => 'WrongPass1!',
        'new_email' => 'other@example.com',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.password.0', 'These credentials do not match our records.');

    expect(User::query()->where('email', 'youremail@gmail.com')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'other@example.com')->exists())->toBeFalse();

    Mail::assertSentTimes(EmailVerificationCode::class, 1);
});

test('change email returns 422 when the account is already verified', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('Secret1!'),
    ]);

    Mail::fake();

    $this->postJson('/api/email/change', [
        'email' => 'ada@example.com',
        'password' => 'Secret1!',
        'new_email' => 'other@example.com',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Email address is already verified.');

    expect(User::query()->where('email', 'ada@example.com')->exists())->toBeTrue();

    Mail::assertNothingSent();
});

test('change email returns 422 when the new email is taken', function () {
    Mail::fake();
    registerPendingUser();
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/email/change', [
        'email' => 'youremail@gmail.com',
        'password' => 'Secret1!',
        'new_email' => 'taken@example.com',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.new_email.0', 'The new email has already been taken.');

    expect(User::query()->where('email', 'youremail@gmail.com')->exists())->toBeTrue();
});

test('change email sends a code to the new address when the password matches', function () {
    Mail::fake();
    $oldCode = registerPendingUser();

    $response = $this->postJson('/api/email/change', [
        'email' => 'youremail@gmail.com',
        'password' => 'Secret1!',
        'new_email' => 'other@example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('email', 'other@example.com')
        ->assertJsonMissingPath('code');

    expect(User::query()->where('email', 'youremail@gmail.com')->exists())->toBeFalse();

    $newCode = queuedVerificationCode('other@example.com');

    expect($newCode)->not->toBe($oldCode);

    $this->postJson('/api/email/verify', [
        'email' => 'other@example.com',
        'code' => $oldCode,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'The verification code is incorrect.');

    $this->postJson('/api/email/verify', [
        'email' => 'other@example.com',
        'code' => $newCode,
    ])->assertOk()
        ->assertJsonPath('user.email', 'other@example.com');
});

test('sign in returns 403 when the password is correct and the email is unverified', function () {
    User::factory()->unverified()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('Secret1!'),
    ]);

    $this->postJson('/api/sign-in', [
        'email' => 'ada@example.com',
        'password' => 'Secret1!',
    ])->assertForbidden()
        ->assertJsonPath('message', 'Email address is not verified.')
        ->assertJsonPath('email', 'ada@example.com');
});

test('sign in returns 422 when the password is wrong for an unverified account', function () {
    User::factory()->unverified()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('Secret1!'),
    ]);

    $this->postJson('/api/sign-in', [
        'email' => 'ada@example.com',
        'password' => 'WrongPass1!',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
});

test('sign in returns 200 after the email code is verified', function () {
    Mail::fake();
    $code = registerPendingUser();

    $this->postJson('/api/sign-in', [
        'email' => 'youremail@gmail.com',
        'password' => 'Secret1!',
    ])->assertForbidden();

    $this->postJson('/api/email/verify', [
        'email' => 'youremail@gmail.com',
        'code' => $code,
    ])->assertOk();

    $this->postJson('/api/sign-in', [
        'email' => 'youremail@gmail.com',
        'password' => 'Secret1!',
    ])->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'email_verified_at']]);
});

test('the verification email shows the code', function () {
    $mail = new EmailVerificationCode('012345');

    $mail->assertHasSubject('Your verification code')
        ->assertSeeInHtml('012345')
        ->assertSeeInText('012345')
        ->assertSeeInText('10 minutes');
});

test('the verification email escapes html in the code', function () {
    $mail = new EmailVerificationCode('<script>alert(1)</script>');

    $mail->assertSeeInHtml('<script>alert(1)</script>')
        ->assertDontSeeInHtml('<script>alert(1)</script>', false);
});

function registerPendingUser(string $email = 'youremail@gmail.com'): string
{
    test()->postJson('/api/sign-up', [
        'email' => $email,
        'password' => 'Secret1!',
        'password_confirmation' => 'Secret1!',
        'terms' => true,
    ])->assertCreated();

    return queuedVerificationCode($email);
}

function queuedVerificationCode(string $email): string
{
    $mailable = Mail::sent(EmailVerificationCode::class, function (EmailVerificationCode $mail) use ($email): bool {
        return $mail->hasTo($email);
    })->first();

    if (! $mailable instanceof EmailVerificationCode) {
        throw new RuntimeException('Verification email was not queued.');
    }

    return $mailable->code;
}
