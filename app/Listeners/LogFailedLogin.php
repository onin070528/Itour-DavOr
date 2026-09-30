<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\SecurityLogger;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        /** @var User|null $user */
        $user = $event->user;

        // Only the email is ever read from $event->credentials — never the
        // password, which lives in the same array.
        SecurityLogger::loginFailed($user, $event->credentials['email'] ?? null, 'invalid_credentials');
    }
}
