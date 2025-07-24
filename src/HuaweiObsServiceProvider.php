<?php

namespace Back\LaravelObs;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class HuaweiObsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        Storage::extend('obs', function ($app, $config) {
            $adapter = new HuaweiObsAdapter(
                $config['key'],
                $config['secret'],
                $config['endpoint'],
                $config['bucket'],
                $config['ssl_verify'] ?? false,
                $config['cdn_domain'],
                $config['options']
            );
            return new Filesystem($adapter);
        });
    }

    /**
     * Register the application services.
     */
    public function register()
    {

    }
}
