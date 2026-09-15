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
            ['email' => 'ebautista@davaooriental.gov.ph'],
            [
                'name' => 'Ma. Elena Bautista',
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => UserRole::PtoAdministrator,
                'organization_name' => 'Provincial Tourism Office',
                'organization_subtitle' => 'Province of Davao Oriental',
                'status' => 'Active',
                'last_login_at' => Carbon::parse('2026-08-22'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'adizon@mati.gov.ph'],
            [
                'name' => 'Arnel Dizon',
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => UserRole::Lgu,
                'organization_name' => 'Mati City Tourism Office',
                'organization_subtitle' => 'City of Mati',
                'status' => 'Active',
                'last_login_at' => Carbon::parse('2026-08-22'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'frontdesk@botanikaresort.ph'],
            [
                'name' => 'Front Desk Account',
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => UserRole::Establishment,
                'organization_name' => 'Botanika Nature Resort',
                'organization_subtitle' => 'Brgy. Dahican, City of Mati',
                'status' => 'Active',
                'last_login_at' => Carbon::parse('2026-08-21'),
            ]
        );

        // The rest of PtoMockData::seedUsers() — the 3 above already have their
        // proper long-form organization_name authored by hand, so they're
        // skipped here rather than flattened to the generic mapping below.
        $seededEmails = ['ebautista@davaooriental.gov.ph', 'adizon@mati.gov.ph', 'frontdesk@botanikaresort.ph'];
        $roleByTitle = collect(UserRole::cases())->keyBy(fn (UserRole $role) => $role->title());

        foreach (PtoMockData::seedUsers() as $row) {
            if (in_array($row['email'], $seededEmails, true)) {
                continue;
            }

            // No independent "long-form office name" source exists for
            // these (unlike the 3 hand-authored accounts above), so
            // organization_name and organization_subtitle both take the
            // mock's single "assignment" value verbatim — documented in
            // Pto\UsersController as the same mapping real account creation
            // uses, not a one-off guess for seed data.
            User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => 'password',
                    'email_verified_at' => now(),
                    'role' => $roleByTitle[$row['role']] ?? UserRole::Lgu,
                    'organization_name' => $row['assignment'],
                    'organization_subtitle' => $row['assignment'],
                    'status' => $row['status'],
                    'last_login_at' => Carbon::parse($row['lastActive']),
                ]
            );
        }
    }
}
