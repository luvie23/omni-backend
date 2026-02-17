<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Contractor;
use App\Models\CertifiedPerson;
use Spatie\Permission\PermissionRegistrar;

class ContractorDemoSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Contractor 1
        |--------------------------------------------------------------------------
        */
        $user1 = User::firstOrCreate(
            ['email' => 'contractor1@example.com'],
            [
                'name' => 'Bright Lights Contractor',
                'password' => Hash::make('password123'),
            ]
        );

        $user1->assignRole('contractor');

        $contractor1 = Contractor::updateOrCreate(
            ['user_id' => $user1->id],
            [
                'company_name' => 'Bright Lights Co.',
                'company_website_url' => 'https://brightlights.com',
                'mailing_address' => '123 Main St',
                'city' => 'Austin',
                'state' => 'TX',
                'zip' => '78701',
                'service_area' => 'Austin Metro',
            ]
        );

        CertifiedPerson::updateOrCreate(
            [
                'contractor_id' => $contractor1->id,
                'certification_number' => 'CERT-1001',
            ],
            [
                'name' => 'Carlos Martinez',
            ]
        );

        CertifiedPerson::updateOrCreate(
            [
                'contractor_id' => $contractor1->id,
                'certification_number' => 'CERT-1002',
            ],
            [
                'name' => 'Maria Lopez',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Contractor 2
        |--------------------------------------------------------------------------
        */
        $user2 = User::firstOrCreate(
            ['email' => 'contractor2@example.com'],
            [
                'name' => 'Holiday Installers Contractor',
                'password' => Hash::make('password123'),
            ]
        );

        $user2->assignRole('contractor');

        $contractor2 = Contractor::updateOrCreate(
            ['user_id' => $user2->id],
            [
                'company_name' => 'Holiday Installers Inc.',
                'company_website_url' => 'https://holidayinstallers.com',
                'mailing_address' => '456 Oak Ave',
                'city' => 'Dallas',
                'state' => 'TX',
                'zip' => '75201',
                'service_area' => 'Dallas–Fort Worth',
            ]
        );

        CertifiedPerson::updateOrCreate(
            [
                'contractor_id' => $contractor2->id,
                'certification_number' => 'CERT-2001',
            ],
            [
                'name' => 'James Wilson',
            ]
        );

        CertifiedPerson::updateOrCreate(
            [
                'contractor_id' => $contractor2->id,
                'certification_number' => 'CERT-2002',
            ],
            [
                'name' => 'Sofia Ramirez',
            ]
        );
    }
}
