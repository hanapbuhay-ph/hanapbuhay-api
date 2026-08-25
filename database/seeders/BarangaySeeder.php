<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BarangaySeeder extends Seeder
{
    public function run(): void
    {
        // Coordinates are verified center points from Google Maps.
        // Source: docs/02_DATABASE_SCHEMA.md — do not edit coordinates
        // here without updating the schema doc first.
        $barangays = [
            ['name' => 'Banlasan',          'latitude' => 10.0244195, 'longitude' => 124.3051417],
            ['name' => 'Bongbong',          'latitude' => 10.0116126, 'longitude' => 124.3267320],
            ['name' => 'Catoogan',          'latitude' => 10.0450587, 'longitude' => 124.4035561],
            ['name' => 'Guinobatan',        'latitude' => 10.0736300, 'longitude' => 124.3562141],
            ['name' => 'Hinlayagan Ilaud',  'latitude' => 10.0336482, 'longitude' => 124.3475796],
            ['name' => 'Hinlayagan Ilaya',  'latitude' => 10.0304092, 'longitude' => 124.3407613],
            ['name' => 'Kauswagan',         'latitude' => 10.0257143, 'longitude' => 124.2627008],
            ['name' => 'Kinan-oan',         'latitude' => 10.0518790, 'longitude' => 124.3251786],
            ['name' => 'La Union',          'latitude' => 10.0560058, 'longitude' => 124.3713304],
            ['name' => 'La Victoria',       'latitude' => 10.0927281, 'longitude' => 124.3630097],
            ['name' => 'Mabuhay Cabigohan', 'latitude' => 10.0475238, 'longitude' => 124.3459718],
            ['name' => 'Mahagbu',           'latitude' => 10.0382731, 'longitude' => 124.3811724],
            ['name' => 'Manuel M. Roxas',   'latitude' => 10.0264936, 'longitude' => 124.3652967],
            ['name' => 'Poblacion',         'latitude' => 10.0800649, 'longitude' => 124.3446833],
            ['name' => 'San Isidro',        'latitude' => 10.0146793, 'longitude' => 124.2978160],
            ['name' => 'San Vicente',       'latitude' => 10.0610914, 'longitude' => 124.3953556],
            ['name' => 'Santo Tomas',       'latitude' => 10.0434725, 'longitude' => 124.3250846],
            ['name' => 'Soom',              'latitude' => 10.0616358, 'longitude' => 124.3942074],
            ['name' => 'Tagum Norte',       'latitude' => 10.0795559, 'longitude' => 124.3746942],
            ['name' => 'Tagum Sur',         'latitude' => 10.0709313, 'longitude' => 124.3757935],
        ];

        $now = now();

        DB::table('barangays')->insert(
            array_map(
                fn (array $barangay) => array_merge($barangay, [
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
                $barangays
            )
        );
    }
}
