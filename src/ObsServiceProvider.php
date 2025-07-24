<?php

namespace Back\Obs;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class ObsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        Storage::extend('obs', function ($app, $config) {

            $client = new ObsClient($config);

            $bucket = $config['bucket'] ?? null;
            $endpoint = $config['endpoint'] ?? null;
            $ssl = $config['ssl'] ?? null;
            $cdnDomain = $config['cdnDomain'] ?? null;

            return new Filesystem(new ObsAdapter($client, $bucket, $endpoint, $cdnDomain, $ssl));
        });
    }

    /**
     * Register the application services.
     */
    public function register()
    {

    }
}
