<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Password;
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

        Password::sendResetLink(['email' => $this->getRecord()->email]);
    }
}
