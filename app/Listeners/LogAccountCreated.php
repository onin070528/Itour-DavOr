<?php

namespace App\Listeners;

use App\Events\UserAccountCreated;
use App\Support\SecurityLogger;

class LogAccountCreated
{
    public function handle(UserAccountCreated $event): void
    {
        SecurityLogger::accountCreated($event->actor, $event->account);
    }
}
