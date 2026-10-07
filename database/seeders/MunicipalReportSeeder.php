<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Seeds sample municipal reports for the PTO review screens.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace Database\Seeders;

use App\Models\MunicipalReport;
use App\Models\User;
use Illuminate\Database\Seeder;

class MunicipalReportSeeder extends Seeder
{
    /**
     * Seeds a handful of municipal reports, submitted by the LGU accounts
     * UserSeeder already creates, in a mix of statuses so the PTO's
     * Municipal Reports review screen has real rows to filter and act on.
     * There is no LGU submission UI yet (out of scope — see
     * Pto\MunicipalReportsController's doc comment), so this seeder is
     * currently the only way these rows come to exist.
     */
    public function run(): void
    {
        $objPto = User::query()->where('usr_email', 'ebautista@davaooriental.gov.ph')->first();

        $arrRows = [
            ['adizon@mati.gov.ph', 'City of Mati', '2026-08-01', '2026-08-31', 1240, MunicipalReport::STATUS_SUBMITTED, null, null],
            ['jreyes@cateel.gov.ph', 'Cateel', '2026-08-01', '2026-08-31', 410, MunicipalReport::STATUS_SUBMITTED, null, null],
            ['lmangubat@baganga.gov.ph', 'Baganga', '2026-07-01', '2026-07-31', 380, MunicipalReport::STATUS_APPROVED, 'Consistent with establishment-level totals.', '2026-08-05'],
            ['puy@sanisidro.gov.ph', 'San Isidro', '2026-07-01', '2026-07-31', 96, MunicipalReport::STATUS_RETURNED, 'Arrival count looks low versus last quarter — please double-check the establishment tallies before resubmitting.', '2026-08-04'],
            ['adizon@mati.gov.ph', 'City of Mati', '2026-07-01', '2026-07-31', 1185, MunicipalReport::STATUS_APPROVED, 'Approved without changes.', '2026-08-03'],
        ];

        foreach ($arrRows as [$email, $objMunicipality, $periodStart, $periodEnd, $totalArrivals, $status, $remarks, $reviewedAt]) {
            $objSubmitter = User::query()->where('usr_email', $email)->first();

            if (! $objSubmitter) {
                continue;
            }

            MunicipalReport::query()->updateOrCreate(
                ['mrp_municipality' => $objMunicipality, 'mrp_period_start' => $periodStart, 'mrp_period_end' => $periodEnd],
                [
                    'mrp_submitted_by' => $objSubmitter->usr_id,
                    'mrp_total_arrivals' => $totalArrivals,
                    'mrp_status' => $status,
                    'mrp_reviewed_by' => $reviewedAt ? $objPto?->usr_id : null,
                    'mrp_reviewed_at' => $reviewedAt,
                    'mrp_remarks' => $remarks,
                ]
            );
        }
    }
}
