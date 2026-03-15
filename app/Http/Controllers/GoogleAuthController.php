<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\PlayerProfile;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if ($request->has('invite_token')) {
            session()->put('google_invite_token', $request->input('invite_token'));
        }

        if ($request->has('join_token')) {
            session()->put('google_join_token', $request->input('join_token'));
        }

        if ($request->boolean('terms_accepted')) {
            session()->put('google_terms_accepted', true);
        }

        return Socialite::driver('google')
            ->scopes([
                'https://www.googleapis.com/auth/user.birthday.read',
                'https://www.googleapis.com/auth/user.gender.read',
                'https://www.googleapis.com/auth/user.phonenumbers.read',
            ])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            /** @var SocialiteUser $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            Log::warning('Google OAuth failed', ['error' => $e->getMessage()]);

            return redirect()->route('login')
                ->with('status', 'No se pudo autenticar con Google. Intenta de nuevo.');
        }

        // 1. Check if already linked via SocialAccount
        $socialAccount = SocialAccount::query()
            ->where('provider', 'google')
            ->where('provider_id', $googleUser->getId())
            ->first();

        if ($socialAccount) {
            Auth::login($socialAccount->user, remember: true);

            return $this->handlePostLogin($socialAccount->user);
        }

        // 2. Check if user exists by email
        $user = User::query()->where('email', $googleUser->getEmail())->first();

        if ($user) {
            // Link Google account to existing user
            $user->socialAccounts()->create([
                'provider' => 'google',
                'provider_id' => $googleUser->getId(),
            ]);

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $this->syncProfileData($user, $googleUser);

            Auth::login($user, remember: true);

            return $this->handlePostLogin($user);
        }

        // 3. Create new user — requires explicit terms acceptance
        if (! session()->pull('google_terms_accepted')) {
            return redirect()->route('register')
                ->with('status', 'Para crear tu cuenta, debes aceptar los terminos y condiciones.');
        }

        $user = User::create([
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'password' => null,
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
            'terms_accepted_ip' => request()->ip(),
            'terms_accepted_user_agent' => request()->userAgent(),
        ]);

        $user->socialAccounts()->create([
            'provider' => 'google',
            'provider_id' => $googleUser->getId(),
        ]);

        $profileData = $this->extractProfileData($googleUser);
        $this->createPlayerProfile($user, $profileData);
        $this->downloadAvatar($user, $googleUser);

        Auth::login($user, remember: true);

        return $this->handlePostLogin($user);
    }

    /**
     * Extract extended profile data from Google People API.
     *
     * @return array{gender: Gender|null, birthday: string|null, phone: string|null}
     */
    private function extractProfileData(SocialiteUser $googleUser): array
    {
        $data = ['gender' => null, 'birthday' => null, 'phone' => null];

        try {
            $response = Http::withToken($googleUser->token)
                ->get('https://people.googleapis.com/v1/people/me', [
                    'personFields' => 'genders,birthdays,phoneNumbers',
                ]);

            if (! $response->successful()) {
                return $data;
            }

            $person = $response->json();

            // Gender
            $googleGender = $person['genders'][0]['value'] ?? null;
            if ($googleGender) {
                $data['gender'] = match ($googleGender) {
                    'male' => Gender::Male,
                    'female' => Gender::Female,
                    default => Gender::Other,
                };
            }

            // Birthday
            $birthday = $person['birthdays'][0]['date'] ?? null;
            if ($birthday && isset($birthday['year'], $birthday['month'], $birthday['day'])) {
                $data['birthday'] = sprintf(
                    '%04d-%02d-%02d',
                    $birthday['year'],
                    $birthday['month'],
                    $birthday['day'],
                );
            }

            // Phone
            $data['phone'] = $person['phoneNumbers'][0]['value'] ?? null;
        } catch (\Exception $e) {
            Log::info('Google People API data extraction failed', ['error' => $e->getMessage()]);
        }

        return $data;
    }

    private function createPlayerProfile(User $user, array $profileData): void
    {
        $attributes = ['user_id' => $user->id];

        if ($profileData['gender']) {
            $attributes['gender'] = $profileData['gender'];
        }
        if ($profileData['birthday']) {
            $attributes['date_of_birth'] = $profileData['birthday'];
        }
        if ($profileData['phone']) {
            $attributes['phone'] = $profileData['phone'];
        }

        PlayerProfile::create($attributes);
    }

    private function downloadAvatar(User $user, SocialiteUser $googleUser): void
    {
        $avatarUrl = $googleUser->getAvatar();

        if (! $avatarUrl) {
            return;
        }

        try {
            // Request higher resolution avatar
            $avatarUrl = preg_replace('/=s\d+-c/', '=s600-c', $avatarUrl);

            $user->playerProfile->addMediaFromUrl($avatarUrl)
                ->toMediaCollection('photo');
        } catch (\Exception $e) {
            Log::info('Google avatar download failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Sync Google profile data to existing user's PlayerProfile (only fill empty fields).
     */
    private function syncProfileData(User $user, SocialiteUser $googleUser): void
    {
        $profileData = $this->extractProfileData($googleUser);

        $profile = $user->playerProfile;

        if (! $profile) {
            $this->createPlayerProfile($user, $profileData);
            $user->refresh();
            $this->downloadAvatar($user, $googleUser);

            return;
        }

        $updates = [];

        if (! $profile->gender && $profileData['gender']) {
            $updates['gender'] = $profileData['gender'];
        }
        if (! $profile->date_of_birth && $profileData['birthday']) {
            $updates['date_of_birth'] = $profileData['birthday'];
        }
        if (! $profile->phone && $profileData['phone']) {
            $updates['phone'] = $profileData['phone'];
        }

        if (! empty($updates)) {
            $profile->update($updates);
        }

        if (! $profile->getFirstMedia('photo')) {
            $this->downloadAvatar($user, $googleUser);
        }
    }

    private function handlePostLogin(User $user): RedirectResponse
    {
        $this->handleTokens($user);

        return redirect()->intended(route('home'));
    }

    private function handleTokens(User $user): void
    {
        $invitationService = app(InvitationService::class);

        // Handle invite_token
        $inviteToken = session()->pull('google_invite_token');
        if ($inviteToken) {
            $invitation = ClubInvitation::query()
                ->valid()
                ->where('token', $inviteToken)
                ->where('email', $user->email)
                ->first();

            if ($invitation) {
                $invitationService->acceptInvitation($invitation, $user);
            }
        }

        // Handle join_token
        $joinToken = session()->pull('google_join_token');
        if ($joinToken) {
            $club = Club::query()
                ->where('invite_token', $joinToken)
                ->where('is_invite_active', true)
                ->first();

            if ($club) {
                $invitationService->joinViaLink($club, $user);
            }
        }
    }
}
