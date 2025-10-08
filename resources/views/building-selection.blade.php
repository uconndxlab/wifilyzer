<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Building - WiFi Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300&display=swap" rel="stylesheet">
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4 shadow-sm">
        <div class="container-fluid">
            <span class="navbar-brand h4 mb-0">WiFi Analytics Dashboard</span>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <strong>Error:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="text-center mb-5">
                    <h1 class="display-4 mb-3">Select Building</h1>
                    <p class="lead">Choose a building to view WiFi analytics</p>
                    <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#addBuildingModal">
                        <i class="bi bi-plus-circle"></i> Add New Building
                    </button>
                </div>

                @if($buildings->count() > 0)
                    <div class="row">
                        @foreach($buildings as $building)
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 shadow-lg building-card">
                                    <div class="card-body d-flex flex-column">

                                        <h5 class="card-title text-center">{{ $building->name }}</h5>
                                        <p class="card-text text-center text-muted mb-3">
                                            Code: <strong>{{ $building->code }}</strong>
                                        </p>
                                        @if($building->description)
                                            <p class="card-text text-center mb-3">{{ $building->description }}</p>
                                        @endif
                                        <div class="text-center mb-3">
                                            <span class="badge bg-info me-2">
                                                {{ $building->floors->count() }} 
                                                {{ $building->floors->count() == 1 ? 'Floor' : 'Floors' }}
                                            </span>
                                            @if($building->total_sessions > 0)
                                                <span class="badge bg-success">
                                                    {{ number_format($building->total_sessions) }} Sessions
                                                </span>
                                            @else
                                                <span class="badge bg-warning text-dark">No Data</span>
                                            @endif
                                        </div>
                                        
                                        {{-- Most Recent Data Info --}}
                                        <div class="text-center mb-3">
                                            @if($building->most_recent_data)
                                                @php
                                                    $mostRecentDate = \Carbon\Carbon::parse($building->most_recent_data);
                                                    // force integer days (no decimal point)
                                                    $daysAgo = (int) $mostRecentDate->diffInDays(now());
                                                @endphp
                                                <small class="text-muted">
                                                    <i class="bi bi-clock"></i> 
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
                                            @else
                                                <small class="text-muted">
                                                    <i class="bi bi-database-slash"></i> No data available
                                                </small>
                                            @endif
                                        </div>
                                        <div class="mt-auto text-center">
                                            @if($building->floors->count() == 1)
                                                {{-- If only one floor, go directly to dashboard --}}
                                                <a href="{{ route('dashboard.building.floor', [
                                                    'building' => $building->code, 
                                                    'floor' => $building->floors->first()->number
                                                ]) }}" 
                                                   class="btn btn-primary btn-lg">
                                                    <i class="bi bi-bar-chart"></i> View Dashboard
                                                </a>
                                            @else
                                                {{-- Multiple floors, go to floor selection --}}
                                                <a href="{{ route('floors.select', ['building' => $building->code]) }}" 
                                                   class="btn btn-primary btn-lg">
                                                    <i class="bi bi-arrow-right"></i> Select Floor
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center">
                        <div class="alert alert-info">
                            <h4><i class="bi bi-info-circle"></i> No Buildings Found</h4>
                            <p>No buildings have been added yet. Use the command line to add buildings and floors:</p>
                            <code>php artisan wifi:add-location</code>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Add Building Modal -->
    <div class="modal fade" id="addBuildingModal" tabindex="-1" aria-labelledby="addBuildingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addBuildingModalLabel">
                        <i class="bi bi-building-add"></i> Add New Building
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('buildings.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="buildingName" class="form-label">Building Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="buildingName" name="name" required 
                                           placeholder="e.g., Science Building">
                                    <div class="form-text">Full name of the building</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="buildingCode" class="form-label">Building Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="buildingCode" name="code" required 
                                           placeholder="e.g., SCI" style="text-transform: uppercase;">
                                    <div class="form-text">Short code (3-4 characters)</div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="buildingDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="buildingDescription" name="description" rows="2" 
                                      placeholder="Optional description of the building"></textarea>
                        </div>
                        
                        <hr class="my-4">
                        <h6 class="text-muted mb-3"><i class="bi bi-layers"></i> Add First Floor (Optional)</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="floorNumber" class="form-label">Floor Number</label>
                                    <input type="number" class="form-control" id="floorNumber" name="floor_number" 
                                           placeholder="e.g., 1">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="floorName" class="form-label">Floor Name</label>
                                    <input type="text" class="form-control" id="floorName" name="floor_name" 
                                           placeholder="e.g., First Floor">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="mapImage" class="form-label">Map Image</label>
                                    <input type="text" class="form-control" id="mapImage" name="map_image_path" 
                                           placeholder="e.g., floor1-map.png">
                                    <div class="form-text">Place image in public/ folder</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="cssClass" class="form-label">CSS Class</label>
                                    <input type="text" class="form-control" id="cssClass" name="map_css_class" 
                                           placeholder="e.g., sci-floor-1">
                                    <div class="form-text">For custom AP positioning</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Create Building
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .building-card {
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
            cursor: pointer;
        }
        
        .building-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2) !important;
        }
        
        .building-card .card-body {
            padding: 2rem;
        }
        
        .building-card .bi-building {
            opacity: 0.8;
        }

        #buildingCode {
            text-transform: uppercase;
        }

        /* Improve modal label contrast */
        .modal-body .form-label {
            color: #212529 !important;
            font-weight: 600;
        }

        .modal-body .form-text {
            color: #6c757d !important;
        }

        .modal-body h6 {
            color: #495057 !important;
            font-weight: 600;
        }
    </style>

    <!-- Bootstrap JS for modal functionality -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto-generate building code from name
        document.getElementById('buildingName').addEventListener('input', function() {
            const name = this.value;
            const codeField = document.getElementById('buildingCode');
            
            if (name && !codeField.value) {
                // Generate code from first letters of words
                const words = name.split(' ').filter(word => word.length > 0);
                let code = '';
                
                if (words.length === 1) {
                    code = words[0].substring(0, 4).toUpperCase();
                } else {
                    code = words.map(word => word.charAt(0).toUpperCase()).join('').substring(0, 4);
                }
                
                codeField.value = code;
            }
        });

        // Auto-generate floor name from number
        document.getElementById('floorNumber').addEventListener('input', function() {
            const number = this.value;
            const nameField = document.getElementById('floorName');
            
            if (number && !nameField.value) {
                const ordinals = ['', '1st Floor', '2nd Floor', '3rd Floor'];
                if (number <= 3 && number > 0) {
                    nameField.value = ordinals[number];
                } else if (number == 0) {
                    nameField.value = 'Ground Floor';
                } else if (number < 0) {
                    nameField.value = 'Basement';
                } else {
                    nameField.value = number + 'th Floor';
                }
            }
        });
    </script>
</body>

</html>