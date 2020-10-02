# resources

[![Software License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.md)

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
