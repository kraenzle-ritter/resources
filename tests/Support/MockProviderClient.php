<?php

namespace KraenzleRitter\Resources\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Builds a Guzzle client backed by a MockHandler queue, with a history
 * middleware so a test can assert on what actually went out — headers, query
 * parameters, request count.
 *
 * Provider clients accept an injected client, so:
 *
 *     $mock = MockProviderClient::withFixture('gnd', 'search');
 *     $results = (new Gnd($mock->client))->search('Hannah Arendt');
 *     $this->assertSame('json', $mock->lastQuery()['format']);
 */
class MockProviderClient
{
    /** @var array<int, array{request: RequestInterface, response: mixed}> */
    public array $history = [];

    public Client $client;

    /**
     * @param array<int, mixed> $responses Responses or exceptions to queue.
     */
    public function __construct(array $responses, array $config = [])
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $this->client = new Client(array_merge([
            'handler' => $stack,
            // Provider clients set this themselves; mirroring it here keeps the
            // mock behaving like the real thing for 4xx/5xx assertions.
            'http_errors' => false,
        ], $config));
    }

    /**
     * Queue one or more fixture files from tests/fixtures/<provider>/<name>.json.
     */
    public static function withFixture(string $provider, string ...$names): self
    {
        return new self(array_map(
            fn (string $name) => self::fixture($provider, $name),
            $names
        ));
    }

    /**
     * Queue the same fixture many times. A Livewire test can trigger render()
     * repeatedly, and a MockHandler whose queue runs dry throws instead of
     * answering, so provider mocks are queued generously.
     */
    public static function repeating(string $provider, string $name, int $times = 20): self
    {
        // A fresh Response per slot: a PSR-7 body is a stream that can only be
        // read once, so reusing one instance would answer the first request and
        // hand back an empty body to every later one.
        return new self(array_map(
            fn () => self::fixture($provider, $name),
            range(1, $times)
        ));
    }

    /**
     * Queue a single raw body.
     */
    public static function withBody(string $body, int $status = 200, array $headers = []): self
    {
        return new self([new Response($status, $headers ?: ['Content-Type' => 'application/json'], $body)]);
    }

    /**
     * Queue a single status code with an empty body.
     */
    public static function withStatus(int $status): self
    {
        return new self([new Response($status, [], '')]);
    }

    /**
     * Build a response from a fixture file. A `.json` file is assumed unless the
     * name already carries an extension, so malformed-body fixtures can be
     * stored as `.txt`.
     */
    public static function fixture(string $provider, string $name, int $status = 200): Response
    {
        $path = self::fixturePath($provider, $name);

        if (! is_file($path)) {
            throw new \RuntimeException("Missing fixture: {$path}");
        }

        return new Response($status, ['Content-Type' => 'application/json'], (string) file_get_contents($path));
    }

    public static function fixturePath(string $provider, string $name): string
    {
        if (! str_contains($name, '.')) {
            $name .= '.json';
        }

        return __DIR__ . '/../fixtures/' . $provider . '/' . $name;
    }

    /**
     * Decoded contents of a fixture, for asserting a parsed result against its
     * source without hardcoding values in two places.
     */
    public static function fixtureData(string $provider, string $name): mixed
    {
        return json_decode((string) file_get_contents(self::fixturePath($provider, $name)), true);
    }

    public function requestCount(): int
    {
        return count($this->history);
    }

    public function lastRequest(): ?RequestInterface
    {
        $last = end($this->history);

        return $last ? $last['request'] : null;
    }

    public function requestAt(int $index): ?RequestInterface
    {
        return $this->history[$index]['request'] ?? null;
    }

    public function lastUri(): string
    {
        return (string) ($this->lastRequest()?->getUri() ?? '');
    }

    /**
     * Query parameters of the last request, decoded.
     *
     * @return array<string, string>
     */
    public function lastQuery(): array
    {
        parse_str($this->lastRequest()?->getUri()->getQuery() ?? '', $query);

        /** @var array<string, string> $query */
        return $query;
    }

    public function lastHeader(string $name): ?string
    {
        $request = $this->lastRequest();

        if (! $request || ! $request->hasHeader($name)) {
            return null;
        }

        return $request->getHeaderLine($name);
    }
}
