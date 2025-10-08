<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AccessPointSession as WifiSession;
use App\Models\Building;
use App\Models\Floor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function selectBuilding()
    {
        $buildings = Building::with(['floors', 'accessPointSessions' => function($query) {
            $query->orderBy('association_time', 'desc')->limit(1);
        }])->get()->map(function($building) {
            // Get the most recent session for this building
            $mostRecentSession = WifiSession::where('building_id', $building->id)
                ->orderBy('association_time', 'desc')
                ->first();
            
            $building->most_recent_data = $mostRecentSession ? $mostRecentSession->association_time : null;
            $building->total_sessions = WifiSession::where('building_id', $building->id)->count();
            
            return $building;
        });
        
        return view('building-selection', compact('buildings'));
    }

    public function selectFloor($buildingCode)
    {
        $building = Building::where('code', $buildingCode)->with('floors')->firstOrFail();
        
        // Add most recent data info for each floor
        $building->floors->transform(function($floor) use ($building) {
            $mostRecentSession = WifiSession::where('building_id', $building->id)
                ->where('floor_id', $floor->id)
                ->orderBy('association_time', 'desc')
                ->first();
            
            $floor->most_recent_data = $mostRecentSession ? $mostRecentSession->association_time : null;
            $floor->total_sessions = WifiSession::where('building_id', $building->id)
                ->where('floor_id', $floor->id)
                ->count();
            
            // Get latest import statistics
            $floor->latest_import_stats = \App\Models\ImportStatistic::where('building_id', $building->id)
                ->where('floor_id', $floor->id)
                ->orderBy('import_date', 'desc')
                ->first();
            
            return $floor;
        });
        
        return view('floor-selection', compact('building'));
    }

    public function storeBuilding(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:buildings,code',
            'description' => 'nullable|string',
            'floor_number' => 'nullable|integer',
            'floor_name' => 'nullable|string|max:255',
            'map_image_path' => 'nullable|string|max:255',
            'map_css_class' => 'nullable|string|max:255',
        ]);

        // Create the building
        $building = Building::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
        ]);

        // Create the first floor if provided
        if ($request->floor_number !== null) {
            Floor::create([
                'building_id' => $building->id,
                'name' => $request->floor_name ?: ($request->floor_number . ' Floor'),
                'number' => $request->floor_number,
                'description' => null,
                'map_image_path' => $request->map_image_path,
                'map_css_class' => $request->map_css_class,
            ]);
        }

        return redirect()->route('home')->with('success', "Building '{$building->name}' created successfully!");
    }

    public function storeFloor(Request $request, $buildingCode)
    {
        $request->validate([
            'number' => 'required|integer',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'map_image_path' => 'nullable|string|max:255',
            'map_css_class' => 'nullable|string|max:255',
        ]);

        $building = Building::where('code', $buildingCode)->firstOrFail();

        // Check if floor number already exists for this building
        $existingFloor = Floor::where('building_id', $building->id)
            ->where('number', $request->number)
            ->first();

        if ($existingFloor) {
            return redirect()->back()
                ->withErrors(['number' => 'A floor with number ' . $request->number . ' already exists in this building.'])
                ->withInput();
        }

        // Create the new floor
        $floor = Floor::create([
            'building_id' => $building->id,
            'name' => $request->name,
            'number' => $request->number,
            'description' => $request->description,
            'map_image_path' => $request->map_image_path,
            'map_css_class' => $request->map_css_class,
        ]);

        return redirect()->route('floors.select', $building->code)
            ->with('success', "Floor '{$floor->name}' created successfully for {$building->name}!");
    }

    public function uploadWifiData(Request $request)
    {
        // Debug: Log what we received
        Log::info('Upload request received', [
            'all_data' => $request->all(),
            'files' => $request->allFiles(),
            'has_csv_file' => $request->hasFile('csv_file'),
            'file_keys' => array_keys($request->allFiles())
        ]);
        
        // Check for file upload errors first
        if (empty($_FILES['csv_file']['tmp_name'])) {
            $uploadError = $_FILES['csv_file']['error'] ?? 0;
            Log::error('File upload failed', [
                'upload_error_code' => $uploadError,
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'file_size_submitted' => $_FILES['csv_file']['size'] ?? 'unknown'
            ]);
            
            if ($uploadError == UPLOAD_ERR_FORM_SIZE || $uploadError == UPLOAD_ERR_INI_SIZE) {
                return back()->withErrors(['csv_file' => 'The uploaded file is too large. Maximum size allowed is ' . ini_get('upload_max_filesize') . '. Please try a smaller file or contact your administrator.']);
            }
        }
        
        // Additional file debugging
        if ($request->hasFile('csv_file')) {
            $file = $request->file('csv_file');
            Log::info('File details', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'is_valid' => $file->isValid(),
                'error' => $file->getError(),
                'error_message' => $file->getErrorMessage(),
                'temp_path' => $file->getRealPath()
            ]);
        } else {
            Log::error('No file received in csv_file field');
        }
        
        $request->validate([
            'building_code' => 'required|exists:buildings,code',
            'floor_number' => 'required|integer',
            'csv_file' => 'required|file|mimes:csv,txt|max:20480', // Max 20MB
        ]);

        $building = Building::where('code', $request->building_code)->first();
        $floor = Floor::where('building_id', $building->id)
            ->where('number', $request->floor_number)
            ->first();

        if (!$floor) {
            return back()->withErrors(['floor_number' => 'Selected floor does not exist for this building.']);
        }

        // Store the uploaded file temporarily
        $uploadedFile = $request->file('csv_file');
        $fileName = time() . '_' . $uploadedFile->getClientOriginalName();
        $filePath = $uploadedFile->storeAs('uploads', $fileName, 'local');
        $fullPath = storage_path('app/' . $filePath);

        try {
            // Call the import command
            Artisan::call('import:wifi-data', [
                'file' => $fullPath,
                'building_code' => $request->building_code,
                'floor_number' => $request->floor_number,
            ]);

            // Get the command output
            $output = Artisan::output();
            
            // Clean up the uploaded file
            Storage::disk('local')->delete($filePath);

            // Parse import statistics from output if available
            $importedCount = 0;
            $totalCount = 0;
            
            // Try to extract numbers from the output
            if (preg_match('/Successfully imported: ([\d,]+)/', $output, $matches)) {
                $importedCount = (int) str_replace(',', '', $matches[1]);
            }
            if (preg_match('/Total records processed: ([\d,]+)/', $output, $matches)) {
                $totalCount = (int) str_replace(',', '', $matches[1]);
            }

            $message = "WiFi data uploaded successfully! ";
            if ($importedCount > 0) {
                $message .= "Imported {$importedCount} sessions";
                if ($totalCount > 0) {
                    $message .= " out of {$totalCount} total records";
                }
                $message .= ".";
            }

            return redirect()->route('floors.select', ['building' => $request->building_code])
                ->with('success', $message);

        } catch (\Exception $e) {
            // Clean up the uploaded file on error
            Storage::disk('local')->delete($filePath);
            
            return back()->withErrors(['csv_file' => 'Error processing file: ' . $e->getMessage()]);
        }
    }

    public function index(Request $request, $building = null, $floor = null)
    {
        // Handle both new URL structure and legacy requests
        if (!$building && !$floor) {
            // Legacy request - redirect to building selection homepage
            return redirect()->route('home');
        }

        // Find the building and floor
        $buildingModel = Building::where('code', $building)->firstOrFail();
        $floorModel = Floor::where('building_id', $buildingModel->id)
            ->where('number', $floor)
            ->firstOrFail();
        $startDateInput = $request->input('start_date', now()->format('Y-m-d'));
        $startDateObj = \Carbon\Carbon::createFromFormat('Y-m-d', $startDateInput);
        $startDate = $startDateObj->copy()->startOfWeek()->format('Y-m-d');

        $endDate = $startDateObj->copy()->endOfWeek()->format('Y-m-d');

        $nextWeekStartDate = $startDateObj->copy()->addWeek()->startOfWeek()->format('Y-m-d');
        $nextWeekEndDate = $startDateObj->copy()->addWeek()->endOfWeek()->format('Y-m-d');

        $previousWeekStartDate = $startDateObj->copy()->subWeek()->startOfWeek()->format('Y-m-d');
        $previousWeekEndDate = $startDateObj->copy()->subWeek()->endOfWeek()->format('Y-m-d');


        // isMostRecentWeek is a boolean that indicates whether the current week is the most recent week in the database,
        // so that i know i can disable the "Next Week" button
        $mostRecentSession = WifiSession::orderBy('association_time', 'desc')->first();
        $mostRecentDate = $mostRecentSession ? \Carbon\Carbon::parse($mostRecentSession->association_time)->format('Y-m-d') : now()->format('Y-m-d');
        $isMostRecentWeek = $startDateObj->copy()->endOfWeek()->format('Y-m-d') >= \Carbon\Carbon::parse($mostRecentDate)->endOfWeek()->format('Y-m-d');

        $oldestSession = WifiSession::orderBy('association_time', 'asc')->first();
        $oldestDate = $oldestSession ? \Carbon\Carbon::parse($oldestSession->association_time)->format('Y-m-d') : now()->format('Y-m-d');
        $isOldestWeek = $startDateObj->copy()->startOfWeek()->format('Y-m-d') <= \Carbon\Carbon::parse($oldestDate)->startOfWeek()->format('Y-m-d');
        
        $query = WifiSession::query()
            ->where('building_id', $buildingModel->id)
            ->where('floor_id', $floorModel->id);

        if ($startDate && $endDate) {
            $query->whereBetween('association_time', [$startDate, $endDate]);
        }

        $totalSessions = $query->count();

        if ($totalSessions == 0) {

            // If there are no sessions, redirect with the most recent date in the database for this building/floor
            $mostRecentSession = WifiSession::where('building_id', $buildingModel->id)
                ->where('floor_id', $floorModel->id)
                ->orderBy('association_time', 'desc')
                ->first();
            $mostRecentDate = $mostRecentSession ? \Carbon\Carbon::parse($mostRecentSession->association_time)->format('Y-m-d') : now()->format('Y-m-d');

            return redirect()->route('dashboard.building.floor', [
                'building' => $building, 
                'floor' => $floor, 
                'start_date' => $mostRecentDate
            ]);
            
        }

        $sessions = (clone $query)->get();
        $uniqueUsers = (clone $query)->distinct('client_username_hash')->count();
        $totalDevices = (clone $query)->distinct('client_mac_address')->count();
        $dateEarliest = (clone $query)->min('association_time');
        $dateLatest = (clone $query)->max('association_time');


        $averageSessionDuration = (clone $query)->avg('session_duration');
        $averageBytes = round((clone $query)->avg(DB::raw('(bytes_sent + bytes_received) / 1048576')), 2);

        $leastActiveAP = (clone $query)->select('ap_name', DB::raw('count(*) as total'))
            ->groupBy('ap_name')
            ->orderBy('total', 'asc')
            ->first();

        $leastActiveAP = [
            'ap_name' => $leastActiveAP->ap_name,
            'total' => $leastActiveAP->total
        ];

        $mostActiveSSID = (clone $query)->select('ssid', DB::raw('count(*) as total'))
            ->groupBy('ssid')
            ->orderBy('total', 'desc')
            ->first();

        $mostActiveSSID = [
            'ssid' => $mostActiveSSID->ssid,
            'total' => $mostActiveSSID->total
        ];

        $leastActiveSSID = (clone $query)->select('ssid', DB::raw('count(*) as total'))
            ->groupBy('ssid')
            ->orderBy('total', 'asc')
            ->first();

        $leastActiveSSID = [
            'ssid' => $leastActiveSSID->ssid,
            'total' => $leastActiveSSID->total
        ];

        $mostActiveUser = (clone $query)->select('client_username_hash', DB::raw('count(*) as total'))
            ->whereNotNull('client_username_hash')
            ->where('client_username_hash', '!=', '')
            ->groupBy('client_username_hash')
            ->orderBy('total', 'desc')
            ->first();

        $mostActiveUser = $mostActiveUser ? [
            'client_username_hash' => $mostActiveUser->client_username_hash,
            'total' => $mostActiveUser->total
        ] : [
            'client_username_hash' => "N/A",
            'total' => 0
        ];

        $leastActiveUser = (clone $query)->select('client_username_hash', DB::raw('count(*) as total'))
            ->groupBy('client_username_hash')
            ->orderBy('total', 'asc')
            ->first();

        $leastActiveUser = [
            'client_username_hash' => $leastActiveUser->client_username_hash,
            'total' => $leastActiveUser->total
        ];

        $affiliations = (clone $query)->select('client_affiliation', DB::raw('count(*) as total'))
            ->groupBy('client_affiliation')
            ->get();

        $numberOfDaysReflectedInData = 0;
        if ($dateEarliest && $dateLatest) {
            try {
                $dateEarliestObj = new \DateTime($dateEarliest);
                $dateLatestObj = new \DateTime($dateLatest);
                $numberOfDaysReflectedInData = $dateLatestObj->diff($dateEarliestObj)->days;
            } catch (\Exception $e) {
                // Handle the exception if needed
            }
        }

        $users = (clone $query)->select('client_username_hash')
            ->groupBy('client_username_hash')
            ->havingRaw('count(*) > 1')
            ->get();

        $totalSessionThroughput = (clone $query)->sum('session_throughput');

        $ssids = (clone $query)->select('ssid', DB::raw('count(*) as total'))
            ->groupBy('ssid')
            ->get()
            ->toArray();

        $mostActiveAP = (clone $query)->select('ap_name', DB::raw('count(*) as total'))
            ->groupBy('ap_name')
            ->orderBy('total', 'desc')
            ->first();
        
        $mostActiveAP = [
            'ap_name' => $mostActiveAP->ap_name,
            'total' => $mostActiveAP->total
        ];


        $returningUsers = (clone $query)->select('client_username_hash', DB::raw('count(*) as total'))
            ->groupBy('client_username_hash')
            ->having('total', '>', 1)
            ->get()
            ->count();

        $longestSessionRecord = (clone $query)->select('session_duration')
            ->whereNotNull('client_username_hash')
            ->where('client_username_hash', '!=', '')
            ->orderBy('session_duration', 'desc')
            ->first();

        $longestSession = $longestSessionRecord ? $longestSessionRecord->session_duration : null;

        $allAPs = (clone $query)->select('ap_name', DB::raw('count(*) as total'))
            ->groupBy('ap_name')
            ->get()
            ->map(function ($ap) use ($query) {
                $ap->unique_users = $query->where('ap_name', $ap->ap_name)
                    ->distinct('client_username_hash')
                    ->count('client_username_hash');

                $ap->returning_users = $query->where('ap_name', $ap->ap_name)
                    ->select('client_username_hash')
                    ->groupBy('client_username_hash')
                    ->havingRaw('count(*) > 1')
                    ->get()
                    ->count();

                $ap->total_devices = $query->where('ap_name', $ap->ap_name)
                    ->distinct('client_mac_address')
                    ->count('client_mac_address');

                $ap->average_bytes = round($query->where('ap_name', $ap->ap_name)
                    ->avg(DB::raw('(bytes_sent + bytes_received) / 1048576')), 2);

                return $ap;
            })
            ->toArray();




        return view('welcome', compact(
            'sessions',
            'totalSessions',
            'affiliations',
            'uniqueUsers',
            'mostActiveAP',
            'returningUsers',
            'totalDevices',
            'dateEarliest',
            'dateLatest',
            'averageSessionDuration',
            'numberOfDaysReflectedInData',
            'longestSession',
            'allAPs',
            'startDate',
            'endDate',
            'nextWeekStartDate',
            'nextWeekEndDate',
            'previousWeekStartDate',
            'previousWeekEndDate',
            'averageBytes',
            'totalSessionThroughput',
            'ssids',
            'users',
            'mostActiveSSID',
            'leastActiveSSID',
            'mostActiveUser',
            'leastActiveUser',
            'leastActiveAP',
            'isMostRecentWeek',
            'isOldestWeek',
            'building',
            'floor',
            'buildingModel',
            'floorModel'
        ));
    }
}
