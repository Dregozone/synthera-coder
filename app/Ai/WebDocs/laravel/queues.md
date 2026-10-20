---
source: laravel
page: queues
url: https://laravel.com/framework/docs/13.x/queues
fetched_at: 2026-10-20T16:27:49+00:00
---

# Queues

- Introduction
   - Connections vs. Queues
   - Driver Notes and Prerequisites
- Creating Jobs
   - Generating Job Classes
   - Class Structure
   - Unique Jobs
   - Debounced Jobs
   - Encrypted Jobs
- Job Middleware
   - Rate Limiting
   - Preventing Job Overlaps
   - Throttling Exceptions
   - Releasing Jobs
   - Skipping Jobs
- Dispatching Jobs
   - Delayed Dispatching
   - Synchronous Dispatching
   - Bulk Dispatching
   - Preparing Jobs Before Dispatch
   - Jobs & Database Transactions
   - Job Chaining
   - Customizing The Queue and Connection
   - Specifying Max Job Attempts / Timeout Values
   - SQS FIFO and Fair Queues
   - Queue Failover
   - Error Handling
- Job Batching
   - Defining Batchable Jobs
   - Dispatching Batches
   - Chains and Batches
   - Adding Jobs to Batches
   - Inspecting Batches
   - Cancelling Batches
   - Batch Failures
   - Pruning Batches
   - Storing Batches in DynamoDB
- Queueing Closures
- Running the Queue Worker
   - The `queue:work` Command
   - Queue Priorities
   - Queue Workers and Deployment
   - Reacting to Worker Signals
   - Job Expirations and Timeouts
   - Pausing and Resuming Queue Workers
- Supervisor Configuration
- Dealing With Failed Jobs
   - Cleaning Up After Failed Jobs
   - Retrying Failed Jobs
   - Ignoring Missing Models
   - Pruning Failed Jobs
   - Storing Failed Jobs in DynamoDB
   - Disabling Failed Job Storage
   - Failed Job Events
- Clearing Jobs From Queues
- Monitoring Your Queues
- Testing
   - Faking a Subset of Jobs
   - Testing Job Chains
   - Testing Job Batches
   - Testing Job / Queue Interactions
- Job Events

## Introduction

While building your web application, you may have some tasks, such as parsing and storing an uploaded CSV file, that take too long to perform during a typical web request. Thankfully, Laravel allows you to easily create queued jobs that may be processed in the background. By moving time intensive tasks to a queue, your application can respond to web requests with blazing speed and provide a better user experience to your customers.

