@php
    // $base_url wird jetzt von der Komponente bereitgestellt
    $base_url = $base_url ?? 'https://www.wikidata.org/wiki/'; // Fallback, falls die Komponente keinen Wert liefert

    // Debug-Ausgabe
    if (class_exists('\Log')) {
    }
@endphp

@include('resources::livewire.partials.results-layout', [
    'providerKey' => 'wikidata',
    'providerName' => \KraenzleRitter\Resources\Helpers\LabelHelper::getProviderLabel('wikidata'),
    'model' => $model,
    'results' => $results,
    'saveAction' => function($result) use ($base_url) {
        $json = addslashes(json_encode($result, JSON_UNESCAPED_UNICODE));
        return "saveResource('{$result->id}', '{$base_url}{$result->id}', '{$json}')";
    },
    'result_heading' => function($result) {
        return e($result->label ?? '');
    },
    'result_content' => function($result) use ($base_url) {
        $output = \KraenzleRitter\Resources\Helpers\UrlHelper::link($base_url . ($result->id ?? ''));

        if (!empty($result->description)) {
            $output .= "<br>" . e($result->description);
        }

        return $output;
    }
])
