<?php

namespace Devel8\LaravelImageFilter;

use Intervention\Image\ImageManager;

class FilterManager {

	/**
	 * @var ImageManager
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
		'path' => [
			'source' => './media/image/',
			'cache' => './media/image/cache/'
		],
		'image' => [
			'driver' => 'gd'
		],
		'cache' => [
			'path' => './../.tmp/cache/',
			'ttl' => null // The image cache never will expire if its null.
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
	 * @return ImageManager
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
	 * Get the filtered image applied path.
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
			$destination_path = $this->saveSourceImage($path);
			return $destination_path;
		});

		return $destination_path;
	}

	/**
	 * Save the source image.
	 *
	 * @param $path
	 *
	 * @return string
	 */
	private function saveSourceImage($path){
		$basename = basename( $path );
		$destination_path = "{$this->config['path']['source']}{$basename}";
		$image_source = $this->make( $path , 15);
		$image_source->save( $destination_path );
		return $destination_path;
	}

	/**
	 * Return the filtered image path.
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
	 * Save the image with de filter applied.
	 *
	 * @param $source_path
	 * @param $filter
	 *
	 * @return string
	 */
	private function saveFilteredImage($source_path, $filter){
		$basename = basename($source_path);
		$image_source = $this->imageManager->make($source_path);

		//Comprobamos si la carpeta del filter existe
		$destination_cache_folder = "{$this->config['path']['cache']}{$filter}";
		if(!$this->filesystem->isDirectory($destination_cache_folder)){
			$this->filesystem->makeDirectory($destination_cache_folder);
		}

		$destination_cache_path = "{$destination_cache_folder}/{$basename}";
		$image_filtered = $this->applyFilter($image_source, $filter);
		$image_filtered->save($destination_cache_path);

		return $destination_cache_path;
	}

	/**
	 * Generate an unique image cache key.
	 *
	 * @param $path
	 *
	 * @return string
	 */
	private function imageCacheKey($path){
		$hash = sha1($path);
		$source_key_cache = 'laraimage.'.$hash;
		return $source_key_cache;
	}


	/**
	 * Apply the image filters.
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
	 * Get the cached image.
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
     * Get an image from URL.
     *
     * @param $path    String Image URL
     * @param $timeout int
     *
     * @return \Intervention\Image\Image
     */
    private function make($path, $timeout) {

        $options = [
            'http' => [
                'method'=>"GET",
                'header'=>"Accept-language: en\r\n".
                    "User-Agent: Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.2 (KHTML, like Gecko) Chrome/22.0.1216.0 Safari/537.2\r\n",
                'timeout'=>$timeout
            ]
        ];

        $context = stream_context_create($options);

        if (!(strpos($path, "http:") === 0)) {
            $path = 'http:'.$path;
        }

        if ($data = @file_get_contents($path, false, $context)) {
            return $this->imageManager->make($data);
        }

        throw new \Intervention\Image\Exception\NotReadableException(
            "Unable to init from given url (".$path.")."
        );
    }
}