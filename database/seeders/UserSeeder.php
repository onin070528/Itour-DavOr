<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed one sample account per authenticated role.
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
            ]
        );
    }
}
