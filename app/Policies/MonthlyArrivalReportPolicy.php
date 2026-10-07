<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: RBAC policy for MonthlyArrivalReport — who may view, draft,
 * submit, return, correct, and verify an establishment's monthly
 * tourist-arrival report. Replaces the inline abort_unless() duplicated
 * across Lgu\MonthlyReportsController's show/edit/update/verify methods
 * with one source of truth.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Policies;

use App\Enums\MonthlyReportStatus;
use App\Enums\ReportSubmissionSource;
use App\Enums\UserRole;
use App\Models\MonthlyArrivalReport;
use App\Models\MunicipalReport;
use App\Models\User;

class MonthlyArrivalReportPolicy
{
    /**
     * A Draft is visible only to its owner (CLAUDE.md 2.5) — the LGU and
     * PTO see an establishment's unsubmitted Draft as "Not Submitted".
     */
    public function view(User $user, MonthlyArrivalReport $report): bool
    {
        if ($report->mar_status === MonthlyReportStatus::Draft) {
            return $this->_isOwner($user, $report);
        }

        return match ($user->usr_role) {
            UserRole::PtoAdministrator => true,
            UserRole::Lgu => $report->mun_id !== null && $report->mun_id === $user->mun_id,
            UserRole::Establishment => $report->lst_id === $user->lst_id,
            default => false,
        };
    }

    /**
     * Correcting a report's figures (the controlled correction process,
     * reason required) — LGU only, own municipality, only once the report
     * has been submitted (For Review) or verified, and only while it isn't
     * locked (see isLocked()): a report that's part of a MunicipalReport
     * PTO hasn't returned can't be corrected here. Drafts and reports
     * returned For Correction are changed by their owner instead.
     */
    public function update(User $user, MonthlyArrivalReport $report): bool
    {
        $blnIsSubmittedOrVerified = in_array($report->mar_status, [MonthlyReportStatus::Submitted, MonthlyReportStatus::ForReview, MonthlyReportStatus::Verified], true);

        return $user->usr_role === UserRole::Lgu
            && $report->mun_id !== null
            && $report->mun_id === $user->mun_id
            && $blnIsSubmittedOrVerified
            && ! $this->isLocked($report);
    }

    /**
     * LGU opening a newly Submitted report for review (Submitted -> For
     * Review) — own municipality only.
     */
    public function startReview(User $user, MonthlyArrivalReport $report): bool
    {
        return $user->usr_role === UserRole::Lgu
            && $report->mun_id !== null
            && $report->mun_id === $user->mun_id
            && $report->mar_status === MonthlyReportStatus::Submitted;
    } // end startReview

    /**
     * Only a report the LGU is reviewing (For Review) can be verified —
     * never a Draft, a not-yet-opened Submitted report, a report returned
     * For Correction, or one already Verified.
     */
    public function verify(User $user, MonthlyArrivalReport $report): bool
    {
        return $user->usr_role === UserRole::Lgu
            && $report->mun_id !== null
            && $report->mun_id === $user->mun_id
            && $report->mar_status === MonthlyReportStatus::ForReview;
    }

    /**
     * LGU returning an establishment's digitally submitted report For
     * Correction (remarks required). A Manual/Paper report is never
     * returned — the LGU encoded it, so it corrects it directly instead.
     */
    public function returnForCorrection(User $user, MonthlyArrivalReport $report): bool
    {
        return $user->usr_role === UserRole::Lgu
            && $report->mun_id !== null
            && $report->mun_id === $user->mun_id
            && $report->mar_status === MonthlyReportStatus::ForReview
            && $report->mar_submission_source === ReportSubmissionSource::Digital;
    } // end returnForCorrection

    /**
     * Saving changes to a Draft (or a report returned For Correction) —
     * only its owner, and only while it is still editable.
     */
    public function editDraft(User $user, MonthlyArrivalReport $report): bool
    {
        return $this->_isOwner($user, $report) && $report->mar_status->isEditableByOwner();
    } // end editDraft

    /**
     * Submitting a Draft / For Correction report to the LGU — same rule as
     * editDraft(): the owner, while it is still editable.
     */
    public function submit(User $user, MonthlyArrivalReport $report): bool
    {
        return $this->editDraft($user, $report);
    } // end submit

    /**
     * A report can't be corrected while it's part of a MunicipalReport PTO
     * hasn't returned — editing it would silently make that MunicipalReport's
     * total_arrivals stale (SUBMITTED, awaiting PTO) or retroactively change
     * a figure PTO already accepted (APPROVED). Once PTO returns it, or if
     * it was never consolidated at all, editing is fine.
     */
    private function isLocked(MonthlyArrivalReport $report): bool
    {
        return $report->municipalReport !== null && $report->municipalReport->mrp_status !== MunicipalReport::STATUS_RETURNED;
    }

    /**
     * The owner of a report is whoever prepares it: the establishment
     * itself for a Digital report, or the LGU of its municipality for a
     * Manual/Paper report encoded on the establishment's behalf.
     */
    private function _isOwner(User $user, MonthlyArrivalReport $report): bool
    {
        $blnIsOwnEstablishment = $user->usr_role === UserRole::Establishment
            && $report->lst_id === $user->lst_id;
        $blnIsOwnMunicipalityLgu = $user->usr_role === UserRole::Lgu
            && $report->mun_id !== null
            && $report->mun_id === $user->mun_id;

        return match ($report->mar_submission_source) {
            ReportSubmissionSource::Digital => $blnIsOwnEstablishment,
            ReportSubmissionSource::ManualPaper => $blnIsOwnMunicipalityLgu,
            default => false,
        };
    } // end _isOwner
}