Laravel queues provide a unified queueing API across a variety of different queue backends, such as [Amazon SQS](https://aws.amazon.com/sqs/), [Redis](https://redis.io), or even a relational database.

Laravel's queue configuration options are stored in your application's `config/queue.php` configuration file. In this file, you will find connection configurations for each of the queue drivers that are included with the framework, including the database, [Amazon SQS](https://aws.amazon.com/sqs/), [Redis](https://redis.io), and [Beanstalkd](https://beanstalkd.github.io/) drivers, as well as a synchronous driver that will execute jobs immediately (for use during development or testing). A `null` queue driver is also included which discards queued jobs.

 Laravel Horizon is a beautiful dashboard and configuration system for your Redis powered queues. Check out the full [Horizon documentation](https://laravel.com/framework/docs/horizon) for more information.

### Connections vs. Queues

Before getting started with Laravel queues, it is important to understand the distinction between "connections" and "queues". In your `config/queue.php` configuration file, there is a `connections` configuration array. This option defines the connections to backend queue services such as Amazon SQS, Beanstalk, or Redis. However, any given queue connection may have multiple "queues" which may be thought of as different stacks or piles of queued jobs.

Note that each connection configuration example in the `queue` configuration file contains a `queue` attribute. This is the default queue that jobs will be dispatched to when they are sent to a given connection. In other words, if you dispatch a job without explicitly defining which queue it should be dispatched to, the job will be placed on the queue that is defined in the `queue` attribute of the connection configuration:

```
1use App\Jobs\ProcessPodcast;
2 
3// This job is sent to the default connection's default queue...
4ProcessPodcast::dispatch();
5 
6// This job is sent to the default connection's "emails" queue...
7ProcessPodcast::dispatch()->onQueue('emails');

use App\Jobs\ProcessPodcast;

// This job is sent to the default connection's default queue...
ProcessPodcast::dispatch();

// This job is sent to the default connection's "emails" queue...
ProcessPodcast::dispatch()->onQueue('emails');
```

Some applications may not need to ever push jobs onto multiple queues, instead preferring to have one simple queue. However, pushing jobs to multiple queues can be especially useful for applications that wish to prioritize or segment how jobs are processed, since the Laravel queue worker allows you to specify which queues it should process by priority. For example, if you push jobs to a `high` queue, you may run a worker that gives them higher processing priority:

```
1php artisan queue:work --queue=high,default

php artisan queue:work --queue=high,default
```

### Driver Notes and Prerequisites

#### Database

In order to use the `database` queue driver, you will need a database table to hold the jobs. Typically, this is included in Laravel's default `0001_01_01_000002_create_jobs_table.php` [database migration](https://laravel.com/framework/docs/migrations); however, if your application does not contain this migration, you may use the `make:queue-table` Artisan command to create it:

```
1php artisan make:queue-table
2 
3php artisan migrate

php artisan make:queue-table

php artisan migrate
```

#### Redis

In order to use the `redis` queue driver, you should configure a Redis database connection in your `config/database.php` configuration file.

 The `serializer` and `compression` Redis options are not supported by the `redis` queue driver.

##### Redis Cluster

If your Redis queue connection uses a [Redis Cluster](https://redis.io/docs/latest/operate/rs/databases/durability-ha/clustering), your queue names must contain a [key hash tag](https://redis.io/docs/latest/develop/using-commands/keyspace/#hashtags). This is required in order to ensure all of the Redis keys for a given queue are placed into the same hash slot:

```
1'redis' => [
2    'driver' => 'redis',
3    'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
4    'queue' => env('REDIS_QUEUE', '{default}'),
5    'retry_after' => env('REDIS_QUEUE_RETRY_AFTER', 90),
6    'block_for' => null,
7    'after_commit' => false,
8],

'redis' => [
    'driver' => 'redis',
    'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
    'queue' => env('REDIS_QUEUE', '{default}'),
    'retry_after' => env('REDIS_QUEUE_RETRY_AFTER', 90),
    'block_for' => null,
    'after_commit' => false,
],
```

##### Blocking

When using the Redis queue, you may use the `block_for` configuration option to specify how long the driver should wait for a job to become available before iterating through the worker loop and re-polling the Redis database.

Adjusting this value based on your queue load can be more efficient than continually polling the Redis database for new jobs. For instance, you may set the value to `5` to indicate that the driver should block for five seconds while waiting for a job to become available:

```
1'redis' => [
2    'driver' => 'redis',
3    'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
4    'queue' => env('REDIS_QUEUE', 'default'),
5    'retry_after' => env('REDIS_QUEUE_RETRY_AFTER', 90),
6    'block_for' => 5,
7    'after_commit' => false,
8],

'redis' => [
    'driver' => 'redis',
    'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => env('REDIS_QUEUE_RETRY_AFTER', 90),
    'block_for' => 5,
    'after_commit' => false,
],
```

 Setting `block_for` to `0` will cause queue workers to block indefinitely until a job is available. This will also prevent signals such as `SIGTERM` from being handled until the next job has been processed.

#### SQS Overflow Storage

Amazon SQS limits the maximum size of a queued message payload. If you need to dispatch jobs with payloads that may exceed this limit, you may configure Laravel to store oversized SQS payloads in a cache store and send a pointer through SQS instead. To enable this feature, add an `overflow` array to your SQS queue connection configuration:

```
 1'sqs' => [
 2    'driver' => 'sqs',
 3    'key' => env('AWS_ACCESS_KEY_ID'),
 4    'secret' => env('AWS_SECRET_ACCESS_KEY'),
 5    'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
 6    'queue' => env('SQS_QUEUE', 'default'),
 7    'suffix' => env('SQS_SUFFIX'),
 8    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
 9    'after_commit' => false,
10    'overflow' => [
11        'enabled' => env('SQS_OVERFLOW_ENABLED', false),
12        'store' => env('SQS_OVERFLOW_STORE'),
13        'always' => false,
14        'delete_after_processing' => true,
15        'flush_on_clear' => env('SQS_OVERFLOW_FLUSH_ON_CLEAR', false),
16    ],
17],

'sqs' => [
    'driver' => 'sqs',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
    'queue' => env('SQS_QUEUE', 'default'),
    'suffix' => env('SQS_SUFFIX'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'after_commit' => false,
    'overflow' => [
        'enabled' => env('SQS_OVERFLOW_ENABLED', false),
        'store' => env('SQS_OVERFLOW_STORE'),
        'always' => false,
        'delete_after_processing' => true,
        'flush_on_clear' => env('SQS_OVERFLOW_FLUSH_ON_CLEAR', false),
    ],
],
```

When overflow storage is enabled, Laravel will store payloads that are at least 1 MB in the configured cache store. If the `always` option is `true`, every SQS payload will be stored in the cache store regardless of its size. Since queued jobs will need to retrieve their payloads from the cache store when they are processed, you should choose a store that can retain the payloads until your workers process them. By default, stored payloads are deleted after their jobs have been successfully processed and deleted from SQS.

If the `flush_on_clear` option is `true`, the configured overflow cache store will be flushed when the `queue:clear` command clears the SQS queue. Since flushing a cache store may remove all items from that store, you should configure SQS overflow storage to use a dedicated cache store when enabling this option.

#### Other Driver Prerequisites

The following dependencies are needed for the listed queue drivers. These dependencies may be installed via the Composer package manager:

- Amazon SQS: `aws/aws-sdk-php ~3.0`
- Beanstalkd: `pda/pheanstalk ^7.0|^8.0`
- Redis: `predis/predis ~3.0` or phpredis PHP extension
- [MongoDB](https://www.mongodb.com/docs/drivers/php/laravel-mongodb/current/queues/): `mongodb/laravel-mongodb`

## Creating Jobs

### Generating Job Classes

By default, all of the queueable jobs for your application are stored in the `app/Jobs` directory. If the `app/Jobs` directory doesn't exist, it will be created when you run the `make:job` Artisan command:

```
1php artisan make:job ProcessPodcast

php artisan make:job ProcessPodcast
```

The generated class will implement the `Illuminate\Contracts\Queue\ShouldQueue` interface, indicating to Laravel that the job should be pushed onto the queue to run asynchronously.

 Job stubs may be customized using [stub publishing](https://laravel.com/framework/docs/artisan#stub-customization).

### Class Structure

Job classes are very simple, normally containing only a `handle` method that is invoked when the job is processed by the queue. To get started, let's take a look at an example job class. In this example, we'll pretend we manage a podcast publishing service and need to process the uploaded podcast files before they are published:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use App\Models\Podcast;
 6use App\Services\AudioProcessor;
 7use Illuminate\Contracts\Queue\ShouldQueue;
 8use Illuminate\Foundation\Queue\Queueable;
 9 
10class ProcessPodcast implements ShouldQueue
11{
12    use Queueable;
13 
14    /**
15     * Create a new job instance.
16     */
17    public function __construct(
18        public Podcast $podcast,
19    ) {}
20 
21    /**
22     * Execute the job.
23     */
24    public function handle(AudioProcessor $processor): void
25    {
26        // Process uploaded podcast...
27    }
28}

<?php

namespace App\Jobs;

use App\Models\Podcast;
use App\Services\AudioProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPodcast implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Podcast $podcast,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AudioProcessor $processor): void
    {
        // Process uploaded podcast...
    }
}
```

In this example, note that we were able to pass an [Eloquent model](https://laravel.com/framework/docs/eloquent) directly into the queued job's constructor. Because of the `Queueable` trait that the job is using, Eloquent models and their loaded relationships will be gracefully serialized and unserialized when the job is processing.

If your queued job accepts an Eloquent model in its constructor, only the identifier for the model will be serialized onto the queue. When the job is actually handled, the queue system will automatically re-retrieve the full model instance and its loaded relationships from the database. This approach to model serialization allows for much smaller job payloads to be sent to your queue driver.

#### `handle` Method Dependency Injection

The `handle` method is invoked when the job is processed by the queue. Note that we are able to type-hint dependencies on the `handle` method of the job. The Laravel [service container](https://laravel.com/framework/docs/container) automatically injects these dependencies.

If you would like to take total control over how the container injects dependencies into the `handle` method, you may use the container's `bindMethod` method. The `bindMethod` method accepts a callback which receives the job and the container. Within the callback, you are free to invoke the `handle` method however you wish. Typically, you should call this method from the `boot` method of your `App\Providers\AppServiceProvider` [service provider](https://laravel.com/framework/docs/providers):

```
1use App\Jobs\ProcessPodcast;
2use App\Services\AudioProcessor;
3use Illuminate\Contracts\Foundation\Application;
4 
5$this->app->bindMethod([ProcessPodcast::class, 'handle'], function (ProcessPodcast $job, Application $app) {
6    return $job->handle($app->make(AudioProcessor::class));
7});

use App\Jobs\ProcessPodcast;
use App\Services\AudioProcessor;
use Illuminate\Contracts\Foundation\Application;

$this->app->bindMethod([ProcessPodcast::class, 'handle'], function (ProcessPodcast $job, Application $app) {
    return $job->handle($app->make(AudioProcessor::class));
});
```

 Binary data, such as raw image contents, should be passed through the `base64_encode` function before being passed to a queued job. Otherwise, the job may not properly serialize to JSON when being placed on the queue.

#### Queued Relationships

Because all loaded Eloquent model relationships also get serialized when a job is queued, the serialized job string can sometimes become quite large. Furthermore, when a job is deserialized and model relationships are re-retrieved from the database, they will be retrieved in their entirety. Any previous relationship constraints that were applied before the model was serialized during the job queueing process will not be applied when the job is deserialized. Therefore, if you wish to work with a subset of a given relationship, you should re-constrain that relationship within your queued job.

Or, to prevent relations from being serialized, you can call the `withoutRelations` method on the model when setting a property value. This method will return an instance of the model without its loaded relationships:

```
1/**
2 * Create a new job instance.
3 */
4public function __construct(
5    Podcast $podcast,
6) {
7    $this->podcast = $podcast->withoutRelations();
8}

/**
 * Create a new job instance.
 */
public function __construct(
    Podcast $podcast,
) {
    $this->podcast = $podcast->withoutRelations();
}
```

If you only need to remove specific relations while keeping the rest, you may use the `withoutRelation` method:

```
1$this->podcast = $podcast->withoutRelation('comments');

$this->podcast = $podcast->withoutRelation('comments');
```

If you are using [PHP constructor property promotion](https://www.php.net/manual/en/language.oop5.decon.php#language.oop5.decon.constructor.promotion) and would like to indicate that an Eloquent model should not have its relations serialized, you may use the `WithoutRelations` attribute:

```
1use Illuminate\Queue\Attributes\WithoutRelations;
2 
3/**
4 * Create a new job instance.
5 */
6public function __construct(
7    #[WithoutRelations]
8    public Podcast $podcast,
9) {}

use Illuminate\Queue\Attributes\WithoutRelations;

/**
 * Create a new job instance.
 */
public function __construct(
    #[WithoutRelations]
    public Podcast $podcast,
) {}
```

For convenience, if you wish to serialize all models without relationships, you may apply the `WithoutRelations` attribute to the entire class instead of applying the attribute to each model:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use App\Models\DistributionPlatform;
 6use App\Models\Podcast;
 7use Illuminate\Contracts\Queue\ShouldQueue;
 8use Illuminate\Foundation\Queue\Queueable;
 9use Illuminate\Queue\Attributes\WithoutRelations;
10 
11#[WithoutRelations]
12class ProcessPodcast implements ShouldQueue
13{
14    use Queueable;
15 
16    /**
17     * Create a new job instance.
18     */
19    public function __construct(
20        public Podcast $podcast,
21        public DistributionPlatform $platform,
22    ) {}
23}

<?php

namespace App\Jobs;

use App\Models\DistributionPlatform;
use App\Models\Podcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;

#[WithoutRelations]
class ProcessPodcast implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Podcast $podcast,
        public DistributionPlatform $platform,
    ) {}
}
```

If a job receives a collection or array of Eloquent models instead of a single model, the models within that collection will not have their relationships restored when the job is deserialized and executed. This is to prevent excessive resource usage on jobs that deal with large numbers of models.

### Unique Jobs

 Unique jobs require a cache driver that supports [locks](https://laravel.com/framework/docs/cache#atomic-locks). Currently, the `memcached`, `redis`, `dynamodb`, `database`, `file`, and `array` cache drivers support atomic locks.

 Unique job constraints do not apply to jobs within batches.

Sometimes, you may want to ensure that only one instance of a specific job is on the queue at any point in time. You may do so by implementing the `ShouldBeUnique` interface on your job class. This interface does not require you to define any additional methods on your class:

```
1<?php
2 
3use Illuminate\Contracts\Queue\ShouldQueue;
4use Illuminate\Contracts\Queue\ShouldBeUnique;
5 
6class UpdateSearchIndex implements ShouldQueue, ShouldBeUnique
7{
8    // ...
9}

<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class UpdateSearchIndex implements ShouldQueue, ShouldBeUnique
{
    // ...
}
```

In the example above, the `UpdateSearchIndex` job is unique. So, the job will not be dispatched if another instance of the job is already on the queue and has not finished processing.

In certain cases, you may want to define a specific "key" that makes the job unique or you may want to specify a timeout beyond which the job no longer stays unique. To accomplish this, you may use the `UniqueFor` attribute and define a `uniqueId` method on your job class:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use Illuminate\Contracts\Queue\ShouldQueue;
 6use Illuminate\Contracts\Queue\ShouldBeUnique;
 7use Illuminate\Queue\Attributes\UniqueFor;
 8 
 9#[UniqueFor(3600)]
10class UpdateSearchIndex implements ShouldQueue, ShouldBeUnique
11{
12    /**
13     * The product instance.
14     *
15     * @var \App\Models\Product
16     */
17    public $product;
18 
19    /**
20     * Get the unique ID for the job.
21     */
22    public function uniqueId(): string
23    {
24        return $this->product->id;
25    }
26}

<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Attributes\UniqueFor;

#[UniqueFor(3600)]
class UpdateSearchIndex implements ShouldQueue, ShouldBeUnique
{
    /**
     * The product instance.
     *
     * @var \App\Models\Product
     */
    public $product;

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->product->id;
    }
}
```

In the example above, the `UpdateSearchIndex` job is unique by a product ID. So, any new dispatches of the job with the same product ID will be ignored until the existing job has completed processing. In addition, if the existing job is not processed within one hour, the unique lock will be released and another job with the same unique key can be dispatched to the queue.

 If your application dispatches jobs from multiple web servers or containers, you should ensure that all of your servers are communicating with the same central cache server so that Laravel can accurately determine if a job is unique.

#### Keeping Jobs Unique Until Processing Begins

By default, unique jobs are "unlocked" after a job completes processing or fails all of its retry attempts. However, there may be situations where you would like your job to unlock immediately before it is processed. To accomplish this, your job should implement the `ShouldBeUniqueUntilProcessing` contract instead of the `ShouldBeUnique` contract:

```
1<?php
2 
3use Illuminate\Contracts\Queue\ShouldQueue;
4use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
5 
6class UpdateSearchIndex implements ShouldQueue, ShouldBeUniqueUntilProcessing
7{
8    // ...
9}

<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

class UpdateSearchIndex implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    // ...
}
```

#### Unique Job Locks

Behind the scenes, when a `ShouldBeUnique` job is dispatched, Laravel attempts to acquire a [lock](https://laravel.com/framework/docs/cache#atomic-locks) with the `uniqueId` key. If the lock is already held, the job is not dispatched. This lock is released when the job completes processing or fails all of its retry attempts. By default, Laravel will use the default cache driver to obtain this lock. However, if you wish to use another driver for acquiring the lock, you may define a `uniqueVia` method that returns the cache driver that should be used:

```
 1use Illuminate\Contracts\Cache\Repository;
 2use Illuminate\Support\Facades\Cache;
 3 
 4class UpdateSearchIndex implements ShouldQueue, ShouldBeUnique
 5{
 6    // ...
 7 
 8    /**
 9     * Get the cache driver for the unique job lock.
10     */
11    public function uniqueVia(): Repository
12    {
13        return Cache::driver('redis');
14    }
15}

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class UpdateSearchIndex implements ShouldQueue, ShouldBeUnique
{
    // ...

    /**
     * Get the cache driver for the unique job lock.
     */
    public function uniqueVia(): Repository
    {
        return Cache::driver('redis');
    }
}
```

 If you only need to limit the concurrent processing of a job, use the [WithoutOverlapping](https://laravel.com/framework/docs/queues#preventing-job-overlaps) job middleware instead.

### Debounced Jobs

Sometimes, you may want to ensure that when the same job is dispatched many times in a short window, only the latest dispatch actually executes. You may do so by adding the `DebounceFor` attribute to your job:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use Illuminate\Contracts\Queue\ShouldQueue;
 6use Illuminate\Foundation\Queue\Queueable;
 7use Illuminate\Queue\Attributes\DebounceFor;
 8 
 9#[DebounceFor(30)]
10class UpdateSearchIndex implements ShouldQueue
11{
12    use Queueable;
13 
14    /**
15     * Create a new job instance.
16     */
17    public function __construct(public int $productId)
18    {
19    }
20 
21    /**
22     * Get the debounce ID for the job.
23     */
24    public function debounceId(): string
25    {
26        return (string) $this->productId;
27    }
28}

<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DebounceFor;

#[DebounceFor(30)]
class UpdateSearchIndex implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $productId)
    {
    }

    /**
     * Get the debounce ID for the job.
     */
    public function debounceId(): string
    {
        return (string) $this->productId;
    }
}
```

In the example above, repeatedly dispatching `UpdateSearchIndex` for the same product within `30` seconds will debounce the job so that only the latest dispatch runs.

If you would like to cap how long a frequently re-dispatched job can be deferred, you may provide the `maxWait` argument to the `DebounceFor` attribute:

```
1#[DebounceFor(30, maxWait: 120)]
2class UpdateSearchIndex implements ShouldQueue
3{
4    use Queueable;
5 
6    // ...
7}

#[DebounceFor(30, maxWait: 120)]
class UpdateSearchIndex implements ShouldQueue
{
    use Queueable;

    // ...
}
```

You may customize the cache store used for debounce tracking by defining a `debounceVia` method on your job:

```
1use Illuminate\Contracts\Cache\Repository;
2use Illuminate\Support\Facades\Cache;
3 
4public function debounceVia(): Repository
5{
6    return Cache::driver('redis');
7}

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

public function debounceVia(): Repository
{
    return Cache::driver('redis');
}
```

If a debounced job is superseded by a newer dispatch, Laravel will dispatch the `Illuminate\Queue\Events\JobDebounced` event and remove the superseded job from the queue.

 Debounced jobs and unique jobs are mutually exclusive. A job using the `DebounceFor` attribute should not implement `ShouldBeUnique`.

 If your application dispatches debounced jobs from multiple web servers or containers, you should ensure that all of your servers are communicating with the same central cache server.

### Encrypted Jobs

Laravel allows you to ensure the privacy and integrity of a job's data via [encryption](https://laravel.com/framework/docs/encryption). To get started, simply add the `ShouldBeEncrypted` interface to the job class. Once this interface has been added to the class, Laravel will automatically encrypt your job before pushing it onto a queue:

```
1<?php
2 
3use Illuminate\Contracts\Queue\ShouldBeEncrypted;
4use Illuminate\Contracts\Queue\ShouldQueue;
5 
6class UpdateSearchIndex implements ShouldQueue, ShouldBeEncrypted
7{
8    // ...
9}

<?php

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateSearchIndex implements ShouldQueue, ShouldBeEncrypted
{
    // ...
}
```

## Job Middleware

Job middleware allow you to wrap custom logic around the execution of queued jobs, reducing boilerplate in the jobs themselves. For example, consider the following `handle` method which leverages Laravel's Redis rate limiting features to allow only one job to process every five seconds:

```
 1use Illuminate\Support\Facades\Redis;
 2 
 3/**
 4 * Execute the job.
 5 */
 6public function handle(): void
 7{
 8    Redis::throttle('key')->block(0)->allow(1)->every(5)->then(function () {
 9        info('Lock obtained...');
10 
11        // Handle job...
12    }, function () {
13        // Could not obtain lock...
14 
15        return $this->release(5);
16    });
17}

use Illuminate\Support\Facades\Redis;

/**
 * Execute the job.
 */
public function handle(): void
{
    Redis::throttle('key')->block(0)->allow(1)->every(5)->then(function () {
        info('Lock obtained...');

        // Handle job...
    }, function () {
        // Could not obtain lock...

        return $this->release(5);
    });
}
```

While this code is valid, the implementation of the `handle` method becomes noisy since it is cluttered with Redis rate limiting logic. In addition, this rate limiting logic must be duplicated for any other jobs that we want to rate limit. Instead of rate limiting in the handle method, we could define a job middleware that handles rate limiting:

```
 1<?php
 2 
 3namespace App\Jobs\Middleware;
 4 
 5use Closure;
 6use Illuminate\Support\Facades\Redis;
 7 
 8class RateLimited
 9{
10    /**
11     * Process the queued job.
12     *
13     * @param  \Closure(object): void  $next
14     */
15    public function handle(object $job, Closure $next): void
16    {
17        Redis::throttle('key')
18            ->block(0)->allow(1)->every(5)
19            ->then(function () use ($job, $next) {
20                // Lock obtained...
21 
22                $next($job);
23            }, function () use ($job) {
24                // Could not obtain lock...
25 
26                $job->release(5);
27            });
28    }
29}

<?php

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\Redis;

class RateLimited
{
    /**
     * Process the queued job.
     *
     * @param  \Closure(object): void  $next
     */
    public function handle(object $job, Closure $next): void
    {
        Redis::throttle('key')
            ->block(0)->allow(1)->every(5)
            ->then(function () use ($job, $next) {
                // Lock obtained...

                $next($job);
            }, function () use ($job) {
                // Could not obtain lock...

                $job->release(5);
            });
    }
}
```

As you can see, like [route middleware](https://laravel.com/framework/docs/middleware), job middleware receive the job being processed and a callback that should be invoked to continue processing the job.

You can generate a new job middleware class using the `make:job-middleware` Artisan command. After creating job middleware, they may be attached to a job by returning them from the job's `middleware` method. This method does not exist on jobs scaffolded by the `make:job` Artisan command, so you will need to manually add it to your job class:

```
 1use App\Jobs\Middleware\RateLimited;
 2 
 3/**
 4 * Get the middleware the job should pass through.
 5 *
 6 * @return array<int, object>
 7 */
 8public function middleware(): array
 9{
10    return [new RateLimited];
11}

use App\Jobs\Middleware\RateLimited;

/**
 * Get the middleware the job should pass through.
 *
 * @return array<int, object>
 */
public function middleware(): array
{
    return [new RateLimited];
}
```

 Job middleware can also be assigned to [queueable event listeners](https://laravel.com/framework/docs/events#queued-event-listeners), [mailables](https://laravel.com/framework/docs/mail#queueing-mail), and [notifications](https://laravel.com/framework/docs/notifications#queueing-notifications).

### Rate Limiting

Although we just demonstrated how to write your own rate limiting job middleware, Laravel actually includes a rate limiting middleware that you may utilize to rate limit jobs. Like [route rate limiters](https://laravel.com/framework/docs/routing#defining-rate-limiters), job rate limiters are defined using the `RateLimiter` facade's `for` method.

For example, you may wish to allow users to backup their data once per hour while imposing no such limit on premium customers. To accomplish this, you may define a `RateLimiter` in the `boot` method of your `AppServiceProvider`:

```
 1use Illuminate\Cache\RateLimiting\Limit;
 2use Illuminate\Support\Facades\RateLimiter;
 3 
 4/**
 5 * Bootstrap any application services.
 6 */
 7public function boot(): void
 8{
 9    RateLimiter::for('backups', function (object $job) {
10        return $job->user->vipCustomer()
11            ? Limit::none()
12            : Limit::perHour(1)->by($job->user->id);
13    });
14}

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    RateLimiter::for('backups', function (object $job) {
        return $job->user->vipCustomer()
            ? Limit::none()
            : Limit::perHour(1)->by($job->user->id);
    });
}
```

In the example above, we defined an hourly rate limit; however, you may easily define a rate limit based on minutes using the `perMinute` method. In addition, you may pass any value you wish to the `by` method of the rate limit; however, this value is most often used to segment rate limits by customer:

```
1return Limit::perMinute(50)->by($job->user->id);

return Limit::perMinute(50)->by($job->user->id);
```

Once you have defined your rate limit, you may attach the rate limiter to your job using the `Illuminate\Queue\Middleware\RateLimited` middleware. Each time the job exceeds the rate limit, this middleware will release the job back to the queue with an appropriate delay based on the rate limit duration:

```
 1use Illuminate\Queue\Middleware\RateLimited;
 2 
 3/**
 4 * Get the middleware the job should pass through.
 5 *
 6 * @return array<int, object>
 7 */
 8public function middleware(): array
 9{
10    return [new RateLimited('backups')];
11}

use Illuminate\Queue\Middleware\RateLimited;

/**
 * Get the middleware the job should pass through.
 *
 * @return array<int, object>
 */
public function middleware(): array
{
    return [new RateLimited('backups')];
}
```

Releasing a rate limited job back onto the queue will still increment the job's total number of `attempts`. You may wish to tune your `Tries` and `MaxExceptions` attributes on your job class accordingly. Or, you may wish to use the retryUntil method to define the amount of time until the job should no longer be attempted.

Using the `releaseAfter` method, you may also specify the number of seconds that must elapse before the released job will be attempted again:

```
1/**
2 * Get the middleware the job should pass through.
3 *
4 * @return array<int, object>
5 */
6public function middleware(): array
7{
8    return [(new RateLimited('backups'))->releaseAfter(60)];
9}

/**
 * Get the middleware the job should pass through.
 *
 * @return array<int, object>
 */
public function middleware(): array
{
    return [(new RateLimited('backups'))->releaseAfter(60)];
}
```

If you do not want a job to be retried when it is rate limited, you may use the `dontRelease` method:

```
1/**
2 * Get the middleware the job should pass through.
3 *
4 * @return array<int, object>
5 */
6public function middleware(): array
7{
8    return [(new RateLimited('backups'))->dontRelease()];
9}

/**
 * Get the middleware the job should pass through.
 *
 * @return array<int, object>
 */
public function middleware(): array
{
    return [(new RateLimited('backups'))->dontRelease()];
}
```

#### Rate Limiting With Redis

If you are using Redis, you may use the `Illuminate\Queue\Middleware\RateLimitedWithRedis` middleware, which is fine-tuned for Redis and more efficient than the basic rate limiting middleware:

```
1use Illuminate\Queue\Middleware\RateLimitedWithRedis;
2 
3public function middleware(): array
4{
5    return [new RateLimitedWithRedis('backups')];
6}

use Illuminate\Queue\Middleware\RateLimitedWithRedis;

public function middleware(): array
{
    return [new RateLimitedWithRedis('backups')];
}
```

The `connection` method may be used to specify which Redis connection the middleware should use:

```
1return [(new RateLimitedWithRedis('backups'))->connection('limiter')];

return [(new RateLimitedWithRedis('backups'))->connection('limiter')];
```

### Preventing Job Overlaps

Laravel includes an `Illuminate\Queue\Middleware\WithoutOverlapping` middleware that allows you to prevent job overlaps based on an arbitrary key. This can be helpful when a queued job is modifying a resource that should only be modified by one job at a time.

For example, let's imagine you have a queued job that updates a user's credit score and you want to prevent credit score update job overlaps for the same user ID. To accomplish this, you can return the `WithoutOverlapping` middleware from your job's `middleware` method:

```
 1use Illuminate\Queue\Middleware\WithoutOverlapping;
 2 
 3/**
 4 * Get the middleware the job should pass through.
 5 *
 6 * @return array<int, object>
 7 */
 8public function middleware(): array
 9{
10    return [new WithoutOverlapping($this->user->id)];
11}

use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Get the middleware the job should pass through.
 *
 * @return array<int, object>
 */
public function middleware(): array
{
    return [new WithoutOverlapping($this->user->id)];
}
```

Releasing an overlapping job back onto the queue will still increment the job's total number of attempts. You may wish to tune your `Tries` and `MaxExceptions` attributes on your job class accordingly. For example, leaving `Tries` to 1 as it is by default would prevent any overlapping job from being retried later.

Any overlapping jobs of the same type will be released back to the queue. You may also specify the number of seconds that must elapse before the released job will be attempted again:

```
1/**
2 * Get the middleware the job should pass through.
3 *
4 * @return array<int, object>
5 */
6public function middleware(): array
7{
8    return [(new WithoutOverlapping($this->order->id))->releaseAfter(60)];
9}

/**
 * Get the middleware the job should pass through.
 *
 * @return array<int, object>
 */
public function middleware(): array
{
    return [(new WithoutOverlapping($this->order->id))->releaseAfter(60)];
}
```

If you wish to immediately delete any overlapping jobs so that they will not be retried, you may use the `dontRelease` method:

```
1/**
2 * Get the middleware the job should pass through.
3 *
4 * @return array<int, object>
5 */
6public function middleware(): array
7{
8    return [(new WithoutOverlapping($this->order->id))->dontRelease()];
9}

/**
 * Get the middleware the job should pass through.
 *
 * @return array<int, object>
 */
public function middleware(): array
{
    return [(new WithoutOverlapping($this->order->id))->dontRelease()];
}
```

The `WithoutOverlapping` middleware is powered by Laravel's atomic lock feature. Sometimes, your job may unexpectedly fail or timeout in such a way that the lock is not released. Therefore, you may explicitly define a lock expiration time using the `expireAfter` method. For example, the example below will instruct Laravel to release the `WithoutOverlapping` lock three minutes after the job has started processing:

```
1/**
2 * Get the middleware the job should pass through.
3 *
4 * @return array<int, object>
5 */
6public function middleware(): array
7{
8    return [(new WithoutOverlapping($this->order->id))->expireAfter(180)];
9}

/**
 * Get the middleware the job should pass through.
 *
 * @return array<int, object>
 */
public function middleware(): array
{
    return [(new WithoutOverlapping($this->order->id))->expireAfter(180)];
}
```

 The `WithoutOverlapping` middleware requires a cache driver that supports [locks](https://laravel.com/framework/docs/cache#atomic-locks). Currently, the `memcached`, `redis`, `dynamodb`, `database`, `file`, and `array` cache drivers support atomic locks.

#### Sharing Lock Keys Across Job Classes

By default, the `WithoutOverlapping` middleware will only prevent overlapping jobs of the same class. So, although two different job classes may use the same lock key, they will not be prevented from overlapping. However, you can instruct Laravel to apply the key across job classes using the `shared` method:

```
 1use Illuminate\Queue\Middleware\WithoutOverlapping;
 2 
 3class ProviderIsDown
 4{
 5    // ...
 6 
 7    public function middleware(): array
 8    {
 9        return [
10            (new WithoutOverlapping("status:{$this->provider}"))->shared(),
11        ];
12    }
13}
14 
15class ProviderIsUp
16{
17    // ...
18 
19    public function middleware(): array
20    {
21        return [
22            (new WithoutOverlapping("status:{$this->provider}"))->shared(),
23        ];
24    }
25}

use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProviderIsDown
{
    // ...

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("status:{$this->provider}"))->shared(),
        ];
    }
}

class ProviderIsUp
{
    // ...

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("status:{$this->provider}"))->shared(),
        ];
    }
}
```

### Throttling Exceptions

Laravel includes a `Illuminate\Queue\Middleware\ThrottlesExceptions` middleware that allows you to throttle exceptions. Once the job throws a given number of exceptions, all further attempts to execute the job are delayed until a specified time interval lapses. This middleware is particularly useful for jobs that interact with third-party services that are unstable.

For example, let's imagine a queued job that interacts with a third-party API that begins throwing exceptions. To throttle exceptions, you can return the `ThrottlesExceptions` middleware from your job's `middleware` method. Typically, this middleware should be paired with a job that implements time based attempts:

```
 1use DateTime;
 2use Illuminate\Queue\Middleware\ThrottlesExceptions;
 3 
 4/**
 5 * Get the middleware the job should pass through.
 6 *
 7 * @return array<int, object>
 8 */
 9public function middleware(): array
10{
11    return [new ThrottlesExceptions(10, 5 * 60)];
12}
13 
14/**
15 * Determine the time at which the job should timeout.
16 */
17public function retryUntil(): DateTime
18{
19    return now()->plus(minutes: 30);
20}

use DateTime;
use Illuminate\Queue\Middleware\ThrottlesExceptions;

/**
 * Get the middleware the job should pass through.
 *
 * @return array<int, object>
 */
public function middleware(): array
{
    return [new ThrottlesExceptions(10, 5 * 60)];
}

/**
 * Determine the time at which the job should timeout.
 */
public function retryUntil(): DateTime
{
    return now()->plus(minutes: 30);
}
```

The first constructor argument accepted by the middleware is the number of exceptions the job can throw before being throttled, while the second constructor argument is the number of seconds that should elapse before the job is attempted again once it has been throttled. In the code example above, if the job throws 10 consecutive exceptions, we will wait 5 minutes before attempting the job again, constrained by the 30-minute time limit.

When a job throws an exception but the exception threshold has not yet been reached, the job will typically be retried immediately. However, you may specify the number of minutes such a job should be delayed by calling the `backoff` method when attaching the middleware to the job:

```
 1use Illuminate\Queue\Middleware\ThrottlesExceptions;
 2 
 3/**
 4 * Get the middleware the job should pass through.
 5 *
 6 * @return array<int, object>
 7 */
 8public function middleware(): array
 9{
10    return [(new ThrottlesExceptions(10, 5 * 60))->backoff(5)];
11}

use Illuminate\Queue\Middleware\ThrottlesExceptions;

/**
 * Get the middleware the job should pass through.
 *
 * @return array<int, object>
 */
public function middleware(): array
{
    return [(new ThrottlesExceptions(10, 5 * 60))->backoff(5)];
}
```

The `backoff` method also accepts a closure that receives the thrown exception, allowing the delay to be determined dynamically:

```
 1use App\Exceptions\RateLimitedException;
 2use Illuminate\Queue\Middleware\ThrottlesExceptions;
 3use Throwable;
 4 
 5/**
 6 * Get the middleware the job should pass through.
 7 *
 8 * @return array<int, object>
 9 */
10public function middleware(): array
11{
12    return [(new ThrottlesExceptions(10, 5 * 60))->backoff(
13        fn (Throwable $throwable) => $throwable instanceof RateLimitedException
14            ? $throwable->retryAfterMinutes()
15            : 5
16    )];
17}

use App\Exceptions\RateLimitedException;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Throwable;

/**
 * Get the middleware the job should pass through.
 *
 * @return array<int, object>
 */
public function middleware(): array
{
    return [(new ThrottlesExceptions(10, 5 * 60))->backoff(
        fn (Throwable $throwable) => $throwable instanceof RateLimitedException
            ? $throwable->retryAfterMinutes()
            : 5
    )];
}
```

Internally, this middleware uses Laravel's cache system to implement rate limiting, and the job's class name is utilized as the cache "key". You may override this key by calling the `by` method when attaching the middleware to your job. This may be useful if you have multiple jobs interacting with the same third-party service and you would like them to share a common throttling "bucket" ensuring they respect a single shared limit:

```
 1use Illuminate\Queue\Middleware\ThrottlesExceptions;
 2 
 3/**
 4 * Get the middleware the job should pass through.
 5 *
 6 * @return array<int, object>
 7 */
 8public function middleware(): array
 9{
10    return [(new ThrottlesExceptions(10, 10 * 60))->by('key')];
11}

use Illuminate\Queue\Middleware\ThrottlesExceptions;

/**
 * Get the middleware the job should pass through.
 *
 * @return array<int, object>
 */
public function middleware(): array
{
    return [(new ThrottlesExceptions(10, 10 * 60))->by('key')];
}
```

By default, this middleware will throttle every exception. You can modify this behavior by invoking the `when` method when attaching the middleware to your job. The exception will then only be throttled if the closure provided to the `when` method returns `true`:

```
 1use Illuminate\Http\Client\HttpClientException;
 2use Illuminate\Queue\Middleware\ThrottlesExceptions;
 3 
 4/**
 5 * Get the middleware the job should pass through.
 6 *
 7 * @return array<int, object>
 8 */
 9public function middleware(): array
10{
11    return [(new ThrottlesExceptions(10, 10 * 60))->when(
12        fn (Throwable $throwable) => $throwable instanceof HttpClientException
13    )];
14}

use Illuminate\Http\Client\HttpClientException;
use Illuminate\Queue\Middleware\ThrottlesExceptions;

/**
 * Get the middleware the job should pass through.
 *
 * @return array<int, object>
 */
public function middleware(): array
{
    return [(new ThrottlesExceptions(10, 10 * 60))->when(
        fn (Throwable $throwable) => $throwable instanceof HttpClientException
    )];
}
```

Unlike the `when` method, which releases the job back onto the queue or throws an exception, the `deleteWhen` method allows you to delete the job entirely when a given exception occurs:

```
 1use App\Exceptions\CustomerDeletedException;
 2use Illuminate\Queue\Middleware\ThrottlesExceptions;
 3 
 4/**
 5 * Get the middleware the job should pass through.
 6 *
 7 * @return array<int, object>
 8 */
 9public function middleware(): array
10{
11    return [(new ThrottlesExceptions(2, 10 * 60))->deleteWhen(CustomerDeletedException::class)];
12}

use App\Exceptions\CustomerDeletedException;
use Illuminate\Queue\Middleware\ThrottlesExceptions;

/**
 * Get the middleware the job should pass through.
 *
 * @return array<int, object>
 */
public function middleware(): array
{
    return [(new ThrottlesExceptions(2, 10 * 60))->deleteWhen(CustomerDeletedException::class)];
}
```

If you would like to have the throttled exceptions reported to your application's exception handler, you can do so by invoking the `report` method when attaching the middleware to your job. Optionally, you may provide a closure to the `report` method and the exception will only be reported if the given closure returns `true`:

```
 1use Illuminate\Http\Client\HttpClientException;
 2use Illuminate\Queue\Middleware\ThrottlesExceptions;
 3 
 4/**
 5 * Get the middleware the job should pass through.
 6 *
 7 * @return array<int, object>
 8 */
 9public function middleware(): array
10{
11    return [(new ThrottlesExceptions(10, 10 * 60))->report(
12        fn (Throwable $throwable) => $throwable instanceof HttpClientException
13    )];
14}

use Illuminate\Http\Client\HttpClientException;
use Illuminate\Queue\Middleware\ThrottlesExceptions;

/**
 * Get the middleware the job should pass through.
 *
 * @return array<int, object>
 */
public function middleware(): array
{
    return [(new ThrottlesExceptions(10, 10 * 60))->report(
        fn (Throwable $throwable) => $throwable instanceof HttpClientException
    )];
}
```

#### Throttling Exceptions With Redis

If you are using Redis, you may use the `Illuminate\Queue\Middleware\ThrottlesExceptionsWithRedis` middleware, which is fine-tuned for Redis and more efficient than the basic exception throttling middleware:

```
1use Illuminate\Queue\Middleware\ThrottlesExceptionsWithRedis;
2 
3public function middleware(): array
4{
5    return [new ThrottlesExceptionsWithRedis(10, 10 * 60)];
6}

use Illuminate\Queue\Middleware\ThrottlesExceptionsWithRedis;

public function middleware(): array
{
    return [new ThrottlesExceptionsWithRedis(10, 10 * 60)];
}
```

The `connection` method may be used to specify which Redis connection the middleware should use:

```
1return [(new ThrottlesExceptionsWithRedis(10, 10 * 60))->connection('limiter')];

return [(new ThrottlesExceptionsWithRedis(10, 10 * 60))->connection('limiter')];
```

### Releasing Jobs

The `Release` middleware allows you to release a job back onto the queue without executing it. The `Release::when` method will release the job if the given condition evaluates to `true`, while the `Release::unless` method will release the job if the condition evaluates to `false`:

```
 1use Illuminate\Queue\Middleware\Release;
 2 
 3/**
 4 * Get the middleware the job should pass through.
 5 */
 6public function middleware(): array
 7{
 8    return [
 9        Release::when($condition, releaseAfter: 60),
10    ];
11}

use Illuminate\Queue\Middleware\Release;

/**
 * Get the middleware the job should pass through.
 */
public function middleware(): array
{
    return [
        Release::when($condition, releaseAfter: 60),
    ];
}
```

Releasing a job back onto the queue will still increment the job's total number of attempts. You may wish to tune your `Tries` and `MaxExceptions` attributes on your job class accordingly.

You can also pass a `Closure` to the `when` and `unless` methods for more complex conditional evaluation:

```
 1use Illuminate\Queue\Middleware\Release;
 2 
 3/**
 4 * Get the middleware the job should pass through.
 5 */
 6public function middleware(): array
 7{
 8    return [
 9        Release::when(function (): bool {
10            return ! $this->order->isPaid();
11        }, releaseAfter: 60),
12    ];
13}

use Illuminate\Queue\Middleware\Release;

/**
 * Get the middleware the job should pass through.
 */
public function middleware(): array
{
    return [
        Release::when(function (): bool {
            return ! $this->order->isPaid();
        }, releaseAfter: 60),
    ];
}
```

### Skipping Jobs

The `Skip` middleware allows you to specify that a job should be skipped / deleted without needing to modify the job's logic. The `Skip::when` method will delete the job if the given condition evaluates to `true`, while the `Skip::unless` method will delete the job if the condition evaluates to `false`:

```
 1use Illuminate\Queue\Middleware\Skip;
 2 
 3/**
 4 * Get the middleware the job should pass through.
 5 */
 6public function middleware(): array
 7{
 8    return [
 9        Skip::when($condition),
10    ];
11}

use Illuminate\Queue\Middleware\Skip;

/**
 * Get the middleware the job should pass through.
 */
public function middleware(): array
{
    return [
        Skip::when($condition),
    ];
}
```

You can also pass a `Closure` to the `when` and `unless` methods for more complex conditional evaluation:

```
 1use Illuminate\Queue\Middleware\Skip;
 2 
 3/**
 4 * Get the middleware the job should pass through.
 5 */
 6public function middleware(): array
 7{
 8    return [
 9        Skip::when(function (): bool {
10            return $this->shouldSkip();
11        }),
12    ];
13}

use Illuminate\Queue\Middleware\Skip;

/**
 * Get the middleware the job should pass through.
 */
public function middleware(): array
{
    return [
        Skip::when(function (): bool {
            return $this->shouldSkip();
        }),
    ];
}
```

## Dispatching Jobs

Once you have written your job class, you may dispatch it using the `dispatch` method on the job itself. The arguments passed to the `dispatch` method will be given to the job's constructor:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use App\Jobs\ProcessPodcast;
 6use App\Models\Podcast;
 7use Illuminate\Http\RedirectResponse;
 8use Illuminate\Http\Request;
 9 
10class PodcastController extends Controller
11{
12    /**
13     * Store a new podcast.
14     */
15    public function store(Request $request): RedirectResponse
16    {
17        $podcast = Podcast::create(/* ... */);
18 
19        // ...
20 
21        ProcessPodcast::dispatch($podcast);
22 
23        return redirect('/podcasts');
24    }
25}

<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPodcast;
use App\Models\Podcast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PodcastController extends Controller
{
    /**
     * Store a new podcast.
     */
    public function store(Request $request): RedirectResponse
    {
        $podcast = Podcast::create(/* ... */);

        // ...

        ProcessPodcast::dispatch($podcast);

        return redirect('/podcasts');
    }
}
```

If you would like to conditionally dispatch a job, you may use the `dispatchIf` and `dispatchUnless` methods:

```
1ProcessPodcast::dispatchIf($accountActive, $podcast);
2 
3ProcessPodcast::dispatchUnless($accountSuspended, $podcast);

ProcessPodcast::dispatchIf($accountActive, $podcast);

ProcessPodcast::dispatchUnless($accountSuspended, $podcast);
```

In new Laravel applications, the `database` connection is defined as the default queue. You may specify a different default queue connection by changing the `QUEUE_CONNECTION` environment variable in your application's `.env` file.

### Delayed Dispatching

If you would like to specify that a job should not be immediately available for processing by a queue worker, you may use the `delay` method when dispatching the job. For example, let's specify that a job should not be available for processing until 10 minutes after it has been dispatched:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use App\Jobs\ProcessPodcast;
 6use App\Models\Podcast;
 7use Illuminate\Http\RedirectResponse;
 8use Illuminate\Http\Request;
 9 
10class PodcastController extends Controller
11{
12    /**
13     * Store a new podcast.
14     */
15    public function store(Request $request): RedirectResponse
16    {
17        $podcast = Podcast::create(/* ... */);
18 
19        // ...
20 
21        ProcessPodcast::dispatch($podcast)
22            ->delay(now()->plus(minutes: 10));
23 
24        return redirect('/podcasts');
25    }
26}

<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPodcast;
use App\Models\Podcast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PodcastController extends Controller
{
    /**
     * Store a new podcast.
     */
    public function store(Request $request): RedirectResponse
    {
        $podcast = Podcast::create(/* ... */);

        // ...

        ProcessPodcast::dispatch($podcast)
            ->delay(now()->plus(minutes: 10));

        return redirect('/podcasts');
    }
}
```

In some cases, jobs may have a default delay configured. If you need to bypass this delay and dispatch a job for immediate processing, you may use the `withoutDelay` method:

```
1ProcessPodcast::dispatch($podcast)->withoutDelay();

ProcessPodcast::dispatch($podcast)->withoutDelay();
```

 The Amazon SQS queue service has a maximum delay time of 15 minutes.

### Synchronous Dispatching

If you would like to dispatch a job immediately (synchronously), you may use the `dispatchSync` method. When using this method, the job will not be queued and will be executed immediately within the current process:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use App\Jobs\ProcessPodcast;
 6use App\Models\Podcast;
 7use Illuminate\Http\RedirectResponse;
 8use Illuminate\Http\Request;
 9 
10class PodcastController extends Controller
11{
12    /**
13     * Store a new podcast.
14     */
15    public function store(Request $request): RedirectResponse
16    {
17        $podcast = Podcast::create(/* ... */);
18 
19        // Create podcast...
20 
21        ProcessPodcast::dispatchSync($podcast);
22 
23        return redirect('/podcasts');
24    }
25}

<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPodcast;
use App\Models\Podcast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PodcastController extends Controller
{
    /**
     * Store a new podcast.
     */
    public function store(Request $request): RedirectResponse
    {
        $podcast = Podcast::create(/* ... */);

        // Create podcast...

        ProcessPodcast::dispatchSync($podcast);

        return redirect('/podcasts');
    }
}
```

#### Deferred Dispatching

Using deferred synchronous dispatching, you can dispatch a job to be processed during the current process, but after the HTTP response has been sent to the user. This allows you to process "queued" jobs synchronously without slowing down your user's application experience. To defer the execution of a synchronous job, dispatch the job to the `deferred` connection:

```
1RecordDelivery::dispatch($order)->onConnection('deferred');

RecordDelivery::dispatch($order)->onConnection('deferred');
```

The `deferred` connection also serves as the default failover queue.

Similarly, the `background` connection processes jobs after the HTTP response has been sent to the user; however, the job is processed in a separately spawned PHP process, allowing the PHP-FPM / application worker to be available to handle another incoming HTTP request:

```
1RecordDelivery::dispatch($order)->onConnection('background');

RecordDelivery::dispatch($order)->onConnection('background');
```

### Bulk Dispatching

If you need to dispatch many independent jobs at once and do not need batch tracking or callbacks, you may use the `bulk` method of the `Bus` facade. Laravel will group the jobs by their configured queue connection and queue name and push each group to the appropriate queue in bulk:

```
1use App\Jobs\ProcessUser;
2use Illuminate\Support\Facades\Bus;
3 
4Bus::bulk(
5    $users->map(fn ($user) => new ProcessUser($user))
6);

use App\Jobs\ProcessUser;
use Illuminate\Support\Facades\Bus;

Bus::bulk(
    $users->map(fn ($user) => new ProcessUser($user))
);
```

### Preparing Jobs Before Dispatch

If a job needs to prepare or inspect its state before it is pushed onto the queue, the job may implement the `Illuminate\Contracts\Queue\PreparesForDispatch` interface. Laravel will invoke the job's `prepareForDispatch` method before dispatching the job. If this method returns `false`, the job will not be dispatched:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use Illuminate\Contracts\Queue\PreparesForDispatch;
 6use Illuminate\Contracts\Queue\ShouldQueue;
 7use Illuminate\Foundation\Queue\Queueable;
 8use Illuminate\Support\Facades\Cache;
 9 
10class SyncPodcasts implements PreparesForDispatch, ShouldQueue
11{
12    use Queueable;
13 
14    /**
15     * Create a new job instance.
16     */
17    public function __construct(
18        public array $podcastIds,
19    ) {}
20 
21    /**
22     * Prepare the job before dispatching.
23     */
24    public function prepareForDispatch(): bool
25    {
26        return collect($this->podcastIds)
27            ->reject(fn (int $id) => Cache::has("podcast-syncing:{$id}"))
28            ->isNotEmpty();
29    }
30}

<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\PreparesForDispatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class SyncPodcasts implements PreparesForDispatch, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $podcastIds,
    ) {}

    /**
     * Prepare the job before dispatching.
     */
    public function prepareForDispatch(): bool
    {
        return collect($this->podcastIds)
            ->reject(fn (int $id) => Cache::has("podcast-syncing:{$id}"))
            ->isNotEmpty();
    }
}
```

### Jobs & Database Transactions

While it is perfectly fine to dispatch jobs within database transactions, you should take special care to ensure that your job will actually be able to execute successfully. When dispatching a job within a transaction, it is possible that the job will be processed by a worker before the parent transaction has committed. When this happens, any updates you have made to models or database records during the database transaction(s) may not yet be reflected in the database. In addition, any models or database records created within the transaction(s) may not exist in the database.

Thankfully, Laravel provides several methods of working around this problem. First, you may set the `after_commit` connection option in your queue connection's configuration array:

```
1'redis' => [
2    'driver' => 'redis',
3    // ...
4    'after_commit' => true,
5],

'redis' => [
    'driver' => 'redis',
    // ...
    'after_commit' => true,
],
```

When the `after_commit` option is `true`, you may dispatch jobs within database transactions; however, Laravel will wait until the open parent database transactions have been committed before actually dispatching the job. Of course, if no database transactions are currently open, the job will be dispatched immediately.

If a transaction is rolled back due to an exception that occurs during the transaction, the jobs that were dispatched during that transaction will be discarded.

 Setting the `after_commit` configuration option to `true` will also cause any queued event listeners, mailables, notifications, and broadcast events to be dispatched after all open database transactions have been committed.

#### Specifying Commit Dispatch Behavior Inline

If you do not set the `after_commit` queue connection configuration option to `true`, you may still indicate that a specific job should be dispatched after all open database transactions have been committed. To accomplish this, you may chain the `afterCommit` method onto your dispatch operation:

```
1use App\Jobs\ProcessPodcast;
2 
3ProcessPodcast::dispatch($podcast)->afterCommit();

use App\Jobs\ProcessPodcast;

ProcessPodcast::dispatch($podcast)->afterCommit();
```

Likewise, if the `after_commit` configuration option is set to `true`, you may indicate that a specific job should be dispatched immediately without waiting for any open database transactions to commit:

```
1ProcessPodcast::dispatch($podcast)->beforeCommit();

ProcessPodcast::dispatch($podcast)->beforeCommit();
```

### Job Chaining

Job chaining allows you to specify a list of queued jobs that should be run in sequence after the primary job has executed successfully. If one job in the sequence fails, the rest of the jobs will not be run. To execute a queued job chain, you may use the `chain` method provided by the `Bus` facade. Laravel's command bus is a lower-level component that queued job dispatching is built on top of:

```
 1use App\Jobs\OptimizePodcast;
 2use App\Jobs\ProcessPodcast;
 3use App\Jobs\ReleasePodcast;
 4use Illuminate\Support\Facades\Bus;
 5 
 6Bus::chain([
 7    new ProcessPodcast,
 8    new OptimizePodcast,
 9    new ReleasePodcast,
10])->dispatch();

use App\Jobs\OptimizePodcast;
use App\Jobs\ProcessPodcast;
use App\Jobs\ReleasePodcast;
use Illuminate\Support\Facades\Bus;

Bus::chain([
    new ProcessPodcast,
    new OptimizePodcast,
    new ReleasePodcast,
])->dispatch();
```

In addition to chaining job class instances, you may also chain closures:

```
1Bus::chain([
2    new ProcessPodcast,
3    new OptimizePodcast,
4    function () {
5        Podcast::update(/* ... */);
6    },
7])->dispatch();

Bus::chain([
    new ProcessPodcast,
    new OptimizePodcast,
    function () {
        Podcast::update(/* ... */);
    },
])->dispatch();
```

 Deleting jobs using the `$this->delete()` method within the job will not prevent chained jobs from being processed. The chain will only stop executing if a job in the chain fails.

#### Chain Connection and Queue

If you would like to specify the connection and queue that should be used for the chained jobs, you may use the `onConnection` and `onQueue` methods. These methods specify the queue connection and queue name that should be used unless the queued job is explicitly assigned a different connection / queue:

```
1Bus::chain([
2    new ProcessPodcast,
3    new OptimizePodcast,
4    new ReleasePodcast,
5])->onConnection('redis')->onQueue('podcasts')->dispatch();

Bus::chain([
    new ProcessPodcast,
    new OptimizePodcast,
    new ReleasePodcast,
])->onConnection('redis')->onQueue('podcasts')->dispatch();
```

#### Adding Jobs to the Chain

Occasionally, you may need to prepend or append a job to an existing job chain from within another job in that chain. You may accomplish this using the `prependToChain` and `appendToChain` methods:

```
 1/**
 2 * Execute the job.
 3 */
 4public function handle(): void
 5{
 6    // ...
 7 
 8    // Prepend to the current chain, run job immediately after current job...
 9    $this->prependToChain(new TranscribePodcast);
10 
11    // Append to the current chain, run job at end of chain...
12    $this->appendToChain(new TranscribePodcast);
13}

/**
 * Execute the job.
 */
public function handle(): void
{
    // ...

    // Prepend to the current chain, run job immediately after current job...
    $this->prependToChain(new TranscribePodcast);

    // Append to the current chain, run job at end of chain...
    $this->appendToChain(new TranscribePodcast);
}
```

#### Chain Failures

When chaining jobs, you may use the `catch` method to specify a closure that should be invoked if a job within the chain fails. The given callback will receive the `Throwable` instance that caused the job failure:

```
 1use Illuminate\Support\Facades\Bus;
 2use Throwable;
 3 
 4Bus::chain([
 5    new ProcessPodcast,
 6    new OptimizePodcast,
 7    new ReleasePodcast,
 8])->catch(function (Throwable $e) {
 9    // A job within the chain has failed...
10})->dispatch();

use Illuminate\Support\Facades\Bus;
use Throwable;

Bus::chain([
    new ProcessPodcast,
    new OptimizePodcast,
    new ReleasePodcast,
])->catch(function (Throwable $e) {
    // A job within the chain has failed...
})->dispatch();
```

 Since chain callbacks are serialized and executed at a later time by the Laravel queue, you should not use the `$this` variable within chain callbacks.

### Customizing the Queue and Connection

#### Dispatching to a Particular Queue

By pushing jobs to different queues, you may "categorize" your queued jobs and even prioritize how many workers you assign to various queues. Keep in mind, this does not push jobs to different queue "connections" as defined by your queue configuration file, but only to specific queues within a single connection. To specify the queue, use the `onQueue` method when dispatching the job:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use App\Jobs\ProcessPodcast;
 6use App\Models\Podcast;
 7use Illuminate\Http\RedirectResponse;
 8use Illuminate\Http\Request;
 9 
10class PodcastController extends Controller
11{
12    /**
13     * Store a new podcast.
14     */
15    public function store(Request $request): RedirectResponse
16    {
17        $podcast = Podcast::create(/* ... */);
18 
19        // Create podcast...
20 
21        ProcessPodcast::dispatch($podcast)->onQueue('processing');
22 
23        return redirect('/podcasts');
24    }
25}

<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPodcast;
use App\Models\Podcast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PodcastController extends Controller
{
    /**
     * Store a new podcast.
     */
    public function store(Request $request): RedirectResponse
    {
        $podcast = Podcast::create(/* ... */);

        // Create podcast...

        ProcessPodcast::dispatch($podcast)->onQueue('processing');

        return redirect('/podcasts');
    }
}
```

Alternatively, you may specify the job's queue by calling the `onQueue` method within the job's constructor:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use Illuminate\Contracts\Queue\ShouldQueue;
 6use Illuminate\Foundation\Queue\Queueable;
 7 
 8class ProcessPodcast implements ShouldQueue
 9{
10    use Queueable;
11 
12    /**
13     * Create a new job instance.
14     */
15    public function __construct()
16    {
17        $this->onQueue('processing');
18    }
19}

<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPodcast implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('processing');
    }
}
```

#### Dispatching to a Particular Connection

If your application interacts with multiple queue connections, you may specify which connection to push a job to using the `onConnection` method:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use App\Jobs\ProcessPodcast;
 6use App\Models\Podcast;
 7use Illuminate\Http\RedirectResponse;
 8use Illuminate\Http\Request;
 9 
10class PodcastController extends Controller
11{
12    /**
13     * Store a new podcast.
14     */
15    public function store(Request $request): RedirectResponse
16    {
17        $podcast = Podcast::create(/* ... */);
18 
19        // Create podcast...
20 
21        ProcessPodcast::dispatch($podcast)->onConnection('sqs');
22 
23        return redirect('/podcasts');
24    }
25}

<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPodcast;
use App\Models\Podcast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PodcastController extends Controller
{
    /**
     * Store a new podcast.
     */
    public function store(Request $request): RedirectResponse
    {
        $podcast = Podcast::create(/* ... */);

        // Create podcast...

        ProcessPodcast::dispatch($podcast)->onConnection('sqs');

        return redirect('/podcasts');
    }
}
```

You may chain the `onConnection` and `onQueue` methods together to specify the connection and the queue for a job:

```
1ProcessPodcast::dispatch($podcast)
2    ->onConnection('sqs')
3    ->onQueue('processing');

ProcessPodcast::dispatch($podcast)
    ->onConnection('sqs')
    ->onQueue('processing');
```

Alternatively, you may specify the job's connection by calling the `onConnection` method within the job's constructor:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use Illuminate\Contracts\Queue\ShouldQueue;
 6use Illuminate\Foundation\Queue\Queueable;
 7 
 8class ProcessPodcast implements ShouldQueue
 9{
10    use Queueable;
11 
12    /**
13     * Create a new job instance.
14     */
15    public function __construct()
16    {
17        $this->onConnection('sqs');
18    }
19}

<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPodcast implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onConnection('sqs');
    }
}
```

#### Queue Routing

You may use the `Queue` facade's `route` method to define a default connection and queue for specific job classes. This is useful when you want to ensure certain jobs always use specific queues without needing to specify the connection or queue on the job.

In addition to routing specific job classes, you may also pass an interface, trait, or parent class to the `route` method. When you do this, any job that implements the interface, uses the trait, or extends the parent class will automatically use the configured connection and queue.

Typically, you should call the `route` method from the `boot` method of a service provider:

```
 1use App\Concerns\RequiresVideo;
 2use App\Jobs\ProcessPodcast;
 3use App\Jobs\ProcessVideo;
 4use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
 5use Illuminate\Support\Facades\Queue;
 6 
 7/**
 8 * Bootstrap any application services.
 9 */
10public function boot(): void
11{
12    Queue::route(ProcessPodcast::class, connection: 'redis', queue: 'podcasts');
13    Queue::route(RequiresVideo::class, queue: 'video');
14    Queue::route(ShouldBroadcast::class, queue: 'events');
15}

use App\Concerns\RequiresVideo;
use App\Jobs\ProcessPodcast;
use App\Jobs\ProcessVideo;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Queue;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Queue::route(ProcessPodcast::class, connection: 'redis', queue: 'podcasts');
    Queue::route(RequiresVideo::class, queue: 'video');
    Queue::route(ShouldBroadcast::class, queue: 'events');
}
```

When a connection is specified without a queue, the job will be sent to the default queue:

```
1Queue::route(ProcessPodcast::class, connection: 'redis');

Queue::route(ProcessPodcast::class, connection: 'redis');
```

You may also route multiple job classes at once by passing an array to the `route` method:

```
1Queue::route([
2    ProcessPodcast::class => ['redis', 'podcasts'], // Connection and queue
3    ProcessVideo::class => 'videos', // Queue only (uses default connection)
4]);

Queue::route([
    ProcessPodcast::class => ['redis', 'podcasts'], // Connection and queue
    ProcessVideo::class => 'videos', // Queue only (uses default connection)
]);
```

 Queue routing can still be overridden by the job on a per-job basis.

You may use the `forward` method to forward jobs from one queue to another queue and / or connection. This is useful when you need to change queue infrastructure without modifying individual jobs or dispatch locations:

```
1Queue::forward('reports', 'reports.fifo', 'sqs');
2Queue::forward('payments', connection: 'sqs');
3Queue::forward('updates', 'notifications');

Queue::forward('reports', 'reports.fifo', 'sqs');
Queue::forward('payments', connection: 'sqs');
Queue::forward('updates', 'notifications');
```

You may also forward multiple queues at once by passing an array:

```
1Queue::forward([
2    'reports' => 'reports.fifo',
3    'emails' => 'emails.fifo',
4], connection: 'sqs');

Queue::forward([
    'reports' => 'reports.fifo',
    'emails' => 'emails.fifo',
], connection: 'sqs');
```

An explicit connection configured on a job takes precedence over a forwarded connection.

### Specifying Max Job Attempts / Timeout Values

#### Max Attempts

Job attempts are a core concept of Laravel's queue system and power many advanced features. While they may seem confusing at first, it's important to understand how they work before modifying the default configuration.

When a job is dispatched, it is pushed onto the queue. A worker then picks it up and attempts to execute it. This is a job attempt.

However, an attempt does not necessarily mean the job's `handle` method was executed. Attempts can also be "consumed" in several ways:

- The job encounters an unhandled exception during execution.
- The job is manually released back to the queue using `$this->release()`.
- Middleware such as `WithoutOverlapping` or `RateLimited` fails to acquire a lock and releases the job.
- The job timed out.
- The job's `handle` method runs and completes without throwing an exception.

You likely do not want to keep attempting a job indefinitely. Therefore, Laravel provides various ways to specify how many times or for how long a job may be attempted.

 By default, Laravel will only attempt a job once. If your job uses middleware like `WithoutOverlapping` or `RateLimited`, or if you're manually releasing jobs, you will likely need to increase the number of allowed attempts via the `tries` option.

One approach to specifying the maximum number of times a job may be attempted is via the `--tries` switch on the Artisan command line. This will apply to all jobs processed by the worker unless the job being processed specifies the number of times it may be attempted:

```
1php artisan queue:work --tries=3

php artisan queue:work --tries=3
```

If a job exceeds its maximum number of attempts, it will be considered a "failed" job. For more information on handling failed jobs, consult the failed job documentation. If `--tries=0` is provided to the `queue:work` command, the job will be retried indefinitely.

You may take a more granular approach by defining the maximum number of times a job may be attempted on the job class itself using the `Tries` attribute. If the maximum number of attempts is specified on the job, it will take precedence over the `--tries` value provided on the command line:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use Illuminate\Queue\Attributes\Tries;
 6 
 7#[Tries(5)]
 8class ProcessPodcast implements ShouldQueue
 9{
10    // ...
11}

<?php

namespace App\Jobs;

use Illuminate\Queue\Attributes\Tries;

#[Tries(5)]
class ProcessPodcast implements ShouldQueue
{
    // ...
}
```

If you need dynamic control over a particular job's maximum attempts, you may define a `tries` method on the job:

```
1/**
2 * Determine number of times the job may be attempted.
3 */
4public function tries(): int
5{
6    return 5;
7}

/**
 * Determine number of times the job may be attempted.
 */
public function tries(): int
{
    return 5;
}
```

#### Time Based Attempts

As an alternative to defining how many times a job may be attempted before it fails, you may define a time at which the job should no longer be attempted. This allows a job to be attempted any number of times within a given time frame. To define the time at which a job should no longer be attempted, add a `retryUntil` method to your job class. This method should return a `DateTime` instance:

```
1use DateTime;
2 
3/**
4 * Determine the time at which the job should timeout.
5 */
6public function retryUntil(): DateTime
7{
8    return now()->plus(minutes: 10);
9}

use DateTime;

/**
 * Determine the time at which the job should timeout.
 */
public function retryUntil(): DateTime
{
    return now()->plus(minutes: 10);
}
```

If both `retryUntil` and `tries` are defined, Laravel gives precedence to the `retryUntil` method.

 You may also define a `Tries` attribute or `retryUntil` method on your [queued event listeners](https://laravel.com/framework/docs/events#queued-event-listeners) and [queued notifications](https://laravel.com/framework/docs/notifications#queueing-notifications).

#### Max Exceptions

Sometimes you may wish to specify that a job may be attempted many times, but should fail if the retries are triggered by a given number of unhandled exceptions (as opposed to being released by the `release` method directly). To accomplish this, you may use the `Tries` and `MaxExceptions` attributes on your job class:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use Illuminate\Contracts\Queue\ShouldQueue;
 6use Illuminate\Foundation\Queue\Queueable;
 7use Illuminate\Queue\Attributes\MaxExceptions;
 8use Illuminate\Queue\Attributes\Tries;
 9use Illuminate\Support\Facades\Redis;
10 
11#[Tries(25)]
12#[MaxExceptions(3)]
13class ProcessPodcast implements ShouldQueue
14{
15    use Queueable;
16 
17    /**
18     * Execute the job.
19     */
20    public function handle(): void
21    {
22        Redis::throttle('key')->allow(10)->every(60)->then(function () {
23            // Lock obtained, process the podcast...
24        }, function () {
25            // Unable to obtain lock...
26            return $this->release(10);
27        });
28    }
29}

<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Redis;

#[Tries(25)]
#[MaxExceptions(3)]
class ProcessPodcast implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Redis::throttle('key')->allow(10)->every(60)->then(function () {
            // Lock obtained, process the podcast...
        }, function () {
            // Unable to obtain lock...
            return $this->release(10);
        });
    }
}
```

In this example, the job is released for ten seconds if the application is unable to obtain a Redis lock and will continue to be retried up to 25 times. However, the job will fail if three unhandled exceptions are thrown by the job.

By default, an attempt that ends because the worker process crashed or was killed, such as when it runs out of memory, does not count towards the job's maximum number of exceptions. If you would like these attempts to count as an exception, you may add the `CountCrashesAsExceptions` attribute to your job class:

```
 1use Illuminate\Queue\Attributes\CountCrashesAsExceptions;
 2use Illuminate\Queue\Attributes\MaxExceptions;
 3use Illuminate\Queue\Attributes\Tries;
 4 
 5#[Tries(25)]
 6#[MaxExceptions(3)]
 7#[CountCrashesAsExceptions]
 8class ProcessPodcast implements ShouldQueue
 9{
10    // ...
11}

use Illuminate\Queue\Attributes\CountCrashesAsExceptions;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Tries;

#[Tries(25)]
#[MaxExceptions(3)]
#[CountCrashesAsExceptions]
class ProcessPodcast implements ShouldQueue
{
    // ...
}
```

When this attribute is present, the worker stores a marker in your application's cache while the job is processing. If the marker still exists when the job is next attempted, the previous attempt is counted as an exception.

#### Stopping Retries by Exception

Sometimes an exception indicates that a queued job should fail immediately instead of being released for another attempt. You may configure exception types that should stop job retries using the `dontRetry` exception method in your application's `bootstrap/app.php` file:

```
1use App\Exceptions\InvalidPodcastSourceException;
2use Illuminate\Foundation\Configuration\Exceptions;
3 
4->withExceptions(function (Exceptions $exceptions): void {
5    $exceptions->dontRetry([
6        InvalidPodcastSourceException::class,
7    ]);
8})

use App\Exceptions\InvalidPodcastSourceException;
use Illuminate\Foundation\Configuration\Exceptions;

->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->dontRetry([
        InvalidPodcastSourceException::class,
    ]);
})
```

If you need more control over when retries should stop, you may provide a closure to the `dontRetryWhen` method. When the closure returns `true`, the job will be marked as failed and will not be retried:

```
1use App\Exceptions\PodcastProcessingException;
2use Illuminate\Foundation\Configuration\Exceptions;
3 
4->withExceptions(function (Exceptions $exceptions): void {
5    $exceptions->dontRetryWhen(function (PodcastProcessingException $e) {
6        return $e->reason() === 'Subscription expired';
7    });
8})

use App\Exceptions\PodcastProcessingException;
use Illuminate\Foundation\Configuration\Exceptions;

->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->dontRetryWhen(function (PodcastProcessingException $e) {
        return $e->reason() === 'Subscription expired';
    });
})
```

#### Timeout

Often, you know roughly how long you expect your queued jobs to take. For this reason, Laravel allows you to specify a "timeout" value. By default, the timeout value is 60 seconds. If a job is processing for longer than the number of seconds specified by the timeout value, the worker processing the job will exit with an error. Typically, the worker will be restarted automatically by a process manager configured on your server.

The maximum number of seconds that jobs can run may be specified using the `--timeout` switch on the Artisan command line:

```
1php artisan queue:work --timeout=30

php artisan queue:work --timeout=30
```

If the job exceeds its maximum attempts by continually timing out, it will be marked as failed.

You may also define the maximum number of seconds a job should be allowed to run using the `Timeout` attribute on the job class. If the timeout is specified on the job, it will take precedence over any timeout specified on the command line:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use Illuminate\Queue\Attributes\Timeout;
 6 
 7#[Timeout(120)]
 8class ProcessPodcast implements ShouldQueue
 9{
10    // ...
11}

<?php

namespace App\Jobs;

use Illuminate\Queue\Attributes\Timeout;

#[Timeout(120)]
class ProcessPodcast implements ShouldQueue
{
    // ...
}
```

Sometimes, IO blocking processes such as sockets or outgoing HTTP connections may not respect your specified timeout. Therefore, when using these features, you should always attempt to specify a timeout using their APIs as well. For example, when using [Guzzle](https://docs.guzzlephp.org), you should always specify a connection and request timeout value.

 The [PCNTL](https://www.php.net/manual/en/book.pcntl.php) PHP extension must be installed in order to specify job timeouts. In addition, a job's "timeout" value should always be less than its "retry after" value. Otherwise, the job may be re-attempted before it has actually finished executing or timed out. The `--timeout` option has no effect when the `queue:work` command is invoked with the `--once` option.

#### Failing on Timeout

If you would like to indicate that a job should be marked as failed on timeout, you may use the `FailOnTimeout` attribute on the job class:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use Illuminate\Queue\Attributes\FailOnTimeout;
 6 
 7#[FailOnTimeout]
 8class ProcessPodcast implements ShouldQueue
 9{
10    // ...
11}

<?php

namespace App\Jobs;

use Illuminate\Queue\Attributes\FailOnTimeout;

#[FailOnTimeout]
class ProcessPodcast implements ShouldQueue
{
    // ...
}
```

 By default, when a job times out, it consumes one attempt and is released back to the queue (if retries are allowed). However, if you configure the job to fail on timeout, it will not be retried, regardless of the value set for tries.

### SQS FIFO and Fair Queues

Laravel supports [Amazon SQS FIFO (First-In-First-Out)](https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-fifo-queues.html) and [fair](https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-fair-queues.html) queues. FIFO queues allow you to process jobs in the exact order they were sent while ensuring exactly-once processing through message deduplication.

FIFO queues require a message group ID to determine which jobs can be processed in parallel. Jobs with the same group ID are processed sequentially, while messages with different group IDs can be processed concurrently.

Laravel provides a fluent `onGroup` method to specify the message group ID when dispatching jobs:

```
1ProcessOrder::dispatch($order)
2    ->onGroup("customer-{$order->customer_id}");

ProcessOrder::dispatch($order)
    ->onGroup("customer-{$order->customer_id}");
```

If you dispatch a job to an SQS FIFO queue without specifying a message group, Laravel will use the queue name as the message group ID.

SQS FIFO queues support message deduplication to ensure exactly-once processing. Implement a `deduplicationId` method in your job class to provide a custom deduplication ID:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use Illuminate\Contracts\Queue\ShouldQueue;
 6use Illuminate\Foundation\Queue\Queueable;
 7 
 8class ProcessSubscriptionRenewal implements ShouldQueue
 9{
10    use Queueable;
11 
12    // ...
13 
14    /**
15     * Get the job's deduplication ID.
16     */
17    public function deduplicationId(): string
18    {
19        return "renewal-{$this->subscription->id}";
20    }
21}

<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessSubscriptionRenewal implements ShouldQueue
{
    use Queueable;

    // ...

    /**
     * Get the job's deduplication ID.
     */
    public function deduplicationId(): string
    {
        return "renewal-{$this->subscription->id}";
    }
}
```

#### Fair Queues

If you are using an SQS standard queue, setting a message group enables fair queueing. In other words, once you assign groups, SQS will use them to maintain fair delivery across tenants / workloads. No additional Laravel configuration is required.

Instead of calling `onGroup` at dispatch time, you may also define a `messageGroup` method directly on the job:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use Illuminate\Contracts\Queue\ShouldQueue;
 6use Illuminate\Foundation\Queue\Queueable;
 7 
 8class ProcessOrder implements ShouldQueue
 9{
10    use Queueable;
11 
12    // ...
13 
14    /**
15     * Get the job's message group.
16     */
17    public function messageGroup(): string
18    {
19        return "customer-{$this->order->customer_id}";
20    }
21}

<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessOrder implements ShouldQueue
{
    use Queueable;

    // ...

    /**
     * Get the job's message group.
     */
    public function messageGroup(): string
    {
        return "customer-{$this->order->customer_id}";
    }
}
```

#### FIFO Listeners, Mail, and Notifications

When utilizing FIFO queues, you will also need to define message groups on listeners, mail, and notifications. Alternatively, you can dispatch queued instances of these objects to a non-FIFO queue.

To define the message group for a [queued event listener](https://laravel.com/framework/docs/events#queued-event-listeners), define a `messageGroup` method on the listener. You may also optionally define a `deduplicator` method, which receives the event and should return a closure that generates the deduplication ID:

```
 1<?php
 2 
 3namespace App\Listeners;
 4 
 5use App\Events\OrderShipped;
 6use Closure;
 7 
 8class SendShipmentNotification
 9{
10    // ...
11 
12    /**
13     * Get the job's message group.
14     */
15    public function messageGroup(): string
16    {
17        return 'shipments';
18    }
19 
20    /**
21     * Get the job's deduplicator.
22     */
23    public function deduplicator(OrderShipped $event): Closure
24    {
25        return fn () => "shipment-notification-{$event->order->id}";
26    }
27}

<?php

namespace App\Listeners;

use App\Events\OrderShipped;
use Closure;

class SendShipmentNotification
{
    // ...

    /**
     * Get the job's message group.
     */
    public function messageGroup(): string
    {
        return 'shipments';
    }

    /**
     * Get the job's deduplicator.
     */
    public function deduplicator(OrderShipped $event): Closure
    {
        return fn () => "shipment-notification-{$event->order->id}";
    }
}
```

When sending a [mail message](https://laravel.com/framework/docs/mail) that is going to be queued on a FIFO queue, you should invoke the `onGroup` method and optionally the `withDeduplicator` method when sending the notification:

```
1use App\Mail\InvoicePaid;
2use Illuminate\Support\Facades\Mail;
3 
4$invoicePaid = (new InvoicePaid($invoice))
5    ->onGroup('invoices')
6    ->withDeduplicator(fn () => 'invoices-'.$invoice->id);
7 
8Mail::to($request->user())->send($invoicePaid);

use App\Mail\InvoicePaid;
use Illuminate\Support\Facades\Mail;

$invoicePaid = (new InvoicePaid($invoice))
    ->onGroup('invoices')
    ->withDeduplicator(fn () => 'invoices-'.$invoice->id);

Mail::to($request->user())->send($invoicePaid);
```

When sending a [notification](https://laravel.com/framework/docs/notifications) that is going to be queued on a FIFO queue, you should invoke the `onGroup` method and optionally the `withDeduplicator` method when sending the notification:

```
1use App\Notifications\InvoicePaid;
2 
3$invoicePaid = (new InvoicePaid($invoice))
4    ->onGroup('invoices')
5    ->withDeduplicator(fn () => 'invoices-'.$invoice->id);
6 
7$user->notify($invoicePaid);

use App\Notifications\InvoicePaid;

$invoicePaid = (new InvoicePaid($invoice))
    ->onGroup('invoices')
    ->withDeduplicator(fn () => 'invoices-'.$invoice->id);

$user->notify($invoicePaid);
```

### Queue Failover

The `failover` queue driver provides automatic failover functionality when pushing jobs to the queue. If the primary queue connection of the `failover` configuration fails for any reason, Laravel will automatically attempt to push the job to the next configured connection in the list. This is particularly useful for ensuring high availability in production environments where queue reliability is critical.

To configure a failover queue connection, specify the `failover` driver and provide an array of connection names to attempt in order. By default, Laravel includes an example failover configuration in your application's `config/queue.php` configuration file:

```
1'failover' => [
2    'driver' => 'failover',
3    'connections' => [
4        'redis',
5        'database',
6        'sync',
7    ],
8],

'failover' => [
    'driver' => 'failover',
    'connections' => [
        'redis',
        'database',
        'sync',
    ],
],
```

Once you have configured a connection that uses the `failover` driver, you will need to set the failover connection as your default queue connection in your application's `.env` file to make use of the failover functionality:

```
1QUEUE_CONNECTION=failover

QUEUE_CONNECTION=failover
```

Next, start at least one worker for each connection in your failover connection list:

```
1php artisan queue:work redis
2php artisan queue:work database

php artisan queue:work redis
php artisan queue:work database
```

 You do not need to run a worker for connections using the `sync`, `background`, or `deferred` queue drivers since those drivers process jobs within the current PHP process.

When a queue connection operation fails and failover is activated, Laravel will dispatch the `Illuminate\Queue\Events\QueueFailedOver` event, allowing you to report or log that a queue connection has failed.

 If you use Laravel Horizon, remember that Horizon manages Redis queues only. If your failover list includes `database`, you should run a regular `php artisan queue:work database` process alongside Horizon.

### Error Handling

If an exception is thrown while the job is being processed, the job will automatically be released back onto the queue so it may be attempted again. The job will continue to be released until it has been attempted the maximum number of times allowed by your application. The maximum number of attempts is defined by the `--tries` switch used on the `queue:work` Artisan command. Alternatively, the maximum number of attempts may be defined on the job class itself. More information on running the queue worker can be found below.

#### Manually Releasing a Job

Sometimes you may wish to manually release a job back onto the queue so that it can be attempted again at a later time. You may accomplish this by calling the `release` method:

```
1/**
2 * Execute the job.
3 */
4public function handle(): void
5{
6    // ...
7 
8    $this->release();
9}

/**
 * Execute the job.
 */
public function handle(): void
{
    // ...

    $this->release();
}
```

By default, the `release` method will release the job back onto the queue for immediate processing. However, you may instruct the queue to not make the job available for processing until a given number of seconds has elapsed by passing an integer or date instance to the `release` method:

```
1$this->release(10);
2 
3$this->release(now()->plus(seconds: 10));

$this->release(10);

$this->release(now()->plus(seconds: 10));
```

#### Manually Failing a Job

Occasionally you may need to manually mark a job as "failed". To do so, you may call the `fail` method:

```
1/**
2 * Execute the job.
3 */
4public function handle(): void
5{
6    // ...
7 
8    $this->fail();
9}

/**
 * Execute the job.
 */
public function handle(): void
{
    // ...

    $this->fail();
}
```

If you would like to mark your job as failed because of an exception that you have caught, you may pass the exception to the `fail` method. Or, for convenience, you may pass a string error message which will be converted to an exception for you:

```
1$this->fail($exception);
2 
3$this->fail('Something went wrong.');

$this->fail($exception);

$this->fail('Something went wrong.');
```

 For more information on failed jobs, check out the documentation on dealing with job failures.

#### Failing Jobs on Specific Exceptions

The `FailOnException` job middleware allows you to short-circuit retries when specific exceptions are thrown. This allows retrying on transient exceptions such as external API errors, but failing the job permanently on persistent exceptions, such as a user's permissions being revoked:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use App\Models\User;
 6use Illuminate\Auth\Access\AuthorizationException;
 7use Illuminate\Contracts\Queue\ShouldQueue;
 8use Illuminate\Foundation\Queue\Queueable;
 9use Illuminate\Queue\Attributes\Tries;
10use Illuminate\Queue\Middleware\FailOnException;
11use Illuminate\Support\Facades\Http;
12 
13#[Tries(3)]
14class SyncChatHistory implements ShouldQueue
15{
16    use Queueable;
17 
18    /**
19     * Create a new job instance.
20     */
21    public function __construct(
22        public User $user,
23    ) {}
24 
25    /**
26     * Execute the job.
27     */
28    public function handle(): void
29    {
30        $this->user->authorize('sync-chat-history');
31 
32        $response = Http::throw()->get(
33            "https://chat.laravel.test/?user={$this->user->uuid}"
34        );
35 
36        // ...
37    }
38 
39    /**
40     * Get the middleware the job should pass through.
41     */
42    public function middleware(): array
43    {
44        return [
45            new FailOnException([AuthorizationException::class])
46        ];
47    }
48}

<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\FailOnException;
use Illuminate\Support\Facades\Http;

#[Tries(3)]
class SyncChatHistory implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->user->authorize('sync-chat-history');

        $response = Http::throw()->get(
            "https://chat.laravel.test/?user={$this->user->uuid}"
        );

        // ...
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            new FailOnException([AuthorizationException::class])
        ];
    }
}
```

## Job Batching

Laravel's job batching feature allows you to easily execute a group of jobs in parallel and then perform some action when the batch of jobs has completed executing.

Before getting started, you should create a database migration to build a table which will contain meta information about your job batches, such as their completion percentage. This migration may be generated using the `make:queue-batches-table` Artisan command:

```
1php artisan make:queue-batches-table
2 
3php artisan migrate

php artisan make:queue-batches-table

php artisan migrate
```

### Defining Batchable Jobs

To define a batchable job, you should create a queueable job as normal; however, you should add the `Illuminate\Bus\Batchable` trait to the job class. This trait provides access to a `batch` method which may be used to retrieve the current batch that the job is executing within:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use Illuminate\Bus\Batchable;
 6use Illuminate\Contracts\Queue\ShouldQueue;
 7use Illuminate\Foundation\Queue\Queueable;
 8 
 9class ImportCsv implements ShouldQueue
10{
11    use Batchable, Queueable;
12 
13    /**
14     * Execute the job.
15     */
16    public function handle(): void
17    {
18        if ($this->batch()->cancelled()) {
19            // Determine if the batch has been cancelled...
20 
21            return;
22        }
23 
24        // Import a portion of the CSV file...
25    }
26}

<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportCsv implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            // Determine if the batch has been cancelled...

            return;
        }

        // Import a portion of the CSV file...
    }
}
```

### Dispatching Batches

To dispatch a batch of jobs, you should use the `batch` method of the `Bus` facade. Of course, batching is primarily useful when combined with completion callbacks. So, you may use the `then`, `catch`, and `finally` methods to define completion callbacks for the batch. Each of these callbacks will receive an `Illuminate\Bus\Batch` instance when they are invoked.

When running multiple queue workers, the jobs in the batch will be processed in parallel. Therefore, the order in which the jobs complete may not be the same as the order in which they were added to the batch. Consult our documentation on job chains and batches for information on how to run a series of jobs in sequence.

In this example, we will imagine we are queueing a batch of jobs that each process a given number of rows from a CSV file:

```
 1use App\Jobs\ImportCsv;
 2use Illuminate\Bus\Batch;
 3use Illuminate\Support\Facades\Bus;
 4use Throwable;
 5 
 6$batch = Bus::batch([
 7    new ImportCsv(1, 100),
 8    new ImportCsv(101, 200),
 9    new ImportCsv(201, 300),
10    new ImportCsv(301, 400),
11    new ImportCsv(401, 500),
12])->before(function (Batch $batch) {
13    // The batch has been created but no jobs have been added...
14})->progress(function (Batch $batch) {
15    // A single job has completed successfully...
16})->then(function (Batch $batch) {
17    // All jobs completed successfully...
18})->catch(function (Batch $batch, Throwable $e) {
19    // Batch job failure detected...
20})->finally(function (Batch $batch) {
21    // The batch has finished executing...
22})->dispatch();
23 
24return $batch->id;

use App\Jobs\ImportCsv;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Throwable;

$batch = Bus::batch([
    new ImportCsv(1, 100),
    new ImportCsv(101, 200),
    new ImportCsv(201, 300),
    new ImportCsv(301, 400),
    new ImportCsv(401, 500),
])->before(function (Batch $batch) {
    // The batch has been created but no jobs have been added...
})->progress(function (Batch $batch) {
    // A single job has completed successfully...
})->then(function (Batch $batch) {
    // All jobs completed successfully...
})->catch(function (Batch $batch, Throwable $e) {
    // Batch job failure detected...
})->finally(function (Batch $batch) {
    // The batch has finished executing...
})->dispatch();

return $batch->id;
```

The batch's ID, which may be accessed via the `$batch->id` property, may be used to query the Laravel command bus for information about the batch after it has been dispatched.

 Since batch callbacks are serialized and executed at a later time by the Laravel queue, you should not use the `$this` variable within the callbacks. In addition, since batched jobs are wrapped within database transactions, database statements that trigger implicit commits should not be executed within the jobs.

#### Naming Batches

Some tools such as [Laravel Horizon](https://laravel.com/framework/docs/horizon) and [Laravel Telescope](https://laravel.com/framework/docs/telescope) may provide more user-friendly debug information for batches if batches are named. To assign an arbitrary name to a batch, you may call the `name` method while defining the batch:

```
1$batch = Bus::batch([
2    // ...
3])->then(function (Batch $batch) {
4    // All jobs completed successfully...
5})->name('Import CSV')->dispatch();

$batch = Bus::batch([
    // ...
])->then(function (Batch $batch) {
    // All jobs completed successfully...
})->name('Import CSV')->dispatch();
```

#### Batch Connection and Queue

If you would like to specify the connection and queue that should be used for the batched jobs, you may use the `onConnection` and `onQueue` methods. All batched jobs must execute within the same connection and queue:

```
1$batch = Bus::batch([
2    // ...
3])->then(function (Batch $batch) {
4    // All jobs completed successfully...
5})->onConnection('redis')->onQueue('imports')->dispatch();

$batch = Bus::batch([
    // ...
])->then(function (Batch $batch) {
    // All jobs completed successfully...
})->onConnection('redis')->onQueue('imports')->dispatch();
```

### Chains and Batches

You may define a set of chained jobs within a batch by placing the chained jobs within an array. For example, we may execute two job chains in parallel and execute a callback when both job chains have finished processing:

```
 1use App\Jobs\ReleasePodcast;
 2use App\Jobs\SendPodcastReleaseNotification;
 3use Illuminate\Bus\Batch;
 4use Illuminate\Support\Facades\Bus;
 5 
 6Bus::batch([
 7    [
 8        new ReleasePodcast(1),
 9        new SendPodcastReleaseNotification(1),
10    ],
11    [
12        new ReleasePodcast(2),
13        new SendPodcastReleaseNotification(2),
14    ],
15])->then(function (Batch $batch) {
16    // All jobs completed successfully...
17})->dispatch();

use App\Jobs\ReleasePodcast;
use App\Jobs\SendPodcastReleaseNotification;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

Bus::batch([
    [
        new ReleasePodcast(1),
        new SendPodcastReleaseNotification(1),
    ],
    [
        new ReleasePodcast(2),
        new SendPodcastReleaseNotification(2),
    ],
])->then(function (Batch $batch) {
    // All jobs completed successfully...
})->dispatch();
```

Conversely, you may run batches of jobs within a chain by defining batches within the chain. For example, you could first run a batch of jobs to release multiple podcasts then a batch of jobs to send the release notifications:

```
 1use App\Jobs\FlushPodcastCache;
 2use App\Jobs\ReleasePodcast;
 3use App\Jobs\SendPodcastReleaseNotification;
 4use Illuminate\Support\Facades\Bus;
 5 
 6Bus::chain([
 7    new FlushPodcastCache,
 8    Bus::batch([
 9        new ReleasePodcast(1),
10        new ReleasePodcast(2),
11    ]),
12    Bus::batch([
13        new SendPodcastReleaseNotification(1),
14        new SendPodcastReleaseNotification(2),
15    ]),
16])->dispatch();

use App\Jobs\FlushPodcastCache;
use App\Jobs\ReleasePodcast;
use App\Jobs\SendPodcastReleaseNotification;
use Illuminate\Support\Facades\Bus;

Bus::chain([
    new FlushPodcastCache,
    Bus::batch([
        new ReleasePodcast(1),
        new ReleasePodcast(2),
    ]),
    Bus::batch([
        new SendPodcastReleaseNotification(1),
        new SendPodcastReleaseNotification(2),
    ]),
])->dispatch();
```

### Adding Jobs to Batches

Sometimes it may be useful to add additional jobs to a batch from within a batched job. This pattern can be useful when you need to batch thousands of jobs which may take too long to dispatch during a web request. So, instead, you may wish to dispatch an initial batch of "loader" jobs that hydrate the batch with even more jobs:

```
1$batch = Bus::batch([
2    new LoadImportBatch,
3    new LoadImportBatch,
4    new LoadImportBatch,
5])->then(function (Batch $batch) {
6    // All jobs completed successfully...
7})->name('Import Contacts')->dispatch();

$batch = Bus::batch([
    new LoadImportBatch,
    new LoadImportBatch,
    new LoadImportBatch,
])->then(function (Batch $batch) {
    // All jobs completed successfully...
})->name('Import Contacts')->dispatch();
```

In this example, we will use the `LoadImportBatch` job to hydrate the batch with additional jobs. To accomplish this, we may use the `add` method on the batch instance that may be accessed via the job's `batch` method:

```
 1use App\Jobs\ImportContacts;
 2use Illuminate\Support\Collection;
 3 
 4/**
 5 * Execute the job.
 6 */
 7public function handle(): void
 8{
 9    if ($this->batch()->cancelled()) {
10        return;
11    }
12 
13    $this->batch()->add(Collection::times(1000, function () {
14        return new ImportContacts;
15    }));
16}

use App\Jobs\ImportContacts;
use Illuminate\Support\Collection;

/**
 * Execute the job.
 */
public function handle(): void
{
    if ($this->batch()->cancelled()) {
        return;
    }

    $this->batch()->add(Collection::times(1000, function () {
        return new ImportContacts;
    }));
}
```

 You may only add jobs to a batch from within a job that belongs to the same batch.

### Inspecting Batches

The `Illuminate\Bus\Batch` instance that is provided to batch completion callbacks has a variety of properties and methods to assist you in interacting with and inspecting a given batch of jobs:

```
 1// The UUID of the batch...
 2$batch->id;
 3 
 4// The name of the batch (if applicable)...
 5$batch->name;
 6 
 7// The number of jobs assigned to the batch...
 8$batch->totalJobs;
 9 
10// The number of jobs that have not been processed by the queue...
11$batch->pendingJobs;
12 
13// The number of jobs that have failed...
14$batch->failedJobs;
15 
16// The number of jobs that have been processed thus far...
17$batch->processedJobs();
18 
19// The completion percentage of the batch (0-100)...
20$batch->progress();
21 
22// Indicates if the batch has finished executing...
23$batch->finished();
24 
25// Cancel the execution of the batch...
26$batch->cancel();
27 
28// Indicates if the batch has been cancelled...
29$batch->cancelled();

// The UUID of the batch...
$batch->id;

// The name of the batch (if applicable)...
$batch->name;

// The number of jobs assigned to the batch...
$batch->totalJobs;

// The number of jobs that have not been processed by the queue...
$batch->pendingJobs;

// The number of jobs that have failed...
$batch->failedJobs;

// The number of jobs that have been processed thus far...
$batch->processedJobs();

// The completion percentage of the batch (0-100)...
$batch->progress();

// Indicates if the batch has finished executing...
$batch->finished();

// Cancel the execution of the batch...
$batch->cancel();

// Indicates if the batch has been cancelled...
$batch->cancelled();
```

#### Returning Batches From Routes

All `Illuminate\Bus\Batch` instances are JSON serializable, meaning you can return them directly from one of your application's routes to retrieve a JSON payload containing information about the batch, including its completion progress. This makes it convenient to display information about the batch's completion progress in your application's UI.

To retrieve a batch by its ID, you may use the `Bus` facade's `findBatch` method:

```
1use Illuminate\Support\Facades\Bus;
2use Illuminate\Support\Facades\Route;
3 
4Route::get('/batch/{batchId}', function (string $batchId) {
5    return Bus::findBatch($batchId);
6});

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;

Route::get('/batch/{batchId}', function (string $batchId) {
    return Bus::findBatch($batchId);
});
```

### Cancelling Batches

Sometimes you may need to cancel a given batch's execution. This can be accomplished by calling the `cancel` method on the `Illuminate\Bus\Batch` instance:

```
 1/**
 2 * Execute the job.
 3 */
 4public function handle(): void
 5{
 6    if ($this->user->exceedsImportLimit()) {
 7        $this->batch()->cancel();
 8 
 9        return;
10    }
11 
12    if ($this->batch()->cancelled()) {
13        return;
14    }
15}

/**
 * Execute the job.
 */
public function handle(): void
{
    if ($this->user->exceedsImportLimit()) {
        $this->batch()->cancel();

        return;
    }

    if ($this->batch()->cancelled()) {
        return;
    }
}
```

As you may have noticed in the previous examples, batched jobs should typically determine if their corresponding batch has been cancelled before continuing execution. However, for convenience, you may assign the `SkipIfBatchCancelled` middleware to the job instead. As its name indicates, this middleware will instruct Laravel to not process the job if its corresponding batch has been cancelled:

```
1use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
2 
3/**
4 * Get the middleware the job should pass through.
5 */
6public function middleware(): array
7{
8    return [new SkipIfBatchCancelled];
9}

use Illuminate\Queue\Middleware\SkipIfBatchCancelled;

/**
 * Get the middleware the job should pass through.
 */
public function middleware(): array
{
    return [new SkipIfBatchCancelled];
}
```

### Batch Failures

When a batched job fails, the `catch` callback (if assigned) will be invoked. This callback is only invoked for the first job that fails within the batch.

#### Allowing Failures

When a job within a batch fails, Laravel will automatically mark the batch as "cancelled". If you wish, you may disable this behavior so that a job failure does not automatically mark the batch as cancelled. This may be accomplished by calling the `allowFailures` method while dispatching the batch:

```
1$batch = Bus::batch([
2    // ...
3])->then(function (Batch $batch) {
4    // All jobs completed successfully...
5})->allowFailures()->dispatch();

$batch = Bus::batch([
    // ...
])->then(function (Batch $batch) {
    // All jobs completed successfully...
})->allowFailures()->dispatch();
```

You may optionally provide a closure to the `allowFailures` method, which will be executed on each job failure:

```
1$batch = Bus::batch([
2    // ...
3])->allowFailures(function (Batch $batch, $exception) {
4    // Handle individual job failures...
5})->dispatch();

$batch = Bus::batch([
    // ...
])->allowFailures(function (Batch $batch, $exception) {
    // Handle individual job failures...
})->dispatch();
```

#### Retrying Failed Batch Jobs

For convenience, Laravel provides a `queue:retry-batch` Artisan command that allows you to easily retry all of the failed jobs for a given batch. This command accepts the UUID of the batch whose failed jobs should be retried:

```
1php artisan queue:retry-batch 32dbc76c-4f82-4749-b610-a639fe0099b5

php artisan queue:retry-batch 32dbc76c-4f82-4749-b610-a639fe0099b5
```

### Pruning Batches

Without pruning, the `job_batches` table can accumulate records very quickly. To mitigate this, you should [schedule](https://laravel.com/framework/docs/scheduling) the `queue:prune-batches` Artisan command to run daily:

```
1use Illuminate\Support\Facades\Schedule;
2 
3Schedule::command('queue:prune-batches')->daily();

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:prune-batches')->daily();
```

By default, all finished batches that are more than 24 hours old will be pruned. You may use the `hours` option when calling the command to determine how long to retain batch data. For example, the following command will delete all batches that finished over 48 hours ago:

```
1use Illuminate\Support\Facades\Schedule;
2 
3Schedule::command('queue:prune-batches --hours=48')->daily();

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:prune-batches --hours=48')->daily();
```

Sometimes, your `job_batches` table may accumulate batch records for batches that never completed successfully, such as batches where a job failed and that job was never retried successfully. You may instruct the `queue:prune-batches` command to prune these unfinished batch records using the `unfinished` option:

```
1use Illuminate\Support\Facades\Schedule;
2 
3Schedule::command('queue:prune-batches --hours=48 --unfinished=72')->daily();

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:prune-batches --hours=48 --unfinished=72')->daily();
```

Likewise, your `job_batches` table may also accumulate batch records for cancelled batches. You may instruct the `queue:prune-batches` command to prune these cancelled batch records using the `cancelled` option:

```
1use Illuminate\Support\Facades\Schedule;
2 
3Schedule::command('queue:prune-batches --hours=48 --cancelled=72')->daily();

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:prune-batches --hours=48 --cancelled=72')->daily();
```

### Storing Batches in DynamoDB

Laravel also provides support for storing batch meta information in [DynamoDB](https://aws.amazon.com/dynamodb) instead of a relational database. However, you will need to manually create a DynamoDB table to store all of the batch records.

Typically, this table should be named `job_batches`, but you should name the table based on the value of the `queue.batching.table` configuration value within your application's `queue` configuration file.

#### DynamoDB Batch Table Configuration

The `job_batches` table should have a string primary partition key named `application` and a string primary sort key named `id`. The `application` portion of the key will contain your application's name as defined by the `name` configuration value within your application's `app` configuration file. Since the application name is part of the DynamoDB table's key, you can use the same table to store job batches for multiple Laravel applications.

In addition, you may define `ttl` attribute for your table if you would like to take advantage of automatic batch pruning.

#### DynamoDB Configuration

Next, install the AWS SDK so that your Laravel application can communicate with Amazon DynamoDB:

```
1composer require aws/aws-sdk-php

composer require aws/aws-sdk-php
```

Then, set the `queue.batching.driver` configuration option's value to `dynamodb`. In addition, you should define `key`, `secret`, and `region` configuration options within the `batching` configuration array. These options will be used to authenticate with AWS. When using the `dynamodb` driver, the `queue.batching.database` configuration option is unnecessary:

```
1'batching' => [
2    'driver' => env('QUEUE_BATCHING_DRIVER', 'dynamodb'),
3    'key' => env('AWS_ACCESS_KEY_ID'),
4    'secret' => env('AWS_SECRET_ACCESS_KEY'),
5    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
6    'table' => 'job_batches',
7],

'batching' => [
    'driver' => env('QUEUE_BATCHING_DRIVER', 'dynamodb'),
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'table' => 'job_batches',
],
```

#### Pruning Batches in DynamoDB

When utilizing [DynamoDB](https://aws.amazon.com/dynamodb) to store job batch information, the typical pruning commands used to prune batches stored in a relational database will not work. Instead, you may utilize [DynamoDB's native TTL functionality](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/TTL.html) to automatically remove records for old batches.

If you defined your DynamoDB table with a `ttl` attribute, you may define configuration parameters to instruct Laravel how to prune batch records. The `queue.batching.ttl_attribute` configuration value defines the name of the attribute holding the TTL, while the `queue.batching.ttl` configuration value defines the number of seconds after which a batch record can be removed from the DynamoDB table, relative to the last time the record was updated:

```
1'batching' => [
2    'driver' => env('QUEUE_BATCHING_DRIVER', 'dynamodb'),
3    'key' => env('AWS_ACCESS_KEY_ID'),
4    'secret' => env('AWS_SECRET_ACCESS_KEY'),
5    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
6    'table' => 'job_batches',
7    'ttl_attribute' => 'ttl',
8    'ttl' => 60 * 60 * 24 * 7, // 7 days...
9],

'batching' => [
    'driver' => env('QUEUE_BATCHING_DRIVER', 'dynamodb'),
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'table' => 'job_batches',
    'ttl_attribute' => 'ttl',
    'ttl' => 60 * 60 * 24 * 7, // 7 days...
],
```

## Queueing Closures

Instead of dispatching a job class to the queue, you may also dispatch a closure. This is great for quick, simple tasks that need to be executed outside of the current request cycle. When dispatching closures to the queue, the closure's code content is cryptographically signed so that it cannot be modified in transit:

```
1use App\Models\Podcast;
2 
3$podcast = Podcast::find(1);
4 
5dispatch(function () use ($podcast) {
6    $podcast->publish();
7});

use App\Models\Podcast;

$podcast = Podcast::find(1);

dispatch(function () use ($podcast) {
    $podcast->publish();
});
```

To assign a name to the queued closure which may be used by queue reporting dashboards, as well as be displayed by the `queue:work` command, you may use the `name` method:

```
1dispatch(function () {
2    // ...
3})->name('Publish Podcast');

dispatch(function () {
    // ...
})->name('Publish Podcast');
```

Using the `catch` method, you may provide a closure that should be executed if the queued closure fails to complete successfully after exhausting all of your queue's configured retry attempts:

```
1use Throwable;
2 
3dispatch(function () use ($podcast) {
4    $podcast->publish();
5})->catch(function (Throwable $e) {
6    // This job has failed...
7});

use Throwable;

dispatch(function () use ($podcast) {
    $podcast->publish();
})->catch(function (Throwable $e) {
    // This job has failed...
});
```

 Since `catch` callbacks are serialized and executed at a later time by the Laravel queue, you should not use the `$this` variable within `catch` callbacks.

## Running the Queue Worker

### The `queue:work` Command

Laravel includes an Artisan command that will start a queue worker and process new jobs as they are pushed onto the queue. You may run the worker using the `queue:work` Artisan command. Note that once the `queue:work` command has started, it will continue to run until it is manually stopped or you close your terminal:

```
1php artisan queue:work

php artisan queue:work
```

 To keep the `queue:work` process running permanently in the background, you should use a process monitor such as Supervisor to ensure that the queue worker does not stop running.

You may include the `-v` flag when invoking the `queue:work` command if you would like the processed job IDs, connection names, and queue names to be included in the command's output:

```
1php artisan queue:work -v

php artisan queue:work -v
```

Remember, queue workers are long-lived processes and store the booted application state in memory. As a result, they will not notice changes in your code base after they have been started. So, during your deployment process, be sure to restart your queue workers. In addition, remember that any static state created or modified by your application will not be automatically reset between jobs.

Alternatively, you may run the `queue:listen` command. When using the `queue:listen` command, you don't have to manually restart the worker when you want to reload your updated code or reset the application state; however, this command is significantly less efficient than the `queue:work` command:

```
1php artisan queue:listen

php artisan queue:listen
```

#### Running Multiple Queue Workers

To assign multiple workers to a queue and process jobs concurrently, you should simply start multiple `queue:work` processes. This can either be done locally via multiple tabs in your terminal or in production using your process manager's configuration settings. When using Supervisor, you may use the `numprocs` configuration value.

#### Specifying the Connection and Queue

You may also specify which queue connection the worker should utilize. The connection name passed to the `work` command should correspond to one of the connections defined in your `config/queue.php` configuration file:

```
1php artisan queue:work redis

php artisan queue:work redis
```

By default, the `queue:work` command only processes jobs for the default queue on a given connection. However, you may customize your queue worker even further by only processing particular queues for a given connection. For example, if all of your emails are processed in an `emails` queue on your `redis` queue connection, you may issue the following command to start a worker that only processes that queue:

```
1php artisan queue:work redis --queue=emails

php artisan queue:work redis --queue=emails
```

#### Processing a Specified Number of Jobs

The `--once` option may be used to instruct the worker to only process a single job from the queue:

```
1php artisan queue:work --once

php artisan queue:work --once
```

The `--max-jobs` option may be used to instruct the worker to process the given number of jobs and then exit. This option may be useful when combined with Supervisor so that your workers are automatically restarted after processing a given number of jobs, releasing any memory they may have accumulated:

```
1php artisan queue:work --max-jobs=1000

php artisan queue:work --max-jobs=1000
```

#### Processing All Queued Jobs and Then Exiting

The `--stop-when-empty` option may be used to instruct the worker to process all jobs and then exit gracefully. This option can be useful when processing Laravel queues within a Docker container if you wish to shutdown the container after the queue is empty:

```
1php artisan queue:work --stop-when-empty

php artisan queue:work --stop-when-empty
```

#### Processing Jobs for a Given Number of Seconds

The `--max-time` option may be used to instruct the worker to process jobs for the given number of seconds and then exit. This option may be useful when combined with Supervisor so that your workers are automatically restarted after processing jobs for a given amount of time, releasing any memory they may have accumulated:

```
1# Process jobs for one hour and then exit...
2php artisan queue:work --max-time=3600

# Process jobs for one hour and then exit...
php artisan queue:work --max-time=3600
```

#### Worker Sleep Duration

When jobs are available on the queue, the worker will keep processing jobs with no delay in between jobs. However, the `sleep` option determines how many seconds the worker will "sleep" if there are no jobs available. Of course, while sleeping, the worker will not process any new jobs:

```
1php artisan queue:work --sleep=3

php artisan queue:work --sleep=3
```

#### Maintenance Mode and Queues

While your application is in [maintenance mode](https://laravel.com/framework/docs/configuration#maintenance-mode), no queued jobs will be handled. The jobs will continue to be handled as normal once the application is out of maintenance mode.

To force your queue workers to process jobs even if maintenance mode is enabled, you may use `--force` option:

```
1php artisan queue:work --force

php artisan queue:work --force
```

#### Resource Considerations

The `--memory` option specifies the memory limit in megabytes at which the worker should exit. By default, this limit is 128 megabytes. You may also specify a percentage of PHP's configured `memory_limit`:

```
1php artisan queue:work --memory=60%

php artisan queue:work --memory=60%
```

Percentage-based limits require a finite PHP `memory_limit`. If PHP's memory limit is unlimited (`-1`), use a limit in megabytes instead. A process monitor such as Supervisor can automatically restart the worker after it exits.

Daemon queue workers do not "reboot" the framework before processing each job. Therefore, you should release any heavy resources after each job completes. For example, if you are doing [image manipulation](https://laravel.com/framework/docs/images) with the [GD library](https://www.php.net/manual/en/book.image.php), you should free the memory with `imagedestroy` when you are done processing the image.

### Queue Priorities

Sometimes you may wish to prioritize how your queues are processed. For example, in your `config/queue.php` configuration file, you may set the default `queue` for your `redis` connection to `low`. However, occasionally you may wish to push a job to a `high` priority queue like so:

```
1dispatch((new Job)->onQueue('high'));

dispatch((new Job)->onQueue('high'));
```

To start a worker that verifies that all of the `high` queue jobs are processed before continuing to any jobs on the `low` queue, pass a comma-delimited list of queue names to the `work` command:

```
1php artisan queue:work --queue=high,low

php artisan queue:work --queue=high,low
```

### Queue Workers and Deployment

Since queue workers are long-lived processes, they will not notice changes to your code without being restarted. So, the simplest way to deploy an application using queue workers is to restart the workers during your deployment process. You may gracefully restart all of the workers by issuing the `queue:restart` command:

```
1php artisan queue:restart

php artisan queue:restart
```

This command will instruct all queue workers to gracefully exit after they finish processing their current job so that no existing jobs are lost. Since the queue workers will exit when the `queue:restart` command is executed, you should be running a process manager such as Supervisor to automatically restart the queue workers.

 The queue uses the [cache](https://laravel.com/framework/docs/cache) to store restart signals, so you should verify that a cache driver is properly configured for your application before using this feature.

### Reacting to Worker Signals

When a queue worker receives a termination signal such as `SIGQUIT`, `SIGTERM`, or `SIGINT` while processing a job, the worker will finish its current job before exiting. However, your job may need to react to the signal before the process is stopped by your server or container orchestrator. For example, a long-running import job may need to stop pulling new records and save its current progress.

To react to worker signals from within a job, implement the `Illuminate\Contracts\Queue\Interruptible` interface and define an `interrupted` method on your job. The signal number received by the worker will be passed to the `interrupted` method:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use App\Models\Import;
 6use Illuminate\Contracts\Queue\Interruptible;
 7use Illuminate\Contracts\Queue\ShouldQueue;
 8use Illuminate\Foundation\Queue\Queueable;
 9 
10class ImportProducts implements ShouldQueue, Interruptible
11{
12    use Queueable;
13 
14    protected bool $shouldStop = false;
15 
16    /**
17     * Create a new job instance.
18     */
19    public function __construct(
20        public Import $import,
21    ) {}
22 
23    /**
24     * Execute the job.
25     */
26    public function handle(): void
27    {
28        foreach ($this->import->pendingRows() as $row) {
29            if ($this->shouldStop) {
30                break;
31            }
32 
33            // Import the product row...
34        }
35 
36        $this->import->saveProgress();
37    }
38 
39    /**
40     * Handle a signal received by the queue worker.
41     */
42    public function interrupted(int $signal): void
43    {
44        $this->shouldStop = true;
45    }
46}

<?php

namespace App\Jobs;

use App\Models\Import;
use Illuminate\Contracts\Queue\Interruptible;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportProducts implements ShouldQueue, Interruptible
{
    use Queueable;

    protected bool $shouldStop = false;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Import $import,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->import->pendingRows() as $row) {
            if ($this->shouldStop) {
                break;
            }

            // Import the product row...
        }

        $this->import->saveProgress();
    }

    /**
     * Handle a signal received by the queue worker.
     */
    public function interrupted(int $signal): void
    {
        $this->shouldStop = true;
    }
}
```

The `interrupted` method is only invoked when the worker receives a process signal while the job is currently running. It is not a replacement for timeouts or the job's `failed` method.

### Job Expirations and Timeouts

#### Job Expiration

In your `config/queue.php` configuration file, each queue connection defines a `retry_after` option. This option specifies how many seconds the queue connection should wait before retrying a job that is being processed. For example, if the value of `retry_after` is set to `90`, the job will be released back onto the queue if it has been processing for 90 seconds without being released or deleted. Typically, you should set the `retry_after` value to the maximum number of seconds your jobs should reasonably take to complete processing.

 The only queue connection which does not contain a `retry_after` value is Amazon SQS. SQS will retry the job based on the [Default Visibility Timeout](https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/AboutVT.html) which is managed within the AWS console.

#### Worker Timeouts

The `queue:work` Artisan command exposes a `--timeout` option. By default, the `--timeout` value is 60 seconds. If a job is processing for longer than the number of seconds specified by the timeout value, the worker processing the job will exit with an error. Typically, the worker will be restarted automatically by a process manager configured on your server:

```
1php artisan queue:work --timeout=60

php artisan queue:work --timeout=60
```

The `retry_after` configuration option and the `--timeout` CLI option are different, but work together to ensure that jobs are not lost and that jobs are only successfully processed once.

 The `--timeout` value should always be at least several seconds shorter than your `retry_after` configuration value. This will ensure that a worker processing a frozen job is always terminated before the job is retried. If your `--timeout` option is longer than your `retry_after` configuration value, your jobs may be processed twice.

### Pausing and Resuming Queue Workers

Sometimes you may need to temporarily prevent a queue worker from processing new jobs without stopping the worker entirely. For example, you may want to pause job processing during system maintenance. Laravel provides the `queue:pause` and `queue:continue` Artisan commands to pause and resume queue workers.

To pause a specific queue, provide the queue connection name and the queue name:

```
1php artisan queue:pause database:default

php artisan queue:pause database:default
```

In this example, `database` is the queue connection name and `default` is the queue name. Once a queue is paused, any workers processing jobs from that queue will continue to finish their current job, but will not pick up any new jobs until the queue is resumed.

To pause job processing for every queue on every connection, use the `--all` option:

```
1php artisan queue:pause --all

php artisan queue:pause --all
```

To resume processing jobs on a paused queue, use the `queue:continue` command:

```
1php artisan queue:continue database:default

php artisan queue:continue database:default
```

To resume job processing for every queue on every connection, use the `--all` option with the `queue:resume` command:

```
1php artisan queue:resume --all

php artisan queue:resume --all
```

After resuming a queue, workers will begin processing new jobs from that queue immediately. Resuming all queues does not resume queues that were paused individually. Note that pausing a queue does not stop the worker process itself - it only prevents the worker from processing new jobs from the specified queue.

#### Worker Restart and Pause Signals

By default, queue workers poll the cache driver for restart and pause signals on each job iteration. While this polling is essential for responding to `queue:restart` and `queue:pause` commands, it does introduce a small performance overhead.

If you need to optimize performance and don't require these interruption features, you may disable this polling globally by calling the `withoutInterruptionPolling` method on the `Queue` facade. This should typically be done in the `boot` method of your `AppServiceProvider`:

```
1use Illuminate\Support\Facades\Queue;
2 
3/**
4 * Bootstrap any application services.
5 */
6public function boot(): void
7{
8    Queue::withoutInterruptionPolling();
9}

use Illuminate\Support\Facades\Queue;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Queue::withoutInterruptionPolling();
}
```

Alternatively, you may disable restart or pause polling individually by setting the static `$restartable` or `$pausable` properties on the `Illuminate\Queue\Worker` class:

```
 1use Illuminate\Queue\Worker;
 2 
 3/**
 4 * Bootstrap any application services.
 5 */
 6public function boot(): void
 7{
 8    Worker::$restartable = false;
 9    Worker::$pausable = false;
10}

use Illuminate\Queue\Worker;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Worker::$restartable = false;
    Worker::$pausable = false;
}
```

 When interruption polling is disabled, workers will not respond to `queue:restart` or `queue:pause` commands (depending on which features are disabled).

## Supervisor Configuration

In production, you need a way to keep your `queue:work` processes running. A `queue:work` process may stop running for a variety of reasons, such as an exceeded worker timeout or the execution of the `queue:restart` command.

For this reason, you need to configure a process monitor that can detect when your `queue:work` processes exit and automatically restart them. In addition, process monitors can allow you to specify how many `queue:work` processes you would like to run concurrently. Supervisor is a process monitor commonly used in Linux environments and we will discuss how to configure it in the following documentation.

#### Installing Supervisor

Supervisor is a process monitor for the Linux operating system, and will automatically restart your `queue:work` processes if they fail. To install Supervisor on Ubuntu, you may use the following command:

```
1sudo apt-get install supervisor

sudo apt-get install supervisor
```

 If configuring and managing Supervisor yourself sounds overwhelming, consider using [Laravel Cloud](https://cloud.laravel.com), which provides a fully-managed platform for running Laravel queue workers.

#### Configuring Supervisor

Supervisor configuration files are typically stored in the `/etc/supervisor/conf.d` directory. Within this directory, you may create any number of configuration files that instruct supervisor how your processes should be monitored. For example, let's create a `laravel-worker.conf` file that starts and monitors `queue:work` processes:

```
 1[program:laravel-worker]
 2process_name=%(program_name)s_%(process_num)02d
 3command=php /home/forge/app.com/artisan queue:work --sleep=3 --tries=3 --max-time=3600
 4autostart=true
 5autorestart=true
 6stopasgroup=true
 7killasgroup=true
 8user=forge
 9numprocs=8
10redirect_stderr=true
11stdout_logfile=/home/forge/app.com/worker.log
12stopwaitsecs=3600

[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/forge/app.com/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=forge
numprocs=8
redirect_stderr=true
stdout_logfile=/home/forge/app.com/worker.log
stopwaitsecs=3600
```

In this example, the `numprocs` directive will instruct Supervisor to run eight `queue:work` processes and monitor all of them, automatically restarting them if they fail. You should change the `command` directive of the configuration to reflect your desired queue connection and worker options.

 You should ensure that the value of `stopwaitsecs` is greater than the number of seconds consumed by your longest running job. Otherwise, Supervisor may kill the job before it is finished processing.

#### Starting Supervisor

Once the configuration file has been created, you may update the Supervisor configuration and start the processes using the following commands:

```
1sudo supervisorctl reread
2 
3sudo supervisorctl update
4 
5sudo supervisorctl start "laravel-worker:*"

sudo supervisorctl reread

sudo supervisorctl update

sudo supervisorctl start "laravel-worker:*"
```

For more information on Supervisor, consult the [Supervisor documentation](http://supervisord.org/index.html).

## Dealing With Failed Jobs

Sometimes your queued jobs will fail. Don't worry, things don't always go as planned! Laravel includes a convenient way to specify the maximum number of times a job should be attempted. After an asynchronous job has exceeded this number of attempts, it will be inserted into the `failed_jobs` database table. [Synchronously dispatched jobs](https://laravel.com/framework/docs/queues#synchronous-dispatching) that fail are not stored in this table and their exceptions are immediately handled by the application.

A migration to create the `failed_jobs` table is typically already present in new Laravel applications. However, if your application does not contain a migration for this table, you may use the `make:queue-failed-table` command to create the migration:

```
1php artisan make:queue-failed-table
2 
3php artisan migrate

php artisan make:queue-failed-table

php artisan migrate
```

When running a queue worker process, you may specify the maximum number of times a job should be attempted using the `--tries` switch on the `queue:work` command. If you do not specify a value for the `--tries` option, jobs will only be attempted once or as many times as specified by the job class' `Tries` attribute:

```
1php artisan queue:work redis --tries=3

php artisan queue:work redis --tries=3
```

Using the `--backoff` option, you may specify how many seconds Laravel should wait before retrying a job that has encountered an exception. By default, a job is immediately released back onto the queue so that it may be attempted again:

```
1php artisan queue:work redis --tries=3 --backoff=3

php artisan queue:work redis --tries=3 --backoff=3
```

If you would like to configure how many seconds Laravel should wait before retrying a job that has encountered an exception on a per-job basis, you may use the `Backoff` attribute on your job class:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use Illuminate\Queue\Attributes\Backoff;
 6 
 7#[Backoff(3)]
 8class ProcessPodcast implements ShouldQueue
 9{
10    // ...
11}

<?php

namespace App\Jobs;

use Illuminate\Queue\Attributes\Backoff;

#[Backoff(3)]
class ProcessPodcast implements ShouldQueue
{
    // ...
}
```

If you require more complex logic for determining the job's backoff time, you may define a `backoff` method on your job class:

```
1/**
2 * Calculate the number of seconds to wait before retrying the job.
3 */
4public function backoff(): int
5{
6    return 3;
7}

/**
 * Calculate the number of seconds to wait before retrying the job.
 */
public function backoff(): int
{
    return 3;
}
```

You may easily configure "exponential" backoffs by defining an array of backoff values. In this example, the retry delay will be 1 second for the first retry, 5 seconds for the second retry, 10 seconds for the third retry, and 10 seconds for every subsequent retry if there are more attempts remaining:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use Illuminate\Queue\Attributes\Backoff;
 6 
 7#[Backoff([1, 5, 10])]
 8class ProcessPodcast implements ShouldQueue
 9{
10    // ...
11}

<?php

namespace App\Jobs;

use Illuminate\Queue\Attributes\Backoff;

#[Backoff([1, 5, 10])]
class ProcessPodcast implements ShouldQueue
{
    // ...
}
```

### Cleaning Up After Failed Jobs

When a particular job fails, you may want to send an alert to your users or revert any actions that were partially completed by the job. To accomplish this, you may define a `failed` method on your job class. The `Throwable` instance that caused the job to fail will be passed to the `failed` method:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use App\Models\Podcast;
 6use App\Services\AudioProcessor;
 7use Illuminate\Contracts\Queue\ShouldQueue;
 8use Illuminate\Foundation\Queue\Queueable;
 9use Throwable;
10 
11class ProcessPodcast implements ShouldQueue
12{
13    use Queueable;
14 
15    /**
16     * Create a new job instance.
17     */
18    public function __construct(
19        public Podcast $podcast,
20    ) {}
21 
22    /**
23     * Execute the job.
24     */
25    public function handle(AudioProcessor $processor): void
26    {
27        // Process uploaded podcast...
28    }
29 
30    /**
31     * Handle a job failure.
32     */
33    public function failed(?Throwable $exception): void
34    {
35        // Send user notification of failure, etc...
36    }
37}

<?php

namespace App\Jobs;

use App\Models\Podcast;
use App\Services\AudioProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessPodcast implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Podcast $podcast,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AudioProcessor $processor): void
    {
        // Process uploaded podcast...
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        // Send user notification of failure, etc...
    }
}
```

 A new instance of the job is instantiated before invoking the `failed` method; therefore, any class property modifications that may have occurred within the `handle` method will be lost.

A failed job is not necessarily one that encountered an unhandled exception. A job may also be considered failed when it has exhausted all of its allowed attempts. These attempts can be consumed in several ways:

- The job timed out.
- The job encounters an unhandled exception during execution.
- The job is released back to the queue either manually or by a middleware.

If the final attempt fails due to an exception thrown during job execution, that exception will be passed to the job's `failed` method. However, if the job fails because it has reached the maximum number of allowed attempts, the `$exception` will be an instance of `Illuminate\Queue\MaxAttemptsExceededException`. Similarly, if the job fails due to exceeding the configured timeout, the `$exception` will be an instance of `Illuminate\Queue\TimeoutExceededException`.

### Retrying Failed Jobs

To view all of the failed jobs that have been inserted into your `failed_jobs` database table, you may use the `queue:failed` Artisan command:

```
1php artisan queue:failed

php artisan queue:failed
```

The `queue:failed` command will list the job ID, connection, queue, failure time, and other information about the job. The job ID may be used to retry the failed job. For instance, to retry a failed job that has an ID of `ce7bb17c-cdd8-41f0-a8ec-7b4fef4e5ece`, issue the following command:

```
1php artisan queue:retry ce7bb17c-cdd8-41f0-a8ec-7b4fef4e5ece

php artisan queue:retry ce7bb17c-cdd8-41f0-a8ec-7b4fef4e5ece
```

If necessary, you may pass multiple IDs to the command:

```
1php artisan queue:retry ce7bb17c-cdd8-41f0-a8ec-7b4fef4e5ece 91401d2c-0784-4f43-824c-34f94a33c24d

php artisan queue:retry ce7bb17c-cdd8-41f0-a8ec-7b4fef4e5ece 91401d2c-0784-4f43-824c-34f94a33c24d
```

You may also retry all of the failed jobs for a particular queue:

```
1php artisan queue:retry --queue=name

php artisan queue:retry --queue=name
```

To retry all of your failed jobs, execute the `queue:retry` command and pass `all` as the ID:

```
1php artisan queue:retry all

php artisan queue:retry all
```

If you would like to delete a failed job, you may use the `queue:forget` command:

```
1php artisan queue:forget 91401d2c-0784-4f43-824c-34f94a33c24d

php artisan queue:forget 91401d2c-0784-4f43-824c-34f94a33c24d
```

 When using [Horizon](https://laravel.com/framework/docs/horizon), you should use the `horizon:forget` command to delete a failed job instead of the `queue:forget` command.

To delete all of your failed jobs from the `failed_jobs` table, you may use the `queue:flush` command:

```
1php artisan queue:flush

php artisan queue:flush
```

The `queue:flush` command removes all failed job records from your queue, no matter how old the failed job is. You may use the `--hours` option to only delete jobs that failed a certain number of hours ago or earlier:

```
1php artisan queue:flush --hours=48

php artisan queue:flush --hours=48
```

### Ignoring Missing Models

When injecting an Eloquent model into a job, the model is automatically serialized before being placed on the queue and re-retrieved from the database when the job is processed. However, if the model has been deleted while the job was waiting to be processed by a worker, your job may fail with a `ModelNotFoundException`.

For convenience, you may choose to automatically delete jobs with missing models using the `DeleteWhenMissingModels` attribute on your job class. When this attribute is present, Laravel will quietly discard the job without raising an exception:

```
 1<?php
 2 
 3namespace App\Jobs;
 4 
 5use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
 6 
 7#[DeleteWhenMissingModels]
 8class ProcessPodcast implements ShouldQueue
 9{
10    // ...
11}

<?php

namespace App\Jobs;

use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

#[DeleteWhenMissingModels]
class ProcessPodcast implements ShouldQueue
{
    // ...
}
```

### Pruning Failed Jobs

You may prune the records in your application's `failed_jobs` table by invoking the `queue:prune-failed` Artisan command:

```
1php artisan queue:prune-failed

php artisan queue:prune-failed
```

By default, all the failed job records that are more than 24 hours old will be pruned. If you provide the `--hours` option to the command, only the failed job records that were inserted within the last N number of hours will be retained. For example, the following command will delete all the failed job records that were inserted more than 48 hours ago:

```
1php artisan queue:prune-failed --hours=48

php artisan queue:prune-failed --hours=48
```

### Storing Failed Jobs in DynamoDB

Laravel also provides support for storing your failed job records in [DynamoDB](https://aws.amazon.com/dynamodb) instead of a relational database table. However, you must manually create a DynamoDB table to store all of the failed job records. Typically, this table should be named `failed_jobs`, but you should name the table based on the value of the `queue.failed.table` configuration value within your application's `queue` configuration file.

The `failed_jobs` table should have a string primary partition key named `application` and a string primary sort key named `uuid`. The `application` portion of the key will contain your application's name as defined by the `name` configuration value within your application's `app` configuration file. Since the application name is part of the DynamoDB table's key, you can use the same table to store failed jobs for multiple Laravel applications.

In addition, ensure that you install the AWS SDK so that your Laravel application can communicate with Amazon DynamoDB:

```
1composer require aws/aws-sdk-php

composer require aws/aws-sdk-php
```

Next, set the `queue.failed.driver` configuration option's value to `dynamodb`. In addition, you should define `key`, `secret`, and `region` configuration options within the failed job configuration array. These options will be used to authenticate with AWS. When using the `dynamodb` driver, the `queue.failed.database` configuration option is unnecessary:

```
1'failed' => [
2    'driver' => env('QUEUE_FAILED_DRIVER', 'dynamodb'),
3    'key' => env('AWS_ACCESS_KEY_ID'),
4    'secret' => env('AWS_SECRET_ACCESS_KEY'),
5    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
6    'table' => 'failed_jobs',
7],

'failed' => [
    'driver' => env('QUEUE_FAILED_DRIVER', 'dynamodb'),
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'table' => 'failed_jobs',
],
```

### Disabling Failed Job Storage

You may instruct Laravel to discard failed jobs without storing them by setting the `queue.failed.driver` configuration option's value to `null`. Typically, this may be accomplished via the `QUEUE_FAILED_DRIVER` environment variable:

```
1QUEUE_FAILED_DRIVER=null

QUEUE_FAILED_DRIVER=null
```

### Failed Job Events

If you would like to register an event listener that will be invoked when a job fails, you may use the `Queue` facade's `failing` method. For example, we may attach a closure to this event from the `boot` method of the `AppServiceProvider` that is included with Laravel:

```
 1<?php
 2 
 3namespace App\Providers;
 4 
 5use Illuminate\Support\Facades\Queue;
 6use Illuminate\Support\ServiceProvider;
 7use Illuminate\Queue\Events\JobFailed;
 8 
 9class AppServiceProvider extends ServiceProvider
10{
11    /**
12     * Register any application services.
13     */
14    public function register(): void
15    {
16        // ...
17    }
18 
19    /**
20     * Bootstrap any application services.
21     */
22    public function boot(): void
23    {
24        Queue::failing(function (JobFailed $event) {
25            // $event->connectionName
26            // $event->job
27            // $event->exception
28        });
29    }
30}

<?php

namespace App\Providers;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\Events\JobFailed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ...
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::failing(function (JobFailed $event) {
            // $event->connectionName
            // $event->job
            // $event->exception
        });
    }
}
```

## Clearing Jobs From Queues

 When using [Horizon](https://laravel.com/framework/docs/horizon), you should use the `horizon:clear` command to clear jobs from the queue instead of the `queue:clear` command.

If you would like to delete all jobs from the default queue of the default connection, you may do so using the `queue:clear` Artisan command:

```
1php artisan queue:clear

php artisan queue:clear
```

You may also provide the `connection` argument and `queue` option to delete jobs from a specific connection and queue:

```
1php artisan queue:clear redis --queue=emails

php artisan queue:clear redis --queue=emails
```

 Clearing jobs from queues is only available for the SQS, Redis, and database queue drivers. In addition, the SQS message deletion process takes up to 60 seconds, so jobs sent to the SQS queue up to 60 seconds after you clear the queue might also be deleted.

## Monitoring Your Queues

If your queue receives a sudden influx of jobs, it could become overwhelmed, leading to a long wait time for jobs to complete. If you wish, Laravel can alert you when your queue job count exceeds a specified threshold.

To get started, you should schedule the `queue:monitor` command to [run every minute](https://laravel.com/framework/docs/scheduling). The command accepts the names of the queues you wish to monitor as well as your desired job count threshold:

```
1php artisan queue:monitor redis:default,redis:deployments --max=100

php artisan queue:monitor redis:default,redis:deployments --max=100
```

Scheduling this command alone is not enough to trigger a notification alerting you of the queue's overwhelmed status. When the command encounters a queue that has a job count exceeding your threshold, an `Illuminate\Queue\Events\QueueBusy` event will be dispatched. You may listen for this event within your application's `AppServiceProvider` in order to send a notification to you or your development team:

```
 1use App\Notifications\QueueHasLongWaitTime;
 2use Illuminate\Queue\Events\QueueBusy;
 3use Illuminate\Support\Facades\Event;
 4use Illuminate\Support\Facades\Notification;
 5 
 6/**
 7 * Bootstrap any application services.
 8 */
 9public function boot(): void
10{
11    Event::listen(function (QueueBusy $event) {
12        Notification::route('mail', '[email protected]')
13            ->notify(new QueueHasLongWaitTime(
14                $event->connectionName,
15                $event->queue,
16                $event->size
17            ));
18    });
19}

use App\Notifications\QueueHasLongWaitTime;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Event::listen(function (QueueBusy $event) {
        Notification::route('mail', '[email protected]')
            ->notify(new QueueHasLongWaitTime(
                $event->connectionName,
                $event->queue,
                $event->size
            ));
    });
}
```

## Testing

When testing code that dispatches jobs, you may wish to instruct Laravel to not actually execute the job itself, since the job's code can be tested directly and separately of the code that dispatches it. Of course, to test the job itself, you may instantiate a job instance and invoke the `handle` method directly in your test.

You may use the `Queue` facade's `fake` method to prevent queued jobs from actually being pushed to the queue. After calling the `Queue` facade's `fake` method, you may then assert that the application attempted to push jobs to the queue:

```
 1<?php
 2 
 3use App\Jobs\AnotherJob;
 4use App\Jobs\ShipOrder;
 5use Illuminate\Support\Facades\Queue;
 6 
 7test('orders can be shipped', function () {
 8    Queue::fake();
 9 
10    // Perform order shipping...
11 
12    // Assert that no jobs were pushed...
13    Queue::assertNothingPushed();
14 
15    // Assert a job was pushed to a given queue...
16    Queue::assertPushedOn('queue-name', ShipOrder::class);
17 
18    // Assert a job was pushed
19    Queue::assertPushed(ShipOrder::class);
20 
21    // Assert a job was pushed exactly once...
22    Queue::assertPushedOnce(ShipOrder::class);
23 
24    // Assert a job was pushed twice...
25    Queue::assertPushedTimes(ShipOrder::class, 2);
26 
27    // Assert a job was not pushed...
28    Queue::assertNotPushed(AnotherJob::class);
29 
30    // Assert that a closure was pushed to the queue...
31    Queue::assertClosurePushed();
32 
33    // Assert that a closure was not pushed...
34    Queue::assertClosureNotPushed();
35 
36    // Assert the total number of jobs that were pushed...
37    Queue::assertCount(3);
38});

<?php

use App\Jobs\AnotherJob;
use App\Jobs\ShipOrder;
use Illuminate\Support\Facades\Queue;

test('orders can be shipped', function () {
    Queue::fake();

    // Perform order shipping...

    // Assert that no jobs were pushed...
    Queue::assertNothingPushed();

    // Assert a job was pushed to a given queue...
    Queue::assertPushedOn('queue-name', ShipOrder::class);

    // Assert a job was pushed
    Queue::assertPushed(ShipOrder::class);

    // Assert a job was pushed exactly once...
    Queue::assertPushedOnce(ShipOrder::class);

    // Assert a job was pushed twice...
    Queue::assertPushedTimes(ShipOrder::class, 2);

    // Assert a job was not pushed...
    Queue::assertNotPushed(AnotherJob::class);

    // Assert that a closure was pushed to the queue...
    Queue::assertClosurePushed();

    // Assert that a closure was not pushed...
    Queue::assertClosureNotPushed();

    // Assert the total number of jobs that were pushed...
    Queue::assertCount(3);
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use App\Jobs\AnotherJob;
 6use App\Jobs\ShipOrder;
 7use Illuminate\Support\Facades\Queue;
 8use Tests\TestCase;
 9 
10class ExampleTest extends TestCase
11{
12    public function test_orders_can_be_shipped(): void
13    {
14        Queue::fake();
15 
16        // Perform order shipping...
17 
18        // Assert that no jobs were pushed...
19        Queue::assertNothingPushed();
20 
21        // Assert a job was pushed to a given queue...
22        Queue::assertPushedOn('queue-name', ShipOrder::class);
23 
24        // Assert a job was pushed
25        Queue::assertPushed(ShipOrder::class);
26 
27        // Assert a job was pushed exactly once...
28        Queue::assertPushedOnce(ShipOrder::class);
29 
30        // Assert a job was pushed twice...
31        Queue::assertPushedTimes(ShipOrder::class, 2);
32 
33        // Assert a job was not pushed...
34        Queue::assertNotPushed(AnotherJob::class);
35 
36        // Assert that a closure was pushed to the queue...
37        Queue::assertClosurePushed();
38 
39        // Assert that a closure was not pushed...
40        Queue::assertClosureNotPushed();
41 
42        // Assert the total number of jobs that were pushed...
43        Queue::assertCount(3);
44    }
45}

<?php

namespace Tests\Feature;

use App\Jobs\AnotherJob;
use App\Jobs\ShipOrder;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_orders_can_be_shipped(): void
    {
        Queue::fake();

        // Perform order shipping...

        // Assert that no jobs were pushed...
        Queue::assertNothingPushed();

        // Assert a job was pushed to a given queue...
        Queue::assertPushedOn('queue-name', ShipOrder::class);

        // Assert a job was pushed
        Queue::assertPushed(ShipOrder::class);

        // Assert a job was pushed exactly once...
        Queue::assertPushedOnce(ShipOrder::class);

        // Assert a job was pushed twice...
        Queue::assertPushedTimes(ShipOrder::class, 2);

        // Assert a job was not pushed...
        Queue::assertNotPushed(AnotherJob::class);

        // Assert that a closure was pushed to the queue...
        Queue::assertClosurePushed();

        // Assert that a closure was not pushed...
        Queue::assertClosureNotPushed();

        // Assert the total number of jobs that were pushed...
        Queue::assertCount(3);
    }
}
```

You may pass a closure to the `assertPushed`, `assertNotPushed`, `assertClosurePushed`, or `assertClosureNotPushed` methods in order to assert that a job was pushed that passes a given "truth test". If at least one job was pushed that passes the given truth test then the assertion will be successful:

```
1use Illuminate\Queue\CallQueuedClosure;
2 
3Queue::assertPushed(function (ShipOrder $job) use ($order) {
4    return $job->order->id === $order->id;
5});
6 
7Queue::assertClosurePushed(function (CallQueuedClosure $job) {
8    return $job->name === 'validate-order';
9});

use Illuminate\Queue\CallQueuedClosure;

Queue::assertPushed(function (ShipOrder $job) use ($order) {
    return $job->order->id === $order->id;
});

Queue::assertClosurePushed(function (CallQueuedClosure $job) {
    return $job->name === 'validate-order';
});
```

Alternatively, you may pass an array of expected property values as the second argument to `assertPushed` or `assertNotPushed`, or as the third argument to `assertPushedOn`:

```
1Queue::assertPushed(ShipOrder::class, ['order' => $order]);
2 
3Queue::assertPushedOn('shipping', ShipOrder::class, ['order' => $order]);

Queue::assertPushed(ShipOrder::class, ['order' => $order]);

Queue::assertPushedOn('shipping', ShipOrder::class, ['order' => $order]);
```

All specified properties must match. Values are compared using strict equality, while Eloquent models are compared using their `is` method. The `Bus` facade's `assertDispatched`, `assertNotDispatched`, `assertDispatchedSync`, and `assertDispatchedAfterResponse` methods also accept an array of expected property values as their second argument.

### Faking a Subset of Jobs

If you only need to fake specific jobs while allowing your other jobs to execute normally, you may pass the class names of the jobs that should be faked to the `fake` method:

```
 1test('orders can be shipped', function () {
 2    Queue::fake([
 3        ShipOrder::class,
 4    ]);
 5 
 6    // Perform order shipping...
 7 
 8    // Assert a job was pushed twice...
 9    Queue::assertPushedTimes(ShipOrder::class, 2);
10});

test('orders can be shipped', function () {
    Queue::fake([
        ShipOrder::class,
    ]);

    // Perform order shipping...

    // Assert a job was pushed twice...
    Queue::assertPushedTimes(ShipOrder::class, 2);
});
```

```
 1public function test_orders_can_be_shipped(): void
 2{
 3    Queue::fake([
 4        ShipOrder::class,
 5    ]);
 6 
 7    // Perform order shipping...
 8 
 9    // Assert a job was pushed twice...
10    Queue::assertPushedTimes(ShipOrder::class, 2);
11}

public function test_orders_can_be_shipped(): void
{
    Queue::fake([
        ShipOrder::class,
    ]);

    // Perform order shipping...

    // Assert a job was pushed twice...
    Queue::assertPushedTimes(ShipOrder::class, 2);
}
```

You may fake all jobs except for a set of specified jobs using the `except` method:

```
1Queue::fake()->except([
2    ShipOrder::class,
3]);

Queue::fake()->except([
    ShipOrder::class,
]);
```

### Testing Job Chains

To test job chains, you will need to utilize the `Bus` facade's faking capabilities. The `Bus` facade's `assertChained` method may be used to assert that a [chain of jobs](https://laravel.com/framework/docs/queues#job-chaining) was dispatched. The `assertChained` method accepts an array of chained jobs as its first argument:

```
 1use App\Jobs\RecordShipment;
 2use App\Jobs\ShipOrder;
 3use App\Jobs\UpdateInventory;
 4use Illuminate\Support\Facades\Bus;
 5 
 6Bus::fake();
 7 
 8// ...
 9 
10Bus::assertChained([
11    ShipOrder::class,
12    RecordShipment::class,
13    UpdateInventory::class
14]);

use App\Jobs\RecordShipment;
use App\Jobs\ShipOrder;
use App\Jobs\UpdateInventory;
use Illuminate\Support\Facades\Bus;

Bus::fake();

// ...

Bus::assertChained([
    ShipOrder::class,
    RecordShipment::class,
    UpdateInventory::class
]);
```

As you can see in the example above, the array of chained jobs may be an array of the job's class names. However, you may also provide an array of actual job instances. When doing so, Laravel will ensure that the job instances are of the same class and have the same property values of the chained jobs dispatched by your application:

```
1Bus::assertChained([
2    new ShipOrder,
3    new RecordShipment,
4    new UpdateInventory,
5]);

Bus::assertChained([
    new ShipOrder,
    new RecordShipment,
    new UpdateInventory,
]);
```

You may use the `assertDispatchedWithoutChain` method to assert that a job was pushed without a chain of jobs:

```
1Bus::assertDispatchedWithoutChain(ShipOrder::class);

Bus::assertDispatchedWithoutChain(ShipOrder::class);
```

#### Testing Chain Modifications

If a chained job prepends or appends jobs to an existing chain, you may use the job's `assertHasChain` method to assert that the job has the expected chain of remaining jobs:

```
1$job = new ProcessPodcast;
2 
3$job->handle();
4 
5$job->assertHasChain([
6    new TranscribePodcast,
7    new OptimizePodcast,
8    new ReleasePodcast,
9]);

$job = new ProcessPodcast;

$job->handle();

$job->assertHasChain([
    new TranscribePodcast,
    new OptimizePodcast,
    new ReleasePodcast,
]);
```

The `assertDoesntHaveChain` method may be used to assert that the job's remaining chain is empty:

```
1$job->assertDoesntHaveChain();

$job->assertDoesntHaveChain();
```

#### Testing Chained Batches

If your job chain contains a batch of jobs, you may assert that the chained batch matches your expectations by inserting a `Bus::chainedBatch` definition within your chain assertion:

```
 1use App\Jobs\ShipOrder;
 2use App\Jobs\UpdateInventory;
 3use Illuminate\Bus\PendingBatch;
 4use Illuminate\Support\Facades\Bus;
 5 
 6Bus::assertChained([
 7    new ShipOrder,
 8    Bus::chainedBatch(function (PendingBatch $batch) {
 9        return $batch->jobs->count() === 3;
10    }),
11    new UpdateInventory,
12]);

use App\Jobs\ShipOrder;
use App\Jobs\UpdateInventory;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

Bus::assertChained([
    new ShipOrder,
    Bus::chainedBatch(function (PendingBatch $batch) {
        return $batch->jobs->count() === 3;
    }),
    new UpdateInventory,
]);
```

### Testing Job Batches

The `Bus` facade's `assertBatched` method may be used to assert that a [batch of jobs](https://laravel.com/framework/docs/queues#job-batching) was dispatched. The closure given to the `assertBatched` method receives an instance of `Illuminate\Bus\PendingBatch`, which may be used to inspect the jobs within the batch:

```
 1use Illuminate\Bus\PendingBatch;
 2use Illuminate\Support\Facades\Bus;
 3 
 4Bus::fake();
 5 
 6// ...
 7 
 8Bus::assertBatched(function (PendingBatch $batch) {
 9    return $batch->name == 'Import CSV' &&
10           $batch->jobs->count() === 10;
11});

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

Bus::fake();

// ...

Bus::assertBatched(function (PendingBatch $batch) {
    return $batch->name == 'Import CSV' &&
           $batch->jobs->count() === 10;
});
```

The `hasJobs` method may be used on the pending batch to verify that the batch contains the expected jobs. The method accepts an array of job instances, class names, or closures:

```
1Bus::assertBatched(function (PendingBatch $batch) {
2    return $batch->hasJobs([
3        new ProcessCsvRow(row: 1),
4        new ProcessCsvRow(row: 2),
5        new ProcessCsvRow(row: 3),
6    ]);
7});

Bus::assertBatched(function (PendingBatch $batch) {
    return $batch->hasJobs([
        new ProcessCsvRow(row: 1),
        new ProcessCsvRow(row: 2),
        new ProcessCsvRow(row: 3),
    ]);
});
```

When using closures, the closure will receive the job instance. The expected job type will be inferred from the closure's type hint:

```
1Bus::assertBatched(function (PendingBatch $batch) {
2    return $batch->hasJobs([
3        fn (ProcessCsvRow $job) => $job->row === 1,
4        fn (ProcessCsvRow $job) => $job->row === 2,
5        fn (ProcessCsvRow $job) => $job->row === 3,
6    ]);
7});

Bus::assertBatched(function (PendingBatch $batch) {
    return $batch->hasJobs([
        fn (ProcessCsvRow $job) => $job->row === 1,
        fn (ProcessCsvRow $job) => $job->row === 2,
        fn (ProcessCsvRow $job) => $job->row === 3,
    ]);
});
```

You may use the `assertBatchCount` method to assert that a given number of batches were dispatched:

```
1Bus::assertBatchCount(3);

Bus::assertBatchCount(3);
```

You may use `assertNothingBatched` to assert that no batches were dispatched:

```
1Bus::assertNothingBatched();

Bus::assertNothingBatched();
```

#### Testing Job / Batch Interaction

In addition, you may occasionally need to test an individual job's interaction with its underlying batch. For example, you may need to test if a job cancelled further processing for its batch. To accomplish this, you need to assign a fake batch to the job via the `withFakeBatch` method. The `withFakeBatch` method returns a tuple containing the job instance and the fake batch:

```
1[$job, $batch] = (new ShipOrder)->withFakeBatch();
2 
3$job->handle();
4 
5$this->assertTrue($batch->cancelled());
6$this->assertEmpty($batch->added);

[$job, $batch] = (new ShipOrder)->withFakeBatch();

$job->handle();

$this->assertTrue($batch->cancelled());
$this->assertEmpty($batch->added);
```

### Testing Job / Queue Interactions

Sometimes, you may need to test that a queued job releases itself back onto the queue. Or, you may need to test that the job deleted itself. You may test these queue interactions by instantiating the job and invoking the `withFakeQueueInteractions` method.

Once the job's queue interactions have been faked, you may invoke the `handle` method on the job. After invoking the job, various assertion methods are available to verify the job's queue interactions:

```
 1use App\Exceptions\CorruptedAudioException;
 2use App\Jobs\ProcessPodcast;
 3 
 4$job = (new ProcessPodcast)->withFakeQueueInteractions();
 5 
 6$job->handle();
 7 
 8$job->assertReleased(delay: 30);
 9$job->assertDeleted();
10$job->assertNotDeleted();
11$job->assertFailed();
12$job->assertFailedWith(CorruptedAudioException::class);
13$job->assertNotFailed();

use App\Exceptions\CorruptedAudioException;
use App\Jobs\ProcessPodcast;

$job = (new ProcessPodcast)->withFakeQueueInteractions();

$job->handle();

$job->assertReleased(delay: 30);
$job->assertDeleted();
$job->assertNotDeleted();
$job->assertFailed();
$job->assertFailedWith(CorruptedAudioException::class);
$job->assertNotFailed();
```

## Job Events

Using the `before` and `after` methods on the `Queue` [facade](https://laravel.com/framework/docs/facades), you may specify callbacks to be executed before or after a queued job is processed. These callbacks are a great opportunity to perform additional logging or increment statistics for a dashboard. Typically, you should call these methods from the `boot` method of a [service provider](https://laravel.com/framework/docs/providers). For example, we may use the `AppServiceProvider` that is included with Laravel:

```
 1<?php
 2 
 3namespace App\Providers;
 4 
 5use Illuminate\Support\Facades\Queue;
 6use Illuminate\Support\ServiceProvider;
 7use Illuminate\Queue\Events\JobProcessed;
 8use Illuminate\Queue\Events\JobProcessing;
 9 
10class AppServiceProvider extends ServiceProvider
11{
12    /**
13     * Register any application services.
14     */
15    public function register(): void
16    {
17        // ...
18    }
19 
20    /**
21     * Bootstrap any application services.
22     */
23    public function boot(): void
24    {
25        Queue::before(function (JobProcessing $event) {
26            // $event->connectionName
27            // $event->job
28            // $event->job->payload()
29        });
30 
31        Queue::after(function (JobProcessed $event) {
32            // $event->connectionName
33            // $event->job
34            // $event->job->payload()
35        });
36    }
37}

<?php

namespace App\Providers;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ...
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::before(function (JobProcessing $event) {
            // $event->connectionName
            // $event->job
            // $event->job->payload()
        });

        Queue::after(function (JobProcessed $event) {
            // $event->connectionName
            // $event->job
            // $event->job->payload()
        });
    }
}
```

Using the `looping` method on the `Queue` [facade](https://laravel.com/framework/docs/facades), you may specify callbacks that execute before the worker attempts to fetch a job from a queue. For example, you might register a closure to rollback any transactions that were left open by a previously failed job:

```
1use Illuminate\Support\Facades\DB;
2use Illuminate\Support\Facades\Queue;
3 
4Queue::looping(function () {
5    while (DB::transactionLevel() > 0) {
6        DB::rollBack();
7    }
8});

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

Queue::looping(function () {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});
```

Laravel also dispatches an `Illuminate\Queue\Events\WorkerIdle` event when a queue worker is unable to retrieve a job from the queue:

```
1use Illuminate\Queue\Events\WorkerIdle;
2use Illuminate\Support\Facades\Event;
3 
4Event::listen(function (WorkerIdle $event) {
5    // $event->connectionName
6    // $event->queue
7    // $event->workerOptions
8});

use Illuminate\Queue\Events\WorkerIdle;
use Illuminate\Support\Facades\Event;

Event::listen(function (WorkerIdle $event) {
    // $event->connectionName
    // $event->queue
    // $event->workerOptions
});
```
