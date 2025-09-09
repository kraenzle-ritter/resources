<?php

namespace KraenzleRitter\Resources\Helpers;

class LabelHelper
{
    /**
     * Get the localized label for a provider.
     * If the label is an array with locale keys, return the appropriate translation.
     * If no translation is found for the current locale, fallback to 'en'.
     * If label is a string, return it as is.
     *
     * @param string|array $label The label configuration
     * @param string|null $locale The locale to use (defaults to app locale)
     * @return string The resolved label
     */
    public static function getLocalizedLabel($label, string $locale = null): string
    {
        if (is_string($label)) {
            return $label;
        }

        if (!is_array($label)) {
            return '';
        }

        $locale = $locale ?? app()->getLocale();

        // Try to get the label for the current locale
        if (isset($label[$locale])) {
            return $label[$locale];
        }

        // Fallback, return the first available label
        return array_values($label)[0] ?? '';
    }

    /**
     * Get the localized label for a provider by provider key.
     *
     * @param string $providerKey The provider key
     * @param string|null $locale The locale to use (defaults to app locale)
     * @return string The resolved label
     */
    public static function getProviderLabel(string $providerKey, string $locale = null): string
    {
        $providers = config('resources.providers', []);

        if (!isset($providers[$providerKey]['label'])) {
            return $providerKey; // Fallback to provider key if no label configured
        }

        return self::getLocalizedLabel($providers[$providerKey]['label'], $locale);
    }
}
