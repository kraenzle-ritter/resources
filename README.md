# resources

[![Latest Stable Version](https://poser.pugx.org/kraenzle-ritter/resources/v)](//packagist.org/packages/kraenzle-ritter/resources) 
[![Total Downloads](https://poser.pugx.org/kraenzle-ritter/resources/downloads)](//packagist.org/packages/kraenzle-ritter/resources) 
[![Latest Unstable Version](https://poser.pugx.org/kraenzle-ritter/resources/v/unstable)](//packagist.org/packages/kraenzle-ritter/resources) 
[![License](https://poser.pugx.org/kraenzle-ritter/resources/license)](//packagist.org/packages/kraenzle-ritter/resources)
[![Tests](https://github.com/kraenzle-ritter/resources/actions/workflows/run-tests.yml/badge.svg)](https://github.com/kraenzle-ritter/resources/actions/workflows/run-tests.yml)

Resource Model and a hasResource trait where resources are basically links to a resources (eg. Wikipedia-Article or GND-Entry).

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

With the artisan-command

```bash
php artisan resources:fetch --provider=gnd // or wikidata or wikipedia
```

You can add more resources to a model which already has a gnd link. You can configure the list of resources in the config file.

## License

License. Please see the [license file](LICENSE.md) for more information.

<!--
[ico-version]: https://img.shields.io/packagist/v/kraenzle-ritter/resources.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/kraenzle-ritter/resources.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/kraenzle-ritter/resources/master.svg?style=flat-square
[ico-styleci]: https://styleci.io/repos/12345678/shield

[link-packagist]: https://packagist.org/packages/kraenzle-ritter/resources
[link-downloads]: https://packagist.org/packages/kraenzle-ritter/resources
[link-travis]: https://travis-ci.org/kraenzle-ritter/resources
[link-styleci]: https://styleci.io/repos/12345678
[link-author]: https://github.com/kraenzle-ritter
[link-contributors]: ../../contributors-->
