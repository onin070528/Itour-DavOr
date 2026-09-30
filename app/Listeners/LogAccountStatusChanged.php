<?php

namespace App\Listeners;

use App\Events\UserAccountStatusChanged;
use App\Support\SecurityLogger;

class LogAccountStatusChanged
{
    public function handle(UserAccountStatusChanged $event): void
    {
        SecurityLogger::accountStatusChanged($event->actor, $event->account, $event->newStatus);
    }
}
