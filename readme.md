# Simple SQS Extended Client
## Introduction


Simple SQS Extended Client is a Laravel queue driver that was designed to work around the AWS SQS 256KB payload size limits.  This queue driver will automatically serialize large payloads to a disk (typically S3) and then unserialize them at run time. 

## Support

You may request professional support by email support@simplesoftware.io.  All requests for support require a $200 / hour fee.  All other support will be provided by the open source community.

## Install

1. First create a disk that will hold all of your large SQS payloads.

> We highly recommend you use a _private_ bucket when storing SQS payloads.  Payloads can contain sensitive information and should never be shared publicly.

2. Run `composer require simplesoftwareio/simple-sqs-extended-client "~1"` to install the queue driver. 

3. Then, add the following default queue settings to your `queue.php` file.

```
  /*
  |--------------------------------------------------------------------------
  | SQS Disk Queue Configuration
  |--------------------------------------------------------------------------
  |
  | Here you may configure the SQS disk queue driver.  It shares all of the same
  | configuration options from the built in Laravel SQS queue driver.  The only added
  | option is `disk_options` which are explained below.
  |
  | always_store: Determines if all payloads should be stored on a disk regardless if they are over SQS's 256KB limit.
  | cleanup:      Determines if the payload files should be removed from the disk once the job is processed. Leaveing the
  |                 files behind can be useful to replay the queue jobs later for debugging reasons.
  | disk:         The disk to save SQS payloads to.  This disk should be configured in your Laravel filesystems.php config file.
  | prefix        The prefix (folder) to store the payloads with.  This is useful if you are sharing a disk with other SQS queues.
  |                 Using a prefix allows for the queue:clear command to destroy the files separately from other sqs-disk backed queues
  |                 sharing the same disk.
  |
  */
  'sqs-disk' => [
      'driver' => 'sqs-disk',
      'key' => env('AWS_ACCESS_KEY_ID'),
      'secret' => env('AWS_SECRET_ACCESS_KEY'),
      'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
      'queue' => env('SQS_QUEUE', 'default'),
      'suffix' => env('SQS_SUFFIX'),
      'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
      'after_commit' => false,
      'disk_options' => [
          'always_store' => false,
          'cleanup' => false,
          'disk' => env('SQS_DISK'),
          'prefix' => 'stage',
      ],
  ],
```

4. Boot up your queues and profit without having to worry about SQS's 256KB limit :)
