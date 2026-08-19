<?php

namespace KraenzleRitter\Resources\Helpers;

class ConfigRedactor
{
    public const PLACEHOLDER = '***redacted***';

    /**
     * Keys whose values must never be rendered.
     *
     * `user_name` is in here because the GeoNames account name is effectively
     * the credential for that API — it is the only thing identifying the quota
     * holder.
     */
    private const SECRET_KEY_PATTERN = '/token|secret|password|api[_-]?key|^key$|^user_name$|^username$/i';

    /**
     * Replace secret-looking values in a configuration array, recursively.
     *
     * Applied in the controller rather than in each view, so that adding a view
     * cannot reintroduce the leak.
     */
    public static function redact(array $config): array
    {
        $redacted = [];

        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $redacted[$key] = self::redact($value);

                continue;
            }

            $redacted[$key] = self::isSecretKey((string) $key) && ! self::isEmpty($value)
                ? self::PLACEHOLDER
                : $value;
        }

        return $redacted;
    }

    public static function isSecretKey(string $key): bool
    {
        return (bool) preg_match(self::SECRET_KEY_PATTERN, $key);
    }

    /**
     * An unset credential is worth showing as unset — that is a configuration
     * problem the diagnostics page exists to surface.
     */
    private static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === false;
    }
}
