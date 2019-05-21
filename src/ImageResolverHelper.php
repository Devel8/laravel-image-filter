<?php

if( !function_exists('image_resolver') )
{

    /**
     * Resolve de image filtered URL from source path image.
     *
     * @param $path
     * @param $filter
     * @return mixed
     */
    function image_resolver($path, $filter)
    {
        return app('filter-manager')->resolve($path, $filter);
    }
}
