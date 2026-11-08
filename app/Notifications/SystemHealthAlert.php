<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SystemHealthAlert extends Notification
{
    public function __construct(
        public readonly string $checkName,
        public readonly string $message,
        public readonly bool $recovered = false,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->recovered ? 'system-health-recovered' : 'system-health-alert';
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->recovered ? 'Pemantauan Sistem Pulih' : 'Peringatan Sistem',
            'message' => "{$this->checkName}: {$this->message}",
            'route' => 'notifications.index',
            'action_label' => 'Lihat Notifikasi',
            'category' => 'system_health',
        ];
    }
}
