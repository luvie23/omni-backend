<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'contractor_search_radius_miles'],
            [
                'value' => '100',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
