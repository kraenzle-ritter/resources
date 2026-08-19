<?php

namespace KraenzleRitter\Resources\Tests\Livewire;

use Illuminate\Support\Facades\Event;
use KraenzleRitter\Resources\Events\ResourceSaved;
use KraenzleRitter\Resources\Http\Livewire\IdRefLwComponent;
use KraenzleRitter\Resources\Http\Livewire\ProviderSelect;
use KraenzleRitter\Resources\IdRef;
use KraenzleRitter\Resources\Tests\Support\MockProviderClient;
use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Tests\TestModel;
use Livewire\Livewire;

class IdRefLwComponentTest extends TestCase
{
    private function fakeIdRef(string $fixture = 'person-search'): MockProviderClient
    {
        return $this->fakeProvider(IdRef::class, 'idref', $fixture);
    }

    public function test_it_renders_and_lists_results()
    {
        $this->fakeIdRef();
        $model = TestModel::create(['name' => 'IdRef']);

        Livewire::test(IdRefLwComponent::class, ['model' => $model])
            ->set('search', 'Karl Barth')
            ->assertOk()
            ->assertSee('Barth, Karl (1886-1968 ; théologien)', false);
    }

    public function test_it_renders_nothing_and_does_not_throw_when_the_service_is_down()
    {
        $this->app->bind(IdRef::class, fn () => new IdRef(MockProviderClient::withStatus(500)->client));
        $model = TestModel::create(['name' => 'IdRef']);

        Livewire::test(IdRefLwComponent::class, ['model' => $model])
            ->set('search', 'Karl Barth')
            ->assertOk()
            ->assertSee(__('resources::messages.No matches'));
    }

    public function test_saving_creates_the_resource_with_the_ppn_and_target_url()
    {
        Event::fake([ResourceSaved::class]);
        $this->fakeIdRef();
        $model = TestModel::create(['name' => 'IdRef']);

        Livewire::test(IdRefLwComponent::class, ['model' => $model])
            ->call('saveResource', '026707357', 'https://www.idref.fr/026707357', json_encode(['ppn_z' => '026707357']));

        $this->assertDatabaseHas('resources', [
            'resourceable_id' => $model->id,
            'provider' => 'idref',
            'provider_id' => '026707357',
            'url' => 'https://www.idref.fr/026707357',
        ]);

        Event::assertDispatched(ResourceSaved::class);
    }

    public function test_a_ppn_with_a_check_character_is_stored_verbatim()
    {
        $this->fakeIdRef('corporate-search');
        $model = TestModel::create(['name' => 'IdRef']);

        Livewire::test(IdRefLwComponent::class, ['model' => $model])
            ->call('saveResource', '12841457X', 'https://www.idref.fr/12841457X', null);

        $this->assertDatabaseHas('resources', ['provider_id' => '12841457X']);
    }

    public function test_the_url_is_rebuilt_from_the_configured_target_url()
    {
        $this->fakeIdRef();
        $model = TestModel::create(['name' => 'IdRef']);

        // Even if the caller passes something else, the configured template wins.
        Livewire::test(IdRefLwComponent::class, ['model' => $model])
            ->call('saveResource', '026707357', 'https://wrong.example/x', null);

        $this->assertDatabaseHas('resources', ['url' => 'https://www.idref.fr/026707357']);
    }

    public function test_saving_twice_updates_instead_of_duplicating()
    {
        $this->fakeIdRef();
        $model = TestModel::create(['name' => 'IdRef']);

        $component = Livewire::test(IdRefLwComponent::class, ['model' => $model]);
        $component->call('saveResource', '026707357', 'https://www.idref.fr/026707357', null);
        $component->call('saveResource', '027098281', 'https://www.idref.fr/027098281', null);

        $this->assertSame(1, $model->fresh()->resources()->where('provider', 'idref')->count());
    }

    public function test_removal_is_scoped_to_the_mounted_model()
    {
        $this->fakeIdRef();
        $mine = TestModel::create(['name' => 'Mine']);
        $theirs = TestModel::create(['name' => 'Theirs']);

        foreach ([$mine, $theirs] as $model) {
            $model->updateOrCreateResource([
                'provider' => 'idref',
                'provider_id' => '026707357',
                'url' => 'https://www.idref.fr/026707357',
            ]);
        }

        Livewire::test(IdRefLwComponent::class, ['model' => $mine])
            ->call('removeResource', 'https://www.idref.fr/026707357');

        $this->assertSame(1, $theirs->fresh()->resources()->count());
        $this->assertSame(0, $mine->fresh()->resources()->count());
    }

