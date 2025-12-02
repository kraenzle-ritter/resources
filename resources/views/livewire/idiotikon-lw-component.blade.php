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
        return $result->lemmaText ?? '';
    },
    'result_content' => function($result) {
        $output = "<a href=\"{$result->url}\" target=\"_blank\">{$result->url}</a>";

        // Verwende die vorbereitete Beschreibung
        if (!empty($result->processedDescription)) {
            $output .= "<br>" . $result->processedDescription;
        }

        return $output;
    }
])
