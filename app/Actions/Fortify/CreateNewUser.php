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
            'join_slug' => ['nullable', 'string'],
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

        if (! empty($input['join_slug'])) {
            $this->handleJoinSlug($input['join_slug'], $user);
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

        if (! $invitation) {
            return;
        }

        app(InvitationService::class)->acceptInvitation($invitation, $user);
        $user->markEmailAsVerified();
    }

    private function handleJoinSlug(string $slug, User $user): void
    {
        $club = Club::query()->where('slug', $slug)->first();

        if ($club) {
            session()->put('join_slug', $slug);
        }
    }
}
