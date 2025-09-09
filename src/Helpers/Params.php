<?php

namespace KraenzleRitter\Resources\Helpers;

class Params
{
     /**
     * Convert Parameters Array to a Query String.
     *
     * Escapes values according to RFC 1738.
     *
     * @see http://forum.geonames.org/gforum/posts/list/8.page
     * @see rawurlencode()
     * @see https://github.com/Aternus/geonames-client/blob/master/src/Client.php
     *
     * @param array $params Associative array of query parameters.
     *
     * @return string The query string.
     */
    public static function toQueryString(array $params = []) : string
    {
        $query_string = [];
        foreach ($params as $name => $value) {
            if (empty($name)) {
                continue;
            }
            if (is_array($value)) {
                // recursion case
                $result_string = static::toQueryString($value);
                if (!empty($result_string)) {
                    $query_string[] = $result_string;
                }
            } else {
                // base case
                $value = (string)$value;
                $query_string[] = $name . '=' . rawurlencode($value);
            }
        }
        return implode('&', $query_string);
    }
}
