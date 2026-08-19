<?php

namespace KraenzleRitter\Resources\Tests\Livewire;

use KraenzleRitter\Resources\Events\ResourceSaved;
use KraenzleRitter\Resources\Http\Livewire\ManualInputLwComponent;
use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Tests\TestModel;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

/**
 * Manual input is the one place where a user types the URL that later ends up
 * in an href, so it is the entry point for a stored-XSS payload.
 *
 * The component declared rules() with `$`-prefixed keys ('$provider' instead of
 * 'provider') and never called validate(), so no rule had ever applied.
 */
class ManualInputLwComponentTest extends TestCase
{
    public function test_rule_keys_match_the_property_names()
    {
        $rules = (new \ReflectionMethod(ManualInputLwComponent::class, 'rules'))
            ->invoke(new ManualInputLwComponent());

        foreach (array_keys($rules) as $key) {
            $this->assertStringNotContainsString('$', $key, "Rule key '{$key}' must be a property name, not a variable");
            $this->assertTrue(
                property_exists(ManualInputLwComponent::class, $key),
                "Rule key '{$key}' does not match any component property"
            );
        }
    }

    public function test_an_empty_form_creates_nothing_and_reports_errors()
    {
        $model = TestModel::create(['name' => 'Manual']);

        Livewire::test(ManualInputLwComponent::class, ['model' => $model])
            ->call('saveResource')
            ->assertHasErrors(['provider', 'url']);

        $this->assertSame(0, $model->fresh()->resources()->count());
    }

    public function test_a_javascript_url_is_rejected()
    {
        Event::fake([ResourceSaved::class]);

        $model = TestModel::create(['name' => 'Manual']);

        Livewire::test(ManualInputLwComponent::class, ['model' => $model])
            ->set('provider', 'evil')
            ->set('provider_id', 'x')
            ->set('url', 'javascript:alert(document.cookie)')
            ->call('saveResource')
            ->assertHasErrors('url');

        $this->assertSame(0, $model->fresh()->resources()->count());
        Event::assertNotDispatched(ResourceSaved::class);
    }

    public function test_a_data_url_is_rejected()
    {
        $model = TestModel::create(['name' => 'Manual']);

        Livewire::test(ManualInputLwComponent::class, ['model' => $model])
            ->set('provider', 'evil')
            ->set('url', 'data:text/html,<script>alert(1)</script>')
            ->call('saveResource')
            ->assertHasErrors('url');

        $this->assertSame(0, $model->fresh()->resources()->count());
    }

    public function test_a_url_without_a_scheme_is_rejected()
    {
        $model = TestModel::create(['name' => 'Manual']);

        Livewire::test(ManualInputLwComponent::class, ['model' => $model])
            ->set('provider', 'dodis')
            ->set('url', 'dodis.ch/12345')
            ->call('saveResource')
            ->assertHasErrors('url');

        $this->assertSame(0, $model->fresh()->resources()->count());
    }

    public function test_valid_manual_input_is_saved()
    {
        Event::fake([ResourceSaved::class]);

        $model = TestModel::create(['name' => 'Manual']);

        Livewire::test(ManualInputLwComponent::class, ['model' => $model])
            ->set('provider', 'dodis')
            ->set('provider_id', '12345')
            ->set('url', 'https://dodis.ch/12345')
            ->call('saveResource')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('resources', [
            'resourceable_id' => $model->id,
            'provider' => 'dodis',
            'provider_id' => '12345',
            'url' => 'https://dodis.ch/12345',
        ]);

        Event::assertDispatched(ResourceSaved::class);
    }

    public function test_provider_id_is_optional()
    {
        $model = TestModel::create(['name' => 'Manual']);

        Livewire::test(ManualInputLwComponent::class, ['model' => $model])
            ->set('provider', 'dodis')
            ->set('url', 'https://dodis.ch/12345')
            ->call('saveResource')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('resources', [
            'resourceable_id' => $model->id,
            'provider' => 'dodis',
        ]);
    }
}
