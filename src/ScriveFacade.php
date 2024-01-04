<?php

namespace KalnaLab\Scrive;

use Illuminate\Support\Facades\Facade;

class ScriveFacade extends Facade
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
