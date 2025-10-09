<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AccessPointSession as WifiSession;
use App\Models\Building;
use App\Models\Floor;
use App\Models\AccessPointPosition;
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
            'map_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240', // 10MB max
            'map_image_filename' => 'nullable|string|max:255',
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
            // Handle image upload for the floor
            $mapImagePath = null;
            if ($request->hasFile('map_image_file')) {
                $uploadedFile = $request->file('map_image_file');
                $filename = $request->map_image_filename ?: 
                    strtolower($building->code) . '-floor-' . $request->floor_number . '.' . $uploadedFile->getClientOriginalExtension();
                
                // Store the image in public directory
                $uploadedFile->move(public_path(), $filename);
                $mapImagePath = $filename;
            }

            Floor::create([
                'building_id' => $building->id,
                'name' => $request->floor_name ?: ($request->floor_number . ' Floor'),
                'number' => $request->floor_number,
                'description' => null,
                'map_image_path' => $mapImagePath,
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
            'map_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240', // 10MB max
            'map_image_filename' => 'nullable|string|max:255',
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

        // Handle image upload
        $mapImagePath = null;
        if ($request->hasFile('map_image_file')) {
            $uploadedFile = $request->file('map_image_file');
            $filename = $request->map_image_filename ?: 
                strtolower($building->code) . '-floor-' . $request->number . '.' . $uploadedFile->getClientOriginalExtension();
            
            // Store the image in public directory
            $uploadedFile->move(public_path(), $filename);
            $mapImagePath = $filename;
        }

        // Create the new floor
        $floor = Floor::create([
            'building_id' => $building->id,
            'name' => $request->name,
            'number' => $request->number,
            'description' => $request->description,
            'map_image_path' => $mapImagePath,
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
        
        try {
            // Store the file using Laravel's storage system - use public disk to avoid the private folder
            $filePath = $uploadedFile->storeAs('uploads', $fileName, 'public');
            $fullPath = storage_path('app/public/' . $filePath);

            // Debug: Log file storage details
            Log::info('File storage details', [
                'original_name' => $uploadedFile->getClientOriginalName(),
                'original_size' => $uploadedFile->getSize(),
                'stored_filename' => $fileName,
                'file_path' => $filePath,
                'full_path' => $fullPath,
                'file_stored_successfully' => file_exists($fullPath),
                'file_size_after_storage' => file_exists($fullPath) ? filesize($fullPath) : 'N/A'
            ]);

            // Verify the file was stored successfully
            if (!file_exists($fullPath)) {
                Log::error('File was not stored successfully', [
                    'expected_path' => $fullPath,
                    'file_path_returned' => $filePath,
                    'storage_path' => storage_path('app'),
                    'is_valid_upload' => $uploadedFile->isValid(),
                    'upload_error' => $uploadedFile->getError(),
                    'upload_error_message' => $uploadedFile->getErrorMessage()
                ]);
                return back()->withErrors(['csv_file' => 'Failed to store uploaded file. Please try again.']);
            }
        } catch (\Exception $e) {
            Log::error('Exception during file storage', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors(['csv_file' => 'Error storing file: ' . $e->getMessage()]);
        }

        try {
            // Debug: Log the command details
            Log::info('About to call import command', [
                'file' => $fullPath,
                'building_code' => $request->building_code,
                'floor_number' => $request->floor_number,
                'file_exists' => file_exists($fullPath),
                'file_size' => file_exists($fullPath) ? filesize($fullPath) : 'N/A'
            ]);

            // Call the import command
            $exitCode = Artisan::call('import:wifi-data', [
                'file' => $fullPath,
                'building_code' => $request->building_code,
                'floor_number' => $request->floor_number,
            ]);

            // Get the command output
            $output = Artisan::output();
            
            // Debug: Log the command results
            Log::info('Import command completed', [
                'exit_code' => $exitCode,
                'output' => $output,
                'output_length' => strlen($output)
            ]);
            
            // Clean up the uploaded file
            Storage::disk('public')->delete($filePath);

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

            // Debug: Log parsing results
            Log::info('Parsed import results', [
                'imported_count' => $importedCount,
                'total_count' => $totalCount,
                'raw_output' => $output
            ]);

            $message = "WiFi data uploaded successfully! ";
            if ($importedCount > 0) {
                $message .= "Imported {$importedCount} sessions";
                if ($totalCount > 0) {
                    $message .= " out of {$totalCount} total records";
                }
                $message .= ".";
            } else {
                // If no records were imported, include more details
                $message .= "Command executed with exit code {$exitCode}. ";
                if (trim($output)) {
                    $message .= "Output: " . trim($output);
                } else {
                    $message .= "No output from import command.";
                }
            }

            return redirect()->route('floors.select', ['building' => $request->building_code])
                ->with('success', $message);

        } catch (\Exception $e) {
            // Clean up the uploaded file on error
            if (isset($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
            
            Log::error('Import command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
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

        // Check if there are any sessions at all for this building/floor (regardless of date range)
        $totalSessionsOverall = WifiSession::where('building_id', $buildingModel->id)
            ->where('floor_id', $floorModel->id)
            ->count();

        if ($totalSessionsOverall == 0) {
            // No sessions exist at all for this building/floor - show empty state
            return view('welcome', [
                'sessions' => collect([]),
                'totalSessions' => 0,
                'affiliations' => collect([]),
                'uniqueUsers' => 0,
                'mostActiveAP' => ['ap_name' => 'N/A', 'total' => 0],
                'returningUsers' => 0,
                'totalDevices' => 0,
                'dateEarliest' => null,
                'dateLatest' => null,
                'averageSessionDuration' => 0,
                'numberOfDaysReflectedInData' => 0,
                'longestSession' => null,
                'allAPs' => [],
                'startDate' => $startDate,
                'endDate' => $endDate,
                'nextWeekStartDate' => $nextWeekStartDate,
                'nextWeekEndDate' => $nextWeekEndDate,
                'previousWeekStartDate' => $previousWeekStartDate,
                'previousWeekEndDate' => $previousWeekEndDate,
                'averageBytes' => 0,
                'totalSessionThroughput' => 0,
                'ssids' => [],
                'users' => collect([]),
                'mostActiveSSID' => ['ssid' => 'N/A', 'total' => 0],
                'leastActiveSSID' => ['ssid' => 'N/A', 'total' => 0],
                'mostActiveUser' => ['client_username_hash' => 'N/A', 'total' => 0],
                'leastActiveUser' => ['client_username_hash' => 'N/A', 'total' => 0],
                'leastActiveAP' => ['ap_name' => 'N/A', 'total' => 0],
                'isMostRecentWeek' => true,
                'isOldestWeek' => true,
                'building' => $building,
                'floor' => $floor,
                'buildingModel' => $buildingModel,
                'floorModel' => $floorModel,
                'noDataMessage' => 'No WiFi session data has been imported for this building and floor yet.'
            ]);
        }

        if ($totalSessions == 0) {
            // Sessions exist for this building/floor but not in the current date range
            // Redirect to the most recent week with data (avoid infinite redirect)
            if (!$request->has('start_date')) {
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

        // Add position data to access points
        $positions = AccessPointPosition::where('floor_id', $floorModel->id)
            ->get()
            ->keyBy('ap_name');

        foreach ($allAPs as &$ap) {
            if (isset($positions[$ap['ap_name']])) {
                $position = $positions[$ap['ap_name']];
                $ap['x_percentage'] = $position->x_percentage;
                $ap['y_percentage'] = $position->y_percentage;
                $ap['positioned'] = true;
            } else {
                $ap['x_percentage'] = null;
                $ap['y_percentage'] = null;
                $ap['positioned'] = false;
            }
        }




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

    public function storeAccessPointPosition(Request $request, Building $building, Floor $floor)
    {
        try {
            \Log::info('Position request received', [
                'building_id' => $building->id,
                'floor_id' => $floor->id,
                'request_data' => $request->all()
            ]);

            $request->validate([
                'ap_name' => 'required|string',
                'x_percentage' => 'required|numeric|min:0|max:100',
                'y_percentage' => 'required|numeric|min:0|max:100',
            ]);

            $position = AccessPointPosition::updateOrCreate(
                [
                    'floor_id' => $floor->id,
                    'ap_name' => $request->ap_name,
                ],
                [
                    'x_percentage' => $request->x_percentage,
                    'y_percentage' => $request->y_percentage,
                ]
            );

            \Log::info('Position saved successfully', ['position' => $position->toArray()]);

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Access point position saved successfully!']);
            }

            return redirect()->back()->with('success', 'Access point position saved successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error in position storage', ['errors' => $e->errors()]);
            
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
            }
            
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Error saving position', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            
            return redirect()->back()->with('error', 'Error saving position: ' . $e->getMessage());
        }
    }
}
