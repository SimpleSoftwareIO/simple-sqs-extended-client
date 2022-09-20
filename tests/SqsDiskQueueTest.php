<?php

namespace SimpleSoftwareIO\Tests\SqsDisk;

use Mockery;
use Aws\Result;
use Aws\Sqs\SqsClient;
use PHPUnit\Framework\TestCase;
use Illuminate\Container\Container;
use SimpleSoftwareIO\SqsDisk\SqsDiskJob;
use SimpleSoftwareIO\SqsDisk\SqsDiskQueue;
use Illuminate\Filesystem\FilesystemAdapter;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class SqsDiskQueueTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function setUp(): void
    {
        $this->mockedMessageId = 'e3cd03ee-59a3-4ad8-b0aa-ee2e3808ac81';
        $this->mockedPayload = json_encode(['job' => 'foo', 'data' => ['data'], 'uuid' => $this->mockedMessageId]);
        $this->mockedPointerPayload = json_encode(['pointer' => 'prefix/e3cd03ee-59a3-4ad8-b0aa-ee2e3808ac81.json']);
        $this->mockedLargePayload = json_encode(['job' => 'foo', 'data' => [base64_encode(random_bytes(262144))], 'uuid' => $this->mockedMessageId]);
        $this->mockedReceiptHandle = '0NNAq8PwvXuWv5gMtS9DJ8qEdyiUwbAjpp45w2m6M4SJ1Y+PxCh7R930NRB8ylSacEmoSnW18bgd4nK\/O6ctE+VFVul4eD23mA07vVoSnPI4F\/voI1eNCp6Iax0ktGmhlNVzBwaZHEr91BRtqTRM3QKd2ASF8u+IQaSwyl\/DGK+P1+dqUOodvOVtExJwdyDLy1glZVgm85Yw9Jf5yZEEErqRwzYz\/qSigdvW4sm2l7e4phRol\/+IjMtovOyH\/ukueYdlVbQ4OshQLENhUKe7RNN5i6bE\/e5x9bnPhfj2gbM';

        $this->mockedJobData = [
            'Body' => $this->mockedPayload,
            'MD5OfBody' => md5($this->mockedPayload),
            'ReceiptHandle' => $this->mockedReceiptHandle,
            'MessageId' => $this->mockedMessageId,
            'Attributes' => ['ApproximateReceiveCount' => 1],
        ];

        $this->mockedSqsClient = Mockery::mock(SqsClient::class);
        $this->mockedFilesystemAdapter = Mockery::mock(FilesystemAdapter::class);
        $this->mockedContainer = Mockery::mock(Container::class)->makePartial();
    }

    public function testItDoesntPushToADiskIfTheAlwaysStoreIsDisabledAndThePayloadIsntLarge()
    {
        $this->mockedFilesystemAdapter->shouldReceive('disk')
            ->never();

        $this->mockedSqsClient->shouldReceive('sendMessage')
            ->with([
                'QueueUrl' => '/default',
                'MessageBody' => $this->mockedPayload,
            ])
            ->once()
            ->andReturnSelf();

        $this->mockedSqsClient->shouldReceive('get')
            ->once();

        $diskOptions = [
            'always_store' => false,
            'cleanup' => true,
            'disk' => 's3',
            'prefix' => 'prefix',
        ];

        $sqsDiskQueue = new SqsDiskQueue($this->mockedSqsClient, 'default', $diskOptions);
        $sqsDiskQueue->pushRaw($this->mockedPayload);
    }

    public function testItPushesLargePayloadsToADisk()
    {
        $this->mockedFilesystemAdapter->shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();

        $this->mockedFilesystemAdapter->shouldReceive('put')
            ->with('prefix/e3cd03ee-59a3-4ad8-b0aa-ee2e3808ac81.json', $this->mockedLargePayload)
            ->once();

        $this->mockedContainer->shouldReceive('make')
            ->with('filesystem')
            ->andReturn($this->mockedFilesystemAdapter);

        $this->mockedSqsClient->shouldReceive('sendMessage')
            ->with([
                'QueueUrl' => '/default',
                'MessageBody' => $this->mockedPointerPayload,
            ])
            ->once()
            ->andReturnSelf();

        $this->mockedSqsClient->shouldReceive('get')
            ->once();

        $diskOptions = [
            'always_store' => false,
            'cleanup' => true,
            'disk' => 's3',
            'prefix' => 'prefix',
        ];

        $sqsDiskQueue = new SqsDiskQueue($this->mockedSqsClient, 'default', $diskOptions);
        $sqsDiskQueue->setContainer($this->mockedContainer);
        $sqsDiskQueue->pushRaw($this->mockedLargePayload);
    }

    public function testItAlwaysPushesPayloadsToADiskIfAlwaysPushIsEnabled()
    {
        $this->mockedFilesystemAdapter->shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();

        $this->mockedFilesystemAdapter->shouldReceive('put')
            ->with('prefix/e3cd03ee-59a3-4ad8-b0aa-ee2e3808ac81.json', $this->mockedPayload)
            ->once();

        $this->mockedContainer->shouldReceive('make')
            ->with('filesystem')
            ->andReturn($this->mockedFilesystemAdapter);

        $this->mockedSqsClient->shouldReceive('sendMessage')
            ->with([
                'QueueUrl' => '/default',
                'MessageBody' => $this->mockedPointerPayload,
            ])
            ->once()
            ->andReturnSelf();

        $this->mockedSqsClient->shouldReceive('get')
            ->once();

        $diskOptions = [
            'always_store' => true,
            'cleanup' => true,
            'disk' => 's3',
            'prefix' => 'prefix',
        ];

        $sqsDiskQueue = new SqsDiskQueue($this->mockedSqsClient, 'default', $diskOptions);
        $sqsDiskQueue->setContainer($this->mockedContainer);
        $sqsDiskQueue->pushRaw($this->mockedPayload);
    }

    public function testItCanDelayAJobWhenPushedToDisk()
    {
        $this->mockedFilesystemAdapter->shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();

        $this->mockedFilesystemAdapter->shouldReceive('put')
            ->once();

        $this->mockedContainer->shouldReceive('make')
            ->with('filesystem')
            ->andReturn($this->mockedFilesystemAdapter);

        $this->mockedSqsClient->shouldReceive('sendMessage')
            ->with(Mockery::on(function ($arguments) {
                return $arguments['DelaySeconds'] === 10;
            }))
            ->once()
            ->andReturnSelf();

        $this->mockedSqsClient->shouldReceive('get')
            ->once();

        $diskOptions = [
            'always_store' => true,
            'cleanup' => true,
            'disk' => 's3',
            'prefix' => 'prefix',
        ];

        $sqsDiskQueue = new SqsDiskQueue($this->mockedSqsClient, 'default', $diskOptions);
        $sqsDiskQueue->setContainer($this->mockedContainer);
        $sqsDiskQueue->later(10, 'foo');
    }

    public function testItCanDelayAJobWhenNotPushedToDisk()
    {
        $this->mockedFilesystemAdapter->shouldReceive('disk')
            ->never();

        $this->mockedSqsClient->shouldReceive('sendMessage')
            ->with(Mockery::on(function ($arguments) {
                return $arguments['DelaySeconds'] === 10;
            }))
            ->once()
            ->andReturnSelf();

        $this->mockedSqsClient->shouldReceive('get')
            ->once();

        $diskOptions = [
            'always_store' => false,
            'cleanup' => true,
            'disk' => 's3',
            'prefix' => 'prefix',
        ];

        $sqsDiskQueue = new SqsDiskQueue($this->mockedSqsClient, 'default', $diskOptions);
        $sqsDiskQueue->setContainer($this->mockedContainer);
        $sqsDiskQueue->later(10, 'foo');
    }

    public function testItCreatesANewSqsDiskJobWhenPopped()
    {
        $this->mockedSqsClient->shouldReceive('receiveMessage')
            ->once()
            ->andReturn([
                'Messages' => [
                    0 => [
                        'Body' => $this->mockedPayload,
                        'MD5OfBody' => md5($this->mockedPayload),
                        'ReceiptHandle' => $this->mockedReceiptHandle,
                        'MessageId' => $this->mockedMessageId,
                    ],
                ],
            ]);

        $diskOptions = [
            'always_store' => true,
            'cleanup' => true,
            'disk' => 's3',
            'prefix' => 'prefix',
        ];

        $sqsDiskQueue = new SqsDiskQueue($this->mockedSqsClient, 'default', $diskOptions);
        $sqsDiskQueue->setContainer($this->mockedContainer);
        $job = $sqsDiskQueue->pop();

        $this->assertInstanceOf(SqsDiskJob::class, $job);
    }

    public function testItClearsTheDiskWhenQueueisCleared()
    {
        $this->mockedFilesystemAdapter->shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();

        $this->mockedFilesystemAdapter->shouldReceive('deleteDirectory')
            ->with('prefix')
            ->once();

        $this->mockedContainer->shouldReceive('make')
            ->with('filesystem')
            ->andReturn($this->mockedFilesystemAdapter);

        $this->mockedSqsClient->shouldReceive('getQueueAttributes')
            ->andReturn(new Result([
                'Attributes' => [
                    'ApproximateNumberOfMessages' => 1,
                ],
            ]));

        $this->mockedSqsClient->shouldReceive('purgeQueue');

        $diskOptions = [
            'always_store' => true,
            'cleanup' => true,
            'disk' => 's3',
            'prefix' => 'prefix',
        ];

        $sqsDiskQueue = new SqsDiskQueue($this->mockedSqsClient, 'default', $diskOptions);
        $sqsDiskQueue->setContainer($this->mockedContainer);
        $sqsDiskQueue->clear('default');
    }
}
