<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Enumerates tbl_establishment_images.img_source_role — who uploaded the image.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Enums;

enum ImageSourceRole: string
{
    case Establishment = 'ESTABLISHMENT';
    case Lgu = 'LGU';
    case Pto = 'PTO';

    /**
     * The role that must review an image this source uploaded — the single
     * source of truth for approval routing (Workflow Rules: "Who uploads
     * and who approves"). Also used by App\Policies\ImagePolicy so the
     * routing rule is never duplicated.
     *
     * @return ?ImageSourceRole Null for PTO uploads, which publish
     *                          immediately and need no approver.
     */
    public function getApproverRole(): ?self
    {
        return match ($this) {
            self::Establishment => self::Lgu,
            self::Lgu => self::Pto,
            self::Pto => null,
        };
    }

    /**
     * Whether this source's uploads publish immediately instead of going
     * through review.
     */
    public function isAutoPublished(): bool
    {
        return $this === self::Pto;
    }
}