    // --- endpoint routing --------------------------------------------------

    public function test_the_endpoint_selects_the_record_types()
    {
        $mock = $this->fakeIdRef('place-search');
        $model = TestModel::create(['name' => 'IdRef']);

        Livewire::test(IdRefLwComponent::class, ['model' => $model, 'search' => '', 'params' => [], 'filter' => [], 'endpoint' => 'places'])
            ->set('search', 'Geneve');

        $q = $mock->lastQuery()['q'];

        $this->assertStringContainsString('geogname_t:', $q);
        $this->assertStringNotContainsString('persname_t:', $q);
    }

    public function test_provider_select_passes_the_endpoint_to_the_component()
    {
        $this->fakeIdRef();
        $model = TestModel::create(['name' => 'IdRef']);

        $component = Livewire::test(ProviderSelect::class, [
            'model' => $model,
            'providers' => ['idref'],
            'endpoint' => 'places',
        ]);

        $component->assertSet('componentToRender', 'idref-lw-component');
        $this->assertSame('places', $component->get('componentParams')['endpoint'] ?? null);
    }

    public function test_provider_select_still_builds_the_other_branches_unchanged()
    {
        $model = TestModel::create(['name' => 'IdRef']);

        $wikipedia = Livewire::test(ProviderSelect::class, ['model' => $model, 'providers' => ['wikipedia-de'], 'endpoint' => 'actors']);
        $this->assertSame('wikipedia-de', $wikipedia->get('componentParams')['providerKey']);
        $this->assertArrayNotHasKey('endpoint', $wikipedia->get('componentParams'));

        $anton = Livewire::test(ProviderSelect::class, ['model' => $model, 'providers' => ['georgfischer'], 'endpoint' => 'actors']);
        $this->assertSame('actors', $anton->get('componentParams')['endpoint']);

        $gnd = Livewire::test(ProviderSelect::class, ['model' => $model, 'providers' => ['gnd'], 'endpoint' => 'actors']);
        $this->assertSame(['providerKey' => 'gnd'], $gnd->get('componentParams')['params']);
    }

    // --- rendering safety --------------------------------------------------

    public function test_hostile_provider_markup_does_not_survive_rendering()
    {
        $this->fakeIdRef('hostile');
        $model = TestModel::create(['name' => 'IdRef']);

        $html = Livewire::test(IdRefLwComponent::class, ['model' => $model])
            ->set('search', 'Karl Barth')
            ->html();

        $this->assertStringNotContainsStringIgnoringCase('<img', $html);
        $this->assertStringNotContainsStringIgnoringCase('<script', $html);
        $this->assertStringContainsString('&lt;img', $html, 'The payload never reached the view');
    }

    public function test_external_links_carry_noopener()
    {
        $this->fakeIdRef();
        $model = TestModel::create(['name' => 'IdRef']);

        $html = Livewire::test(IdRefLwComponent::class, ['model' => $model])
            ->set('search', 'Karl Barth')
            ->html();

        preg_match_all('/<a\b[^>]*target="_blank"[^>]*>/i', $html, $matches);

        $this->assertNotEmpty($matches[0]);
        foreach ($matches[0] as $anchor) {
            $this->assertMatchesRegularExpression('/rel="[^"]*noopener[^"]*"/i', $anchor);
        }
    }

    public function test_an_unmapped_record_type_renders_without_a_label()
    {
        $mock = MockProviderClient::withBody(json_encode([
            'response' => ['numFound' => 1, 'docs' => [[
                'ppn_z' => '026707357',
                'recordtype_z' => 'z',   // no mapping for this code
                'affcourt_z' => 'Something Unmapped',
            ]]],
        ]));
        $this->app->bind(IdRef::class, fn () => new IdRef($mock->client));

        $model = TestModel::create(['name' => 'IdRef']);

        Livewire::test(IdRefLwComponent::class, ['model' => $model])
            ->set('search', 'Karl Barth')
            ->assertOk()
            ->assertSee('Something Unmapped', false);
    }
}
