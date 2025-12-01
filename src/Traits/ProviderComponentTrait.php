<?php

namespace KraenzleRitter\Resources\Traits;

use KraenzleRitter\Resources\Resource;
use KraenzleRitter\Resources\Helpers\TextHelper;

/**
 * Trait for provider Livewire components
 */
trait ProviderComponentTrait
{
    /**
     * Extract the first sentence from a text
     *
     * @param string $text The text to extract from
     * @param int $maxLength Maximum length of the sentence (0 = unlimited)
     * @return string The first sentence
     */
    public function extractFirstSentence($text, $maxLength = 150)
    {
        return TextHelper::extractFirstSentence($text, $maxLength);
    }

    /**
     * Show all search results by updating the query options
     *
     * @return void
     */
    public function showAllResults()
    {
        // Increase the limit for displaying all results
        $this->queryOptions['limit'] = 50;
        $this->showAll = true;

        // If a search is active, we execute it again
        if (!empty($this->search)) {
            $this->updatedSearch($this->search);
        }
    }

    /**
     * Wird von Livewire aufgerufen, wenn sich die Property $search ändert.
     * Kann von der Komponente überschrieben werden.
     *
     * @param mixed $value
     * @return void
     */
    public function updatedSearch($value)
    {
        // Standard: keine Aktion. Kann in der Komponente überschrieben werden.
    }

    /**
     * Save a resource to the associated model
     *
     * @param mixed $model The model to associate the resource with
     * @param array $resourceData Resource data array
     * @return Resource The saved resource
     */
    protected function saveResourceToModel($model, array $resourceData)
    {
        // Check if model has required methods
        if (!method_exists($model, 'updateOrCreateResource')) {
            throw new \Exception('Model does not implement updateOrCreateResource method');
        }

        // Required fields
        $requiredFields = ['provider', 'url'];
        foreach ($requiredFields as $field) {
            if (!isset($resourceData[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        // Save the resource
        $resource = $model->updateOrCreateResource(
            $resourceData['provider'],
            $resourceData['url'],
            $resourceData['title'] ?? null,
            $resourceData['data'] ?? null
        );

        // Notify other components that resources have changed
        $this->dispatch('resourcesChanged');

        return $resource;
    }

    /**
     * Generic error handler for search operations
     *
     * @param \Exception $e The exception to handle
     * @return void
     */
    protected function handleSearchError(\Exception $e)
    {
        $this->error = 'Error: ' . $e->getMessage();
        $this->results = [];
    }

    /**
     * Get configuration for the current provider
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    protected function getProviderConfig(string $key, $default = null)
    {
        if (!isset($this->providerKey)) {
            return $default;
        }

        return config("resources.providers.{$this->providerKey}.{$key}", $default);
    }
}
