<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\School;
use App\Services\Notifier;
use App\Services\SchoolSettings;
use App\Support\SchoolContext;
use Illuminate\Console\Command;

/**
 * Reminds families about outstanding fees (spec section 46).
 *
 * Runs per school so the tenant scope is correct for each one, and skips
 * anything that has already been chased inside the reminder window, so a daily
 * schedule does not send the same family a message every morning.
 */
class SendFeeReminders extends Command
{
    /*
     | The two timing options have no default on purpose. The scheduler runs
     | this across every school at once, and each school sets its own reminder
     | window in settings; a default here would silently override all of them.
     | Passing a flag is still allowed, for a one-off run from the console.
     */
    protected $signature = 'gsms:fee-reminders
        {--school= : Limit to one school by slug}
        {--days-before= : Override the school setting for how far ahead to remind}
        {--cooldown= : Override the school setting for the gap between reminders}
        {--dry-run : Report what would be sent without sending anything}';

    protected $description = 'Notify guardians about fees that are due or overdue';

    public function handle(SchoolContext $context, Notifier $notifier): int
    {
        $schools = School::query()
            ->where('is_active', true)
            ->when($this->option('school'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get();

        if ($schools->isEmpty()) {
            $this->warn('No active schools matched.');

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($schools as $school) {
            $sent = $context->for($school, fn () => $this->remindFor($school, $notifier));

            $this->line(sprintf('%-40s %d reminder(s)', $school->name, $sent));

            $total += $sent;
        }

        $this->info($this->option('dry-run')
            ? "{$total} reminders would be sent."
            : "{$total} reminders sent.");

        return self::SUCCESS;
    }

    protected function remindFor(School $school, Notifier $notifier): int
    {
        $settings = app(SchoolSettings::class);

        $cutoff = now()->addDays((int) ($this->option('days-before') ?? $settings->get('finance_reminder_days_before')));
        $cooldown = now()->subDays((int) ($this->option('cooldown') ?? $settings->get('finance_reminder_cooldown_days')));

        $invoices = Invoice::outstanding()
            ->whereNotNull('due_on')
            ->where('due_on', '<=', $cutoff)
            ->with('student.guardians')
            ->get()
            // A balance of zero can still be flagged "issued" if a payment was
            // recorded directly, so the real balance decides.
            ->filter(fn (Invoice $invoice) => $invoice->balanceMinor() > 0);

        $sent = 0;

        foreach ($invoices as $invoice) {
            if ($this->recentlyReminded($invoice, $cooldown)) {
                continue;
            }

            if ($this->option('dry-run')) {
                $sent++;

                continue;
            }

            $sent += $notifier->feeReminder($invoice) > 0 ? 1 : 0;
        }

        return $sent;
    }

    /** Has this invoice already been chased inside the cooldown window? */
    protected function recentlyReminded(Invoice $invoice, $cooldown): bool
    {
        $guardianUserIds = $invoice->student?->guardians->pluck('user_id')->filter();

        if ($guardianUserIds === null || $guardianUserIds->isEmpty()) {
            return true;
        }

        return \Illuminate\Notifications\DatabaseNotification::query()
            ->where('type', \App\Notifications\FeeReminder::class)
            ->whereIn('notifiable_id', $guardianUserIds->all())
            ->where('created_at', '>=', $cooldown)
            ->where('data', 'like', '%'.$invoice->invoice_number.'%')
            ->exists();
    }
}
