@include('resources::livewire.partials.results-layout', [
    'providerKey' => 'metagrid',
    'providerName' => \KraenzleRitter\Resources\Helpers\LabelHelper::getProviderLabel('metagrid'),
    'model' => $model,
    'results' => $results,
    'saveAction' => function($result) {
        return "saveResource('{$result->id}', '{$result->uri}', '" . json_encode($result, JSON_UNESCAPED_UNICODE) . "')";
    },
    'result_heading' => function($result) {
        // Name als Hauptüberschrift verwenden
        $name = $result->name;
        $name = preg_replace('/^([^0-9]+)(\d{4}).*(\d{4}?).*$/', '${1} ($2-$3)', $name);
        $name = preg_replace('/^([^0-9]+)(\d{4})-\d{2}-\d{2}$/', '${1} ($2)', $name);
        return $name;
    },
    'result_content' => function($result) {
        $output = "<a href=\"{$result->uri}\" target=\"_blank\">{$result->uri}</a>";

        // Verwende die vorbereitete Beschreibung
        if (!empty($result->processedDescription)) {
            $output .= "<br>" . $result->processedDescription;
        }

        return $output;
    }
])
