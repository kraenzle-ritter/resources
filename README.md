# resources

[![Latest Stable Version](https://poser.pugx.org/kraenzle-ritter/resources/v)](//packagist.org/packages/kraenzle-ritter/resources) 
[![Total Downloads](https://poser.pugx.org/kraenzle-ritter/resources/downloads)](//packagist.org/packages/kraenzle-ritter/resources) 
[![Latest Unstable Version](https://poser.pugx.org/kraenzle-ritter/resources/v/unstable)](//packagist.org/packages/kraenzle-ritter/resources) 
[![License](https://poser.pugx.org/kraenzle-ritter/resources/license)](//packagist.org/packages/kraenzle-ritter/resources)
[![Tests](https://github.com/kraenzle-ritter/resources/actions/workflows/run-tests.yml/badge.svg)](https://github.com/kraenzle-ritter/resources/actions/workflows/run-tests.yml)

Resource Model and a hasResource trait where resources are basically links to a resources (eg. Wikipedia-Article or GND-Entry). Livewire Components (Bootstrap 5) for searching, selecting and listing the links.

## Supported Providers

- [GND](https://lobid.org/gnd) (Gemeinsame Normdatei)
- [Geonames](http://www.geonames.org/) (Geographical database)
- [Wikipedia](https://www.wikipedia.org/) (Multiple languages: DE, EN, FR, IT some others)
- [Wikidata](https://www.wikidata.org/) (Structured data)
- [Idiotikon](https://www.idiotikon.ch/) (Swiss German dictionary)
- [Ortsnamen.ch](https://ortsnamen.ch/) (Swiss place names)
- [IdRef](https://www.idref.fr/) (French authority file, ABES)
- [Metagrid](https://metagrid.ch/) (Swiss humanities database network)
- [Anton API](https://anton.ch/) (Archives and collections)
  - [Archiv der Georg Fischer AG](https://archives.georgfischer.com)
  - [Gosteli Archiv](https://gosteli.anton.ch)
  - [Karl Barth-Archiv](https://kba.karl-barth.ch)
- Manual Input (Custom links)

## Installation

Via Composer

``` bash
$ composer require kraenzle-ritter/resources
```

Then either run `php artisan vendor:publish` and publish the migration or copy the file to your migrations directory. Then run `php artisan migrate`.


## Usage

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model
use KraenzleRitter\Resources\hasResources;

class MyModel extends Model
{
    use hasResources;

    ...

}
```

Then you are ready to go:

```php

$resource = [
    'provider' => 'Wikipedia',
    'provider_id' => 4013996,
    'url' => 'https://fr.wikipedia.org/wiki/Érik_Desmazières'
    // optional 'full_json' => [...]
];

$model = MyModel::find(1);
$this->model->updateOrCreateResource($resource);
$model->resources;
...
```

### IdRef record types

IdRef covers persons, corporate bodies, places, families and subject headings in
one index. Which of them a search returns is driven by the endpoint the
component is mounted with:

```php
'idref' => [
    'default_record_types' => ['person', 'corporate'],
    'endpoint_record_types' => [
        'actors'   => ['person', 'corporate', 'family'],
        'places'   => ['place'],
        'keywords' => ['subject'],
    ],
],
```

Publish the config to adjust the mapping for your own endpoint names; an
endpoint with no mapping falls back to `default_record_types`.

Because `idref` declares `wikidata_property: P269` **and** a `target_url`,
`syncFromProvider()` will also create IdRef links automatically whenever a
synced Wikidata item carries a P269 claim. Add `idref` to your filter list to
suppress that.

### Checking provider health

```bash
php artisan resources:test-resources                  # all configured providers
php artisan resources:test-resources --provider=gnd   # just one
```

### Diagnostics routes

The package ships a diagnostics UI at `/resources-check`. It is **disabled by
default** — it renders provider configuration, so it should not be reachable in
a host application unless you ask for it. Enable it per environment:

```dotenv
RESOURCES_DIAGNOSTICS=true
```

and optionally restrict it further in `config/resources.php`:

```php
'diagnostics' => [
    'enabled' => env('RESOURCES_DIAGNOSTICS', false),
    'middleware' => ['web', 'auth'],
],
```

You can add more resources to a model which already has a gnd link. You can configure the list of resources in the config file.

## License

License. Please see the [license file](LICENSE.md) for more information.
