<?php

namespace SimpleSoftwareIO\SqsDisk;

use Laravel\Vapor\Console\Commands\VaporWorkCommand as LaravelVaporWorkCommand;

class VaporWorkCommand extends LaravelVaporWorkCommand
{
    /**
     * Marshal the job with the given message ID.
     *
     * @param  array  $message
     *
     * @return \Laravel\Vapor\Queue\VaporJob
     */
    protected function marshalJob(array $message)
    {
        $normalizedMessage = $this->normalizeMessage($message);

        $queue = $this->worker->getManager()->connection('sqs');

        return new SqsDiskJob(
            $this->laravel,
            $queue->getSqs(),
            $normalizedMessage,
            'sqs',
            $this->queueUrl($message),
            config('queue.connections.sqs.disk_options')
        );
    }
}
