@php
    // Ensure $base_url is defined - should be passed from the component
    // The base_url depends on the selected language (via providerKey)
    $base_url = $base_url ?? 'https://de.wikipedia.org/wiki/';

    // Additionally output the complete query string for URL debugging
    $debugInfo = 'Base URL: ' . $base_url;
    if (isset($_SERVER['QUERY_STRING'])) {
        $debugInfo .= ', Query: ' . $_SERVER['QUERY_STRING'];
    }

    // For debugging
    if (class_exists('\Log')) {

        // Check if the URL is for the correct language
        if (preg_match('/https?:\/\/([a-z]{2})\.wikipedia\.org\/wiki\//', $base_url, $matches)) {
            $language = $matches[1];
        } else {
        }
    }
@endphp

@php
    // Extrahiere den korrekten Provider-Namen aus der base_url, falls vorhanden
    $displayProviderKey = 'wikipedia';

    // Versuche, die Sprache aus der URL zu extrahieren
    if (preg_match('/https?:\/\/([a-z]{2})\.wikipedia\.org\/wiki\//', $base_url, $matches)) {
        $language = $matches[1];
        $displayProviderKey = 'wikipedia-' . $language;
    }

    if (class_exists('\Log')) {
    }
@endphp

@include('resources::livewire.partials.results-layout', [
    'providerKey' => $displayProviderKey,
    'providerName' => \KraenzleRitter\Resources\Helpers\LabelHelper::getProviderLabel($displayProviderKey),
    'model' => $model,
    'results' => $results,
    'saveAction' => function($result) use ($base_url) {
        // Debug output for the URL directly before use
        if (class_exists('\Log')) {
        }

        // Encode URL correctly for JavaScript attribute
        $encodedTitle = str_replace("'", "\\'", $result->title);
        $encodedUrl = str_replace("'", "\\'", $base_url . $result->title);

        return "saveResource('{$result->pageid}', '{$encodedUrl}', '{$encodedTitle}')";
    },
    'result_heading' => function($result) {
        return e($result->title ?? ''); // Use title as heading
    },
    'result_content' => function($result) use ($base_url) {
        $title = $result->title ?? '';
        $output = \KraenzleRitter\Resources\Helpers\UrlHelper::link($base_url . str_replace(' ', '_', $title));

        if (!empty($result->firstSentence)) {
            $output .= "<br>" . e($result->firstSentence);
        }

        return $output;
    }
])
