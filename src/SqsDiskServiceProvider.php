<?php

namespace SimpleSoftwareIO\SqsDisk;

use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;

class SqsDiskServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register()
    {
        $manager = Container::getInstance()->make('queue');
        $manager->addConnector('sqs-disk', fn () => new SqsDiskConnector());
    }
}
