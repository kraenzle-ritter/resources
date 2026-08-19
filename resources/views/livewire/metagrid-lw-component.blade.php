@include('resources::livewire.partials.results-layout', [
    'providerKey' => 'metagrid',
    'providerName' => \KraenzleRitter\Resources\Helpers\LabelHelper::getProviderLabel('metagrid'),
    'model' => $model,
    'results' => $results,
    'saveAction' => function($result) {
        $json = addslashes(json_encode($result, JSON_UNESCAPED_UNICODE));
        return "saveResource('{$result->id}', '{$result->uri}', '{$json}')";
    },
    'result_heading' => function($result) {
        // Name als Hauptüberschrift verwenden
        $name = $result->name;
        $name = preg_replace('/^([^0-9]+)(\d{4}).*(\d{4}?).*$/', '${1} ($2-$3)', $name);
        $name = preg_replace('/^([^0-9]+)(\d{4})-\d{2}-\d{2}$/', '${1} ($2)', $name);
        return e($name);
    },
    'result_content' => function($result) {
        $output = \KraenzleRitter\Resources\Helpers\UrlHelper::link($result->uri ?? null);

        if (!empty($result->processedDescription)) {
            $output .= "<br>" . e($result->processedDescription);
        }

        return $output;
    }
])
