@include('resources::livewire.partials.results-layout', [
    'providerKey' => 'idiotikon',
    'providerName' => \KraenzleRitter\Resources\Helpers\LabelHelper::getProviderLabel('idiotikon'),
    'model' => $model,
    'results' => $results,
    'saveAction' => function($result) {
        $json = addslashes(json_encode($result, JSON_UNESCAPED_UNICODE));
        return "saveResource('{$result->lemmaID}', '{$result->url}', '{$json}')";
    },
    'result_heading' => function($result) {
        return e($result->lemmaText ?? '');
    },
    'result_content' => function($result) {
        $output = \KraenzleRitter\Resources\Helpers\UrlHelper::link($result->url ?? null);

        if (!empty($result->processedDescription)) {
            $output .= "<br>" . e($result->processedDescription);
        }

        return $output;
    }
])
