<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\SecurityLogger;
use Illuminate\Auth\Events\PasswordReset;

class LogPasswordResetCompleted
{
    public function handle(PasswordReset $event): void
    {
        /** @var User $user */
        $user = $event->user;

        SecurityLogger::passwordResetCompleted($user);
    }
}
