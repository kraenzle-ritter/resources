<?php

namespace KraenzleRitter\Resources\Tests\Livewire;

use KraenzleRitter\Resources\Tests\TestCase;

class SimpleLivewireTest extends TestCase
{
    public function test_livewire_is_available()
    {
        $this->assertTrue(class_exists(\Livewire\Livewire::class));
    }
}
