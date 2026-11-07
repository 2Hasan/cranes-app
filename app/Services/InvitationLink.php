<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Password;

class InvitationLink
{
    /**
     * Creates a one-time link where the user chooses their own password.
     * The link expires after the period set in config('auth.passwords.users.expire').
     */
    public function for(User $user): string
    {
        $token = Password::broker()->createToken($user);

        return route('filament.admin.auth.password-reset.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);
    }
}
