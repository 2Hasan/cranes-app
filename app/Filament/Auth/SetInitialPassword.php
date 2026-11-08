<?php

namespace App\Filament\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The page behind an invitation link: a new user chooses their first password here.
 */
class SetInitialPassword extends ResetPassword
{
    public function getTitle(): string|Htmlable
    {
        return 'إنشاء كلمة المرور';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'إنشاء كلمة المرور';
    }

    public function getResetPasswordFormAction(): Action
    {
        return Action::make('resetPassword')
            ->label('حفظ والدخول إلى النظام')
            ->submit('resetPassword');
    }
}
