<?php

namespace KraenzleRitter\Resources\Tests;

use Illuminate\Database\Eloquent\Model;
use KraenzleRitter\Resources\HasResources;

class TestModel extends Model
{
    use HasResources;

    protected $table = 'test_models';
    protected $guarded = [];
    public $timestamps = true;
}
