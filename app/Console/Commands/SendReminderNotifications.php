<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\FirebaseService;
use Carbon\Carbon;

class SendReminderNotifications extends Command
{
    protected $signature = 'reminders:send-notifications';
    protected $description = 'Send daily push notifications for due and overdue reminders, loans, and license renewals';

    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        parent::__construct();
        $this->firebaseService = $firebaseService;
    }

    public function handle(): int
    {
        $this->info('Starting to process due reminders...');
        $today = Carbon::today();
        
        // Find users with FCM tokens
        $users = User::whereNotNull('fcm_token')->get();
        
        if ($users->isEmpty()) {
            $this->info('No users found with FCM token registered.');
            return Command::SUCCESS;
        }

        $totalNotificationsSent = 0;

        foreach ($users as $user) {
            $fcmToken = $user->fcm_token;
            
            // 1. Process Recurring Schedule Reminders
            // We notify if next_due_date is today or in the past (overdue) and active
            $dueSchedules = $user->recurringSchedules()
                ->where('is_active', true)
                ->where('next_due_date', '<=', $today)
                ->get();

            foreach ($dueSchedules as $schedule) {
                $typeLabel = ucfirst(str_replace('_', ' ', $schedule->type));
                $amountFormatted = number_format($schedule->amount, 2);
                $title = "⏰ {$typeLabel} Reminder Due";
                
                $descriptionPart = $schedule->description ? " ({$schedule->description})" : "";
                
                if ($schedule->type === 'expense') {
                    $categoryName = $schedule->category?->name ?? 'Uncategorized';
                    $body = "Your recurring expense of \${$amountFormatted} for {$categoryName} is due today{$descriptionPart}.";
                } elseif ($schedule->type === 'loan_installment') {
                    $personName = $schedule->loan?->person_name ?? 'Unknown';
                    $body = "Your loan installment of \${$amountFormatted} for {$personName} is due today{$descriptionPart}.";
                } else {
                    $body = "Your recurring item of \${$amountFormatted} is due today{$descriptionPart}.";
                }

                if ($schedule->next_due_date->lt($today)) {
                    $title = "🚨 {$typeLabel} Reminder Overdue!";
                    $body = "Your recurring item of \${$amountFormatted} is overdue (was due {$schedule->next_due_date->format('Y-m-d')}){$descriptionPart}.";
                }

                $sent = $this->sendNotification($fcmToken, $title, $body, [
                    'type' => 'recurring_schedule',
                    'id' => $schedule->id,
                    'schedule_type' => $schedule->type,
                ]);

                if ($sent) {
                    $totalNotificationsSent++;
                }
            }

            // 2. Process Active Loans Due Date
            // We notify if due_date is today or overdue
            $dueLoans = $user->loans()
                ->where('status', 'active')
                ->whereNotNull('due_date')
                ->where('due_date', '<=', $today)
                ->get();

            foreach ($dueLoans as $loan) {
                $amountFormatted = number_format($loan->amount, 2);
                $actionLabel = $loan->type === 'lent' ? "collect from" : "repay to";
                $title = "🤝 Loan Due Today";
                $body = "You are due to {$actionLabel} {$loan->person_name} the amount of \${$amountFormatted}.";

                if ($loan->due_date->lt($today)) {
                    $title = "🚨 Loan Payment Overdue!";
                    $body = "Your loan with {$loan->person_name} of \${$amountFormatted} is overdue (was due {$loan->due_date->format('Y-m-d')}).";
                }

                $sent = $this->sendNotification($fcmToken, $title, $body, [
                    'type' => 'loan',
                    'id' => $loan->id,
                ]);

                if ($sent) {
                    $totalNotificationsSent++;
                }
            }

            // 3. Process SaaS License Renewals
            // We notify if next_renewal_date is active and within next 7 days
            $dueSoonLicenses = $user->licenses()
                ->where('status', 'active')
                ->where('next_renewal_date', '<=', $today->copy()->addDays(7))
                ->get();

            foreach ($dueSoonLicenses as $license) {
                $amountFormatted = number_format($license->amount, 2);
                $saasName = $license->client?->saas_name ?? 'SaaS Service';
                
                $daysDiff = $today->diffInDays($license->next_renewal_date, false);

                if ($daysDiff < 0) {
                    $title = "🚨 SaaS License Overdue!";
                    $body = "Your SaaS license for {$saasName} (\${$amountFormatted}) was due on {$license->next_renewal_date->format('Y-m-d')}!";
                } elseif ($daysDiff === 0) {
                    $title = "⏳ SaaS License Renewing Today";
                    $body = "Your SaaS license for {$saasName} (\${$amountFormatted}) is renewing today.";
                } else {
                    $title = "⏳ SaaS License Renewing Soon";
                    $body = "Your SaaS license for {$saasName} (\${$amountFormatted}) is renewing in {$daysDiff} days ({$license->next_renewal_date->format('Y-m-d')}).";
                }

                $sent = $this->sendNotification($fcmToken, $title, $body, [
                    'type' => 'license',
                    'id' => $license->id,
                ]);

                if ($sent) {
                    $totalNotificationsSent++;
                }
            }
        }

        $this->info("Completed processing. Sent {$totalNotificationsSent} notifications successfully.");
        return Command::SUCCESS;
    }

    /**
     * Send push notification helper that enforces a configurable interval delay.
     */
    private function sendNotification(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        $sent = $this->firebaseService->sendPushNotification($fcmToken, $title, $body, $data);
        
        $delay = (int) config('services.firebase.notification_delay', 5);
        if ($delay > 0) {
            sleep($delay);
        }
        
        return $sent;
    }
}
