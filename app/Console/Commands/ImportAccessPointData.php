<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AccessPointSession;
use Carbon\Carbon;

class ImportAccessPointData extends Command
{
    protected $signature = 'import:wifi-data {file}';
    protected $description = 'Import WiFi access point data from a CSV file';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: $filePath");
            return;
        }

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

        while (($row = fgetcsv($handle, 30000, ",")) !== false) {
            $record = array_combine($header, $row);

            // format of a session duration is like "1 min 0 sec" or "2 sec"
            // if it's shorter than 5 minutes, we'll skip it
            if (strpos($record['Session Duration'], 'min') === false) {
                $bar->advance();
                continue;
            }

            // if rssi is less than -100, we'll skip it

            if ($this->parseInteger($record['RSSI (dBm)']) < -70) {
                $bar->advance();
                continue;
            }




            


            AccessPointSession::create([
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

            $bar->advance();
        }

        fclose($handle);
        $bar->finish();
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
