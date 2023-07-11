<?php

namespace SimpleSoftwareIO\SqsDisk;

use Aws\Sqs\SqsClient;
use Illuminate\Support\Arr;
use Illuminate\Queue\Connectors\SqsConnector;
use Illuminate\Queue\Connectors\ConnectorInterface;

class SqsDiskConnector extends SqsConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        $config = $this->getDefaultConfiguration($config);

        if (! empty($config['key']) && ! empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return new SqsDiskQueue(
            new SqsClient(
                Arr::except($config, ['token'])
            ),
            $config['queue'],
            $config['disk_options'],
            $config['prefix'] ?? '',
            $config['suffix'] ?? '',
            $config['after_commit'] ?? null,
        );
    }
}
