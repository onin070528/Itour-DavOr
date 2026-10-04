<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Welcome email for a newly created account — plain text only, carries
 * the one-time temporary passphrase and the first-login instruction.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeAccountCreated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $objUser,
        public readonly string $strPassphrase,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Your iTOUR account is ready')
            ->text('emails.welcome_account', [
                'strName' => $this->objUser->name,
                'strRoleLabel' => $this->objUser->role->title(),
                'strMunicipality' => $this->objUser->organization_subtitle,
                'strPassphrase' => $this->strPassphrase,
                'strLoginUrl' => route('login'),
            ]);
    }
}
