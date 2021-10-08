<?php

namespace SimpleSoftwareIO\SqsDisk;

use Illuminate\Support\ServiceProvider;

class SqsDiskServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register()
    {
        $manager = $this->app->make('queue');
        $manager->addConnector('sqs-disk', fn () => new SqsDiskConnector());
    }
}
