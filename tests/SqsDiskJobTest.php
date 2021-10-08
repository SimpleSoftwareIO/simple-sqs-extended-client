<?php

namespace SimpleSoftwareIO\Tests\SqsDisk;

use Mockery;
use Aws\Sqs\SqsClient;
use PHPUnit\Framework\TestCase;
use SimpleSoftwareIO\SqsDisk\SqsDiskJob;
use Illuminate\Filesystem\FilesystemAdapter;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class SqsDiskJobTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function setUp(): void
    {
        $this->mockedPayload = json_encode(['pointer' => 'prefix/e3cd03ee-59a3-4ad8-b0aa-ee2e3808ac81.json']);
        $this->mockedMessageId = 'e3cd03ee-59a3-4ad8-b0aa-ee2e3808ac81';
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
        $this->mockedContainer = Mockery::mock('Illuminate\Container\Container');
    }

    public function testItRemovesTheJobFromTheDiskIfCleanupIsEnabled()
    {
        $this->mockedFilesystemAdapter->shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();

        $this->mockedFilesystemAdapter->shouldReceive('delete')
            ->with('prefix/e3cd03ee-59a3-4ad8-b0aa-ee2e3808ac81.json')
            ->andReturnSelf();

        $this->mockedContainer->shouldReceive('make')
            ->with('filesystem')
            ->andReturn($this->mockedFilesystemAdapter);

        $this->mockedSqsClient->shouldReceive('deleteMessage');

        $diskOptions = [
            'always_store' => true,
            'cleanup' => true,
            'disk' => 's3',
            'prefix' => 'prefix',
        ];

        $sqsDiskJob = new SqsDiskJob(
            $this->mockedContainer,
            $this->mockedSqsClient,
            $this->mockedJobData,
            'connection',
            'queue',
            $diskOptions
        );

        $sqsDiskJob->delete();
    }

    public function testItLeavesTheJobOnTheDiskIfCleanupIsDisabled()
    {
        $this->mockedContainer->shouldReceive('make')
            ->with('filesystem')
            ->never();

        $this->mockedSqsClient->shouldReceive('deleteMessage')
            ->once();

        $diskOptions = [
            'always_store' => true,
            'cleanup' => false,
            'disk' => 's3',
            'prefix' => 'prefix',
        ];

        $sqsDiskJob = new SqsDiskJob(
            $this->mockedContainer,
            $this->mockedSqsClient,
            $this->mockedJobData,
            'connection',
            'queue',
            $diskOptions
        );

        $sqsDiskJob->delete();
    }

    public function testItReturnsTheRawBodyFromTheDiskIfAPointerExists()
    {
        $jobData = json_encode([
            'job' => 'job',
            'data' => ['data'],
            'attempts' => 1,
        ]);

        $this->mockedFilesystemAdapter->shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();

        $this->mockedFilesystemAdapter->shouldReceive('get')
            ->with('prefix/e3cd03ee-59a3-4ad8-b0aa-ee2e3808ac81.json')
            ->once()
            ->andReturn($jobData);

        $this->mockedContainer->shouldReceive('make')
            ->with('filesystem')
            ->andReturn($this->mockedFilesystemAdapter);

        $diskOptions = [
            'always_store' => true,
            'cleanup' => true,
            'disk' => 's3',
            'prefix' => 'prefix',
        ];

        $sqsDiskJob = new SqsDiskJob(
            $this->mockedContainer,
            $this->mockedSqsClient,
            $this->mockedJobData,
            'connection',
            'queue',
            $diskOptions
        );

        $this->assertEquals($jobData, $sqsDiskJob->getRawBody());
    }
}
