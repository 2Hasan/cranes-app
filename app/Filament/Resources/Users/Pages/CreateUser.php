<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\InvitationLink;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private string $role = '';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->role = $data['role'];
        unset($data['role']);

        // Nobody knows this password; the invited user sets their own through the link.
        $data['password'] = Str::password(64);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->getRecord()->syncRoles([$this->role]);

        $invitation = app(InvitationLink::class);
        $link = $invitation->for($this->getRecord());

        $this->js($invitation->copyScript($link));

        Notification::make()
            ->title('تم إنشاء المستخدم (رابط الدعوة منسوخ)')
            ->body('أرسل هذا الرابط للمستخدم ليختار كلمة المرور. ينتهي خلال 48 ساعة:'."\n".$link)
            ->success()
            ->persistent()
            ->send();
    }
}
