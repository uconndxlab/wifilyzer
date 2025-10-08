<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Building;
use App\Models\Floor;

class AddBuildingFloor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wifi:add-location 
                          {--building-name= : The full name of the building}
                          {--building-code= : The short code for the building}
                          {--floor-number= : The floor number}
                          {--floor-name= : The floor name}
                          {--map-image= : Path to the floor map image}
                          {--map-css-class= : CSS class for custom floor styling}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a new building and/or floor for WiFi data analysis';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get or ask for building information
        $buildingName = $this->option('building-name') ?: $this->ask('Building name (e.g., "Hall Building")');
        $buildingCode = $this->option('building-code') ?: $this->ask('Building code (e.g., "HALL")');
        
        // Find or create building
        $building = Building::where('code', $buildingCode)->first();
        if (!$building) {
            $building = Building::create([
                'name' => $buildingName,
                'code' => strtoupper($buildingCode),
                'description' => $this->ask('Building description (optional)', '')
            ]);
            $this->info("Created building: {$building->name} ({$building->code})");
        } else {
            $this->info("Using existing building: {$building->name} ({$building->code})");
        }

        // Get floor information
        $floorNumber = $this->option('floor-number') ?: $this->ask('Floor number');
        $floorName = $this->option('floor-name') ?: $this->ask('Floor name (e.g., "4th Floor")', "{$floorNumber} Floor");
        $mapImage = $this->option('map-image') ?: $this->ask('Map image filename (place in public/ folder)', 'floor-map.png');
        $mapCssClass = $this->option('map-css-class') ?: $this->ask('CSS class for styling (optional)', '');

        // Check if floor already exists
        $existingFloor = Floor::where('building_id', $building->id)
            ->where('number', $floorNumber)
            ->first();

        if ($existingFloor) {
            $this->error("Floor {$floorNumber} already exists in {$building->name}");
            return;
        }

        // Create floor
        $floor = Floor::create([
            'building_id' => $building->id,
            'name' => $floorName,
            'number' => (int) $floorNumber,
            'description' => $this->ask('Floor description (optional)', ''),
            'map_image_path' => $mapImage,
            'map_css_class' => $mapCssClass
        ]);

        $this->info("Created floor: {$floor->name} in {$building->name}");
        $this->info("You can now import WiFi data using:");
        $this->line("php artisan import:wifi-data /path/to/data.csv {$building->code} {$floor->number}");
        $this->info("Dashboard will be available at:");
        $this->line("/dashboard/{$building->code}/{$floor->number}");
    }
}
