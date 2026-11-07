<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AccountInvitation extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('دعوة للدخول إلى نظام متابعة الرافعات')
            ->greeting('مرحبًا '.$notifiable->name)
            ->line('تمت إضافتك إلى نظام متابعة الرافعات. اضغط الزر أدناه لاختيار كلمة المرور.')
            ->action('اختيار كلمة المرور', $this->resetUrl($notifiable))
            ->line('ينتهي هذا الرابط خلال 48 ساعة.');
    }
}
