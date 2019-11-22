<?php

namespace Devel8\LaravelImageFilter;

use Intervention\Image\ImageManager;

class FilterManager {

    /**
     * @var FilterManagerFacade
     */
    private $imageManager;

    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    private $filesystem;

    /**
     * @var \Illuminate\Cache\Repository
     */
    private $cache;

    /**
     * @var array
     */
    private $config = [
        'storage' => [
            'source' => './media/image/',
            'cache' => './media/image/cache/'
        ],
        'image' => [
            'driver' => 'gd'
        ],
        'cache' => [
            'prefix' => 'media_filter',
            'path' => './../.tmp/cache/',
            'ttl' => null //Tiempo de vida en minutos. Por defecto la cache no tiene ttl por lo tanto nunca expirará.
        ],
        'filters' => []
    ];

    /**
     * Image constructor.
     *
     * @param array $config
     */
    public function __construct(Array $config = []) {
        $this->config = $this->getConfig($config);
        $this->imageManager = $this->getImageManager();
        $this->filesystem = $this->getFileSystem();
        $this->cache = $this->getCache();
    }

    /**
     * @param $config
     *
     * @return array
     */
    private function getConfig($config){
        return array_merge($this->config, $config);
    }

    /**
     * @return FilterManagerFacade
     */
    private function getImageManager(){
        return new ImageManager($this->config['image']);
    }

    /**
     * @return \Illuminate\Filesystem\Filesystem
     */
    private function getFileSystem(){
        return new \Illuminate\Filesystem\Filesystem();
    }

    /**
     * @return \Illuminate\Cache\Repository
     */
    private function getCache(){
        $filesystem = $this->getFileSystem();
        $storage = new \Illuminate\Cache\FileStore($filesystem, $this->config['cache']['path']);
        $cache = new \Illuminate\Cache\Repository($storage);

        return $cache;
    }

    /**
     * Get the URL of the image with de filter applied.
     *
     * @param $path
     * @param $filter
     *
     * @return mixed
     */
    public function resolve($path, $filter){
        $source_path = $this->sourceImagePath($path);
        $filter_path = $this->filteredImagePath($path, $source_path, $filter);
        return $this->getImageCacheUrlFromPath($filter_path);
    }

    /**
     * Get the source image path.
     *
     * @param $path
     *
     * @return mixed
     */
    private function sourceImagePath($path){
        $source_key_cache = $this->imageCacheKey($path);

        $destination_path = $this->getKeyCache($source_key_cache, function () use ($path) {
            $destination_path = $path;
            return $destination_path;
        });

        return $destination_path;
    }

    /**
     * Get the cached image path with the image filter applied.
     *
     * @param $path
     * @param $source_path
     * @param $filter
     *
     * @return mixed
     */
    private function filteredImagePath($path, $source_path, $filter){
        $source_key_cache = $this->imageCacheKey($path);
        $filter_key_cache = "{$source_key_cache}.{$filter}";

        $destination_path = $this->getKeyCache($filter_key_cache, function () use ($source_path, $filter) {
            $destination_path = $this->saveFilteredImage($source_path, $filter);
            return $destination_path;
        });

        return $destination_path;
    }

    /**
     * Save the image with the filter applied.
     *
     * @param $source_path
     * @param $filter
     *
     * @return string
     */
    private function saveFilteredImage($source_path, $filter){
        $basename = basename($source_path);
        $image_source = $this->imageManager->make($source_path);

        // Check if the filter folder exists.
        $destination_cache_folder = public_path()."/{$this->config['storage']['cache']}{$filter}";
        if(!$this->filesystem->isDirectory($destination_cache_folder)){
            $this->filesystem->makeDirectory($destination_cache_folder);
        }

        $destination_cache_path = "{$destination_cache_folder}/{$basename}";
        $image_filtered = $this->applyFilter($image_source, $filter);
        $image_filtered->save($destination_cache_path);

        $url = $this->config['storage']['cache'].$filter.DIRECTORY_SEPARATOR.$basename;

        return $url;
    }

    /**
     * Generates a valid string for cache key.
     * It makes a hash from image path.
     *
     * @param $path
     *
     * @return string
     */
    private function imageCacheKey($path){
        $hash = sha1($path);
        $source_key_cache = $this->config['cache']['prefix'].$hash;
        return $source_key_cache;
    }


    /**
     * Apply the filters from configuration file to the images.
     *
     * @param \Intervention\Image\Image $image
     * @param $filter
     *
     * @return \Intervention\Image\Image
     */
    private function applyFilter(\Intervention\Image\Image $image, $filter){
        $filter = $this->config['filters'][$filter];

        if($filter['type'] == 'fit') {
            $image->fit( $filter['width'], $filter['height'] );
        }else if($filter['type'] == 'crop'){
            $image->crop($filter['width'], $filter['height']);
        }else if($filter['type'] == 'resize'){
            $image->resize($filter['width'], $filter['height'], function ($constraint) {
                //Mantiene el ratio/proporcionalidad de la imagen
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        return $image;
    }

    /**
     * Save the image in cache.
     * Checks if the ttl configuration option is enabled.
     *
     * @param $key
     * @param \Closure $callback
     *
     * @return mixed
     */
    private function getKeyCache($key, \Closure $callback)
    {
        if(!$this->config['cache']['ttl']){
            return $this->cache->rememberForever($key, $callback);
        }else{
            return $this->cache->remember($key, $this->config['cache']['ttl'], $callback);
        }
    }

    /**
     * @param $path
     *
     * @return bool|string
     */
    private function getImageCacheUrlFromPath($path){
        if(substr($path, 0, 1) == '.') return substr($path, 1);
        return $path;
    }

    /**
     * Gets the source image from filesystem path or URL.
     *
     * @param $path    String Ruta de la imagen
     * @param $timeout int Tiempo de timeout para obtener la imagen
     *
     * @return \Intervention\Image\Image
     */
    private function make($path, $timeout) {

        $options = [
            'http' => [
                'method' => "GET",
                'header' => "Accept-language: en\r\n".
                    "User-Agent: Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.2 (KHTML, like Gecko) Chrome/22.0.1216.0 Safari/537.2\r\n",
                'timeout' => $timeout
            ]
        ];

        $context = stream_context_create($options);

        if ($data = @file_get_contents($path, false, $context)) {
            return $this->imageManager->make($data);
        }

        throw new \Intervention\Image\Exception\NotReadableException(
            "Unable to init from given url (".$path.")."
        );
    }
}