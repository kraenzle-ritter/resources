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
     * Remove a resource of the mounted model by its url.
     *
     * Scoped to $this->model on purpose: Livewire method calls are
     * client-controlled, so an unscoped `Resource::where('url', ...)->delete()`
     * lets any caller delete the matching rows of every other model.
     *
     * @return bool Whether anything was deleted.
     */
    protected function removeResourceByUrl(string $url): bool
    {
        if (! $this->model || ! method_exists($this->model, 'resources')) {
            return false;
        }

        $deleted = (bool) $this->model->resources()->where('url', $url)->delete();

        $this->dispatch('resourcesChanged');

        return $deleted;
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
