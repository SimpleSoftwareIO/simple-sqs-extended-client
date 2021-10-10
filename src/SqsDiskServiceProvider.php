<?php

namespace SimpleSoftwareIO\SqsDisk;

use Illuminate\Support\ServiceProvider;

class SqsDiskServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function boot()
    {
        $manager = $this->app->make('queue');
        $manager->addConnector('sqs-disk', fn () => new SqsDiskConnector());

        $this->app->extend('command.vapor.work', fn () => new VaporWorkCommand($this->app['queue.vaporWorker']));
    }
}
