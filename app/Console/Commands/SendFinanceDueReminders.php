<?php

namespace App\Console\Commands;

use App\Models\DueReminder;
use App\Models\Sale;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Notifications\FinanceDueReminderNotification;
use App\UserRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('finance:send-due-reminders')]
#[Description('Kirim notifikasi hutang dan piutang yang jatuh tempo dalam tiga hari atau sudah lewat.')]
class SendFinanceDueReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->sendReceivables();
        $this->sendPayables();
        $this->info('Pengingat jatuh tempo selesai dikirim.');

        return self::SUCCESS;
    }

    private function sendReceivables(): void
    {
        $users = User::query()->where('is_active', true)->whereIn('role', [UserRole::Admin, UserRole::Sales, UserRole::SuperAdministrator, UserRole::Owner])->get();
        Sale::query()->where('outstanding_amount', '>', 0)->whereNotNull('due_date')->whereDate('due_date', '<=', today()->addDays(3))->with('customer:id,name')->each(function (Sale $sale) use ($users): void {
            if (! $this->reserve('receivable', $sale)) {
                return;
            }
            foreach ($users as $user) {
                $user->notify(new FinanceDueReminderNotification('receivable', $sale->number, $sale->customer->name, $sale->due_date->format('d/m/Y'), number_format((float) $sale->outstanding_amount, 0, ',', '.')));
            }
        });
    }

    private function sendPayables(): void
    {
        $users = User::query()->where('is_active', true)->whereIn('role', [UserRole::Admin, UserRole::SuperAdministrator, UserRole::Owner])->get();
        SupplierInvoice::query()->where('outstanding_amount', '>', 0)->whereNotNull('due_date')->whereDate('due_date', '<=', today()->addDays(3))->with('purchaseOrder.supplier:id,name')->each(function (SupplierInvoice $invoice) use ($users): void {
            if (! $this->reserve('payable', $invoice)) {
                return;
            }
            foreach ($users as $user) {
                $user->notify(new FinanceDueReminderNotification('payable', $invoice->number, $invoice->purchaseOrder->supplier->name, $invoice->due_date->format('d/m/Y'), number_format((float) $invoice->outstanding_amount, 0, ',', '.')));
            }
        });
    }

    private function reserve(string $kind, Sale|SupplierInvoice $subject): bool
    {
        return DueReminder::query()->firstOrCreate(['reminder_date' => today(), 'kind' => $kind, 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->id])->wasRecentlyCreated;
    }
}
