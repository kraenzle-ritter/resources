@include('resources::livewire.partials.results-layout', [
    'providerKey' => 'ortsnamen',
    'providerName' => \KraenzleRitter\Resources\Helpers\LabelHelper::getProviderLabel('ortsnamen'),
    'model' => $model,
    'results' => $results,
    'saveAction' => function($result) {
        $json = addslashes(json_encode($result, JSON_UNESCAPED_UNICODE));
        return "saveResource('{$result->id}', '{$result->permalink}', '{$json}')";
    },
    'result_heading' => function($result) {
        $types = array_map('e', (array) ($result->types ?? []));

        return e($result->name ?? '') . ' (' . join(', ', $types) . ')';
    },
    'result_content' => function($result) {
        $output = \KraenzleRitter\Resources\Helpers\UrlHelper::link($result->permalink ?? null);

        if (!empty($result->processedDescription)) {
            $output .= "<br>" . e($result->processedDescription);
        }

        return $output;
    }
])
