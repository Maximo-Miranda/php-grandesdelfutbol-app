<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
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
            'invite_token' => ['nullable', 'string'],
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        if (! empty($input['invite_token'])) {
            $this->handleInviteToken($input['invite_token'], $user);
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
}
