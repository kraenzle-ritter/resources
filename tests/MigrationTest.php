<?php

namespace KraenzleRitter\Resources\Tests;

use KraenzleRitter\Resources\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class MigrationTest extends TestCase
{
    public function test_resources_table_exists()
    {
        $this->assertTrue(Schema::hasTable('resources'));
    }

    public function test_resources_table_has_required_columns()
    {
        $this->assertTrue(Schema::hasColumn('resources', 'id'));
        $this->assertTrue(Schema::hasColumn('resources', 'provider'));
        $this->assertTrue(Schema::hasColumn('resources', 'provider_id'));
        $this->assertTrue(Schema::hasColumn('resources', 'url'));
        $this->assertTrue(Schema::hasColumn('resources', 'full_json'));
        $this->assertTrue(Schema::hasColumn('resources', 'resourceable_type'));
        $this->assertTrue(Schema::hasColumn('resources', 'resourceable_id'));
        $this->assertTrue(Schema::hasColumn('resources', 'created_at'));
        $this->assertTrue(Schema::hasColumn('resources', 'updated_at'));
    }

    public function test_test_models_table_exists()
    {
        $this->assertTrue(Schema::hasTable('test_models'));
    }

    public function test_test_models_table_has_required_columns()
    {
        $this->assertTrue(Schema::hasColumn('test_models', 'id'));
        $this->assertTrue(Schema::hasColumn('test_models', 'name'));
        $this->assertTrue(Schema::hasColumn('test_models', 'created_at'));
        $this->assertTrue(Schema::hasColumn('test_models', 'updated_at'));
    }

    public function test_database_connection_works()
    {
        // Test eine einfache Query
        $result = \DB::select('SELECT 1 as test');
        $this->assertEquals(1, $result[0]->test);
    }
}
