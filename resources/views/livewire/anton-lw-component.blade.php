@php
    // Erhalte den aktuellen providerKey aus der AntonLwComponent-Klasse
    $currentProviderKey = $this->providerKey ?? 'anton';
    $endpoint = $this->endpoint ?? 'objects';
    
    // Hilfsfunktion um die target_url für ein Result zu generieren
    $getTargetUrl = function($result) use ($currentProviderKey, $endpoint) {
        $targetUrlTemplate = config("resources.providers.{$currentProviderKey}.target_url");
        $slug = config("resources.providers.{$currentProviderKey}.slug", $currentProviderKey);
        
        if ($targetUrlTemplate) {
            return str_replace(
                ['{endpoint}', '{short_provider_id}', '{provider_id}', '{slug}'],
                [$endpoint, $result->id, $slug . '-' . $endpoint . '-' . $result->id, $slug],
                $targetUrlTemplate
            );
        }
        
        // Fallback: API-URL ohne /api/
        $apiUrl = $result->links[0]->url ?? '';
        return str_replace('/api/', '/', $apiUrl);
    };
@endphp

@include('resources::livewire.partials.results-layout', [
    'providerKey' => $currentProviderKey,
    'providerName' => \KraenzleRitter\Resources\Helpers\LabelHelper::getProviderLabel($currentProviderKey),
    'model' => $model,
    'results' => $results,
    'saveAction' => function($result) use ($currentProviderKey, $endpoint) {
        // Bestimme die volle provider_id nach dem Schema slug-endpoint-id
        $slug = config("resources.providers.{$currentProviderKey}.slug", $currentProviderKey);
        $fullProviderId = $slug . '-' . $endpoint . '-' . $result->id;

        $json = addslashes(json_encode($result, JSON_UNESCAPED_UNICODE));
        return "saveResource('{$fullProviderId}', '{$result->links[0]->url}', '{$json}')";
    },
    'result_heading' => function($result) {
        return $result->fullname ?? '';
    },
    'result_content' => function($result) use ($getTargetUrl) {
        $targetUrl = $getTargetUrl($result);
        $output = "<a href=\"{$targetUrl}\" target=\"_blank\">{$targetUrl}</a>";

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
