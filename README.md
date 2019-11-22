# Laravel Image Filter

A simple cache library and image formatting in `PHP`

- [Requirements](#requirements)
- [Supported image libraries](#)
- [Installation](#installation)
- [How to use](#)
- [Configuration parameters](#)
    - [Filtration parameters](#)
    - [Filter types](#)
- [Methods](#methods)
- [Dependencies](#dependencies)

### Requirements

- PHP >=5.4
- Fileinfo Extension

### Supported image libraries

- GD Library (>=2.0)
- Imagick PHP extension (>=6.5.7)

### Installation

Execute the following command to get the latest version of the package:

```
composer require devel8/laravel-image-filter
```

In your `config/app.php` add `Devel8\LaravelImageFilter\ImageFilterProvider::class` to the end of the `providers` array:

```php
'providers' => [
    ...
    Devel8\LaravelImageFilter\ImageFilterProvider::class,
],
```

If Lumen

```php
$app->register(Devel8\LaravelImageFilter\ImageFilterProvider::class);
```

Publish Configuration

```shell
php artisan vendor:publish --provider "Devel8\LaravelImageFilter\ImageFilterProvider"
```

### How to use

```php
$imageFilteredUrl = $imageManager->resolve( '/source/image/path/file.jpg', 'image_small' );
```

### Configuration parameters

| Parameter | Type | Value | Description |
| ------ | ------ | ------ | ------ |
| path.source | String |   | Path where the original image is stored |
| path.cache | String |  | Path where the image is stored with the filter applied |
| image.driver | String | **gd** o **imagick** | Driver that manipulates the images |
| cache.path | String |  | Path where the files of cache manager will be stored |
| cache.ttl | Integer |  | Life time of the cache in minutes. By default the cache does not have ttl therefore it will never expire. |
| filters | Array | [Filtration parameters](#) | Set the filters with the formats that are applied to the images |

### Filtration parameters

| Parámetro | Tipo | Valor | Descripción |
| ------ | ------ | ------ | ------ |
| width | Integer |   | Image width |
| height | Integer |  | Image height |
| type | String | [Filter types](#) | Type of filter for the image |

### Tipo de filtros

| Tipo | Descripción |
| ------ | ------ |
| resize | Change the size of the current image according to a width and height |
| crop | Crop a part of the current image with width and height |
| fit | Combine cropping and resizing to format the image in an intelligent way. You will automatically find the most suitable aspect ratio of your width and height determined in the current image, cutting out and changing its size to the given dimension. |

### Methods

- [resolve($path, $filter)](#resolve)

## Resolve

Returns the path of the image with the filter applied.

| Parameter | Description |
| ------ | ------ |
| path | Path of the original image that you want to apply the filter |
| filter | Nombre del filtro que se le ha indicado en el parámetro de configuración `filters` |

```php
$imageFilteredUrl = $imageManager->resolve( '/source/image/path/file.jpg', 'image_small' );
```

### Dependencies

- [Intervention Image (>=2.4)](https://github.com/Intervention/image)
- [Illuminate Cache (>=5.0.*)](https://github.com/illuminate/cache)
- [Illuminate Filesystem (>=5.0.*)](https://github.com/illuminate/filesystem)