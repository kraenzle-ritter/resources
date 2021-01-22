<?php

namespace KraenzleRitter\Resources\Console\Commands;

use Illuminate\Console\Command;
use KraenzleRitter\Resources\Resource;
use KraenzleRitter\Resources\FetchResourcesService;

class ResourcesFetch extends Command
{
    protected $signature = 'resources:fetch {--provider= : gnd, wikidata or wikipedia}
                                            {--debug : debug modus; just show the resources array}';

    protected $description = 'Fetch resources and show them.';

    public $client;

    public function handle()
    {
        if (!$this->option('provider')) {
            $this->error('A provider is required');
            return 1;
        }

        $provider = $this->option('provider');

        if (!in_array($provider, ['gnd', 'wikidata', 'wikipedia'])) {
            $this->error('provider only gnd, wikidata or wipedia allowed. ' . $provider .' given');
            return 3;
        }

        $resources = Resource::where('provider', $provider)->get();
        $service = new FetchResourcesService($provider);

        foreach ($resources as $resource) {
            //echo $resource->provider_id;
            $new_resources = $service->run($resource->provider_id);
            $model = $resource->resourceable_type::find($resource->resourceable_id);

            if ($new_resources) {
                foreach($new_resources as $new_resource) {
                    $model->updateOrCreateResource($new_resource);
                }
            }
        }
    }


}
