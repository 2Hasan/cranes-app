<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class MaintenanceDigestNotification extends Notification
{
    public function __construct(
        private int $lateServices,
        private int $soonServices,
        private int $openSevereFaults,
    ) {}

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $parts = array_filter([
            $this->lateServices > 0 ? "صيانة متأخرة: {$this->lateServices}" : null,
            $this->soonServices > 0 ? "صيانة قريبة: {$this->soonServices}" : null,
            $this->openSevereFaults > 0 ? "أعطال مهمة مفتوحة: {$this->openSevereFaults}" : null,
        ]);

        return (new WebPushMessage)
            ->title('ملخص الرافعات اليومي')
            ->body(implode(' · ', $parts))
            ->tag('maintenance-digest')
            ->data(['url' => url('/admin/cranes')]);
    }
}
