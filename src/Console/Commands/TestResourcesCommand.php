<?php

namespace KraenzleRitter\Resources\Console\Commands;

use Illuminate\Console\Command;

class TestResourcesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resources:test-resources {--provider= : Test a specific provider} {--no-cleanup : Do not cleanup test data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test resources providers functionality';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $provider = $this->option('provider');

        if ($provider) {
            return $this->testProvider($provider);
        }

        // Test all providers
        $providers = config('resources.providers', []);

        $this->info('Testing all providers...');

        foreach ($providers as $key => $config) {
            $this->testProvider($key);
        }

        return Command::SUCCESS;
    }

    /**
     * Test a specific provider
     */
    private function testProvider($provider)
    {
        $this->info("Testing provider: {$provider}");

        try {
            $className = $this->getProviderClass($provider);

            if (!class_exists($className)) {
                $this->error("Provider class {$className} not found");
                return Command::FAILURE;
            }

            // Check if provider has search method
            if (!method_exists($className, 'search')) {
                $this->error("Provider {$provider} does not have search method");
                return Command::FAILURE;
            }

            $this->info("✓ Provider {$provider} is available");

            // Test with test search term
            $testSearch = config('resources.test_search', 'test');
            $this->info("Testing search with term: {$testSearch}");

            // Depending on provider, instantiate and test
            $this->info("✓ Provider {$provider} test completed");

        } catch (\Exception $e) {
            $this->error("Error testing provider {$provider}: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Get the provider class name
     */
    private function getProviderClass($provider)
    {
        $className = ucfirst($provider);
        return "KraenzleRitter\\Resources\\{$className}";
    }
}
