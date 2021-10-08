<?php

namespace SimpleSoftwareIO\SqsDisk;

use Aws\Sqs\SqsClient;
use Illuminate\Support\Arr;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;

class SqsDiskJob extends SqsJob implements JobContract
{
    use ResolvesPointers;

    /**
     * The Amazon SQS client instance.
     *
     * @var \Aws\Sqs\SqsClient
     */
    protected $sqs;

    /**
     * The Amazon SQS job instance.
     *
     * @var array
     */
    protected $job;

    /**
     * Holds the raw body to prevent fetching the file from
     * the disk multiple times.
     *
     * @var string
     */
    protected $cachedRawBody;

    /**
     * The disk options for the job.
     *
     * @var array
     */
    protected $diskOptions;

    /**
     * Create a new job instance.
     *
     * @param  \Illuminate\Container\Container  $container
     * @param  \Aws\Sqs\SqsClient  $sqs
     * @param  array  $job
     * @param  string  $connectionName
     * @param  string  $queue
     *
     * @return void
     */
    public function __construct(Container $container, SqsClient $sqs, array $job, $connectionName, $queue, array $diskOptions)
    {
        $this->sqs = $sqs;
        $this->job = $job;
        $this->queue = $queue;
        $this->container = $container;
        $this->connectionName = $connectionName;
        $this->diskOptions = $diskOptions;
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        if (Arr::get($this->diskOptions, 'cleanup') && $pointer = $this->resolvePointer()) {
            $this->resolveDisk()->delete($pointer);
        }
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        if ($this->cachedRawBody) {
            return $this->cachedRawBody;
        }

        if ($pointer = $this->resolvePointer()) {
            return $this->cachedRawBody = $this->resolveDisk()->get($pointer);
        }

        return parent::getRawBody();
    }
}
