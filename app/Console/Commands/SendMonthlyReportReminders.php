<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Daily job that puts a bell notification in front of every
 * establishment whose last-month report is almost due (3 days before the
 * 15th), due, or overdue. Each stage notifies once (overdue: once a week).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\SystemNotice;
use App\Support\MonthlyReportReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendMonthlyReportReminders extends Command
{
    protected $signature = 'reports:send-reminders';

    protected $description = 'Notify establishments whose monthly report is almost due, due, or overdue';

    public function handle(): int
    {
        $dtmToday = CarbonImmutable::now();
        $intSent = 0;

        User::query()
            ->where('usr_role', UserRole::Establishment)
            ->whereNotNull('lst_id')
            ->with('establishment')
            ->each(function (User $objUser) use ($dtmToday, &$intSent) {
                if (! $objUser->establishment) {
                    return;
                }

                $arrReminder = MonthlyReportReminder::forListing($objUser->establishment, $dtmToday);
                if (! $arrReminder) {
                    return;
                }

                $strMonth = $arrReminder['period']->format('F Y');
                $strDedupe = 'report-reminder:'.$arrReminder['period']->format('Y-m').':'.$arrReminder['stage']
                    .($arrReminder['stage'] === 'overdue' ? ':'.$dtmToday->format('o-W') : '');

                if ($objUser->notifications()->where('data', 'like', '%"dedupe":"'.$strDedupe.'"%')->exists()) {
                    return;
                }

                $strMessage = match ($arrReminder['stage']) {
                    'upcoming' => "Reminder: your {$strMonth} report is due on {$arrReminder['due']->format('F j')} — send it to your LGU.",
                    'due' => "Your {$strMonth} report is due today. Please send it to your LGU.",
                    default => "Your {$strMonth} report is overdue. Please send it to your LGU now.",
                };

                $objUser->notify(new SystemNotice(
                    'report-reminder',
                    $strMessage,
                    route('establishment.arrivals.monthly'),
                    'ti-alert-triangle',
                    ['dedupe' => $strDedupe],
                ));
                $intSent++;
            });

        $this->info("Sent {$intSent} reminder(s).");

        return self::SUCCESS;
    }
}
