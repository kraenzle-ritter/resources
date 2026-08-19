<?php

namespace KraenzleRitter\Resources\Helpers;

class UrlHelper
{
    /**
     * Schemes a stored resource URL may use.
     *
     * Anything else — `javascript:`, `data:`, `vbscript:` — is a script vector
     * once the URL is rendered into an href, and no provider legitimately needs
     * one. Configurable so a deployment that stores e.g. `urn:` identifiers can
     * widen it without a package change.
     *
     * @return array<int, string>
     */
    public static function allowedSchemes(): array
    {
        $schemes = config('resources.allowed_url_schemes', ['http', 'https']);

        return array_map('strtolower', (array) $schemes);
    }

    /**
     * Whether a URL is safe to store and to render as an href.
     *
     * An absent or empty URL is not "safe" — there is nothing to link to. Use
     * isAbsent() to tell the two cases apart where that matters.
     */
    public static function isSafe(?string $url): bool
    {
        if (self::isAbsent($url)) {
            return false;
        }

        $scheme = parse_url(trim($url), PHP_URL_SCHEME);

        if (! is_string($scheme)) {
            return false;
        }

        return in_array(strtolower($scheme), self::allowedSchemes(), true);
    }

    /**
     * A resource may legitimately carry no URL: KB stores identifier-only rows
     * (`Place::setGeonamesIdAttribute()` writes a provider_id and nothing else),
     * and the url column is NOT NULL, so those arrive as an empty string.
     */
    public static function isAbsent(?string $url): bool
    {
        return $url === null || trim($url) === '';
    }

    /**
     * The URL if it is safe to render, otherwise null.
     */
    public static function safe(?string $url): ?string
    {
        return self::isSafe($url) ? trim($url) : null;
    }

    /**
     * Render an external link for a provider-supplied URL.
     *
     * Centralised so the escaping, the scheme check and rel="noopener" are not
     * repeated — and cannot be forgotten — in each of the provider views. An
     * unsafe or absent URL degrades to escaped text with no href at all.
     *
     * @param string|null $url   URL from a provider response.
     * @param string|null $label Link text; defaults to the URL itself.
     */
    public static function link(?string $url, ?string $label = null): string
    {
        $safe = self::safe($url);
        $text = e($label ?? $url ?? '');

        if ($safe === null) {
            return $text;
        }

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            e($safe),
            $text
        );
    }
}
