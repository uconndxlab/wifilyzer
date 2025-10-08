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
            <div class="d-flex align-items-center">
                <a href="{{ route('home') }}" class="btn btn-outline-secondary me-2" title="Home">
                    <i class="bi bi-house"></i>
                </a>
                <a href="{{ route('floors.select', ['building' => $building]) }}" class="btn btn-outline-info me-3" title="Change Floor">
                    <i class="bi bi-building"></i>
                </a>
                <a href="{{ route('dashboard.building.floor', ['building' => $building, 'floor' => $floor, 'start_date' => $previousWeekStartDate]) }}"
                    class="btn btn-primary @if ($isOldestWeek) disabled @endif d-none d-lg-inline">Previous Week</a>
                <a href="{{ route('dashboard.building.floor', ['building' => $building, 'floor' => $floor, 'start_date' => $previousWeekStartDate]) }}"
                    class="btn btn-primary @if ($isOldestWeek) disabled @endif d-lg-none"><i class="bi bi-arrow-left"></i></a>
            </div>

            <span class="navbar-text mx-auto h4 mb-0">{{ $buildingModel->name }} - {{ $floorModel->name }} - Week of {{ $startDate }}</span>

            <div class="d-flex align-items-center">
                <a href="{{ route('dashboard.building.floor', ['building' => $building, 'floor' => $floor, 'start_date' => $nextWeekStartDate]) }}"
                    class="btn btn-primary @if ($isMostRecentWeek) disabled @endif d-none d-lg-inline">Next Week</a>
                <a href="{{ route('dashboard.building.floor', ['building' => $building, 'floor' => $floor, 'start_date' => $nextWeekStartDate]) }}"
                    class="btn btn-primary @if ($isMostRecentWeek) disabled @endif d-lg-none"><i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-3">{{ $buildingModel->name }} - {{ $floorModel->name }}</h2>





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

                    <div class="map {{ $floorModel->map_css_class }}">
                        <img src="{{ asset($floorModel->map_image_path) }}" alt="{{ $buildingModel->name }} {{ $floorModel->name }}" class="img-fluid">
                        @foreach ($allAPs as $ap)
                            @php
                                $maxSessions = 500; // Assume a max total for scaling
                                $size = ($ap['total'] / $maxSessions) * 350;
                                $size = max(min($size, 350), 30);
                            @endphp
                            <div class="heatmap-circle" 
                                 title="{{ $ap['ap_name'] }}: {{ $ap['total'] }} sessions"
                                 style="width: {{ $size }}px; height: {{ $size }}px; 
                                        background: radial-gradient(circle, rgba(255, 69, 0, 0.8) 0%, rgba(255, 69, 0, 0.5) 50%, rgba(255, 69, 0, 0) 100%);">
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

            <!-- Data Import Criteria -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow-lg">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title"><i class="bi bi-funnel"></i> Data Import Filtering Criteria</h5>
                        </div>
                        <div class="card-body bg-light text-dark">
                            <p class="mb-3">WiFi session data is filtered during import to ensure quality and relevance:</p>
                            <dl class="row">
                                <dt class="col-sm-3">Minimum Duration</dt>
                                <dd class="col-sm-9">
                                    <span class="badge bg-primary">≥ 1 minute</span>
                                    <small class="text-muted d-block">Sessions shorter than 1 minute are excluded as they typically represent brief connections or authentication attempts.</small>
                                </dd>

                                <dt class="col-sm-3">Signal Strength</dt>
                                <dd class="col-sm-9">
                                    <span class="badge bg-success">≥ -70 dBm</span>
                                    <small class="text-muted d-block">Weak signals below -70 dBm are excluded to focus on reliable connections with good signal quality.</small>
                                </dd>

                                <dt class="col-sm-3">Quality Threshold</dt>
                                <dd class="col-sm-9">
                                    <span class="text-muted">These filters help ensure the dashboard shows meaningful WiFi usage patterns rather than connection noise.</span>
                                </dd>
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
