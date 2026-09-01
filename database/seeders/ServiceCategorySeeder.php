<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Electrical Works',
            'Plumbing',
            'House Cleaning',
            'Tutoring',
            'Aircon Repair & Cleaning',
            'Carpentry',
            'Painting',
            'Masonry',
            'Gardening & Landscaping',
            'Cooking & Catering',
            'Caregiving',
            'Laundry',
            'Welding',
            'Auto Repair & Mechanic',
            'Computer Repair & IT',
        ];

        foreach ($categories as $name) {
            ServiceCategory::firstOrCreate(['name' => $name]);
        }
    }
}
