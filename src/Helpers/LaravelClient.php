<?php

namespace KraenzleRitter\Resources\Helpers;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A Guzzle client in shape that sends through the Laravel HTTP client.
 *
 * The providers ask `$this->client->get(...)` and read a PSR-7 response; that
 * stays. What changes is the road: through `Http`, so a host application's
 * `Http::fake()`, `Http::preventStrayRequests()` and `Http::globalOptions()`
 * reach every provider call, which a Guzzle client of its own bypassed
 * (kraenzle-ritter/anton#791).
 *
 * Laravel joins a base URL and a path by string; Guzzle resolves them as
 * RFC 3986 does, and the providers rely on that (`…/w/api.php` with `?query`,
 * an empty path, an absolute URL). The URL is therefore resolved here and
 * sent whole.
 */
final class LaravelClient implements ClientInterface
{
    /**
     * @param  array<string, mixed>  $config  Guzzle-style options: base_uri, timeout, connect_timeout, headers, http_errors, allow_redirects
     */
    public function __construct(private readonly array $config) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function get(string|\Psr\Http\Message\UriInterface $uri = '', array $options = []): ResponseInterface
    {
        return $this->request('GET', $uri, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        $url = $this->resolve((string) $uri);
        $clientOptions = array_diff_key($this->config, ['base_uri' => true, 'handler' => true]);
        $requestOptions = array_diff_key($options, ['handler' => true]);

        try {
            return Http::withOptions($clientOptions)->send($method, $url, $requestOptions)->toPsrResponse();
        } catch (ConnectionException $e) {
            // The providers handle Guzzle's connection failure; it stays theirs.
            throw new ConnectException($e->getMessage(), new Request($method, $url), $e);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        $headers = array_map(fn (array $values): string => implode(', ', $values), $request->getHeaders());

        return $this->request($request->getMethod(), $request->getUri(), $options + [
            'headers' => $headers,
            'body' => (string) $request->getBody(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        return $this->promise(fn () => $this->send($request, $options));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function requestAsync(string $method, $uri, array $options = []): PromiseInterface
    {
        return $this->promise(fn () => $this->request($method, $uri, $options));
    }

    /**
     * @deprecated as in Guzzle; kept so the interface is complete
     */
    public function getConfig(?string $option = null)
    {
        return $option === null ? $this->config : ($this->config[$option] ?? null);
    }

    private function resolve(string $uri): string
    {
        $base = $this->config['base_uri'] ?? null;

        return $base === null || $base === ''
            ? $uri
            : (string) UriResolver::resolve(Utils::uriFor((string) $base), Utils::uriFor($uri));
    }

    private function promise(callable $call): PromiseInterface
    {
        try {
            return Create::promiseFor($call());
        } catch (\Throwable $e) {
            return Create::rejectionFor($e);
        }
    }
}
