@extends('resources::layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Provider: {{ $provider }}</h1>
        <a href="{{ route('resources.check.index') }}" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Zurück zur Übersicht
        </a>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Provider Konfiguration</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                @foreach($config as $key => $value)
                                    <tr>
                                        <td width="30%"><strong>{{ $key }}</strong></td>
                                        <td>
                                            @if(is_array($value))
                                                <pre class="mb-0">{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                                            @else
                                                {{ $value }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Test Ergebnisse</h5>
                </div>
                <div class="card-body">
                    <p>
                        <strong>Status:</strong>
                        @if($result['status'] === 'success')
                            <span class="badge bg-success">Erfolg</span>
                        @elseif($result['status'] === 'warning')
                            <span class="badge bg-warning text-dark">Warnung</span>
                        @else
                            <span class="badge bg-danger">Fehler</span>
                        @endif
                    </p>
                    <p><strong>Nachricht:</strong> {{ $result['message'] }}</p>
                    <p><strong>Suchbegriff:</strong> {{ $searchTerm }}</p>

                    <form action="{{ route('resources.check.test-provider', ['provider' => $provider]) }}" method="post" class="mt-3">
                        @csrf
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" name="search" placeholder="Eigenen Suchbegriff testen..." value="{{ request('search') }}">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search me-2"></i> Testen
                            </button>
                        </div>

                        @if(!empty($availableEndpoints))
                            <div class="mb-3">
                                <label for="endpoint" class="form-label">Endpoint:</label>
                                <select name="endpoint" id="endpoint" class="form-select">
                                    @foreach($availableEndpoints as $availableEndpoint)
                                        <option value="{{ $availableEndpoint }}"
                                            {{ $endpoint === $availableEndpoint ? 'selected' : '' }}>
                                            {{ ucfirst($availableEndpoint) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($result['results']))
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Gefundene Ergebnisse</h5>
            </div>
            <div class="card-body">
                @php
                    $resultCount = 0;
                    if (isset($result['results'])) {
                        if (is_array($result['results'])) {
                            $resultCount = count($result['results']);
                        } elseif (is_object($result['results']) && isset($result['results']->member)) {
                            if (is_array($result['results']->member)) {
                                $resultCount = count($result['results']->member);
                            } elseif (is_object($result['results']->member)) {
                                $resultCount = 1;
                            }
                        } elseif (is_object($result['results'])) {
                            $resultCount = count((array) $result['results']);
                        }
                    }

                    $configLimit = $config['limit'] ?? config('components.limit') ?? 5;
                    $hasMore = $resultCount >= $configLimit && !$showAll;
                @endphp

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Gefundene Treffer: {{ $resultCount }}</h5>
                    @if($hasMore)
                        <a href="{{ route('resources.check.provider', ['provider' => $provider, 'search' => $searchTerm, 'show_all' => true]) }}"
                           class="btn btn-outline-primary">
                            <i class="fas fa-list me-1"></i> Alle Ergebnisse anzeigen
                        </a>
                    @endif
                </div>

                <div class="mb-3">
                    <strong>Provider API Request:</strong>
                    @php
                        // Create the provider API URL based on the configuration
                        $apiUrl = $config['base_url'] ?? '#';

                        // Handle Anton providers with endpoint
                        if (($config['api-type'] ?? '') === 'Anton') {
                            $currentEndpoint = $endpoint ?? 'actors';
                            $apiUrl = rtrim($apiUrl, '/') . '/' . $currentEndpoint;
                        } elseif (isset($config['endpoint'])) {
                            $apiUrl = rtrim($apiUrl, '/') . '/' . ltrim($config['endpoint'], '/');
                        }

                        // Add parameters
                        $params = [];
                        if ($searchTerm) {
                            // Use 'search' parameter for Anton providers, 'q' for others
                            $searchParam = ($config['api-type'] ?? '') === 'Anton' ? 'search' : ($config['search_param'] ?? 'q');
                            $params[$searchParam] = $searchTerm;
                        }

                        if (!empty($params)) {
                            $apiUrl .= '?' . http_build_query($params);
                        }
                    @endphp
                    <pre class="bg-light p-2 border rounded small mb-0">{{ $apiUrl }}</pre>
                </div>

                @php
                    // Determine the correct array for iteration - use the proven logic
                    $items = [];
                    if (isset($result['results'])) {
                        if (is_array($result['results'])) {
                            $items = $result['results'];
                        } elseif (is_object($result['results']) && isset($result['results']->member)) {
                            if (is_array($result['results']->member)) {
                                $items = $result['results']->member;
                            } elseif (is_object($result['results']->member)) {
                                $items = [$result['results']->member]; // Einzelnes Objekt in Array einpacken
                            }
                        } elseif (is_object($result['results'])) {
                            // Versuche das Objekt direkt als Array zu behandeln
                            $items = (array) $result['results'];
                        }
                    }

                @endphp

                @if(config('app.debug'))
                    <div class="alert alert-warning mb-3">
                        <strong>Debug Information:</strong><br>
                        <strong>Search Term Variable:</strong> {{ $searchTerm ?? 'NOT SET' }}<br>
                        <strong>Request Search:</strong> {{ request('search') ?? 'NOT SET' }}<br>
                        <strong>Result Status:</strong> {{ $result['status'] ?? 'N/A' }}<br>
                        <strong>Result Keys:</strong> {{ implode(', ', array_keys($result ?? [])) }}<br>
                        <strong>Results Type:</strong> {{ gettype($result['results'] ?? null) }}<br>
                        <strong>Results Is Array:</strong> {{ is_array($result['results'] ?? null) ? 'YES' : 'NO' }}<br>
                        <strong>Results Is Object:</strong> {{ is_object($result['results'] ?? null) ? 'YES' : 'NO' }}<br>
                        <strong>Results Count:</strong> {{ is_countable($result['results'] ?? null) ? count($result['results']) : 'not countable' }}<br>
                        @if(isset($result['results']) && is_object($result['results']))
                            <strong>Object Properties:</strong> {{ implode(', ', array_keys(get_object_vars($result['results']))) }}<br>
                        @endif
                        <strong>Items Count after processing:</strong> {{ count($items) }}<br>
                        @if(count($items) > 0)
                            <strong>First Item Type:</strong> {{ gettype($items[0]) }}<br>
                            @if(is_object($items[0]))
                                <strong>First Item Properties:</strong> {{ implode(', ', array_keys(get_object_vars($items[0]))) }}<br>
                            @elseif(is_array($items[0]))
                                <strong>First Item Keys:</strong> {{ implode(', ', array_keys($items[0])) }}<br>
                            @endif
                        @endif
                    </div>
                @endif

                @if(count($items) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Provider ID</th>
                                    <th>Name/Titel</th>
                                    <th>Beschreibung</th>
                                    <th>Link</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                    <tr>
                                        @php
                                            $provider_id = '-';
                                            $name = '-';
                                            $desc = '-';
                                            $url = '-';

                                            if (is_object($item)) {
                                                // Wikipedia-specific fields
                                                if (isset($item->pageid)) {
                                                    $provider_id = $item->pageid;
                                                    $name = $item->title ?? '-';
                                                    $desc = strip_tags($item->snippet ?? '-');
                                                }
                                                // Geonames-specific fields
                                                elseif (isset($item->geonameId)) {
                                                    $provider_id = $item->geonameId;
                                                    $name = $item->name ?? '-';
                                                    $desc = '';
                                                    // Build description from available Geonames fields
                                                    $descParts = [];
                                                    if (isset($item->adminName1)) $descParts[] = $item->adminName1;
                                                    if (isset($item->countryName)) $descParts[] = $item->countryName;
                                                    if (isset($item->fclName)) $descParts[] = $item->fclName;
                                                    $desc = implode(', ', $descParts) ?: '-';
                                                }
                                                // Anton-specific fields (Georgfischer, Gosteli, KBA)
                                                elseif (isset($item->fullname) || (isset($item->name) && isset($item->signature))) {
                                                    $provider_id = $item->id ?? '-';
                                                    $name = $item->fullname ?? $item->name ?? $item->title ?? '-';
                                                    $desc = '';
                                                    // Build description from available Anton fields
                                                    $descParts = [];
                                                    if (isset($item->signature)) $descParts[] = $item->signature;
                                                    if (isset($item->birth_year)) $descParts[] = 'geb. ' . $item->birth_year;
                                                    if (isset($item->death_year)) $descParts[] = 'gest. ' . $item->death_year;
                                                    if (isset($item->occupation)) $descParts[] = $item->occupation;
                                                    if (isset($item->description)) $descParts[] = $item->description;
                                                    $desc = implode(', ', $descParts) ?: '-';
                                                }
                                                // Other providers
                                                else {
                                                    $provider_id = $item->gndIdentifier ?? $item->id ?? $item->lemmaID ?? $item->provider_id ?? '-';
                                                    $name = $item->preferredName ?? $item->title ?? $item->lemmaText ?? $item->name ?? '-';
                                                    if (isset($item->processedDescription) && $item->processedDescription) {
                                                        $desc = $item->processedDescription;
                                                    } elseif (isset($item->biographicalOrHistoricalInformation) && is_array($item->biographicalOrHistoricalInformation) && count($item->biographicalOrHistoricalInformation)) {
                                                        $desc = $item->biographicalOrHistoricalInformation[0];
                                                    } elseif (isset($item->description)) {
                                                        $desc = $item->description;
                                                    }
                                                }
                                                $url = $item->url ?? $item->id ?? '-';
                                            } elseif (is_array($item)) {
                                                // Wikipedia-specific fields
                                                if (isset($item['pageid'])) {
                                                    $provider_id = $item['pageid'];
                                                    $name = $item['title'] ?? '-';
                                                    $desc = strip_tags($item['snippet'] ?? '-');
                                                }
                                                // Geonames-specific fields
                                                elseif (isset($item['geonameId'])) {
                                                    $provider_id = $item['geonameId'];
                                                    $name = $item['name'] ?? '-';
                                                    $desc = '';
                                                    // Build description from available Geonames fields
                                                    $descParts = [];
                                                    if (!empty($item['adminName1'])) $descParts[] = $item['adminName1'];
                                                    if (!empty($item['countryName'])) $descParts[] = $item['countryName'];
                                                    if (!empty($item['fclName'])) $descParts[] = $item['fclName'];
                                                    $desc = implode(', ', $descParts) ?: '-';
                                                }
                                                // Anton-specific fields (Georgfischer, Gosteli, KBA)
                                                elseif (isset($item['fullname']) || (isset($item['name']) && isset($item['signature']))) {
                                                    $provider_id = $item['id'] ?? '-';
                                                    $name = $item['fullname'] ?? $item['name'] ?? $item['title'] ?? '-';
                                                    $desc = '';
                                                    // Build description from available Anton fields
                                                    $descParts = [];
                                                    if (!empty($item['signature'])) $descParts[] = $item['signature'];
                                                    if (!empty($item['birth_year'])) $descParts[] = 'geb. ' . $item['birth_year'];
                                                    if (!empty($item['death_year'])) $descParts[] = 'gest. ' . $item['death_year'];
                                                    if (!empty($item['occupation'])) $descParts[] = $item['occupation'];
                                                    if (!empty($item['description'])) $descParts[] = $item['description'];
                                                    $desc = implode(', ', $descParts) ?: '-';
                                                }
                                                // Other providers
                                                else {
                                                    $provider_id = $item['gndIdentifier'] ?? $item['id'] ?? $item['lemmaID'] ?? $item['provider_id'] ?? '-';
                                                    $name = $item['preferredName'] ?? $item['title'] ?? $item['lemmaText'] ?? $item['name'] ?? '-';
                                                    if (!empty($item['processedDescription'])) {
                                                        $desc = $item['processedDescription'];
                                                    } elseif (!empty($item['biographicalOrHistoricalInformation'][0])) {
                                                        $desc = $item['biographicalOrHistoricalInformation'][0];
                                                    } elseif (!empty($item['description'])) {
                                                        $desc = $item['description'];
                                                    }
                                                }
                                                $url = $item['url'] ?? $item['id'] ?? '-';
                                            }

                                            // Generate target URL from configuration
                                            $targetUrlTemplate = $config['target_url'] ?? null;
                                            if ($targetUrlTemplate && $provider_id && $provider_id !== '-') {
                                                if (str_contains($targetUrlTemplate, '{underscored_name}') && $name !== '-') {
                                                    // For Wikipedia URLs that use underscored names
                                                    $url = str_replace('{underscored_name}', str_replace(' ', '_', $name), $targetUrlTemplate);
                                                } elseif (str_contains($targetUrlTemplate, '{endpoint}') || str_contains($targetUrlTemplate, '{short_provider_id}')) {
                                                    // For Anton URLs that use endpoint and short_provider_id
                                                    $endpoint = request()->get('endpoint', 'actors'); // Default to 'actors'
                                                    $shortProviderId = $provider_id; // Use the ID as short provider ID
                                                    $url = str_replace(['{endpoint}', '{short_provider_id}'], [$endpoint, $shortProviderId], $targetUrlTemplate);
                                                } else {
                                                    // For URLs that use provider_id
                                                    $url = str_replace('{provider_id}', $provider_id, $targetUrlTemplate);
                                                }
                                            }
                                        @endphp
                                        <td>{{ $provider_id }}</td>
                                        <td>{{ $name }}</td>
                                        <td>{{ \Illuminate\Support\Str::limit($desc, 100) }}</td>
                                        <td>
                                            @if($url && $url !== '-')
                                                <a href="{{ $url }}" target="_blank">{{ $url }}</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info mt-3">
                        @if(config('app.debug'))
                            Keine Treffer gefunden. Debug: {{ json_encode($result['results'] ?? 'No results key', JSON_PRETTY_PRINT) }}
                        @else
                            Keine Treffer gefunden
                        @endif
                    </div>
                @endif

                @if($hasMore)
                    <div class="mt-3 text-center">
                        <a href="{{ route('resources.check.provider', ['provider' => $provider, 'search' => $searchTerm, 'show_all' => true]) }}"
                            class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i> Alle Ergebnisse anzeigen
                        </a>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
