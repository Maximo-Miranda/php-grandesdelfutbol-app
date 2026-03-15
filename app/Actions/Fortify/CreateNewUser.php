<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'terms' => ['required', 'accepted'],
            'invite_token' => ['nullable', 'string'],
            'join_token' => ['nullable', 'string'],
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'terms_accepted_at' => now(),
            'terms_accepted_ip' => request()->ip(),
            'terms_accepted_user_agent' => request()->userAgent(),
        ]);

        if (! empty($input['invite_token'])) {
            $this->handleInviteToken($input['invite_token'], $user);
        }

        if (! empty($input['join_token'])) {
            $this->handleJoinToken($input['join_token'], $user);
        }

        return $user;
    }

    private function handleInviteToken(string $token, User $user): void
    {
        $invitation = ClubInvitation::query()
            ->valid()
            ->where('token', $token)
            ->where('email', $user->email)
            ->first();

        if ($invitation) {
            app(InvitationService::class)->acceptInvitation($invitation, $user);

            // Auto-verify: clicking the invitation link proves email ownership
            $user->markEmailAsVerified();
        }
    }

    private function handleJoinToken(string $token, User $user): void
    {
        // Store the join token in session so the intended redirect
        // can complete the join after email verification.
        // We intentionally do NOT auto-verify email for shared links.
        $club = Club::query()
            ->where('invite_token', $token)
            ->where('is_invite_active', true)
            ->first();

        if ($club) {
            session()->put('join_token', $token);
        }
    }
}
