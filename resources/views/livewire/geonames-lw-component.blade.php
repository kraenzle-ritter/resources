@php
    // $base_url wird jetzt von der Komponente bereitgestellt
    $base_url = $base_url ?? 'https://www.geonames.org/'; // Fallback, falls die Komponente keinen Wert liefert
    $apiLimitReached = empty($results) && $search && config('components.providers.geonames.user_name') === 'demo';

    // Debug-Ausgabe
    if (class_exists('\Log')) {
    }
@endphp

@include('resources::livewire.partials.results-layout', [
    'providerKey' => 'geonames',
    'providerName' => \KraenzleRitter\Resources\Helpers\LabelHelper::getProviderLabel('geonames'),
    'model' => $model,
    'results' => $results,
    'apiLimitReached' => $apiLimitReached,
    'saveAction' => function($result) use ($base_url) {
        return "saveResource('{$result->geonameId}', '{$base_url}{$result->geonameId}', '" . json_encode($result, JSON_UNESCAPED_UNICODE) . "')";
    },
    'result_heading' => function($result) {
        return $result->toponymName ?? '';
    },
    'result_content' => function($result) use ($base_url) {
        $output = "<a href=\"{$base_url}{$result->geonameId}\" target=\"_blank\">{$base_url}{$result->geonameId}</a>";

        // Verwende die vorbereitete kombinierte Beschreibung
        if (!empty($result->combinedDescription)) {
            $output .= "<br>" . $result->combinedDescription;
        }

        return $output;
    }
])
