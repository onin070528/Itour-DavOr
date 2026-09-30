<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\SecurityLogger;
use Illuminate\Auth\Events\Logout;

class LogLogout
{
    public function handle(Logout $event): void
    {
        /** @var User|null $user */
        $user = $event->user;

        if ($user) {
            SecurityLogger::logout($user);
        }
    }
}
