<?php

namespace KraenzleRitter\Resources\Traits;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Log;

trait HttpClientTrait
{
    /**
     * Make a safe HTTP GET request with comprehensive error handling
     *
     * @param string $endpoint The endpoint to request
     * @param string $apiName Name of the API for logging (e.g. 'GND API', 'Geonames API')
     * @param mixed $fallbackValue The value to return on error
     * @param callable|null $responseProcessor Optional callback to process successful responses
     * @return mixed
     */
    protected function safeHttpGet(string $endpoint, string $apiName, $fallbackValue, ?callable $responseProcessor = null)
    {
        try {
            $response = $this->client->get($endpoint);

            // Check response status
            if ($response->getStatusCode() >= 500) {
                Log::warning("{$apiName}: Server error", [
                    'status_code' => $response->getStatusCode(),
                    'endpoint' => $endpoint,
                    'response' => $response->getBody()->getContents()
                ]);
                return $fallbackValue;
            }

            if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500) {
                Log::warning("{$apiName}: Client error", [
                    'status_code' => $response->getStatusCode(),
                    'endpoint' => $endpoint,
                    'response' => $response->getBody()->getContents()
                ]);
                return $fallbackValue;
            }

            if ($response->getStatusCode() == 200) {
                $content = $response->getBody()->getContents();

                // If a response processor is provided, use it
                if ($responseProcessor !== null) {
                    return $responseProcessor($content, $endpoint, $apiName, $fallbackValue);
                }

                // Default JSON processing
                $result = json_decode($content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning("{$apiName}: Invalid JSON response", [
                        'error' => json_last_error_msg(),
                        'endpoint' => $endpoint,
                        'response' => $content
                    ]);
                    return $fallbackValue;
                }

                return $result;
            }

            Log::warning("{$apiName}: Unexpected response status", [
                'status_code' => $response->getStatusCode(),
                'endpoint' => $endpoint
            ]);
            return $fallbackValue;

        } catch (ConnectException $e) {
            // Connection timeout or DNS issues
            Log::error("{$apiName}: Connection error", [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
                'type' => 'connection_timeout'
            ]);
            return $fallbackValue;

        } catch (ClientException $e) {
            // 4xx errors
            Log::warning("{$apiName}: Client error", [
                'error' => $e->getMessage(),
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : 'unknown',
                'endpoint' => $endpoint
            ]);
            return $fallbackValue;

        } catch (ServerException $e) {
            // 5xx errors
            Log::error("{$apiName}: Server error", [
                'error' => $e->getMessage(),
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : 'unknown',
                'endpoint' => $endpoint
            ]);
            return $fallbackValue;

        } catch (RequestException $e) {
            // General request exceptions (includes timeout)
            Log::error("{$apiName}: Request failed", [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
                'type' => 'request_exception'
            ]);
            return $fallbackValue;

        } catch (\Exception $e) {
            // Catch any other exceptions
            Log::error("{$apiName}: Unexpected error", [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
                'type' => 'unexpected_exception'
            ]);
            return $fallbackValue;
        }
    }
}
