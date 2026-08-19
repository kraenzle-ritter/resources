<?php

namespace KraenzleRitter\Resources\Tests;

use Illuminate\Validation\ValidationException;
use KraenzleRitter\Resources\Helpers\UrlHelper;

class ResourceUrlSafetyTest extends TestCase
{
    public function test_http_and_https_are_safe()
    {
        $this->assertTrue(UrlHelper::isSafe('https://d-nb.info/gnd/118500775'));
        $this->assertTrue(UrlHelper::isSafe('http://api.geonames.org/2657896'));
    }

    public function test_script_bearing_schemes_are_not_safe()
    {
        foreach ([
            'javascript:alert(1)',
            'JavaScript:alert(1)',
            '  javascript:alert(1)  ',
            'data:text/html,<script>alert(1)</script>',
            'vbscript:msgbox(1)',
            'file:///etc/passwd',
        ] as $url) {
            $this->assertFalse(UrlHelper::isSafe($url), "{$url} must not be considered safe");
            $this->assertNull(UrlHelper::safe($url));
        }
    }

    public function test_a_url_without_a_scheme_is_not_safe()
    {
        $this->assertFalse(UrlHelper::isSafe('d-nb.info/gnd/118500775'));
        $this->assertFalse(UrlHelper::isSafe('//evil.example/x'));
    }

    public function test_absent_urls_are_recognised_separately()
    {
        foreach ([null, '', '   '] as $url) {
            $this->assertTrue(UrlHelper::isAbsent($url));
            $this->assertFalse(UrlHelper::isSafe($url));
        }

        $this->assertFalse(UrlHelper::isAbsent('https://example.org'));
    }

    public function test_the_allow_list_is_configurable()
    {
        config(['resources.allowed_url_schemes' => ['http', 'https', 'urn']]);

        $this->assertTrue(UrlHelper::isSafe('urn:nbn:de:bsz:14-qucosa-12345'));
        $this->assertFalse(UrlHelper::isSafe('javascript:alert(1)'));
    }

    public function test_saving_a_javascript_url_is_rejected()
    {
        $model = TestModel::create(['name' => 'Victim']);

        $this->expectException(ValidationException::class);

        $model->updateOrCreateResource([
            'provider' => 'manual-input',
            'provider_id' => 'x',
            'url' => 'javascript:alert(document.cookie)',
        ]);
    }

    public function test_saving_a_data_url_is_rejected()
    {
        $model = TestModel::create(['name' => 'Victim']);

        $this->expectException(ValidationException::class);

        $model->updateOrCreateResource([
            'provider' => 'manual-input',
            'provider_id' => 'x',
            'url' => 'data:text/html,<script>alert(1)</script>',
        ]);
    }

    public function test_a_rejected_url_creates_no_row()
    {
        $model = TestModel::create(['name' => 'Victim']);

        try {
            $model->updateOrCreateResource([
                'provider' => 'manual-input',
                'provider_id' => 'x',
                'url' => 'javascript:alert(1)',
            ]);
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(0, $model->fresh()->resources()->count());
    }

    public function test_ordinary_provider_urls_are_stored_unchanged()
    {
        $model = TestModel::create(['name' => 'Fine']);

        $resource = $model->updateOrCreateResource([
            'provider' => 'gnd',
            'provider_id' => '118500775',
            'url' => 'https://d-nb.info/gnd/118500775',
        ]);

        $this->assertSame('https://d-nb.info/gnd/118500775', $resource->url);
    }

    /**
     * KB writes identifier-only rows through the relation, with no url at all:
     * Place::setGeonamesIdAttribute(). That must keep working — which is why the
     * rule lives in updateOrCreateResource() and not in a model event.
     */
    public function test_a_write_without_a_url_is_accepted()
    {
        $model = TestModel::create(['name' => 'Identifier only']);

        $resource = $model->updateOrCreateResource([
            'provider' => 'geonames',
            'provider_id' => '2657896',
        ]);

        $this->assertSame('geonames', $resource->provider);
        $this->assertSame('2657896', $resource->provider_id);
    }

    public function test_the_relation_write_used_by_kb_still_works()
    {
        $model = TestModel::create(['name' => 'KB pattern']);

        $model->resources()->updateOrCreate(
            ['provider' => 'geonames'],
            ['provider_id' => '2657896']
        );

        $this->assertDatabaseHas('resources', [
            'resourceable_id' => $model->id,
            'provider' => 'geonames',
            'provider_id' => '2657896',
        ]);
    }

    public function test_link_renders_a_safe_url_with_noopener()
    {
        $html = UrlHelper::link('https://d-nb.info/gnd/118500775');

        $this->assertStringContainsString('href="https://d-nb.info/gnd/118500775"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_link_escapes_its_label()
    {
        $html = UrlHelper::link('https://example.org', '<img src=x onerror=alert(1)>');

        // The payload survives as text - that is fine. What must not survive is
        // it being markup, so assert on the tag form, not on the substring.
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    public function test_link_refuses_an_unsafe_url_but_keeps_the_text()
    {
        $html = UrlHelper::link('javascript:alert(1)');

        $this->assertStringNotContainsString('href', $html);
        $this->assertStringNotContainsString('<a', $html);
        $this->assertStringContainsString('javascript:alert(1)', $html);
    }

    public function test_link_handles_an_absent_url()
    {
        $this->assertSame('', UrlHelper::link(null));
        $this->assertSame('', UrlHelper::link(''));
    }

    public function test_an_existing_row_with_an_unsafe_url_still_reads()
    {
        $model = TestModel::create(['name' => 'Legacy']);

        // Written before the rule existed - straight through the relation.
        $model->resources()->create([
            'provider' => 'manual-input',
            'provider_id' => 'legacy',
            'url' => 'javascript:alert(1)',
        ]);

        $resources = $model->fresh()->resources;

        $this->assertCount(1, $resources);
        $this->assertNull(UrlHelper::safe($resources->first()->url), 'A legacy unsafe url must not be rendered as a link');
    }
}
