<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Floor - {{ $building->name }} - WiFi Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300&display=swap" rel="stylesheet">
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4 shadow-sm">
        <div class="container-fluid">
            <a href="{{ route('home') }}" class="btn btn-outline-primary me-3">
                <i class="bi bi-arrow-left"></i> Back to Buildings
            </a>
            <span class="navbar-brand h4 mb-0">WiFi Analytics Dashboard</span>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <strong>Upload Error:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="text-center mb-5">
                    <h1 class="display-4 mb-3">{{ $building->name }}</h1>
                    <p class="lead">Select a floor to view WiFi analytics</p>
                    <span class="badge bg-secondary fs-6">Building Code: {{ $building->code }}</span>
                    
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary btn-lg me-3" data-bs-toggle="modal" data-bs-target="#addFloorModal">
                            <i class="bi bi-plus-circle"></i> Add Floor
                        </button>
                        <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#uploadDataModal">
                            <i class="bi bi-cloud-upload"></i> Upload WiFi Data
                        </button>
                    </div>
                </div>

                @if($building->floors->count() > 0)
                    <div class="row">
                        @foreach($building->floors->sortBy('number') as $floor)
                            <div class="col-md-4 col-lg-3 mb-4">
                                <div class="card h-100 shadow-lg floor-card">
                                    <div class="card-body d-flex flex-column text-center">
                                        <div class="mb-3">
                                            <i class="bi bi-layers display-1 text-success"></i>
                                        </div>
                                        <h5 class="card-title">{{ $floor->name }}</h5>
                                        <div class="mb-3">
                                            <span class="badge bg-primary fs-6">Floor {{ $floor->number }}</span>
                                        </div>
                                        @if($floor->description)
                                            <p class="card-text text-muted mb-3">{{ $floor->description }}</p>
                                        @endif
                                        
                                        {{-- Show data availability --}}
                                        <div class="mb-3">
                                            @if($floor->total_sessions > 0)
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle"></i> {{ number_format($floor->total_sessions) }} Sessions
                                                </span>
                                            @else
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-exclamation-triangle"></i> No Data
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Most Recent Data Info --}}
                                        @if($floor->most_recent_data)
                                            @php
                                                $mostRecentDate = \Carbon\Carbon::parse($floor->most_recent_data);
                                                $daysAgo = (int) round($mostRecentDate->floatDiffInDays(now()), 0);
                                            @endphp
                                            
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    
                                                    Most recent: 
                                                    @if($daysAgo == 0)
                                                        <span class="text-success fw-bold">Today</span>
                                                    @elseif($daysAgo == 1)
                                                        <span class="text-danger fw-bold">Yesterday</span>
                                                    @elseif($daysAgo <= 7)
                                                        <span class="text-danger fw-bold">{{ $daysAgo }} days ago</span>
                                                    @else
                                                        <span class="text-danger fw-bold">{{ $mostRecentDate->format('M j, Y') }}</span>
                                                    @endif
                                                    <br>
                                                    <span class="text-muted">{{ $mostRecentDate->format('g:i A') }}</span>
                                                </small>
                                            </div>
                                        @endif

                                        {{-- Import Statistics --}}
                                        @if($floor->latest_import_stats)
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    <i class="bi bi-funnel"></i> Import Rate: 
                                                    <span class="fw-bold">{{ $floor->latest_import_stats->import_rate }}%</span>
                                                    <br>
                                                    <span class="text-muted">
                                                        {{ number_format($floor->latest_import_stats->imported_records) }} of {{ number_format($floor->latest_import_stats->total_records_processed) }} records
                                                    </span>
                                                </small>
                                            </div>
                                        @endif

                                        <div class="mt-auto">
                                            @if($floor->total_sessions > 0)
                                                <a href="{{ route('dashboard.building.floor', [
                                                    'building' => $building->code, 
                                                    'floor' => $floor->number
                                                ]) }}" 
                                                   class="btn btn-success btn-lg w-100">
                                                    <i class="bi bi-bar-chart"></i> View Dashboard
                                                </a>
                                            @else
                                                <button class="btn btn-outline-secondary btn-lg w-100" disabled>
                                                    <i class="bi bi-database-slash"></i> No Data Available
                                                </button>
                                                <small class="text-muted mt-2 d-block">
                                                    Import data with:<br>
                                                    <code>php artisan import:wifi-data file.csv {{ $building->code }} {{ $floor->number }}</code>
                                                </small>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center">
                        <div class="alert alert-warning">
                            <h4><i class="bi bi-exclamation-triangle"></i> No Floors Found</h4>
                            <p>No floors have been added to <strong>{{ $building->name }}</strong> yet.</p>
                            <p>Use the command line to add floors:</p>
                            <code>php artisan wifi:add-location --building-code="{{ $building->code }}" --floor-number=1</code>
                        </div>
                    </div>
                @endif

                {{-- Import Statistics Summary --}}
                @if($building->floors->where('latest_import_stats')->isNotEmpty())
                <div class="row mt-5">
                    <div class="col-md-12">
                        <div class="card shadow-lg">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title"><i class="bi bi-funnel"></i> Data Import Statistics</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">WiFi data is filtered during import to ensure quality. Records are excluded if they have:</p>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <span class="badge bg-warning text-dark me-2">Duration < 1 minute</span>
                                        <small class="text-muted">Brief connections or authentication attempts</small>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="badge bg-danger me-2">Signal < -70 dBm</span>
                                        <small class="text-muted">Weak or unreliable connections</small>
                                    </div>
                                </div>
                                
                                @foreach($building->floors->where('latest_import_stats') as $floor)
                                <div class="border rounded p-3 mb-3">
                                    <h6>{{ $floor->name }} - Latest Import</h6>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>{{ number_format($floor->latest_import_stats->total_records_processed) }}</strong><br>
                                            <small class="text-muted">Total Records</small>
                                        </div>
                                        <div class="col-md-3">
                                            <strong class="text-success">{{ number_format($floor->latest_import_stats->imported_records) }}</strong><br>
                                            <small class="text-muted">Imported ({{ $floor->latest_import_stats->import_rate }}%)</small>
                                        </div>
                                        <div class="col-md-3">
                                            <strong class="text-warning">{{ number_format($floor->latest_import_stats->short_duration_filtered) }}</strong><br>
                                            <small class="text-muted">Short Duration</small>
                                        </div>
                                        <div class="col-md-3">
                                            <strong class="text-danger">{{ number_format($floor->latest_import_stats->weak_signal_filtered) }}</strong><br>
                                            <small class="text-muted">Weak Signal</small>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i> {{ $floor->latest_import_stats->import_date->format('M j, Y g:i A') }} 
                                        | <i class="bi bi-file-earmark-text"></i> {{ $floor->latest_import_stats->file_name }}
                                    </small>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Quick actions --}}
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title"><i class="bi bi-tools"></i> Quick Actions</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Add New Floor:</h6>
                                        <code>php artisan wifi:add-location --building-code="{{ $building->code }}"</code>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Import Data:</h6>
                                        <code>php artisan import:wifi-data file.csv {{ $building->code }} FLOOR_NUM</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .floor-card {
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
            cursor: pointer;
        }
        
        .floor-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2) !important;
        }
        
        .floor-card .card-body {
            padding: 1.5rem;
        }
        
        .floor-card .bi-layers {
            opacity: 0.8;
        }


    </style>

    <!-- Add Floor Modal -->
    <div class="modal fade" id="addFloorModal" tabindex="-1" aria-labelledby="addFloorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addFloorModalLabel">
                        <i class="bi bi-plus-circle"></i> Add New Floor to {{ $building->name }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('dashboard.building.floor.store', $building->code) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="floorNumber" class="form-label fw-bold text-dark">Floor Number <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="floorNumber" name="number" required min="1" max="50">
                            <div class="form-text text-muted">Enter the floor number (e.g., 1, 2, 3, -1 for basement)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="floorName" class="form-label fw-bold text-dark">Floor Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="floorName" name="name" required maxlength="100" placeholder="e.g., Ground Floor, First Floor, Basement">
                            <div class="form-text text-muted">Descriptive name for this floor</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="floorDescription" class="form-label fw-bold text-dark">Description</label>
                            <textarea class="form-control" id="floorDescription" name="description" rows="3" maxlength="500" placeholder="Optional description of this floor (e.g., Main entrance, Library level, etc.)"></textarea>
                            <div class="form-text text-muted">Optional additional details about this floor</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mapImagePath" class="form-label fw-bold text-dark">Floor Map Image Path</label>
                            <input type="text" class="form-control" id="mapImagePath" name="map_image_path" placeholder="e.g., Hall4.png">
                            <div class="form-text text-muted">Optional: Path to floor map image in public directory</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mapCssClass" class="form-label fw-bold text-dark">Map CSS Class</label>
                            <input type="text" class="form-control" id="mapCssClass" name="map_css_class" placeholder="e.g., hall-4th-floor">
                            <div class="form-text text-muted">Optional: CSS class for positioning access points on the map</div>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle"></i> After Creating:</h6>
                            <ul class="mb-0">
                                <li>The new floor will appear in the floor selection list</li>
                                <li>You can then upload WiFi data for this floor</li>
                                <li>Access point positions can be configured later in the CSS</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Create Floor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Upload WiFi Data Modal -->
    <div class="modal fade" id="uploadDataModal" tabindex="-1" aria-labelledby="uploadDataModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="uploadDataModalLabel">
                        <i class="bi bi-cloud-upload"></i> Upload WiFi Data for {{ $building->name }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('wifi.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                    @csrf
                    <input type="hidden" name="building_code" value="{{ $building->code }}">
                    
                    <div class="modal-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="floorSelect" class="form-label fw-bold text-dark">Target Floor <span class="text-danger">*</span></label>
                                <select class="form-select" id="floorSelect" name="floor_number" required>
                                    <option value="">Select a floor...</option>
                                    @foreach($building->floors->sortBy('number') as $floor)
                                        <option value="{{ $floor->number }}">{{ $floor->name }} (Floor {{ $floor->number }})</option>
                                    @endforeach
                                </select>
                                <div class="form-text text-muted">Choose which floor this data belongs to</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Building</label>
                                <input type="text" class="form-control" value="{{ $building->name }} ({{ $building->code }})" disabled>
                                <div class="form-text text-muted">Data will be imported for this building</div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="csvFile" class="form-label fw-bold text-dark">CSV Data File <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="csvFile" name="csv_file" accept=".csv" required>
                            <div class="form-text text-muted mt-2">
                                <strong>Accepted format:</strong> CSV files with WiFi session data including columns for Client Username Hash, Session Duration, RSSI, etc.
                            </div>
                        </div>

                        <div id="filePreview" class="mb-3" style="display: none;">
                            <div class="alert alert-info">
                                <h6><i class="bi bi-file-earmark-text"></i> Selected File:</h6>
                                <div id="fileName"></div>
                                <div id="fileSize" class="text-muted"></div>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <h6><i class="bi bi-info-circle"></i> Data Processing Info:</h6>
                            <ul class="mb-0">
                                <li>Sessions shorter than 1 minute will be filtered out</li>
                                <li>Connections with signal strength weaker than -70 dBm will be excluded</li>
                                <li>Duplicate entries will be handled automatically</li>
                                <li>Large files may take several minutes to process</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="uploadBtn" disabled>
                            <i class="bi bi-upload"></i> Upload & Process Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS for modal functionality -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('csvFile');
            const filePreview = document.getElementById('filePreview');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const uploadBtn = document.getElementById('uploadBtn');
            const floorSelect = document.getElementById('floorSelect');

            // Enable upload button when both file and floor are selected
            function updateUploadButton() {
                uploadBtn.disabled = !(fileInput.files.length > 0 && floorSelect.value);
            }

            // Update button when floor or file changes
            floorSelect.addEventListener('change', updateUploadButton);
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    console.log('File selected:', file.name, 'Size:', file.size);
                    
                    // Show file preview
                    fileName.textContent = file.name;
                    fileSize.textContent = `Size: ${(file.size / 1024 / 1024).toFixed(2)} MB`;
                    filePreview.style.display = 'block';
                }
                updateUploadButton();
            });

            // Form submission
            document.getElementById('uploadForm').addEventListener('submit', function(e) {
                console.log('Form submission started');
                console.log('File input files:', fileInput.files.length);
                console.log('Floor select value:', floorSelect.value);
                
                if (!fileInput.files || fileInput.files.length === 0) {
                    e.preventDefault();
                    alert('Please select a CSV file.');
                    return;
                }
                
                if (!floorSelect.value) {
                    e.preventDefault();
                    alert('Please select a target floor.');
                    return;
                }
                
                // Debug: Log form data
                const formData = new FormData(this);
                console.log('FormData contents:');
                for (let pair of formData.entries()) {
                    console.log(pair[0] + ':', pair[1]);
                }
                
                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
            });
        });
    </script>
</body>

</html>