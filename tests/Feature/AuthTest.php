<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test resending verification email successfully.
     *
     * @return void
     */
    public function test_resend_verification_email_successfully(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'verification_token' => null,
        ]);

        $response = $this->postJson('/api/resendVerificationEmail', ['email' => $user->email]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Verification email has been resent to the user successfully',
            ]);

        $this->assertNotNull(User::find($user->id)->verification_token);

        Mail::assertSent(EmailVerificationMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    /**
     * Test resending verification email for a non-existent user.
     *
     * @return void
     */
    public function test_resend_verification_email_for_non_existent_user(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/resendVerificationEmail', ['email' => 'nonexistent@example.com']);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'The user with the email provided does not exist.',
            ]);

        Mail::assertNothingSent();
    }

    /**
     * Test resending verification email with missing email.
     *
     * @return void
     */
    public function test_resend_verification_email_with_missing_email(): void
    {
        $response = $this->postJson('/api/resendVerificationEmail', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
