<?php

namespace KraenzleRitter\Resources\Console\Commands;

use Illuminate\Console\Command;
use KraenzleRitter\Resources\Resource;
use KraenzleRitter\Resources\FetchResourcesService;

class ResourcesFetch extends Command
{
    protected $signature = 'resources:fetch {--provider= : gnd, wikidata or wikipedia}
                                            {--repair : repair urls and ids of bsg and heveticat}
                                            {--debug : debug modus; just show the resources array}';

    protected $description = 'Fetch resources and show them.';

    public $client;

    public function handle()
    {
        if ($this->option('repair')) {
            return $this->repair();
        }

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

        foreach ($resources as $resource) {
            sleep(1);

            $this->info('Fetch resources for ' . $resourceable_type  . ' with resourceable_id ' . $resource->resourceable_id);
            $model = $resource->resourceable_type::find($resource->resourceable_id);
            $model->saveMoreResources($provider);

            // returns an array of resources for an id (wiki wikidata-id)
            //$new_resources = $service->run($resource->provider_id);
            //if ($new_resources) {
            //    foreach ($new_resources as $new_resource) {
            //        $model->updateOrCreateResource($new_resource);
            //    }
            //} else {
            //    $this->warning('Could not find a wikidata id for '. $resource->provider_id .': '. $resource->provider_id);
            //}
        }

        return 0;
    }

    protected $providers = [
        'gnd' => [
                    'http://d-nb.info/gnd/',
                    'https://d-nb.info/gnd/',
                    'https://d-nb.info/gnd/'
        ],
        'burgerbibliothek' => [
            'http://katalog.burgerbib.ch/parametersuche.aspx?DeskriptorId=',
            'https://katalog.burgerbib.ch/parametersuche.aspx?DeskriptorId='
        ],
        'dodis' => [
            'http://dodis.ch/',
            'https://dodis.ch/'
        ],
        'elites-suisses-au-xxe-siecle' => [
            'http://www2.unil.ch/elitessuisses/index.php?page=detailPerso&idIdentite=',
            'https://www2.unil.ch/elitessuisses/index.php?page=detailPerso&idIdentite='
        ],
        'ethz' => [
            'http://archivdatenbank-online.ethz.ch/hsa/#/search?q=',
            'https://archivdatenbank-online.ethz.ch/hsa/#/search?q='
        ],
        'hallernet' => [
            'http://hallernet.org/data/person/',
            'https://hallernet.org/data/person/',
        ],
        'hfls' => [
            'http://www.hfls.ch/humo-gen/family/humo_/',
            'https://www.hfls.ch/humo-gen/family/humo_/',
        ],
        'histoirerurale' => [
            'http://www.histoirerurale.ch/pers/personnes/',
            'https://www.histoirerurale.ch/pers/personnes/',
            '.html'
        ],
        'hls-dhs-dss' => [
            'http://www.hls-dhs-dss.ch/textes/d/D',
            'https://www.hls-dhs-dss.ch/textes/d/D',
            '.php',
        ],
        'lonsea' => [
            'http://www.lonsea.de/pub/person/',
            'https://www.lonsea.de/pub/person/'
        ],
        'parlamentch' => [
            'http://www.parlament.ch/de/biografie?CouncillorId=',
            'https://www.parlament.ch/de/biografie?CouncillorId='
        ],
        'rag' => [
            'http://resource.database.rag-online.org/',
            'https://resource.database.rag-online.org/'
        ],
        'sikart' => [
            'http://www.sikart.ch/kuenstlerinnen.aspx?id=',
            'https://www.sikart.ch/kuenstlerinnen.aspx?id='
        ],
        'viaf' => [
            'http://viaf.org/viaf/',
            'https://viaf.org/viaf/'
        ],
        'worldcat' => [
            'http://www.worldcat.org/wcidentities/',
            'https://www.worldcat.org/wcidentities/',
        ],
    ];

    public function repair()
    {
        $this->info('Repair some database entries');

        foreach ($this->providers as $provider => $needles) {
            $this->info('working on ' . $provider);
            $resources = Resource::where('provider', $provider)->get();
            foreach ($resources as $resource) {
                $resource->provider_id = str_replace($needles, '', $resource->url);
                $resource->save();
            }
        }

        $this->info('working on bsg');
        $resources = Resource::where('provider', 'bsg')->get();
        foreach ($resources as $resource) {
            $model = $resource->resourceable_type::find($resource->resourceable_id);
            $gnd_id = $model->resources->where('provider', 'gnd')->first()->provider_id ?? 0;
            if ($gnd_id) {
                $resource->provider_id = $gnd_id;
                $resource->url = 'https://www.bsg.nb.admin.ch/discovery/search?query=lds50,contains,'.$gnd_id.'&vid=41SNL_54_INST:bsg';
                $resource->save();
            } else {
                $this->warn('Could not find a gnd for resource '. $resource->resourceable_type .': ' . $model->id);
            }
        }

        $this->info('working on helveticat');
        $resources = Resource::where('provider', 'helveticat')->get();
        foreach ($resources as $resource) {
            $model = $resource->resourceable_type::find($resource->resourceable_id);
            $gnd_id = $model->resources->where('provider', 'gnd')->first()->provider_id ?? 0;
            if ($gnd_id) {
                $resource->provider_id = $gnd_id;
                $resource->url = 'https://www.helveticat.ch/discovery/search?query=lds50,contains,'.$gnd_id.'&vid=41SNL_51_INST:helveticat';
                $resource->save();
            } else {
                $this->warn('Could not find a gnd for resource '. $resource->resourceable_type .': ' . $model->id);
            }
        }

        $this->info('working on fotostiftung');
        $resources = Resource::where('provider', 'fotostiftung')->get();
        foreach ($resources as $resource) {
            $resource->provider_id = preg_replace('|https://www.fotostiftung.ch/de/nc/index-der-fotografinnen/fotografin/cumulus/(\d+)/0/show/\d*/?|', "$1", $resource->url);
            $resource->save();
        }
    }
}
