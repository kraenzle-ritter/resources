<?php

namespace KraenzleRitter\Resources\Helpers;

use GuzzleHttp\Client;

class HttpClient
{
    /**
     * A Guzzle client for a provider API that follows no redirect.
     *
     * Guzzle follows up to five redirects by default, to any host (ASVS
     * V15.3.2). Every provider answers its API calls directly (measured
     * 2026-10-30), so a redirect is not followed: the 3xx comes back as the
     * response. A caller that needs one passes its own `allow_redirects`.
     */
    public static function make(array $options): Client
    {
        return new Client($options + ['allow_redirects' => false]);
    }
}
