<?php

namespace Back\LaravelObs;

use Illuminate\Support\Facades\Facade;

class HuaweiObsFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravel-obs';
    }
}
