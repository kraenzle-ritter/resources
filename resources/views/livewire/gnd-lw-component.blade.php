@php
    // $base_url wird jetzt von der Komponente bereitgestellt
    $base_url = $base_url ?? 'https://lobid.org/gnd/'; // Fallback, falls die Komponente keinen Wert liefert

    // Debug-Ausgabe
    if (class_exists('\Log')) {
    }
@endphp

@include('resources::livewire.partials.results-layout', [
    'providerKey' => 'gnd',
    'providerName' => \KraenzleRitter\Resources\Helpers\LabelHelper::getProviderLabel('gnd'),
    'model' => $model,
    'results' => $results,
    'showAll' => $showAll,
    'saveAction' => function($result) {
        $json = addslashes(json_encode($result, JSON_UNESCAPED_UNICODE));
        return "saveResource('{$result->gndIdentifier}', '{$result->id}', '{$json}')";
    },
    'result_heading' => function($result) {
        $heading = e($result->preferredName ?? '');
        $birthYear = isset($result->dateOfBirth[0]) ? e(substr($result->dateOfBirth[0], 0, 4)) : '';
        $deathYear = isset($result->dateOfDeath[0]) ? e(substr($result->dateOfDeath[0], 0, 4)) : '';
        $separator = (isset($result->dateOfBirth[0]) || isset($result->dateOfDeath[0])) ? '–' : '';

        return "{$heading} {$birthYear} {$separator} {$deathYear}";
    },
    'result_content' => function($result) use ($base_url) {
        // The GND url comes with the result; it is third-party data, so it is
        // rendered through UrlHelper rather than interpolated into an href.
        $output = \KraenzleRitter\Resources\Helpers\UrlHelper::link($result->id ?? null);

        if(!empty($result->processedDescription)) {
            $output .= "<br>" . e($result->processedDescription);
        } elseif(isset($result->biographicalOrHistoricalInformation)) {
            $output .= "<br>" . e($result->biographicalOrHistoricalInformation[0]);
        }

        return $output;
    }
])
