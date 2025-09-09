@php
    // Erhalte den aktuellen providerKey aus der AntonLwComponent-Klasse
    $currentProviderKey = $this->providerKey ?? 'anton';
@endphp

@include('resources::livewire.partials.results-layout', [
    'providerKey' => $currentProviderKey,
    'providerName' => \KraenzleRitter\Resources\Helpers\LabelHelper::getProviderLabel($currentProviderKey),
    'model' => $model,
    'results' => $results,
    'saveAction' => function($result) use ($currentProviderKey) {
        // Bestimme die volle provider_id nach dem Schema slug-endpoint-id
        $slug = config("components.providers.{$currentProviderKey}.slug", $currentProviderKey);
        // Verwende den Endpoint aus der Komponente
        $endpoint = $this->endpoint ?? 'objects'; // Fallback auf 'objects' wenn kein endpoint spezifiziert
        $fullProviderId = $slug . '-' . $endpoint . '-' . $result->id;

        return "saveResource('{$fullProviderId}', '{$result->links[0]->url}', '" . json_encode($result, JSON_UNESCAPED_UNICODE) . "')";
    },
    'result_heading' => function($result) {
        return $result->fullname ?? '';
    },
    'result_content' => function($result) {
        $output = "<a href=\"{$result->links[0]->url}\" target=\"_blank\">{$result->links[0]->url}</a>";

        // Beschreibung, falls vorhanden
        if (!empty($result->description)) {
            // Ersten Satz extrahieren
            $firstSentence = preg_split('/[.!?]/', $result->description, 2);
            if (!empty($firstSentence[0])) {
                $output .= "<br>" . trim($firstSentence[0]) . ".";
            } else {
                $output .= "<br>" . $result->description;
            }
        }

        return $output;
    }
])
