<?php

namespace App\Notifications;

use App\Models\Fault;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class SevereFaultNotification extends Notification
{
    public function __construct(private Fault $fault) {}

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $crane = $this->fault->crane;

        return (new WebPushMessage)
            ->title('عطل '.$this->fault->severity->getLabel().' على الرافعة '.$crane->code)
            ->body($this->fault->title)
            ->tag('fault-'.$this->fault->getKey())
            ->data(['url' => route('filament.admin.resources.cranes.edit', $crane)]);
    }
}
