<?php

namespace Back\LaravelObs;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Back\LaravelObs\Plugins\ObsClient;

class HuaweiObsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        Storage::extend('obs', function ($app, $config) {
            $obsClient = new ObsClient([
                'key' => $config['key'],
                'secret' => $config['secret'],
                'endpoint' => $config['endpoint'],
                'ssl_verify' => $config['ssl_verify'] ?? false,
                'max_retry_count' => $config['max_retry_count'] ?? 3,
                'socket_timeout' => $config['socket_timeout'] ?? 60,
                'connect_timeout' => $config['connect_timeout'] ?? 60,
                'chunk_size' => $config['chunk_size'] ?? 65536,
            ]);
            $adapter = new HuaweiObsAdapter($obsClient, $config['bucket']);
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
