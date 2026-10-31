<?php

namespace KraenzleRitter\Resources\Helpers;

use GuzzleHttp\ClientInterface;

class HttpClient
{
    /**
     * A client for a provider API that follows no redirect, sending through
     * the Laravel HTTP client ({@see LaravelClient}).
     *
     * Guzzle follows up to five redirects by default, to any host (ASVS
     * V15.3.2). Every provider answers its API calls directly (measured
     * 2026-10-30), so a redirect is not followed: the 3xx comes back as the
     * response. A caller that needs one passes its own `allow_redirects`.
     */
    public static function make(array $options): ClientInterface
    {
        return new LaravelClient($options + ['allow_redirects' => false]);
    }
}
