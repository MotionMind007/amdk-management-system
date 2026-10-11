<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class FinanceDueReminderNotification extends Notification
{
    public function __construct(public readonly string $kind, public readonly string $number, public readonly string $party, public readonly string $dueDate, public readonly string $amount) {}

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
        return 'finance-due-reminder';
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $isReceivable = $this->kind === 'receivable';

        return [
            'title' => $isReceivable ? 'Piutang Mendekati Jatuh Tempo' : 'Hutang Mendekati Jatuh Tempo',
            'message' => "{$this->number} — {$this->party}, jatuh tempo {$this->dueDate}, sisa Rp {$this->amount}.",
            'route' => 'finance.aging',
            'action_label' => 'Lihat Aging',
            'category' => $this->kind,
        ];
    }
}
