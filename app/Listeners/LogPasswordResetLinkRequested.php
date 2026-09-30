<?php

namespace App\Listeners;

use App\Events\PasswordResetLinkRequested;
use App\Support\SecurityLogger;

class LogPasswordResetLinkRequested
{
    public function handle(PasswordResetLinkRequested $event): void
    {
        SecurityLogger::passwordResetRequested($event->user);
    }
}
