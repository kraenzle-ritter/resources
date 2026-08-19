<?php

namespace KraenzleRitter\Resources\Console\Commands;

use Illuminate\Console\Command;

/**
 * Headless provider health check.
 *
 * Resolves the client class from the provider's `api-type`, not from the
 * provider key: georgfischer/gosteli/kba all share the Anton client, and
 * manual-input / wikipedia-de are not class names at all.
 */
class TestResourcesCommand extends Command
{
    protected $signature = 'resources:test-resources
        {--provider= : Test a single provider}
        {--timeout=15 : Per-provider request timeout in seconds}
        {--json : Emit a machine-readable report}';

    protected $description = 'Check that every configured resource provider still answers';

    public function handle(): int
    {
        $providers = config('resources.providers', []);

        if ($key = $this->option('provider')) {
            if (! isset($providers[$key])) {
                $this->reportUnknownProvider($key);

                return Command::FAILURE;
            }

            $providers = [$key => $providers[$key]];
        }

        $report = [];
        foreach ($providers as $providerKey => $config) {
            if (empty($config['api-type'])) {
                continue; // Label-only provider, nothing to call.
            }

            $report[$providerKey] = $this->testProvider($providerKey, $config);
        }

        $failed = array_filter($report, fn ($row) => $row['status'] === 'failed');

        $this->option('json')
            ? $this->renderJson($report, $failed)
            : $this->renderTable($report, $failed);

        return $failed === [] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return array{status: string, api_type: string, count: int, duration_ms: int, error: string|null}
     */
    private function testProvider(string $providerKey, array $config): array
    {
        $apiType = $config['api-type'];

        $row = [
            'status' => 'failed',
            'api_type' => $apiType,
            'count' => 0,
            'duration_ms' => 0,
            'error' => null,
        ];

        if ($apiType === 'ManualInput') {
            return array_merge($row, ['status' => 'skipped', 'error' => 'not an API provider']);
        }

        $class = 'KraenzleRitter\\Resources\\' . $apiType;

        if (! class_exists($class)) {
            return array_merge($row, ['error' => "class {$class} does not exist"]);
        }

        $term = $config['test_search'] ?? 'test';
        $limit = $config['limit'] ?? config('resources.limit', 5);
        $start = microtime(true);

        try {
            $results = $this->search($class, $apiType, $providerKey, $term, $limit);
        } catch (\Throwable $e) {
            return array_merge($row, [
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'error' => $e->getMessage(),
            ]);
        }

        $count = $this->countResults($results);

        return array_merge($row, [
            'status' => $count > 0 ? 'ok' : 'failed',
            'count' => $count,
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            'error' => $count > 0 ? null : "no results for \"{$term}\"",
        ]);
    }

    /**
     * Construction and call shape differ per api-type; see design D9.
     */
    private function search(string $class, string $apiType, string $providerKey, string $term, int $limit): mixed
    {
        return match ($apiType) {
            'Anton' => app()->makeWith($class, ['providerKey' => $providerKey])
                ->search($term, ['limit' => $limit], 'actors'),
            'Wikipedia' => app($class)->search($term, ['limit' => $limit, 'providerKey' => $providerKey]),
            default => app($class)->search($term, ['limit' => $limit]),
        };
    }

    private function countResults(mixed $results): int
    {
        if (is_array($results)) {
            return count($results);
        }

        // GND answers with an object carrying a member list.
        if (is_object($results) && isset($results->member)) {
            return is_array($results->member) ? count($results->member) : 1;
        }

        if (is_object($results)) {
            return count((array) $results);
        }

        return 0;
    }

    private function renderTable(array $report, array $failed): void
    {
        $this->table(
            ['Provider', 'API type', 'Status', 'Results', 'ms', 'Error'],
            array_map(fn ($key, $row) => [
                $key,
                $row['api_type'],
                match ($row['status']) {
                    'ok' => '<info>ok</info>',
                    'skipped' => '<comment>skipped</comment>',
                    default => '<error>failed</error>',
                },
                $row['count'],
                $row['duration_ms'],
                $row['error'] ?? '',
            ], array_keys($report), $report)
        );

        $failed === []
            ? $this->info(sprintf('%d provider(s) checked, all healthy.', count($report)))
            : $this->error(sprintf('%d of %d provider(s) failed: %s', count($failed), count($report), implode(', ', array_keys($failed))));
    }

    private function renderJson(array $report, array $failed): void
    {
        $this->output->writeln((string) json_encode([
            'providers' => $report,
            'failed' => array_keys($failed),
            'healthy' => $failed === [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function reportUnknownProvider(string $key): void
    {
        $this->option('json')
            ? $this->output->writeln((string) json_encode(['error' => "unknown provider: {$key}"]))
            : $this->error("Unknown provider: {$key}");
    }
}
