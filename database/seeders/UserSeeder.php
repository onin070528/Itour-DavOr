<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\PtoMockData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Seed one full-detail sample account per authenticated role, plus the
     * rest of App\Support\PtoMockData::users()' illustrative accounts (used
     * by the User Management page) so switching that page from mock data to
     * the real `users` table isn't a step backward, demo-content-wise.
     *
     * All demo accounts use the password "password" — change these before
     * any non-local deployment.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['usr_email' => 'ebautista@davaooriental.gov.ph'],
            [
                'usr_name' => 'Ma. Elena Bautista',
                'usr_password' => 'password',
                'usr_email_verified_at' => now(),
                'usr_role' => UserRole::PtoAdministrator,
                'usr_organization_name' => 'Provincial Tourism Office',
                'usr_organization_subtitle' => 'Province of Davao Oriental',
                'usr_status' => 'Active',
                'usr_last_login_at' => Carbon::parse('2026-08-22'),
            ]
        );

        User::query()->updateOrCreate(
            ['usr_email' => 'adizon@mati.gov.ph'],
            [
                'usr_name' => 'Arnel Dizon',
                'usr_password' => 'password',
                'usr_email_verified_at' => now(),
                'usr_role' => UserRole::Lgu,
                'usr_organization_name' => 'Mati City Tourism Office',
                'usr_organization_subtitle' => 'City of Mati',
                'usr_status' => 'Active',
                'usr_last_login_at' => Carbon::parse('2026-08-22'),
            ]
        );

        User::query()->updateOrCreate(
            ['usr_email' => 'frontdesk@botanikaresort.ph'],
            [
                'usr_name' => 'Front Desk Account',
                'usr_password' => 'password',
                'usr_email_verified_at' => now(),
                'usr_role' => UserRole::Establishment,
                'usr_organization_name' => 'Botanika Nature Resort',
                'usr_organization_subtitle' => 'Brgy. Dahican, City of Mati',
                'usr_status' => 'Active',
                'usr_last_login_at' => Carbon::parse('2026-08-21'),
            ]
        );

        // The rest of PtoMockData::seedUsers() — the 3 above already have their
        // proper long-form usr_organization_name authored by hand, so they're
        // skipped here rather than flattened to the generic mapping below.
        $seededEmails = ['ebautista@davaooriental.gov.ph', 'adizon@mati.gov.ph', 'frontdesk@botanikaresort.ph'];
        $roleByTitle = collect(UserRole::cases())->keyBy(fn (UserRole $role) => $role->title());

        foreach (PtoMockData::seedUsers() as $row) {
            if (in_array($row['email'], $seededEmails, true)) {
                continue;
            }

            // No independent "long-form office name" source exists for
            // these (unlike the 3 hand-authored accounts above), so
            // usr_organization_name and usr_organization_subtitle both take the
            // mock's single "assignment" value verbatim — documented in
            // Pto\UsersController as the same mapping real account creation
            // uses, not a one-off guess for seed data.
            User::query()->updateOrCreate(
                ['usr_email' => $row['email']],
                [
                    'usr_name' => $row['name'],
                    'usr_password' => 'password',
                    'usr_email_verified_at' => now(),
                    'usr_role' => $roleByTitle[$row['role']] ?? UserRole::Lgu,
                    'usr_organization_name' => $row['assignment'],
                    'usr_organization_subtitle' => $row['assignment'],
                    'usr_status' => $row['status'],
                    'usr_last_login_at' => Carbon::parse($row['lastActive']),
                ]
            );
        }
    }
}
