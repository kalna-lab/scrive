<?php

namespace KalnaLab\Scrive\Facades;

use Illuminate\Support\Facades\Facade;

class Scrive extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'scrive';
    }
}
