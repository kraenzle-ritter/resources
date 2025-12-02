@include('resources::livewire.partials.results-layout', [
    'providerKey' => 'ortsnamen',
    'providerName' => \KraenzleRitter\Resources\Helpers\LabelHelper::getProviderLabel('ortsnamen'),
    'model' => $model,
    'results' => $results,
    'saveAction' => function($result) {
        $json = addslashes(json_encode($result, JSON_UNESCAPED_UNICODE));
        return "saveResource('{$result->id}', '{$result->permalink}', '{$json}')";
    },
    'result_heading' => function($result) {
        return ($result->name ?? '') . ' (' . join(', ', $result->types) . ')';
    },
    'result_content' => function($result) {
        $output = "<a href=\"{$result->permalink}\" target=\"_blank\">{$result->permalink}</a>";

        // Verwende die vorbereitete Beschreibung
        if (!empty($result->processedDescription)) {
            $output .= "<br>" . $result->processedDescription;
        }

        return $output;
    }
])
