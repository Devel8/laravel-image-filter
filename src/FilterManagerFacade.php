<?php

namespace Devel8\LaravelImageFilter;

use Illuminate\Support\Facades\Facade;

class FilterManagerFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'filter-manager'; }
}