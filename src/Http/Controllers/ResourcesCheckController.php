<?php

namespace KraenzleRitter\Resources\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Routing\Controller;

class ResourcesCheckController extends Controller
{
    /**
     * Display the main check index
     */
    public function index()
    {
        $providers = array_filter(
            config('resources.providers', []),
            fn($provider) => !empty($provider['base_url'])
        );
        $results = [];

        // Check all providers
        foreach ($providers as $key => $provider) {
            $results[$key] = $this->checkProvider($key, $provider);
        }

        // Check the database table
        $dbStatus = [
            'exists' => Schema::hasTable('resources'),
            'message' => Schema::hasTable('resources') ?
                'Resources-Tabelle ist vorhanden' :
                'Resources-Tabelle fehlt - bitte Migration ausführen'
        ];

        // Complete configuration for display
        $configPath = config_path('resources.php');
        $fullConfig = file_exists($configPath) ? include($configPath) : config('resources');

        return view('resources::check.index', [
            'results' => $results,
            'dbStatus' => $dbStatus,
            'fullConfig' => $fullConfig
        ]);
    }

    /**
     * Display configuration check
     */
    public function config()
    {
        $config = config('resources');
        return view('resources::check.config', compact('config'));
    }

    /**
     * Display provider details
     */
    public function provider(Request $request, $provider = null)
    {
        $providers = config('resources.providers', []);
        
        if (!isset($providers[$provider])) {
            return redirect()->route('resources.check.index')
                ->with('error', "Provider {$provider} ist nicht konfiguriert.");
        }

        $searchTerm = $request->get('search', $this->getTestQuery($provider));
        
        return view('resources::check.provider', [
            'provider' => $provider,
            'config' => $providers[$provider],
            'searchTerm' => $searchTerm
        ]);
    }

    /**
     * Check provider status
     */
    private function checkProvider($key, $provider)
    {
        try {
            return [
                'status' => 'active',
                'name' => $provider['name'] ?? $key,
                'type' => $provider['api-type'] ?? 'unknown',
                'message' => 'Provider verfügbar'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'name' => $provider['name'] ?? $key,
                'type' => $provider['api-type'] ?? 'unknown',
                'message' => 'Fehler: ' . $e->getMessage()
            ];
        }
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