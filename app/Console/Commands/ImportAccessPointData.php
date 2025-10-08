<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AccessPointSession;
use App\Models\Building;
use App\Models\Floor;
use App\Models\ImportStatistic;
use Carbon\Carbon;

class ImportAccessPointData extends Command
{
    protected $signature = 'import:wifi-data {file} {building_code} {floor_number}';
    protected $description = 'Import WiFi access point data from a CSV file for a specific building and floor';

    public function handle()
    {
        $filePath = $this->argument('file');
        $buildingCode = $this->argument('building_code');
        $floorNumber = (int) $this->argument('floor_number');

        if (!file_exists($filePath)) {
            $this->error("File not found: $filePath");
            return;
        }

        // Find or create building
        $building = Building::where('code', $buildingCode)->first();
        if (!$building) {
            $this->error("Building with code '{$buildingCode}' not found. Please create it first.");
            return;
        }

        // Find or create floor
        $floor = Floor::where('building_id', $building->id)
            ->where('number', $floorNumber)
            ->first();
        if (!$floor) {
            $this->error("Floor {$floorNumber} not found in building '{$buildingCode}'. Please create it first.");
            return;
        }

        $this->info("Importing data for {$building->name}, Floor {$floor->number}");

        $handle = fopen($filePath, 'r');

        if (!$handle) {
            $this->error("Unable to open file: $filePath");
            return;
        }

        $header = fgetcsv($handle, 1000, ","); // Read header row (assuming comma-separated values)

        if (!$header) {
            $this->error("Invalid CSV format: Missing header row");
            fclose($handle);
            return;
        }

        $bar = $this->output->createProgressBar();
        $bar->start();

        // Track filtering statistics
        $totalRecords = 0;
        $shortDurationFiltered = 0;
        $weakSignalFiltered = 0;
        $importedRecords = 0;

        while (($row = fgetcsv($handle, 30000, ",")) !== false) {
            $totalRecords++;
            $record = array_combine($header, $row);

            // format of a session duration is like "1 min 0 sec" or "2 sec"
            // if it's shorter than 1 minute, we'll skip it
            if (strpos($record['Session Duration'], 'min') === false) {
                $shortDurationFiltered++;
                $bar->advance();
                continue;
            }

            // if rssi is weaker than -70 dBm, we'll skip it
            if ($this->parseInteger($record['RSSI (dBm)']) < -70) {
                $weakSignalFiltered++;
                $bar->advance();
                continue;
            }




            


            AccessPointSession::create([
                'building_id'            => $building->id,
                'floor_id'               => $floor->id,
                'client_username_hash'   => $record['Client Username Hash'] ?? null,
                'client_affiliation'     => $record['Client Affiliation'] ?? null,
                'client_ip_address'      => $record['Client IP Address'] ?? null,
                'client_mac_address'     => $record['Client MAC Address'] ?? null,
                'association_time'       => $this->parseTimestamp($record['Association Time'] ?? null),
                'ap_name'                => $record['AP Name'] ?? null,
                'ssid'                   => $record['SSID'] ?? null,
                'session_duration'       => $this->parseInteger($record['Session Duration'] ?? null),
                'avg_session_throughput' => $this->parseInteger($record['Avg. Session Throughput(Kbps)'] ?? null),
                'endpoint_type'          => $record['Endpoint Type'] ?? null,
                'disassociation_time'    => $this->parseTimestamp($record['Disassociation Time'] ?? null),
                'bytes_sent'             => $this->parseInteger($record['Bytes Sent'] ?? null),
                'bytes_received'         => $this->parseInteger($record['Bytes Received'] ?? null),
                'rssi'                   => $this->parseInteger($record['RSSI (dBm)'] ?? null),
            ]);

            $importedRecords++;
            $bar->advance();
        }

        fclose($handle);
        $bar->finish();
        
        // Save import statistics
        ImportStatistic::create([
            'building_id' => $building->id,
            'floor_id' => $floor->id,
            'total_records_processed' => $totalRecords,
            'imported_records' => $importedRecords,
            'short_duration_filtered' => $shortDurationFiltered,
            'weak_signal_filtered' => $weakSignalFiltered,
            'import_date' => now(),
            'file_name' => basename($filePath),
        ]);

        // Display import statistics
        $this->info("\n=== Import Statistics ===");
        $this->info("Total records processed: " . number_format($totalRecords));
        $this->info("Successfully imported: " . number_format($importedRecords));
        $this->info("Filtered out (short duration < 1 min): " . number_format($shortDurationFiltered));
        $this->info("Filtered out (weak signal < -70 dBm): " . number_format($weakSignalFiltered));
        $this->info("Import rate: " . round(($importedRecords / $totalRecords) * 100, 1) . "%");
        $this->info("\nImport completed successfully!");
    }

    private function parseTimestamp($timestamp)
    {
        return $timestamp ? Carbon::parse($timestamp)->toDateTimeString() : null;
    }

    private function parseInteger($value)
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
