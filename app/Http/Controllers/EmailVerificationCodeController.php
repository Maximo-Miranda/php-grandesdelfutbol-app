<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class EmailVerificationCodeController extends Controller
{
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(config('fortify.home'));
        }

        $cacheKey = "verification-code:{$user->id}";
        $storedCode = Cache::get($cacheKey);

        if (! $storedCode || $storedCode !== $request->input('code')) {
            throw ValidationException::withMessages([
                'code' => 'El codigo es incorrecto o ha expirado.',
            ]);
        }

        $user->markEmailAsVerified();
        Cache::forget($cacheKey);

        return redirect()->intended(config('fortify.home'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(config('fortify.home'));
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'Se ha enviado un nuevo codigo a tu correo.');
    }
}
