<?php

namespace App\Services;

use App\Models\User;
use Filament\Facades\Filament;
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

        return Filament::getPanel('admin')->getResetPasswordUrl($token, $user);
    }

    /**
     * Browser script that copies the link to the clipboard.
     */
    public function copyScript(string $link): string
    {
        return 'navigator.clipboard.writeText('.json_encode($link).');';
    }
}
