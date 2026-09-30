<?php

namespace App\Listeners;

use App\Events\UserRoleChanged;
use App\Support\SecurityLogger;

class LogRoleChanged
{
    public function handle(UserRoleChanged $event): void
    {
        SecurityLogger::roleChanged($event->actor, $event->account, $event->fromRole, $event->toRole);
    }
}
