<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>WiFi Usage Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    {{-- some cool modern font --}}
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300&display=swap" rel="stylesheet">
    <link href={{ asset('css/style.css') }} rel="stylesheet">
    <script src="https://unpkg.com/htmx.org@1.9.2"></script>

</head>

<body hx-boost hx-target="#dashboard">

    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4 position-sticky top-0 shadow-sm">
        <div class="container-fluid">
            <a href="{{ route('dashboard.index', ['start_date' => $previousWeekStartDate]) }}"
                class="btn btn-primary @if ($isOldestWeek) disabled @endif d-none d-lg-inline">Previous Week</a>
            <a href="{{ route('dashboard.index', ['start_date' => $previousWeekStartDate]) }}"
                class="btn btn-primary @if ($isOldestWeek) disabled @endif d-lg-none"><i class="bi bi-arrow-left"></i></a>

            <span class="navbar-text mx-auto h4 mb-0">Week of {{ $startDate }}</span>

            <a href="{{ route('dashboard.index', ['start_date' => $nextWeekStartDate]) }}"
                class="btn btn-primary @if ($isMostRecentWeek) disabled @endif d-none d-lg-inline">Next Week</a>
            <a href="{{ route('dashboard.index', ['start_date' => $nextWeekStartDate]) }}"
                class="btn btn-primary @if ($isMostRecentWeek) disabled @endif d-lg-none"><i class="bi bi-arrow-right"></i></a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-3">Hall 4th Floor</h2>





        {{-- form to submit date range --}}

        {{-- if totalsessions is not null or empty --}}

        @if (!empty($sessions))

            <!-- KPI Cards -->
            <div class="row mb-4 kpi-cards">
                <div class="col">
                    <div class="card text-white bg-primary shadow-lg">
                        <div class="card-body">
                            <h5 class="card-title badge bg-dark">Total Sessions</h5>
                            <p class="card-text h6">{{ $totalSessions }}</p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-white bg-success shadow-lg">
                        <div class="card-body">
                            <h5 class="card-title badge bg-dark">Unique Users</h5>
                            <p class="card-text h6">{{ $uniqueUsers }}</p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-white bg-warning shadow-lg">
                        <div class="card-body">
                            <h5 class="card-title badge bg-dark">Most Active AP</h5>
                            <p class="card-text h6">{{ $mostActiveAP['ap_name'] }}</p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-white bg-danger shadow-lg">
                        <div class="card-body">
                            <h5 class="card-title badge bg-dark">Returning Users</h5>
                            <p class="card-text h6">{{ $returningUsers }}</p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-white bg-info shadow-lg">
                        <div class="card-body">
                            <h5 class="card-title badge bg-dark">Total Devices</h5>
                            <p class="card-text h6">{{ $totalDevices }}</p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-white bg-tertiary shadow-lg">
                        <div class="card-body">
                            <h5 class="card-title badge bg-dark">Average MB</h5>
                            <p class="card-text h6">{{ $averageBytes }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4 affiliation-cards">
                {{-- for each $affiliations (which is grouped) --}}
                @foreach ($affiliations as $affiliation)
                    <div class="col">
                        <div class="card text-white bg-tertiary shadow-lg">
                            <div class="card-body">
                                <h5 class="card-title badge bg-dark">{{ $affiliation['client_affiliation'] }}</h5>
                                <p class="card-text h6">{{ $affiliation['total'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row mb-4">
                <div class="col-md-10 offset-md-1">
                    {{-- image with background of Hall4.jpg, width 100% background must be contained --}}

                    <div class="map">
                        <img src="{{ asset('Hall4.png') }}" alt="Hall 4th Floor" class="img-fluid">
                        @foreach ($allAPs as $ap)
                            <div class="heatmap-circle" title="{{ $ap['ap_name'] }}: {{ $ap['total'] }} sessions"
                                {{-- set top and left based on AP name --}}
                                @php

                                switch ($ap['ap_name']) {
                                    case 'HALL-406-STRS':
                                        $ap['top'] = 26;
                                        $ap['left'] = 21;
                                        break;
                                    case 'HALL-409hall-STRS':
                                        $ap['top'] = 38;
                                        $ap['left'] = 34;
                                        break;
                                    case 'HALL-416hall-STRS':
                                        $ap['top'] = 38;
                                        $ap['left'] = 66;
                                        break;
                                    
                                    case 'HALL-418-STRS':
                                        $ap['top'] = 23;
                                        $ap['left'] = 87;
                                        break;
                                    case 'HALL-421hall-STRS':
                                        $ap['top'] = 65;
                                        $ap['left'] = 86;
                                        break;
                                    case 'HALL-C4A.E05-STRS':
                                        $ap['top'] = 60;
                                        $ap['left'] = 12;
                                        break;
                                }
                                    
                                @endphp
                                 @php
                                    $maxSessions = max(array_column($allAPs, 'total'));
                                    $size = ($ap['total'] / $maxSessions) * 150;
                                    $size = max(min($size, 150), 30);
                                 @endphp
                                 style="top: {{ $ap['top'] }}%; left: {{ $ap['left'] }}%; 
                                        width: {{ $size }}px; height: {{ $size }}px; 
                                        background: radial-gradient(circle, rgba(255, 0, 0, 0.8) 0%, rgba(255, 255, 0, 0.5) 100%);">
                                    <span class="badge bg-dark">{{ $ap['total'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    
                </div>
            </div>


            <div class="row mb-4">
                <div class="col-md-12">
                    <!-- Other Info -->
                    <div class="card shadow-lg">
                        <div class="card-header bg-dark text-white">
                            <h5 class="card-title">More Information</h5>
                        </div>
                        <div class="card-body bg-light text-dark">
                            <dl class="row">

                                <dt class="col-sm-3">Earliest Session Date</dt>
                                <dd class="col-sm-9">{{ \Carbon\Carbon::parse($dateEarliest)->format('F j, Y g:i A') }}
                                </dd>

                                <dt class="col-sm-3">Latest Session Date</dt>
                                <dd class="col-sm-9">{{ \Carbon\Carbon::parse($dateLatest)->format('F j, Y g:i A') }}
                                </dd>

                                <dt class="col-sm-3">Least Active AP</dt>
                                <dd class="col-sm-9">{{ $leastActiveAP['ap_name'] }} ({{ $leastActiveAP['total'] }}
                                    sessions)
                                </dd>

                                <dt class="col-sm-3">Most Active SSID</dt>
                                <dd class="col-sm-9">{{ $mostActiveSSID['ssid'] }} ({{ $mostActiveSSID['total'] }}
                                    sessions)
                                </dd>

                                <dt class="col-sm-3">Least Active SSID</dt>
                                <dd class="col-sm-9">{{ $leastActiveSSID['ssid'] }} ({{ $leastActiveSSID['total'] }}
                                    sessions)
                                </dd>

                                <dt class="col-sm-3">Most Active User</dt>
                                <dd class="col-sm-9">{{ $mostActiveUser['client_username_hash'] }}
                                    ({{ $mostActiveUser['total'] }}
                                    sessions)</dd>

                                <dt class="col-sm-3">Least Active User</dt>
                                <dd class="col-sm-9">{{ $leastActiveUser['client_username_hash'] }}
                                    ({{ $leastActiveUser['total'] }} sessions)</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                {{-- card for each access point --}}
                @foreach ($allAPs as $accessPoint)
                    <div class="col-md-4">
                        <div class="card mb-3 shadow-lg">
                            <div class="card-header bg-dark text-white">
                                <h5 class="card-title">{{ $accessPoint['ap_name'] }}</h5>
                            </div>
                            <div class="card-body bg-light text-dark">
                                <dl class="row">
                                    <dt class="col-sm-6">Total Sessions</dt>
                                    <dd class="col-sm-6">{{ $accessPoint['total'] }}</dd>
                                    <dt class="col-sm-6">Unique Users</dt>
                                    <dd class="col-sm-6">{{ $accessPoint['unique_users'] }}</dd>
                                    <dt class="col-sm-6">Returning Users</dt>
                                    <dd class="col-sm-6">{{ $accessPoint['returning_users'] }}</dd>
                                    <dt class="col-sm-6">Total Devices</dt>
                                    <dd class="col-sm-6">{{ $accessPoint['total_devices'] }}</dd>
                                    <dt class="col-sm-6">Average MB</dt>
                                    <dd class="col-sm-6">{{ $accessPoint['average_bytes'] }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="alert alert-danger" role="alert">
                No data available for the selected date range: {{ $startDate }} to {{ $endDate }}
            </div>

        @endif




    </div>
</body>

</html>
