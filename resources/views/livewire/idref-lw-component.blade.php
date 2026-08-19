@include('resources::livewire.partials.results-layout', [
    'providerKey' => 'idref',
    'providerName' => \KraenzleRitter\Resources\Helpers\LabelHelper::getProviderLabel('idref'),
    'model' => $model,
    'results' => $results,
    'showAll' => $showAll,
    'saveAction' => function($result) {
        $json = addslashes(json_encode($result->raw, JSON_UNESCAPED_UNICODE));
        return "saveResource('{$result->ppn}', '{$result->url}', '{$json}')";
    },
    'result_heading' => function($result) {
        // affcourt_z already carries the disambiguating parenthetical,
        // e.g. "Barth, Karl (1886-1968 ; théologien)".
        return e($result->heading);
    },
    'result_content' => function($result) {
        $output = \KraenzleRitter\Resources\Helpers\UrlHelper::link($result->url);

        $typeKey = collect(config('resources.providers.idref.record_types', []))
            ->search(fn($type) => ($type['code'] ?? null) === $result->recordType);

        if ($typeKey) {
            $label = __('resources::messages.idref.record_type.' . $typeKey);
            // An unmapped code renders no label rather than a raw letter.
            if ($label !== 'resources::messages.idref.record_type.' . $typeKey) {
                $output .= ' <span class="badge bg-secondary">' . e($label) . '</span>';
            }
        }

        $variants = array_slice($result->variants, 0, 3);
        if ($variants) {
            $output .= '<br><span class="text-muted">'
                . e(__('resources::messages.Also known as')) . ': '
                . e(implode(' · ', $variants))
                . '</span>';
        }

        return $output;
    }
])
