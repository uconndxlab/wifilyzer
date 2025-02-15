<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AccessPointSession as WifiSession;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
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
        
        $query = WifiSession::query();

        if ($startDate && $endDate) {
            $query->whereBetween('association_time', [$startDate, $endDate]);
        }

        $totalSessions = $query->count();

        if ($totalSessions == 0) {

            // If there are no sessions, redirect to the index route with ?start_date equal to the most recent date in the database
            $mostRecentSession = WifiSession::orderBy('association_time', 'desc')->first();
            $mostRecentDate = $mostRecentSession ? \Carbon\Carbon::parse($mostRecentSession->association_time)->format('Y-m-d') : now()->format('Y-m-d');

            return redirect()->route('dashboard.index', ['start_date' => $mostRecentDate]);
            
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
            'isOldestWeek'
        ));
    }
}
