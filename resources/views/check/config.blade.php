@extends('resources::layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Resources Components Konfiguration</h1>
        <a href="{{ route('resources.check.index') }}" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Zurück zur Übersicht
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Aktive Konfiguration</h5>
        </div>
        <div class="card-body">
            <p>
                <strong>Konfigurationsquelle:</strong>
                @if(file_exists(config_path('components.php')))
                    <span class="badge bg-success">Veröffentlicht</span>
                    <small class="ms-2 text-muted">{{ config_path('components.php') }}</small>
                @else
                    <span class="badge bg-info">Paket-Standard</span>
                    <small class="ms-2 text-muted">vendor/kraenzle-ritter/components/config/components.php</small>
                @endif
            </p>

            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Um die Konfiguration anzupassen, können Sie die Konfigurationsdatei veröffentlichen:
                <pre><code>php artisan vendor:publish --tag=components-config</code></pre>
            </div>

            <div class="table-responsive mb-4">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Einstellung</th>
                            <th>Wert</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($config as $key => $value)
                            @if($key !== 'providers')
                                <tr>
                                    <td>{{ $key }}</td>
                                    <td>
                                        @if(is_array($value))
                                            <pre class="mb-0">{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h5>Provider Konfiguration</h5>
            <div class="accordion" id="providerAccordion">
                @foreach($config['providers'] ?? [] as $providerKey => $provider)
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading{{ $providerKey }}">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                   data-bs-target="#collapse{{ $providerKey }}" aria-expanded="false"
                                   aria-controls="collapse{{ $providerKey }}">
                                {{ $providerKey }}
                                <span class="ms-2 badge bg-secondary">{{ $provider['api-type'] ?? 'Unbekannt' }}</span>
                            </button>
                        </h2>
                        <div id="collapse{{ $providerKey }}" class="accordion-collapse collapse"
                             aria-labelledby="heading{{ $providerKey }}" data-bs-parent="#providerAccordion">
                            <div class="accordion-body">
                                <table class="table table-sm table-bordered">
                                    <tbody>
                                        @foreach($provider as $settingKey => $settingValue)
                                            <tr>
                                                <td width="30%"><strong>{{ $settingKey }}</strong></td>
                                                <td>
                                                    @if(is_array($settingValue))
                                                        <pre class="mb-0">{{ json_encode($settingValue, JSON_PRETTY_PRINT) }}</pre>
                                                    @elseif($settingKey == 'api_token' && !empty($settingValue))
                                                        <span class="text-muted">***TOKEN***</span>
                                                    @else
                                                        {{ $settingValue }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                <div class="mt-2">
                                    <a href="{{ route('resources.check.provider', ['provider' => $providerKey]) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-search me-1"></i> Provider testen
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
