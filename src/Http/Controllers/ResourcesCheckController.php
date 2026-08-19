<?php

namespace KraenzleRitter\Resources\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use KraenzleRitter\Resources\Helpers\ConfigRedactor;

class ResourcesCheckController extends Controller
{
    /**
     * Endpoints an Anton instance exposes; the provider page lets you pick one.
     */
    private const ANTON_ENDPOINTS = ['actors', 'objects', 'places', 'keywords'];

    /**
     * Display the main check index
     */
    public function index()
    {
        $providers = array_filter(
            config('resources.providers', []),
            fn ($provider) => ! empty($provider['base_url'])
        );

        $results = [];
        foreach ($providers as $key => $provider) {
            $results[$key] = $this->checkProvider($key, $provider);
        }

        $table = config('resources.table', 'resources');
        $tableExists = Schema::hasTable($table);

        return view('resources::check.index', [
            'results' => $results,
            'dbStatus' => [
                'exists' => $tableExists,
                'message' => $tableExists
                    ? 'Resources-Tabelle ist vorhanden'
                    : 'Resources-Tabelle fehlt - bitte Migration ausführen',
            ],
            'fullConfig' => ConfigRedactor::redact(config('resources', [])),
        ]);
    }

    /**
     * Display configuration check
     */
    public function config()
    {
        return view('resources::check.config', [
            'config' => ConfigRedactor::redact(config('resources', [])),
        ]);
    }

    /**
     * Display provider details, including a live search against the provider.
     */
    public function provider(Request $request, $provider = null)
    {
        $providers = config('resources.providers', []);

        if (! isset($providers[$provider])) {
            return redirect()->route('resources.check.index')
                ->with('error', "Provider {$provider} ist nicht konfiguriert.");
        }

        $config = $providers[$provider];
        $searchTerm = $request->get('search') ?: $this->getTestQuery($provider);
        $endpoint = $request->get('endpoint', 'actors');
        $showAll = (bool) $request->get('show_all', false);

        return view('resources::check.provider', [
            'provider' => $provider,
            // Redacted here rather than in the view, so a future view cannot
            // reintroduce the api_token leak.
            'config' => ConfigRedactor::redact($config),
            'searchTerm' => $searchTerm,
            'endpoint' => $endpoint,
            'availableEndpoints' => ($config['api-type'] ?? null) === 'Anton' ? self::ANTON_ENDPOINTS : [],
            'showAll' => $showAll,
            'result' => $this->runProviderSearch($provider, $config, $searchTerm, $endpoint, $showAll),
        ]);
    }

    /**
     * Run the provider's own client against a search term.
     *
     * Never throws: this page exists to report that a provider is broken, so an
     * upstream failure has to become a status, not a 500.
     *
     * @return array{status: string, message: string, results: mixed}
     */
    private function runProviderSearch(string $provider, array $config, string $searchTerm, string $endpoint, bool $showAll): array
    {
        $apiType = $config['api-type'] ?? null;

        if ($apiType === null) {
            return ['status' => 'warning', 'message' => 'Kein api-type konfiguriert.', 'results' => null];
        }

        if ($apiType === 'ManualInput') {
            return ['status' => 'warning', 'message' => 'Manuelle Eingabe ist kein API-Provider.', 'results' => null];
        }

        $class = 'KraenzleRitter\\Resources\\' . $apiType;

        if (! class_exists($class)) {
            return ['status' => 'error', 'message' => "Provider-Klasse {$class} nicht gefunden.", 'results' => null];
        }

        $limit = $showAll ? 50 : ($config['limit'] ?? config('resources.limit', 5));

        try {
            $results = match ($apiType) {
                'Anton' => app()->makeWith($class, ['providerKey' => $provider])
                    ->search($searchTerm, ['limit' => $limit], $endpoint),
                'Wikipedia' => app($class)->search($searchTerm, ['limit' => $limit, 'providerKey' => $provider]),
                default => app($class)->search($searchTerm, ['limit' => $limit]),
            };
        } catch (\Throwable $e) {
            Log::warning('Diagnostics provider search failed', [
                'provider' => $provider,
                'exception' => $e->getMessage(),
            ]);

            return ['status' => 'error', 'message' => 'Fehler: ' . $e->getMessage(), 'results' => null];
        }

        $count = $this->countResults($results);

        return $count > 0
            ? ['status' => 'success', 'message' => "{$count} Treffer für „{$searchTerm}“.", 'results' => $results]
            : ['status' => 'warning', 'message' => "Keine Treffer für „{$searchTerm}“.", 'results' => $results];
    }

    private function countResults(mixed $results): int
    {
        if (is_array($results)) {
            return count($results);
        }

        // GND answers with an object carrying a `member` list.
        if (is_object($results) && isset($results->member)) {
            return is_array($results->member) ? count($results->member) : 1;
        }

        if (is_object($results)) {
            return count((array) $results);
        }

        return 0;
    }

    /**
     * Check provider status
     */
    private function checkProvider($key, $provider)
    {
        return [
            'status' => 'active',
            'name' => $provider['name'] ?? $key,
            'type' => $provider['api-type'] ?? 'unknown',
            'message' => 'Provider verfügbar',
        ];
    }

    /**
     * Get test query for a provider
     */
    protected function getTestQuery($provider)
    {
        $providers = config('resources.providers', []);

        return $providers[$provider]['test_search'] ?? 'test';
    }
}
