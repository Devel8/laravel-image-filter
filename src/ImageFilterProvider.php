<?php

namespace Devel8\LaravelImageFilter;

use Illuminate\Support\ServiceProvider;

class ImageFilterProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/image-filter.php' => config_path('image-filter.php'),
        ], 'config');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/image-filter.php', 'image-filter'
        );

        $this->app->singleton('filter-manager', function(){
            $config = config('image-filter');
            return new FilterManager($config);
        });

        //require_once(__DIR__ . '/ImagesResolverHelper.php');
    }
}
