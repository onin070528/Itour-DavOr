<?php

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
        $pto = User::query()->where('email', 'ebautista@davaooriental.gov.ph')->first();

        $rows = [
            ['adizon@mati.gov.ph', 'City of Mati', '2026-08-01', '2026-08-31', 1240, MunicipalReport::STATUS_SUBMITTED, null, null],
            ['jreyes@cateel.gov.ph', 'Cateel', '2026-08-01', '2026-08-31', 410, MunicipalReport::STATUS_SUBMITTED, null, null],
            ['lmangubat@baganga.gov.ph', 'Baganga', '2026-07-01', '2026-07-31', 380, MunicipalReport::STATUS_APPROVED, 'Consistent with establishment-level totals.', '2026-08-05'],
            ['puy@sanisidro.gov.ph', 'San Isidro', '2026-07-01', '2026-07-31', 96, MunicipalReport::STATUS_RETURNED, 'Arrival count looks low versus last quarter — please double-check the establishment tallies before resubmitting.', '2026-08-04'],
            ['adizon@mati.gov.ph', 'City of Mati', '2026-07-01', '2026-07-31', 1185, MunicipalReport::STATUS_APPROVED, 'Approved without changes.', '2026-08-03'],
        ];

        foreach ($rows as [$email, $municipality, $periodStart, $periodEnd, $totalArrivals, $status, $remarks, $reviewedAt]) {
            $submitter = User::query()->where('email', $email)->first();

            if (! $submitter) {
                continue;
            }

            MunicipalReport::query()->updateOrCreate(
                ['municipality' => $municipality, 'period_start' => $periodStart, 'period_end' => $periodEnd],
                [
                    'submitted_by' => $submitter->id,
                    'total_arrivals' => $totalArrivals,
                    'status' => $status,
                    'reviewed_by' => $reviewedAt ? $pto?->id : null,
                    'reviewed_at' => $reviewedAt,
                    'remarks' => $remarks,
                ]
            );
        }
    }
}
