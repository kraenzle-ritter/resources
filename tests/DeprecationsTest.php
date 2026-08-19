<?php

namespace KraenzleRitter\Resources\Tests;

use KraenzleRitter\Resources\Anton;
use KraenzleRitter\Resources\Geonames;
use KraenzleRitter\Resources\Gnd;
use KraenzleRitter\Resources\Helpers\LabelHelper;
use KraenzleRitter\Resources\Helpers\UserAgent;
use KraenzleRitter\Resources\Idiotikon;
use KraenzleRitter\Resources\Metagrid;
use KraenzleRitter\Resources\Ortsnamen;
use KraenzleRitter\Resources\Wikidata;
use KraenzleRitter\Resources\Wikipedia;

/**
 * Guards against PHP deprecations raised by this package.
 *
 * phpunit.xml sets failOnDeprecation, but that alone does not cover us: Laravel
 * installs its own error handler (HandleExceptions) that captures deprecations
 * and routes them to the log, so PHPUnit never sees them. A handler installed
 * inside the test body takes precedence for the duration of the call, which is
 * what makes these assertions work.
 *
 * Compile-time deprecations — implicit nullable parameters such as
 * `string $x = null` — are caught too, as long as the class is first loaded
 * inside the callback: PHP raises them when compiling the file. They only exist
 * from PHP 8.4 on, so that half of the coverage comes from the CI matrix.
 *
 * Only deprecations originating in src/ are recorded; dependencies raise their
 * own (Mockery does, on PHP 8.4) and those are not this package's to fix.
 */
class DeprecationsTest extends TestCase
{
    private static function srcPath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
    }

    /**
     * Run a callable with a deprecation-recording error handler installed.
     *
     * @return array<int, string> Deprecation messages raised during the call.
     */
    private function deprecationsDuring(callable $callback): array
    {
        $seen = [];

        set_error_handler(
            function (int $errno, string $message, string $file = '', int $line = 0) use (&$seen): bool {
                if (str_starts_with($file, self::srcPath())) {
                    $seen[] = "{$message} ({$file}:{$line})";
                }

                return true;
            },
            E_DEPRECATED | E_USER_DEPRECATED
        );

        try {
            $callback();
        } finally {
            restore_error_handler();
        }

        return $seen;
    }

    public function test_constructing_every_provider_client_raises_no_deprecation()
    {
        $seen = $this->deprecationsDuring(function () {
            new Gnd();
            new Geonames();
            new Idiotikon();
            new Ortsnamen();
            new Metagrid();
            new Wikidata();
            new Wikipedia();
            new Anton('georgfischer');
        });

        $this->assertSame([], $seen, "Constructing provider clients raised:\n" . implode("\n", $seen));
    }

    /**
     * Regression: buildFilter() passed null as http_build_query's
     * $numeric_prefix, which is deprecated since PHP 8.1.
     */
    public function test_gnd_filter_building_raises_no_deprecation()
    {
        $gnd = new Gnd();

        $seen = $this->deprecationsDuring(function () use ($gnd) {
            $gnd->buildFilter(['type' => 'Person']);
            $gnd->buildFilter(['type' => 'Person', 'gndSubjectCategory' => 'Test']);
            $gnd->buildFilter([]);
        });

        $this->assertSame([], $seen, "buildFilter() raised:\n" . implode("\n", $seen));
    }

    public function test_gnd_filter_still_builds_the_expected_query()
    {
        $gnd = new Gnd();

        $this->assertSame('', $gnd->buildFilter([]));
        $this->assertSame('&filter=type:Person', $gnd->buildFilter(['type' => 'Person']));
    }

    public function test_helpers_raise_no_deprecation()
    {
        $seen = $this->deprecationsDuring(function () {
            UserAgent::get();
            LabelHelper::getLocalizedLabel('GND');
            LabelHelper::getLocalizedLabel(['de' => 'Deutsch', 'en' => 'English'], 'de');
            LabelHelper::getLocalizedLabel(['de' => 'Deutsch'], null);
            LabelHelper::getProviderLabel('gnd');
            LabelHelper::getProviderLabel('gnd', null);
        });

        $this->assertSame([], $seen, "Helpers raised:\n" . implode("\n", $seen));
    }

    /**
     * Regression: Idiotikon and Ortsnamen assigned $client without declaring it,
     * which is a deprecated dynamic property creation since PHP 8.2.
     */
    public function test_provider_clients_declare_every_property_they_assign()
    {
        foreach ([Gnd::class, Geonames::class, Idiotikon::class, Ortsnamen::class, Metagrid::class, Wikidata::class, Wikipedia::class] as $class) {
            $client = new $class();

            $this->assertTrue(
                property_exists($client, 'client'),
                "{$class} must declare its \$client property"
            );
        }
    }
}
