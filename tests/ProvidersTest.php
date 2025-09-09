<?php

namespace KraenzleRitter\Resources\Tests;

use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Wikidata;
use KraenzleRitter\Resources\Gnd;
use KraenzleRitter\Resources\Wikipedia;
use KraenzleRitter\Resources\Metagrid;
use Illuminate\Support\Facades\Http;

class ProvidersTest extends TestCase
{
    public function test_wikidata_provider_can_be_instantiated()
    {
        $wikidata = new Wikidata();
        $this->assertInstanceOf(Wikidata::class, $wikidata);
    }

    public function test_gnd_provider_can_be_instantiated()
    {
        $gnd = new Gnd();
        $this->assertInstanceOf(Gnd::class, $gnd);
    }

    public function test_wikipedia_provider_can_be_instantiated()
    {
        $wikipedia = new Wikipedia();
        $this->assertInstanceOf(Wikipedia::class, $wikipedia);
    }

    public function test_metagrid_provider_can_be_instantiated()
    {
        $metagrid = new Metagrid();
        $this->assertInstanceOf(Metagrid::class, $metagrid);
    }

    public function test_providers_are_bound_in_container()
    {
        $this->assertTrue($this->app->bound(Wikidata::class));
        $this->assertTrue($this->app->bound(Gnd::class));
        $this->assertTrue($this->app->bound(Wikipedia::class));
        $this->assertTrue($this->app->bound(Metagrid::class));
    }

    public function test_providers_can_be_resolved_from_container()
    {
        $wikidata = $this->app->make(Wikidata::class);
        $gnd = $this->app->make(Gnd::class);
        $wikipedia = $this->app->make(Wikipedia::class);
        $metagrid = $this->app->make(Metagrid::class);

        $this->assertInstanceOf(Wikidata::class, $wikidata);
        $this->assertInstanceOf(Gnd::class, $gnd);
        $this->assertInstanceOf(Wikipedia::class, $wikipedia);
        $this->assertInstanceOf(Metagrid::class, $metagrid);
    }
}
