<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Enumerates listings.reporting_mode — how an establishment reports tourist arrivals (iTOUR adoption).
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Enums;

/**
 * Backed by the values already stored in listings.reporting_mode, so no
 * data changes: DIGITAL is an establishment that adopted iTOUR (may have an
 * account, a QR code, and submits monthly reports in-system); PAPER_LGU is
 * one that keeps submitting paper reports, which the LGU encodes on its
 * behalf (no account or QR needed). This says nothing about whether the
 * establishment is featured as a public destination — that is the
 * separate destination listing workflow.
 */
enum ReportingMethod: string
{
    case OnlineItour = 'DIGITAL';
    case ManualPaper = 'PAPER_LGU';

    /**
     * The method a newly registered establishment starts with — creating an
     * establishment never creates an account, so it cannot report online
     * until the LGU activates one.
     */
    public static function default(): self
    {
        return self::ManualPaper;
    } // end default

    public function label(): string
    {
        return match ($this) {
            self::OnlineItour => 'Online iTOUR',
            self::ManualPaper => 'Manual/Paper',
        };
    }

    /**
     * Tabler icon class shown next to the label in establishment lists.
     */
    public function icon(): string
    {
        return match ($this) {
            self::OnlineItour => 'ti-device-laptop',
            self::ManualPaper => 'ti-file-text',
        };
    }

    public function isOnline(): bool
    {
        return $this === self::OnlineItour;
    }
}
