<?php

namespace App\Listeners;

use App\Events\UserPasswordChanged;
use App\Support\SecurityLogger;

class LogPasswordChanged
{
    public function handle(UserPasswordChanged $event): void
    {
        SecurityLogger::passwordChanged($event->user);
    }
}
