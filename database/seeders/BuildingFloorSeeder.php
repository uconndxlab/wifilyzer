<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Building;
use App\Models\Floor;

class BuildingFloorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Hall building
        $hall = Building::create([
            'name' => 'Hall Building',
            'code' => 'HALL',
            'description' => 'Main Hall building'
        ]);

        // Create 4th floor
        Floor::create([
            'building_id' => $hall->id,
            'name' => '4th Floor',
            'number' => 4,
            'description' => 'Hall 4th Floor',
            'map_image_path' => 'Hall4.png',
            'map_css_class' => 'hall-4th-floor'
        ]);
    }
}
