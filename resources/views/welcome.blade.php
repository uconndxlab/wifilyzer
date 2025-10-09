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
                    {{-- Setup Mode Toggle --}}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5>{{ $buildingModel->name }} - {{ $floorModel->name }} Floor Plan</h5>
                        <button id="setupModeToggle" class="btn btn-outline-primary">
                            <i class="bi bi-gear"></i> Setup Mode
                        </button>
                    </div>
                    
                    {{-- Setup Mode Instructions --}}
                    <div id="setupInstructions" class="alert alert-info" style="display: none;">
                        <strong>Setup Mode Active:</strong> Click anywhere on the map to position an access point, or click on an existing access point (highlighted with yellow border) to reposition it.
                    </div>

                    <div class="map {{ $floorModel->map_css_class }}" id="floorMap" style="position: relative; cursor: default;">
                        <img src="{{ asset($floorModel->map_image_path) }}" alt="{{ $buildingModel->name }} {{ $floorModel->name }}" class="img-fluid" id="mapImage">
                        
                        @foreach ($allAPs as $ap)
                            @if($ap['positioned'])
                                @php
                                    $maxSessions = 500; // Assume a max total for scaling
                                    $size = ($ap['total'] / $maxSessions) * 350;
                                    $size = max(min($size, 350), 30);
                                @endphp
                                <div class="heatmap-circle positioned" 
                                     title="{{ $ap['ap_name'] }}: {{ $ap['total'] }} sessions"
                                     data-ap-name="{{ $ap['ap_name'] }}"
                                     style="position: absolute; 
                                            left: {{ $ap['x_percentage'] }}%; 
                                            top: {{ $ap['y_percentage'] }}%; 
                                            transform: translate(-50%, -50%);
                                            width: {{ $size }}px; 
                                            height: {{ $size }}px; 
                                            background: radial-gradient(circle, rgba(255, 69, 0, 0.8) 0%, rgba(255, 69, 0, 0.5) 50%, rgba(255, 69, 0, 0) 100%);">
                                        <span class="badge bg-dark">{{ $ap['total'] }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    
                    {{-- Unpositioned Access Points List --}}
                    @php
                        $unpositionedAPs = array_filter($allAPs, function($ap) { return !$ap['positioned']; });
                    @endphp
                    <div class="mt-3">
                        @if(count($unpositionedAPs) > 0)
                            <div class="alert alert-warning">
                                <strong>Unpositioned Access Points:</strong>
                                <div class="mt-2">
                                    @foreach($unpositionedAPs as $ap)
                                        <span class="badge bg-secondary me-2">{{ $ap['ap_name'] }} ({{ $ap['total'] }} sessions)</span>
                                    @endforeach
                                </div>
                                <small class="d-block mt-2">Enable setup mode and click on the map to position these access points.</small>
                            </div>
                        @endif
                        
                        @php
                            $positionedAPs = array_filter($allAPs, function($ap) { return $ap['positioned']; });
                        @endphp
                        @if(count($positionedAPs) > 0)
                            <div class="alert alert-info">
                                <strong>Positioned Access Points:</strong>
                                <div class="mt-2">
                                    @foreach($positionedAPs as $ap)
                                        <span class="badge bg-success me-2">{{ $ap['ap_name'] }} ({{ $ap['total'] }} sessions)</span>
                                    @endforeach
                                </div>
                                <small class="d-block mt-2">In setup mode, you can click on positioned access points to move them, or click anywhere on the map to position/reposition access points.</small>
                            </div>
                        @endif
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
            <div class="alert alert-warning" role="alert">
                @if(isset($noDataMessage))
                    <h4><i class="bi bi-database-slash"></i> No Data Available</h4>
                    <p>{{ $noDataMessage }}</p>
                    <p>To import WiFi data for this building and floor, use:</p>
                    <code>php artisan import:wifi-data file.csv {{ $building }} {{ $floor }}</code>
                @else
                    <h4><i class="bi bi-calendar-x"></i> No Data for Selected Date Range</h4>
                    <p>No data available for the selected date range: <strong>{{ $startDate }}</strong> to <strong>{{ $endDate }}</strong></p>
                    <p>Try selecting a different date range or check if data has been imported for this building and floor.</p>
                @endif
            </div>

        @endif




    </div>

    {{-- Access Point Selection Modal --}}
    <div class="modal fade" id="apSelectionModal" tabindex="-1" aria-labelledby="apSelectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="apSelectionModalLabel">Select Access Point</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Which access point did you just click on?</p>
                    <p class="text-muted small">Clicked position: <span id="clickedCoords"></span></p>
                    <form id="positionForm">
                        @csrf
                        <div class="mb-3">
                            <label for="apSelect" class="form-label">Access Point</label>
                            <select class="form-select" id="apSelect" name="ap_name" required>
                                <option value="">Choose an access point...</option>
                                @foreach($allAPs as $ap)
                                    <option value="{{ $ap['ap_name'] }}" data-sessions="{{ $ap['total'] }}" data-positioned="{{ $ap['positioned'] ? 'true' : 'false' }}">
                                        {{ $ap['ap_name'] }} ({{ $ap['total'] }} sessions){{ $ap['positioned'] ? ' - Currently Positioned' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <input type="hidden" id="xPercentage" name="x_percentage">
                        <input type="hidden" id="yPercentage" name="y_percentage">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="savePosition">Save Position</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let setupMode = false;
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing positioning system...');
        
        const setupModeToggle = document.getElementById('setupModeToggle');
        const setupInstructions = document.getElementById('setupInstructions');
        const floorMap = document.getElementById('floorMap');
        const mapImage = document.getElementById('mapImage');
        const modal = new bootstrap.Modal(document.getElementById('apSelectionModal'));
        const saveButton = document.getElementById('savePosition');
        
        console.log('Elements found:', {
            setupModeToggle: !!setupModeToggle,
            setupInstructions: !!setupInstructions,
            floorMap: !!floorMap,
            mapImage: !!mapImage,
            saveButton: !!saveButton
        });
        
        // Toggle setup mode
        setupModeToggle.addEventListener('click', function() {
            setupMode = !setupMode;
            console.log('Setup mode toggled to:', setupMode);
            
            if (setupMode) {
                setupModeToggle.classList.remove('btn-outline-primary');
                setupModeToggle.classList.add('btn-primary');
                setupModeToggle.innerHTML = '<i class="bi bi-gear-fill"></i> Setup Mode ON';
                setupInstructions.style.display = 'block';
                floorMap.style.cursor = 'crosshair';
                floorMap.classList.add('setup-mode');
            } else {
                setupModeToggle.classList.remove('btn-primary');
                setupModeToggle.classList.add('btn-outline-primary');
                setupModeToggle.innerHTML = '<i class="bi bi-gear"></i> Setup Mode';
                setupInstructions.style.display = 'none';
                floorMap.style.cursor = 'default';
                floorMap.classList.remove('setup-mode');
            }
        });
        
        // Map click handler
        floorMap.addEventListener('click', function(e) {
            console.log('Map clicked! Setup mode:', setupMode);
            if (!setupMode) {
                console.log('Setup mode is off, ignoring click');
                return;
            }
            
            // Check if we clicked on an existing access point
            let clickedAP = null;
            if (e.target.closest('.heatmap-circle.positioned')) {
                clickedAP = e.target.closest('.heatmap-circle.positioned').getAttribute('data-ap-name');
                console.log('Clicked on existing AP:', clickedAP);
            }
            
            // Prevent event bubbling
            e.preventDefault();
            e.stopPropagation();
            
            // Get click coordinates relative to the image
            const rect = mapImage.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            console.log('Click coordinates:', { x, y, rect });
            
            // Convert to percentages
            const xPercent = (x / rect.width) * 100;
            const yPercent = (y / rect.height) * 100;
            
            console.log('Percentages:', { xPercent, yPercent });
            
            // Store coordinates
            document.getElementById('xPercentage').value = xPercent.toFixed(2);
            document.getElementById('yPercentage').value = yPercent.toFixed(2);
            document.getElementById('clickedCoords').textContent = `${xPercent.toFixed(1)}%, ${yPercent.toFixed(1)}%`;
            
            // Pre-select the access point if we clicked on one
            if (clickedAP) {
                document.getElementById('apSelect').value = clickedAP;
            } else {
                document.getElementById('apSelect').value = '';
            }
            
            // Show modal
            console.log('Showing modal...');
            modal.show();
        });
        
        // Save position handler
        saveButton.addEventListener('click', function() {
            const apName = document.getElementById('apSelect').value;
            const xPercentage = document.getElementById('xPercentage').value;
            const yPercentage = document.getElementById('yPercentage').value;
            
            if (!apName) {
                alert('Please select an access point');
                return;
            }
            
            // Prepare form data
            const formData = new FormData();
            formData.append('_token', document.querySelector('input[name="_token"]').value);
            formData.append('ap_name', apName);
            formData.append('x_percentage', xPercentage);
            formData.append('y_percentage', yPercentage);
            
            // Send to server
            console.log('Sending data:', { apName, xPercentage, yPercentage });
            fetch('{{ route("dashboard.store.ap.position", [$buildingModel->id, $floorModel->id]) }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse JSON:', e);
                        throw new Error('Invalid JSON response: ' + text);
                    }
                });
            })
            .then(data => {
                console.log('Parsed data:', data);
                if (data.success) {
                    // Reload page to show new position
                    location.reload();
                } else {
                    alert('Error saving position: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Full error:', error);
                alert('Error saving position: ' + error.message);
            });
            
            modal.hide();
        });
    });
    </script>
</body>

</html>
