<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Enumerates tblestablishment_images.img_source_role — who uploaded the image.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
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
