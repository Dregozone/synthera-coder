---
source: laravel
page: eloquent
url: https://laravel.com/framework/docs/13.x/eloquent
fetched_at: 2026-10-20T16:27:46+00:00
---

# Eloquent: Getting Started

- Introduction
- Generating Model Classes
- Eloquent Model Conventions
   - Table Names
   - Primary Keys
   - UUID and ULID Keys
   - Timestamps
   - Database Connections
   - Default Attribute Values
   - Refreshing Attributes After Writes
   - Configuring Eloquent Strictness
- Retrieving Models
   - Collections
   - Chunking Results
   - Chunking Using Lazy Collections
   - Cursors
   - Advanced Subqueries
- Retrieving Single Models / Aggregates
   - Retrieving or Creating Models
   - Retrieving Aggregates
- Inserting and Updating Models
   - Inserts
   - Updates
   - Mass Assignment
   - Upserts
- Deleting Models
   - Soft Deleting
   - Querying Soft Deleted Models
- Pruning Models
- Replicating Models
- Query Scopes
   - Global Scopes
   - Local Scopes
   - Pending Attributes
- Comparing Models
- Events
   - Using Closures
   - Observers
   - Muting Events

## Introduction

Laravel includes Eloquent, an object-relational mapper (ORM) that makes it enjoyable to interact with your database. When using Eloquent, each database table has a corresponding "Model" that is used to interact with that table. In addition to retrieving records from the database table, Eloquent models allow you to insert, update, and delete records from the table as well.

 Before getting started, be sure to configure a database connection in your application's `config/database.php` configuration file. For more information on configuring your database, check out [the database configuration documentation](https://laravel.com/framework/docs/database#configuration).

## Generating Model Classes

To get started, let's create an Eloquent model. Models typically live in the `app\Models` directory and extend the `Illuminate\Database\Eloquent\Model` class. You may use the `make:model` [Artisan command](https://laravel.com/framework/docs/artisan) to generate a new model:

```
1php artisan make:model Flight

php artisan make:model Flight
```

If you would like to generate a [database migration](https://laravel.com/framework/docs/migrations) when you generate the model, you may use the `--migration` or `-m` option:

```
1php artisan make:model Flight --migration

php artisan make:model Flight --migration
```

You may generate various other types of classes when generating a model, such as factories, seeders, policies, controllers, and form requests. In addition, these options may be combined to create multiple classes at once:

```
 1# Generate a model and a FlightFactory class...
 2php artisan make:model Flight --factory
 3php artisan make:model Flight -f
 4 
 5# Generate a model and a FlightSeeder class...
 6php artisan make:model Flight --seed
 7php artisan make:model Flight -s
 8 
 9# Generate a model and a FlightController class...
10php artisan make:model Flight --controller
11php artisan make:model Flight -c
12 
13# Generate a model, FlightController resource class, and form request classes...
14php artisan make:model Flight --controller --resource --requests
15php artisan make:model Flight -crR
16 
17# Generate a model and a FlightPolicy class...
18php artisan make:model Flight --policy
19 
20# Generate a model and a migration, factory, seeder, and controller...
21php artisan make:model Flight -mfsc
22 
23# Shortcut to generate a model, migration, factory, seeder, policy, controller, and form requests...
24php artisan make:model Flight --all
25php artisan make:model Flight -a
26 
27# Generate a pivot model...
28php artisan make:model Member --pivot
29php artisan make:model Member -p

# Generate a model and a FlightFactory class...
php artisan make:model Flight --factory
php artisan make:model Flight -f

# Generate a model and a FlightSeeder class...
php artisan make:model Flight --seed
php artisan make:model Flight -s

# Generate a model and a FlightController class...
php artisan make:model Flight --controller
php artisan make:model Flight -c

# Generate a model, FlightController resource class, and form request classes...
php artisan make:model Flight --controller --resource --requests
php artisan make:model Flight -crR

# Generate a model and a FlightPolicy class...
php artisan make:model Flight --policy

# Generate a model and a migration, factory, seeder, and controller...
php artisan make:model Flight -mfsc

# Shortcut to generate a model, migration, factory, seeder, policy, controller, and form requests...
php artisan make:model Flight --all
php artisan make:model Flight -a

# Generate a pivot model...
php artisan make:model Member --pivot
php artisan make:model Member -p
```

#### Inspecting Models

Sometimes it can be difficult to determine all of a model's available attributes and relationships just by skimming its code. Instead, try the `model:show` Artisan command, which provides a convenient overview of all the model's attributes and relations:

```
1php artisan model:show Flight

php artisan model:show Flight
```

## Eloquent Model Conventions

Models generated by the `make:model` command will be placed in the `app/Models` directory. Let's examine a basic model class and discuss some of Eloquent's key conventions:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Model;
 6 
 7class Flight extends Model
 8{
 9    // ...
10}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flight extends Model
{
    // ...
}
```

### Table Names

After glancing at the example above, you may have noticed that we did not tell Eloquent which database table corresponds to our `Flight` model. By convention, the "snake case", plural name of the class will be used as the table name unless another name is explicitly specified. So, in this case, Eloquent will assume the `Flight` model stores records in the `flights` table, while an `AirTrafficController` model would store records in an `air_traffic_controllers` table.

If your model's corresponding database table does not fit this convention, you may manually specify the model's table name using the `Table` attribute:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Attributes\Table;
 6use Illuminate\Database\Eloquent\Model;
 7 
 8#[Table('my_flights')]
 9class Flight extends Model
10{
11    // ...
12}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('my_flights')]
class Flight extends Model
{
    // ...
}
```

### Primary Keys

Eloquent will also assume that each model's corresponding database table has a primary key column named `id`. If necessary, you may specify a different column that serves as your model's primary key using the `key` argument on the `Table` attribute:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Attributes\Table;
 6use Illuminate\Database\Eloquent\Model;
 7 
 8#[Table(key: 'flight_id')]
 9class Flight extends Model
10{
11    // ...
12}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table(key: 'flight_id')]
class Flight extends Model
{
    // ...
}
```

In addition, Eloquent assumes that the primary key is an incrementing integer value, which means that Eloquent will automatically cast the primary key to an integer. If you wish to use a non-incrementing or a non-numeric primary key, you should specify the `keyType` and `incrementing` arguments on the `Table` attribute:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Attributes\Table;
 6use Illuminate\Database\Eloquent\Model;
 7 
 8#[Table(key: 'uuid', keyType: 'string', incrementing: false)]
 9class Flight extends Model
10{
11    // ...
12}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table(key: 'uuid', keyType: 'string', incrementing: false)]
class Flight extends Model
{
    // ...
}
```

If you only need to disable auto-incrementing IDs, you may use the `WithoutIncrementing` attribute:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
 6use Illuminate\Database\Eloquent\Model;
 7 
 8#[WithoutIncrementing]
 9class Flight extends Model
10{
11    // ...
12}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;

#[WithoutIncrementing]
class Flight extends Model
{
    // ...
}
```

#### "Composite" Primary Keys

Eloquent requires each model to have at least one uniquely identifying "ID" that can serve as its primary key. "Composite" primary keys are not supported by Eloquent models. However, you are free to add additional multi-column, unique indexes to your database tables in addition to the table's uniquely identifying primary key.

### UUID and ULID Keys

Instead of using auto-incrementing integers as your Eloquent model's primary keys, you may choose to use UUIDs instead. UUIDs are universally unique alpha-numeric identifiers that are 36 characters long.

If you would like a model to use a UUID key instead of an auto-incrementing integer key, you may use the `Illuminate\Database\Eloquent\Concerns\HasUuids` trait on the model. Of course, you should ensure that the model has a [UUID equivalent primary key column](https://laravel.com/framework/docs/migrations#column-method-uuid):

```
 1use Illuminate\Database\Eloquent\Concerns\HasUuids;
 2use Illuminate\Database\Eloquent\Model;
 3 
 4class Article extends Model
 5{
 6    use HasUuids;
 7 
 8    // ...
 9}
10 
11$article = Article::create(['title' => 'Traveling to Europe']);
12 
13$article->id; // "018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11"

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasUuids;

    // ...
}

$article = Article::create(['title' => 'Traveling to Europe']);

$article->id; // "018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11"
```

By default, the `HasUuids` trait will generate [UUIDv7](https://laravel.com/framework/docs/strings#method-str-uuid7) identifiers for your models. These UUIDs are more efficient for indexed database storage because they can be sorted lexicographically.

You can override the UUID generation process for a given model by defining a `newUniqueId` method on the model. In addition, you may specify which columns should receive UUIDs by defining a `uniqueIds` method on the model:

```
 1use Ramsey\Uuid\Uuid;
 2 
 3/**
 4 * Generate a new UUID for the model.
 5 */
 6public function newUniqueId(): string
 7{
 8    return (string) Uuid::uuid4();
 9}
10 
11/**
12 * Get the columns that should receive a unique identifier.
13 *
14 * @return array<int, string>
15 */
16public function uniqueIds(): array
17{
18    return ['id', 'discount_code'];
19}

use Ramsey\Uuid\Uuid;

/**
 * Generate a new UUID for the model.
 */
public function newUniqueId(): string
{
    return (string) Uuid::uuid4();
}

/**
 * Get the columns that should receive a unique identifier.
 *
 * @return array<int, string>
 */
public function uniqueIds(): array
{
    return ['id', 'discount_code'];
}
```

If you wish, you may choose to utilize "ULIDs" instead of UUIDs. ULIDs are similar to UUIDs; however, they are only 26 characters in length. Like ordered UUIDs, ULIDs are lexicographically sortable for efficient database indexing. To utilize ULIDs, you should use the `Illuminate\Database\Eloquent\Concerns\HasUlids` trait on your model. You should also ensure that the model has a [ULID equivalent primary key column](https://laravel.com/framework/docs/migrations#column-method-ulid):

```
 1use Illuminate\Database\Eloquent\Concerns\HasUlids;
 2use Illuminate\Database\Eloquent\Model;
 3 
 4class Article extends Model
 5{
 6    use HasUlids;
 7 
 8    // ...
 9}
10 
11$article = Article::create(['title' => 'Traveling to Asia']);
12 
13$article->id; // "01gd4d3tgrrfqeda94gdbtdk5c"

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasUlids;

    // ...
}

$article = Article::create(['title' => 'Traveling to Asia']);

$article->id; // "01gd4d3tgrrfqeda94gdbtdk5c"
```

### Timestamps

By default, Eloquent expects `created_at` and `updated_at` columns to exist on your model's corresponding database table. Eloquent will automatically set these column's values when models are created or updated. If you do not want these columns to be automatically managed by Eloquent, you may set `timestamps` to `false` on your model's `Table` attribute:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Attributes\Table;
 6use Illuminate\Database\Eloquent\Model;
 7 
 8#[Table(timestamps: false)]
 9class Flight extends Model
10{
11    // ...
12}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table(timestamps: false)]
class Flight extends Model
{
    // ...
}
```

If you only need to disable timestamps, you may use the `WithoutTimestamps` attribute:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
 6use Illuminate\Database\Eloquent\Model;
 7 
 8#[WithoutTimestamps]
 9class Flight extends Model
10{
11    // ...
12}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[WithoutTimestamps]
class Flight extends Model
{
    // ...
}
```

If you need to customize the format of your model's timestamps, you may use the `dateFormat` argument on the `Table` attribute. This determines how date attributes are stored in the database:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Attributes\Table;
 6use Illuminate\Database\Eloquent\Model;
 7 
 8#[Table(dateFormat: 'U')]
 9class Flight extends Model
10{
11    // ...
12}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table(dateFormat: 'U')]
class Flight extends Model
{
    // ...
}
```

If you only need to define a date format, you may use the `DateFormat` attribute:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Attributes\DateFormat;
 6use Illuminate\Database\Eloquent\Model;
 7 
 8#[DateFormat('U')]
 9class Flight extends Model
10{
11    // ...
12}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Model;

#[DateFormat('U')]
class Flight extends Model
{
    // ...
}
```

If you need to customize the names of the columns used to store the timestamps, you may define `CREATED_AT` and `UPDATED_AT` constants on your model:

```
 1<?php
 2 
 3class Flight extends Model
 4{
 5    /**
 6     * The name of the "created at" column.
 7     *
 8     * @var string|null
 9     */
10    public const CREATED_AT = 'creation_date';
11 
12    /**
13     * The name of the "updated at" column.
14     *
15     * @var string|null
16     */
17    public const UPDATED_AT = 'updated_date';
18}

<?php

class Flight extends Model
{
    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    public const CREATED_AT = 'creation_date';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    public const UPDATED_AT = 'updated_date';
}
```

If you would like to perform model operations without the model having its `updated_at` timestamp modified, you may operate on the model within a closure given to the `withoutTimestamps` method:

```
1Model::withoutTimestamps(fn () => $post->increment('reads'));

Model::withoutTimestamps(fn () => $post->increment('reads'));
```

### Database Connections

By default, all Eloquent models will use the default database connection that is configured for your application. If you would like to specify a different connection that should be used when interacting with a particular model, you may use the `Connection` attribute:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Attributes\Connection;
 6use Illuminate\Database\Eloquent\Model;
 7 
 8#[Connection('mysql')]
 9class Flight extends Model
10{
11    // ...
12}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Model;

#[Connection('mysql')]
class Flight extends Model
{
    // ...
}
```

### Default Attribute Values

By default, a newly instantiated model instance will not contain any attribute values. If you would like to define the default values for some of your model's attributes, you may define an `$attributes` property on your model. Attribute values placed in the `$attributes` array should be in their raw, "storable" format as if they were just read from the database:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Model;
 6 
 7class Flight extends Model
 8{
 9    /**
10     * The model's default values for attributes.
11     *
12     * @var array<string, mixed>
13     */
14    protected $attributes = [
15        'options' => '[]',
16        'delayed' => false,
17    ];
18}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flight extends Model
{
    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'options' => '[]',
        'delayed' => false,
    ];
}
```

### Refreshing Attributes After Writes

If your database contains generated columns, you may configure Eloquent to refresh specific attributes after a model is inserted or updated. To do so, define the `Refreshes` attribute on your model:

```
1use Illuminate\Database\Eloquent\Attributes\Refreshes;
2 
3#[Refreshes('name')]
4class User extends Model
5{
6    // ...
7}

use Illuminate\Database\Eloquent\Attributes\Refreshes;

#[Refreshes('name')]
class User extends Model
{
    // ...
}
```

You may specify multiple attributes as an array:

```
1#[Refreshes(['name', 'slug'])]

#[Refreshes(['name', 'slug'])]
```

After the model is written, the configured attributes will be refreshed from the database.

### Configuring Eloquent Strictness

Laravel offers several methods that allow you to configure Eloquent's behavior and "strictness" in a variety of situations.

First, the `preventLazyLoading` method accepts an optional boolean argument that indicates if lazy loading should be prevented. For example, you may wish to only disable lazy loading in non-production environments so that your production environment will continue to function normally even if a lazy loaded relationship is accidentally present in production code. Typically, this method should be invoked in the `boot` method of your application's `AppServiceProvider`:

```
1use Illuminate\Database\Eloquent\Model;
2 
3/**
4 * Bootstrap any application services.
5 */
6public function boot(): void
7{
8    Model::preventLazyLoading(! $this->app->isProduction());
9}

use Illuminate\Database\Eloquent\Model;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Model::preventLazyLoading(! $this->app->isProduction());
}
```

Also, you may instruct Laravel to throw an exception when attempting to fill an unfillable attribute by invoking the `preventSilentlyDiscardingAttributes` method. This can help prevent unexpected errors during local development when attempting to set an attribute that has not been added to the model's `fillable` array:

```
1Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
```

## Retrieving Models

Once you have created a model and [its associated database table](https://laravel.com/framework/docs/migrations#generating-migrations), you are ready to start retrieving data from your database. You can think of each Eloquent model as a powerful [query builder](https://laravel.com/framework/docs/queries) allowing you to fluently query the database table associated with the model. The model's `all` method will retrieve all of the records from the model's associated database table:

```
1use App\Models\Flight;
2 
3foreach (Flight::all() as $flight) {
4    echo $flight->name;
5}

use App\Models\Flight;

foreach (Flight::all() as $flight) {
    echo $flight->name;
}
```

#### Building Queries

The Eloquent `all` method will return all of the results in the model's table. However, since each Eloquent model serves as a [query builder](https://laravel.com/framework/docs/queries), you may add additional constraints to queries and then invoke the `get` method to retrieve the results:

```
1$flights = Flight::where('active', 1)
2    ->orderBy('name')
3    ->limit(10)
4    ->get();

$flights = Flight::where('active', 1)
    ->orderBy('name')
    ->limit(10)
    ->get();
```

 Since Eloquent models are query builders, you should review all of the methods provided by Laravel's [query builder](https://laravel.com/framework/docs/queries). You may use any of these methods when writing your Eloquent queries.

#### Refreshing Models

If you already have an instance of an Eloquent model that was retrieved from the database, you can "refresh" the model using the `fresh` and `refresh` methods. The `fresh` method will re-retrieve the model from the database. The existing model instance will not be affected:

```
1$flight = Flight::where('number', 'FR 900')->first();
2 
3$freshFlight = $flight->fresh();

$flight = Flight::where('number', 'FR 900')->first();

$freshFlight = $flight->fresh();
```

The `refresh` method will re-hydrate the existing model using fresh data from the database. In addition, all of its loaded relationships will be refreshed as well:

```
1$flight = Flight::where('number', 'FR 900')->first();
2 
3$flight->number = 'FR 456';
4 
5$flight->refresh();
6 
7$flight->number; // "FR 900"

$flight = Flight::where('number', 'FR 900')->first();

$flight->number = 'FR 456';

$flight->refresh();

$flight->number; // "FR 900"
```

If you need to refresh a model and acquire a pessimistic lock within a transaction, you may use the `refreshForUpdate` method. This method reloads the model using a `FOR UPDATE` lock:

```
1DB::transaction(function () use ($flight) {
2    $flight->refreshForUpdate();
3 
4    // Update the locked model...
5});

DB::transaction(function () use ($flight) {
    $flight->refreshForUpdate();

    // Update the locked model...
});
```

### Collections

As we have seen, Eloquent methods like `all` and `get` retrieve multiple records from the database. However, these methods don't return a plain PHP array. Instead, an instance of `Illuminate\Database\Eloquent\Collection` is returned.

The Eloquent `Collection` class extends Laravel's base `Illuminate\Support\Collection` class, which provides a [variety of helpful methods](https://laravel.com/framework/docs/collections#available-methods) for interacting with data collections. For example, the `reject` method may be used to remove models from a collection based on the results of an invoked closure:

```
1$flights = Flight::where('destination', 'Paris')->get();
2 
3$flights = $flights->reject(function (Flight $flight) {
4    return $flight->cancelled;
5});

$flights = Flight::where('destination', 'Paris')->get();

$flights = $flights->reject(function (Flight $flight) {
    return $flight->cancelled;
});
```

In addition to the methods provided by Laravel's base collection class, the Eloquent collection class provides [a few extra methods](https://laravel.com/framework/docs/eloquent-collections#available-methods) that are specifically intended for interacting with collections of Eloquent models.

Since all of Laravel's collections implement PHP's iterable interfaces, you may loop over collections as if they were an array:

```
1foreach ($flights as $flight) {
2    echo $flight->name;
3}

foreach ($flights as $flight) {
    echo $flight->name;
}
```

### Chunking Results

Your application may run out of memory if you attempt to load tens of thousands of Eloquent records via the `all` or `get` methods. Instead of using these methods, the `chunk` method may be used to process large numbers of models more efficiently.

The `chunk` method will retrieve a subset of Eloquent models, passing them to a closure for processing. Since only the current chunk of Eloquent models is retrieved at a time, the `chunk` method will provide significantly reduced memory usage when working with a large number of models:

```
1use App\Models\Flight;
2use Illuminate\Database\Eloquent\Collection;
3 
4Flight::chunk(200, function (Collection $flights) {
5    foreach ($flights as $flight) {
6        // ...
7    }
8});

use App\Models\Flight;
use Illuminate\Database\Eloquent\Collection;

Flight::chunk(200, function (Collection $flights) {
    foreach ($flights as $flight) {
        // ...
    }
});
```

The first argument passed to the `chunk` method is the number of records you wish to receive per "chunk". The closure passed as the second argument will be invoked for each chunk that is retrieved from the database. A database query will be executed to retrieve each chunk of records passed to the closure.

If you are filtering the results of the `chunk` method based on a column that you will also be updating while iterating over the results, you should use the `chunkById` method. Using the `chunk` method in these scenarios could lead to unexpected and inconsistent results. Internally, the `chunkById` method will always retrieve models with an `id` column greater than the last model in the previous chunk:

```
1Flight::where('departed', true)
2    ->chunkById(200, function (Collection $flights) {
3        $flights->each->update(['departed' => false]);
4    }, column: 'id');

Flight::where('departed', true)
    ->chunkById(200, function (Collection $flights) {
        $flights->each->update(['departed' => false]);
    }, column: 'id');
```

Since the `chunkById` and `lazyById` methods add their own "where" conditions to the query being executed, you should typically [logically group](https://laravel.com/framework/docs/queries#logical-grouping) your own conditions within a closure:

```
1Flight::where(function ($query) {
2    $query->where('delayed', true)->orWhere('cancelled', true);
3})->chunkById(200, function (Collection $flights) {
4    $flights->each->update([
5        'departed' => false,
6        'cancelled' => true
7    ]);
8}, column: 'id');

Flight::where(function ($query) {
    $query->where('delayed', true)->orWhere('cancelled', true);
})->chunkById(200, function (Collection $flights) {
    $flights->each->update([
        'departed' => false,
        'cancelled' => true
    ]);
}, column: 'id');
```

### Chunking Using Lazy Collections

The `lazy` method works similarly to the `chunk` method in the sense that, behind the scenes, it executes the query in chunks. However, instead of passing each chunk directly into a callback as is, the `lazy` method returns a flattened [LazyCollection](https://laravel.com/framework/docs/collections#lazy-collections) of Eloquent models, which lets you interact with the results as a single stream:

```
1use App\Models\Flight;
2 
3foreach (Flight::lazy() as $flight) {
4    // ...
5}

use App\Models\Flight;

foreach (Flight::lazy() as $flight) {
    // ...
}
```

If you are filtering the results of the `lazy` method based on a column that you will also be updating while iterating over the results, you should use the `lazyById` method. Internally, the `lazyById` method will always retrieve models with an `id` column greater than the last model in the previous chunk:

```
1Flight::where('departed', true)
2    ->lazyById(200, column: 'id')
3    ->each->update(['departed' => false]);

Flight::where('departed', true)
    ->lazyById(200, column: 'id')
    ->each->update(['departed' => false]);
```

You may filter the results based on the descending order of the `id` using the `lazyByIdDesc` method.

### Cursors

Similar to the `lazy` method, the `cursor` method may be used to significantly reduce your application's memory consumption when iterating through tens of thousands of Eloquent model records.

The `cursor` method will only execute a single database query; however, the individual Eloquent models will not be hydrated until they are actually iterated over. Therefore, only one Eloquent model is kept in memory at any given time while iterating over the cursor.

 Since the `cursor` method only ever holds a single Eloquent model in memory at a time, it cannot eager load relationships. If you need to eager load relationships, consider using the `lazy` method instead.

Internally, the `cursor` method uses PHP [generators](https://www.php.net/manual/en/language.generators.overview.php) to implement this functionality:

```
1use App\Models\Flight;
2 
3foreach (Flight::where('destination', 'Zurich')->cursor() as $flight) {
4    // ...
5}

use App\Models\Flight;

foreach (Flight::where('destination', 'Zurich')->cursor() as $flight) {
    // ...
}
```

The `cursor` returns an `Illuminate\Support\LazyCollection` instance. [Lazy collections](https://laravel.com/framework/docs/collections#lazy-collections) allow you to use many of the collection methods available on typical Laravel collections while only loading a single model into memory at a time:

```
1use App\Models\User;
2 
3$users = User::cursor()->filter(function (User $user) {
4    return $user->id > 500;
5});
6 
7foreach ($users as $user) {
8    echo $user->id;
9}

use App\Models\User;

$users = User::cursor()->filter(function (User $user) {
    return $user->id > 500;
});

foreach ($users as $user) {
    echo $user->id;
}
```

Although the `cursor` method uses far less memory than a regular query (by only holding a single Eloquent model in memory at a time), it will still eventually run out of memory. This is [due to PHP's PDO driver internally caching all raw query results in its buffer](https://www.php.net/manual/en/mysqlinfo.concepts.buffering.php). If you're dealing with a very large number of Eloquent records, consider using the `lazy` method instead.

### Advanced Subqueries

#### Subquery Selects

Eloquent also offers advanced subquery support, which allows you to pull information from related tables in a single query. For example, let's imagine that we have a table of flight `destinations` and a table of `flights` to destinations. The `flights` table contains an `arrived_at` column which indicates when the flight arrived at the destination.

Using the subquery functionality available to the query builder's `select` and `addSelect` methods, we can select all of the `destinations` and the name of the flight that most recently arrived at that destination using a single query:

```
1use App\Models\Destination;
2use App\Models\Flight;
3 
4return Destination::addSelect(['last_flight' => Flight::select('name')
5    ->whereColumn('destination_id', 'destinations.id')
6    ->orderByDesc('arrived_at')
7    ->limit(1)
8])->get();

use App\Models\Destination;
use App\Models\Flight;

return Destination::addSelect(['last_flight' => Flight::select('name')
    ->whereColumn('destination_id', 'destinations.id')
    ->orderByDesc('arrived_at')
    ->limit(1)
])->get();
```

#### Subquery Ordering

In addition, the query builder's `orderBy` function supports subqueries. Continuing to use our flight example, we may use this functionality to sort all destinations based on when the last flight arrived at that destination. Again, this may be done while executing a single database query:

```
1return Destination::orderByDesc(
2    Flight::select('arrived_at')
3        ->whereColumn('destination_id', 'destinations.id')
4        ->orderByDesc('arrived_at')
5        ->limit(1)
6)->get();

return Destination::orderByDesc(
    Flight::select('arrived_at')
        ->whereColumn('destination_id', 'destinations.id')
        ->orderByDesc('arrived_at')
        ->limit(1)
)->get();
```

## Retrieving Single Models / Aggregates

In addition to retrieving all of the records matching a given query, you may also retrieve single records using the `find`, `first`, or `firstWhere` methods. Instead of returning a collection of models, these methods return a single model instance:

```
 1use App\Models\Flight;
 2 
 3// Retrieve a model by its primary key...
 4$flight = Flight::find(1);
 5 
 6// Retrieve the first model matching the query constraints...
 7$flight = Flight::where('active', 1)->first();
 8 
 9// Alternative to retrieving the first model matching the query constraints...
10$flight = Flight::firstWhere('active', 1);

use App\Models\Flight;

// Retrieve a model by its primary key...
$flight = Flight::find(1);

// Retrieve the first model matching the query constraints...
$flight = Flight::where('active', 1)->first();

// Alternative to retrieving the first model matching the query constraints...
$flight = Flight::firstWhere('active', 1);
```

Sometimes you may wish to perform some other action if no results are found. The `findOr` and `firstOr` methods will return a single model instance or, if no results are found, execute the given closure. The value returned by the closure will be considered the result of the method:

```
1$flight = Flight::findOr(1, function () {
2    // ...
3});
4 
5$flight = Flight::where('legs', '>', 3)->firstOr(function () {
6    // ...
7});

$flight = Flight::findOr(1, function () {
    // ...
});

$flight = Flight::where('legs', '>', 3)->firstOr(function () {
    // ...
});
```

#### Not Found Exceptions

Sometimes you may wish to throw an exception if a model is not found. This is particularly useful in routes or controllers. The `findOrFail` and `firstOrFail` methods will retrieve the first result of the query; however, if no result is found, an `Illuminate\Database\Eloquent\ModelNotFoundException` will be thrown:

```
1$flight = Flight::findOrFail(1);
2 
3$flight = Flight::where('legs', '>', 3)->firstOrFail();

$flight = Flight::findOrFail(1);

$flight = Flight::where('legs', '>', 3)->firstOrFail();
```

If the `ModelNotFoundException` is not caught, a 404 HTTP response is automatically sent back to the client:

```
1use App\Models\Flight;
2 
3Route::get('/api/flights/{id}', function (string $id) {
4    return Flight::findOrFail($id);
5});

use App\Models\Flight;

Route::get('/api/flights/{id}', function (string $id) {
    return Flight::findOrFail($id);
});
```

### Retrieving or Creating Models

The `firstOrCreate` method will attempt to locate a database record using the given column / value pairs. If the model cannot be found in the database, a record will be inserted with the attributes resulting from merging the first array argument with the optional second array argument.

The `firstOrNew` method, like `firstOrCreate`, will attempt to locate a record in the database matching the given attributes. However, if a model is not found, a new model instance will be returned. Note that the model returned by `firstOrNew` has not yet been persisted to the database. You will need to manually call the `save` method to persist it:

```
 1use App\Models\Flight;
 2 
 3// Retrieve flight by name or create it if it doesn't exist...
 4$flight = Flight::firstOrCreate([
 5    'name' => 'London to Paris'
 6]);
 7 
 8// Retrieve flight by name or create it with the name, delayed, and arrival_time attributes...
 9$flight = Flight::firstOrCreate(
10    ['name' => 'London to Paris'],
11    ['delayed' => 1, 'arrival_time' => '11:30']
12);
13 
14// Retrieve flight by name or instantiate a new Flight instance...
15$flight = Flight::firstOrNew([
16    'name' => 'London to Paris'
17]);
18 
19// Retrieve flight by name or instantiate with the name, delayed, and arrival_time attributes...
20$flight = Flight::firstOrNew(
21    ['name' => 'Tokyo to Sydney'],
22    ['delayed' => 1, 'arrival_time' => '11:30']
23);

use App\Models\Flight;

// Retrieve flight by name or create it if it doesn't exist...
$flight = Flight::firstOrCreate([
    'name' => 'London to Paris'
]);

// Retrieve flight by name or create it with the name, delayed, and arrival_time attributes...
$flight = Flight::firstOrCreate(
    ['name' => 'London to Paris'],
    ['delayed' => 1, 'arrival_time' => '11:30']
);

// Retrieve flight by name or instantiate a new Flight instance...
$flight = Flight::firstOrNew([
    'name' => 'London to Paris'
]);

// Retrieve flight by name or instantiate with the name, delayed, and arrival_time attributes...
$flight = Flight::firstOrNew(
    ['name' => 'Tokyo to Sydney'],
    ['delayed' => 1, 'arrival_time' => '11:30']
);
```

### Retrieving Aggregates

When interacting with Eloquent models, you may also use the `count`, `sum`, `max`, and other [aggregate methods](https://laravel.com/framework/docs/queries#aggregates) provided by the Laravel [query builder](https://laravel.com/framework/docs/queries). As you might expect, these methods return a scalar value instead of an Eloquent model instance:

```
1$count = Flight::where('active', 1)->count();
2 
3$max = Flight::where('active', 1)->max('price');

$count = Flight::where('active', 1)->count();

$max = Flight::where('active', 1)->max('price');
```

## Inserting and Updating Models

### Inserts

Of course, when using Eloquent, we don't only need to retrieve models from the database. We also need to insert new records. Thankfully, Eloquent makes it simple. To insert a new record into the database, you should instantiate a new model instance and set attributes on the model. Then, call the `save` method on the model instance:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use App\Models\Flight;
 6use Illuminate\Http\RedirectResponse;
 7use Illuminate\Http\Request;
 8 
 9class FlightController extends Controller
10{
11    /**
12     * Store a new flight in the database.
13     */
14    public function store(Request $request): RedirectResponse
15    {
16        // Validate the request...
17 
18        $flight = new Flight;
19 
20        $flight->name = $request->name;
21 
22        $flight->save();
23 
24        return redirect('/flights');
25    }
26}

<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FlightController extends Controller
{
    /**
     * Store a new flight in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        // Validate the request...

        $flight = new Flight;

        $flight->name = $request->name;

        $flight->save();

        return redirect('/flights');
    }
}
```

In this example, we assign the `name` field from the incoming HTTP request to the `name` attribute of the `App\Models\Flight` model instance. When we call the `save` method, a record will be inserted into the database. The model's `created_at` and `updated_at` timestamps will automatically be set when the `save` method is called, so there is no need to set them manually.

If you would like to save the model within a database transaction, you may use the `saveOrFail` method. If an exception is thrown during the save, the transaction will automatically be rolled back:

```
1$flight->saveOrFail();

$flight->saveOrFail();
```

Alternatively, you may use the `create` method to "save" a new model using a single PHP statement. The inserted model instance will be returned to you by the `create` method:

```
1use App\Models\Flight;
2 
3$flight = Flight::create([
4    'name' => 'London to Paris',
5]);

use App\Models\Flight;

$flight = Flight::create([
    'name' => 'London to Paris',
]);
```

However, before using the `create` method, you will need to specify either a `Fillable` or `Guarded` attribute on your model class. These attributes are required because all Eloquent models are protected against mass assignment vulnerabilities by default. To learn more about mass assignment, please consult the mass assignment documentation.

### Updates

The `save` method may also be used to update models that already exist in the database. To update a model, you should retrieve it and set any attributes you wish to update. Then, you should call the model's `save` method. Again, the `updated_at` timestamp will automatically be updated, so there is no need to manually set its value:

```
1use App\Models\Flight;
2 
3$flight = Flight::find(1);
4 
5$flight->name = 'Paris to London';
6 
7$flight->save();

use App\Models\Flight;

$flight = Flight::find(1);

$flight->name = 'Paris to London';

$flight->save();
```

If you would like to update the model within a database transaction, you may use the `updateOrFail` method. If an exception is thrown during the update, the transaction will automatically be rolled back:

```
1$flight->updateOrFail(['name' => 'Paris to London']);

$flight->updateOrFail(['name' => 'Paris to London']);
```

Occasionally, you may need to update an existing model or create a new model if no matching model exists. Like the `firstOrCreate` method, the `updateOrCreate` method persists the model, so there's no need to manually call the `save` method.

In the example below, if a flight exists with a `departure` location of `Oakland` and a `destination` location of `San Diego`, its `price` and `discounted` columns will be updated. If no such flight exists, a new flight will be created which has the attributes resulting from merging the first argument array with the second argument array:

```
1$flight = Flight::updateOrCreate(
2    ['departure' => 'Oakland', 'destination' => 'San Diego'],
3    ['price' => 99, 'discounted' => 1]
4);

$flight = Flight::updateOrCreate(
    ['departure' => 'Oakland', 'destination' => 'San Diego'],
    ['price' => 99, 'discounted' => 1]
);
```

When using methods such as `firstOrCreate` or `updateOrCreate`, you may not know whether a new model has been created or an existing one has been updated. The `wasRecentlyCreated` property indicates if the model was created during its current lifecycle:

```
1$flight = Flight::updateOrCreate(
2    // ...
3);
4 
5if ($flight->wasRecentlyCreated) {
6    // New flight record was inserted...
7}

$flight = Flight::updateOrCreate(
    // ...
);

if ($flight->wasRecentlyCreated) {
    // New flight record was inserted...
}
```

#### Mass Updates

Updates can also be performed against models that match a given query. In this example, all flights that are `active` and have a `destination` of `San Diego` will be marked as delayed:

```
1Flight::where('active', 1)
2    ->where('destination', 'San Diego')
3    ->update(['delayed' => 1]);

Flight::where('active', 1)
    ->where('destination', 'San Diego')
    ->update(['delayed' => 1]);
```

The `update` method expects an array of column and value pairs representing the columns that should be updated. The `update` method returns the number of affected rows.

 When issuing a mass update via Eloquent, the `saving`, `saved`, `updating`, and `updated` model events will not be fired for the updated models. This is because the models are never actually retrieved when issuing a mass update.

#### Examining Attribute Changes

Eloquent provides the `isDirty`, `isClean`, and `wasChanged` methods to examine the internal state of your model and determine how its attributes have changed from when the model was originally retrieved.

The `isDirty` method determines if any of the model's attributes have been changed since the model was retrieved. You may pass a specific attribute name or an array of attributes to the `isDirty` method to determine if any of the attributes are "dirty". The `isClean` method will determine if an attribute has remained unchanged since the model was retrieved. This method also accepts an optional attribute argument:

```
 1use App\Models\User;
 2 
 3$user = User::create([
 4    'first_name' => 'Taylor',
 5    'last_name' => 'Otwell',
 6    'title' => 'Developer',
 7]);
 8 
 9$user->title = 'Painter';
10 
11$user->isDirty(); // true
12$user->isDirty('title'); // true
13$user->isDirty('first_name'); // false
14$user->isDirty(['first_name', 'title']); // true
15 
16$user->isClean(); // false
17$user->isClean('title'); // false
18$user->isClean('first_name'); // true
19$user->isClean(['first_name', 'title']); // false
20 
21$user->save();
22 
23$user->isDirty(); // false
24$user->isClean(); // true

use App\Models\User;

$user = User::create([
    'first_name' => 'Taylor',
    'last_name' => 'Otwell',
    'title' => 'Developer',
]);

$user->title = 'Painter';

$user->isDirty(); // true
$user->isDirty('title'); // true
$user->isDirty('first_name'); // false
$user->isDirty(['first_name', 'title']); // true

$user->isClean(); // false
$user->isClean('title'); // false
$user->isClean('first_name'); // true
$user->isClean(['first_name', 'title']); // false

$user->save();

$user->isDirty(); // false
$user->isClean(); // true
```

The `wasChanged` method determines if any attributes were changed when the model was last saved within the current request cycle. If needed, you may pass an attribute name to see if a particular attribute was changed:

```
 1$user = User::create([
 2    'first_name' => 'Taylor',
 3    'last_name' => 'Otwell',
 4    'title' => 'Developer',
 5]);
 6 
 7$user->title = 'Painter';
 8 
 9$user->save();
10 
11$user->wasChanged(); // true
12$user->wasChanged('title'); // true
13$user->wasChanged(['title', 'slug']); // true
14$user->wasChanged('first_name'); // false
15$user->wasChanged(['first_name', 'title']); // true

$user = User::create([
    'first_name' => 'Taylor',
    'last_name' => 'Otwell',
    'title' => 'Developer',
]);

$user->title = 'Painter';

$user->save();

$user->wasChanged(); // true
$user->wasChanged('title'); // true
$user->wasChanged(['title', 'slug']); // true
$user->wasChanged('first_name'); // false
$user->wasChanged(['first_name', 'title']); // true
```

The `getOriginal` method returns an array containing the original attributes of the model regardless of any changes to the model since it was retrieved. If needed, you may pass a specific attribute name to get the original value of a particular attribute:

```
 1$user = User::find(1);
 2 
 3$user->name; // John
 4$user->email; // [email protected]
 5 
 6$user->name = 'Jack';
 7$user->name; // Jack
 8 
 9$user->getOriginal('name'); // John
10$user->getOriginal(); // Array of original attributes...

$user = User::find(1);

$user->name; // John
$user->email; // [email protected]

$user->name = 'Jack';
$user->name; // Jack

$user->getOriginal('name'); // John
$user->getOriginal(); // Array of original attributes...
```

The `getChanges` method returns an array containing the attributes that changed when the model was last saved, while the `getPrevious` method returns an array containing the original attribute values before the model was last saved:

```
 1$user = User::find(1);
 2 
 3$user->name; // John
 4$user->email; // [email protected]
 5 
 6$user->update([
 7    'name' => 'Jack',
 8    'email' => '[email protected]',
 9]);
10 
11$user->getChanges();
12 
13/*
14    [
15        'name' => 'Jack',
16        'email' => '[email protected]',
17    ]
18*/
19 
20$user->getPrevious();
21 
22/*
23    [
24        'name' => 'John',
25        'email' => '[email protected]',
26    ]
27*/

$user = User::find(1);

$user->name; // John
$user->email; // [email protected]

$user->update([
    'name' => 'Jack',
    'email' => '[email protected]',
]);

$user->getChanges();

/*
    [
        'name' => 'Jack',
        'email' => '[email protected]',
    ]
*/

$user->getPrevious();

/*
    [
        'name' => 'John',
        'email' => '[email protected]',
    ]
*/
```

### Mass Assignment

You may use the `create` method to "save" a new model using a single PHP statement. The inserted model instance will be returned to you by the method:

```
1use App\Models\Flight;
2 
3$flight = Flight::create([
4    'name' => 'London to Paris',
5]);

use App\Models\Flight;

$flight = Flight::create([
    'name' => 'London to Paris',
]);
```

However, before using the `create` method, you will need to specify either a `Fillable` or `Guarded` attribute on your model class. These attributes are required because all Eloquent models are protected against mass assignment vulnerabilities by default.

A mass assignment vulnerability occurs when a user passes an unexpected HTTP request field and that field changes a column in your database that you did not expect. For example, a malicious user might send an `is_admin` parameter through an HTTP request, which is then passed to your model's `create` method, allowing the user to escalate themselves to an administrator.

So, to get started, you should define which model attributes you want to make mass assignable. You may do this using the `Fillable` attribute on the model. For example, let's make the `name` attribute of our `Flight` model mass assignable:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Attributes\Fillable;
 6use Illuminate\Database\Eloquent\Model;
 7 
 8#[Fillable(['name'])]
 9class Flight extends Model
10{
11    // ...
12}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
class Flight extends Model
{
    // ...
}
```

Once you have specified which attributes are mass assignable, you may use the `create` method to insert a new record in the database. The `create` method returns the newly created model instance:

```
1$flight = Flight::create(['name' => 'London to Paris']);

$flight = Flight::create(['name' => 'London to Paris']);
```

If you already have a model instance, you may use the `fill` method to populate it with an array of attributes:

```
1$flight->fill(['name' => 'Amsterdam to Frankfurt']);

$flight->fill(['name' => 'Amsterdam to Frankfurt']);
```

#### Mass Assignment and JSON Columns

When assigning JSON columns, each column's mass assignable key must be specified in your model's `Fillable` attribute. For security, Laravel does not support updating nested JSON attributes when using the `Guarded` attribute:

```
1use Illuminate\Database\Eloquent\Attributes\Fillable;
2 
3#[Fillable(['options->enabled'])]
4class Flight extends Model
5{
6    // ...
7}

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['options->enabled'])]
class Flight extends Model
{
    // ...
}
```

#### Allowing Mass Assignment

If you would like to make all of your attributes mass assignable, you may use the `Unguarded` attribute on your model. If you choose to unguard your model, you should take special care to always hand-craft the arrays passed to Eloquent's `fill`, `create`, and `update` methods:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Attributes\Unguarded;
 6use Illuminate\Database\Eloquent\Model;
 7 
 8#[Unguarded]
 9class Flight extends Model
10{
11    // ...
12}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
class Flight extends Model
{
    // ...
}
```

#### Mass Assignment Exceptions

By default, attributes that are not included in the `Fillable` attribute are silently discarded when performing mass-assignment operations. In production, this is expected behavior; however, during local development it can lead to confusion as to why model changes are not taking effect.

If you wish, you may instruct Laravel to throw an exception when attempting to fill an unfillable attribute by invoking the `preventSilentlyDiscardingAttributes` method. Typically, this method should be invoked in the `boot` method of your application's `AppServiceProvider` class:

```
1use Illuminate\Database\Eloquent\Model;
2 
3/**
4 * Bootstrap any application services.
5 */
6public function boot(): void
7{
8    Model::preventSilentlyDiscardingAttributes($this->app->isLocal());
9}

use Illuminate\Database\Eloquent\Model;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Model::preventSilentlyDiscardingAttributes($this->app->isLocal());
}
```

### Upserts

Eloquent's `upsert` method may be used to update or create records in a single, atomic operation. The method's first argument consists of the values to insert or update, while the second argument lists the column(s) that uniquely identify records within the associated table. The method's third and final argument is an array of the columns that should be updated if a matching record already exists in the database. The `upsert` method will automatically set the `created_at` and `updated_at` timestamps if timestamps are enabled on the model:

```
1Flight::upsert([
2    ['departure' => 'Oakland', 'destination' => 'San Diego', 'price' => 99],
3    ['departure' => 'Chicago', 'destination' => 'New York', 'price' => 150]
4], uniqueBy: ['departure', 'destination'], update: ['price']);

Flight::upsert([
    ['departure' => 'Oakland', 'destination' => 'San Diego', 'price' => 99],
    ['departure' => 'Chicago', 'destination' => 'New York', 'price' => 150]
], uniqueBy: ['departure', 'destination'], update: ['price']);
```

 All databases except SQL Server require the columns in the second argument of the `upsert` method to have a "primary" or "unique" index. In addition, the MariaDB and MySQL database drivers ignore the second argument of the `upsert` method and always use the "primary" and "unique" indexes of the table to detect existing records.

## Deleting Models

To delete a model, you may call the `delete` method on the model instance:

```
1use App\Models\Flight;
2 
3$flight = Flight::find(1);
4 
5$flight->delete();

use App\Models\Flight;

$flight = Flight::find(1);

$flight->delete();
```

If you would like to delete the model within a database transaction, you may use the `deleteOrFail` method. If an exception is thrown during the delete, the transaction will automatically be rolled back:

```
1$flight->deleteOrFail();

$flight->deleteOrFail();
```

#### Deleting an Existing Model by its Primary Key

In the example above, we are retrieving the model from the database before calling the `delete` method. However, if you know the primary key of the model, you may delete the model without explicitly retrieving it by calling the `destroy` method. In addition to accepting the single primary key, the `destroy` method will accept multiple primary keys, an array of primary keys, or a [collection](https://laravel.com/framework/docs/collections) of primary keys:

```
1Flight::destroy(1);
2 
3Flight::destroy(1, 2, 3);
4 
5Flight::destroy([1, 2, 3]);
6 
7Flight::destroy(collect([1, 2, 3]));

Flight::destroy(1);

Flight::destroy(1, 2, 3);

Flight::destroy([1, 2, 3]);

Flight::destroy(collect([1, 2, 3]));
```

If you are utilizing soft deleting models, you may permanently delete models via the `forceDestroy` method:

```
1Flight::forceDestroy(1);

Flight::forceDestroy(1);
```

 The `destroy` method loads each model individually and calls the `delete` method so that the `deleting` and `deleted` events are properly dispatched for each model.

#### Deleting Models Using Queries

Of course, you may build an Eloquent query to delete all models matching your query's criteria. In this example, we will delete all flights that are marked as inactive. Like mass updates, mass deletes will not dispatch model events for the models that are deleted:

```
1$deleted = Flight::where('active', 0)->delete();

$deleted = Flight::where('active', 0)->delete();
```

To delete all models in a table, you should execute a query without adding any conditions:

```
1$deleted = Flight::query()->delete();

$deleted = Flight::query()->delete();
```

 When executing a mass delete statement via Eloquent, the `deleting` and `deleted` model events will not be dispatched for the deleted models. This is because the models are never actually retrieved when executing the delete statement.

### Soft Deleting

In addition to actually removing records from your database, Eloquent can also "soft delete" models. When models are soft deleted, they are not actually removed from your database. Instead, a `deleted_at` attribute is set on the model indicating the date and time at which the model was "deleted". To enable soft deletes for a model, add the `Illuminate\Database\Eloquent\SoftDeletes` trait to the model:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Model;
 6use Illuminate\Database\Eloquent\SoftDeletes;
 7 
 8class Flight extends Model
 9{
10    use SoftDeletes;
11}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Flight extends Model
{
    use SoftDeletes;
}
```

 The `SoftDeletes` trait will automatically cast the `deleted_at` attribute to a `DateTime` / `Carbon` instance for you.

You should also add the `deleted_at` column to your database table. The Laravel [schema builder](https://laravel.com/framework/docs/migrations) contains a helper method to create this column:

```
 1use Illuminate\Database\Schema\Blueprint;
 2use Illuminate\Support\Facades\Schema;
 3 
 4Schema::table('flights', function (Blueprint $table) {
 5    $table->softDeletes();
 6});
 7 
 8Schema::table('flights', function (Blueprint $table) {
 9    $table->dropSoftDeletes();
10});

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('flights', function (Blueprint $table) {
    $table->softDeletes();
});

Schema::table('flights', function (Blueprint $table) {
    $table->dropSoftDeletes();
});
```

Now, when you call the `delete` method on the model, the `deleted_at` column will be set to the current date and time. However, the model's database record will be left in the table. When querying a model that uses soft deletes, the soft deleted models will automatically be excluded from all query results.

To determine if a given model instance has been soft deleted, you may use the `trashed` method:

```
1if ($flight->trashed()) {
2    // ...
3}

if ($flight->trashed()) {
    // ...
}
```

#### Restoring Soft Deleted Models

Sometimes you may wish to "un-delete" a soft deleted model. To restore a soft deleted model, you may call the `restore` method on a model instance. The `restore` method will set the model's `deleted_at` column to `null`:

```
1$flight->restore();

$flight->restore();
```

You may also use the `restore` method in a query to restore multiple models. Again, like other "mass" operations, this will not dispatch any model events for the models that are restored:

```
1Flight::withTrashed()
2    ->where('airline_id', 1)
3    ->restore();

Flight::withTrashed()
    ->where('airline_id', 1)
    ->restore();
```

The `restore` method may also be used when building [relationship](https://laravel.com/framework/docs/eloquent-relationships) queries:

```
1$flight->history()->restore();

$flight->history()->restore();
```

#### Permanently Deleting Models

Sometimes you may need to truly remove a model from your database. You may use the `forceDelete` method to permanently remove a soft deleted model from the database table:

```
1$flight->forceDelete();

$flight->forceDelete();
```

You may also use the `forceDelete` method when building Eloquent relationship queries:

```
1$flight->history()->forceDelete();

$flight->history()->forceDelete();
```

### Querying Soft Deleted Models

#### Including Soft Deleted Models

As noted above, soft deleted models will automatically be excluded from query results. However, you may force soft deleted models to be included in a query's results by calling the `withTrashed` method on the query:

```
1use App\Models\Flight;
2 
3$flights = Flight::withTrashed()
4    ->where('account_id', 1)
5    ->get();

use App\Models\Flight;

$flights = Flight::withTrashed()
    ->where('account_id', 1)
    ->get();
```

The `withTrashed` method may also be called when building a [relationship](https://laravel.com/framework/docs/eloquent-relationships) query:

```
1$flight->history()->withTrashed()->get();

$flight->history()->withTrashed()->get();
```

#### Retrieving Only Soft Deleted Models

The `onlyTrashed` method will retrieve **only** soft deleted models:

```
1$flights = Flight::onlyTrashed()
2    ->where('airline_id', 1)
3    ->get();

$flights = Flight::onlyTrashed()
    ->where('airline_id', 1)
    ->get();
```

## Pruning Models

Sometimes you may want to periodically delete models that are no longer needed. To accomplish this, you may add the `Illuminate\Database\Eloquent\Prunable` or `Illuminate\Database\Eloquent\MassPrunable` trait to the models you would like to periodically prune. After adding one of the traits to the model, implement a `prunable` method which returns an Eloquent query builder that resolves the models that are no longer needed:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Builder;
 6use Illuminate\Database\Eloquent\Model;
 7use Illuminate\Database\Eloquent\Prunable;
 8 
 9class Flight extends Model
10{
11    use Prunable;
12 
13    /**
14     * Get the prunable model query.
15     */
16    public function prunable(): Builder
17    {
18        return static::where('created_at', '<=', now()->minus(months: 1));
19    }
20}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class Flight extends Model
{
    use Prunable;

    /**
     * Get the prunable model query.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->minus(months: 1));
    }
}
```

When marking models as `Prunable`, you may also define a `pruning` method on the model. This method will be called before the model is deleted. This method can be useful for deleting any additional resources associated with the model, such as stored files, before the model is permanently removed from the database:

```
1/**
2 * Prepare the model for pruning.
3 */
4protected function pruning(): void
5{
6    // ...
7}

/**
 * Prepare the model for pruning.
 */
protected function pruning(): void
{
    // ...
}
```

After configuring your prunable model, you should schedule the `model:prune` Artisan command in your application's `routes/console.php` file. You are free to choose the appropriate interval at which this command should be run:

```
1use Illuminate\Support\Facades\Schedule;
2 
3Schedule::command('model:prune')->daily();

use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune')->daily();
```

Behind the scenes, the `model:prune` command will automatically detect "Prunable" models within your application's `app/Models` directory. If your models are in a different location, you may use the `--model` option to specify the model class names:

```
1Schedule::command('model:prune', [
2    '--model' => [Address::class, Flight::class],
3])->daily();

Schedule::command('model:prune', [
    '--model' => [Address::class, Flight::class],
])->daily();
```

If you wish to exclude certain models from being pruned while pruning all other detected models, you may use the `--except` option:

```
1Schedule::command('model:prune', [
2    '--except' => [Address::class, Flight::class],
3])->daily();

Schedule::command('model:prune', [
    '--except' => [Address::class, Flight::class],
])->daily();
```

You may test your `prunable` query by executing the `model:prune` command with the `--pretend` option. When pretending, the `model:prune` command will simply report how many records would be pruned if the command were to actually run:

```
1php artisan model:prune --pretend

php artisan model:prune --pretend
```

 Soft deleting models will be permanently deleted (`forceDelete`) if they match the prunable query.

#### Mass Pruning

When models are marked with the `Illuminate\Database\Eloquent\MassPrunable` trait, models are deleted from the database using mass-deletion queries. Therefore, the `pruning` method will not be invoked, nor will the `deleting` and `deleted` model events be dispatched. This is because the models are never actually retrieved before deletion, thus making the pruning process much more efficient:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Builder;
 6use Illuminate\Database\Eloquent\Model;
 7use Illuminate\Database\Eloquent\MassPrunable;
 8 
 9class Flight extends Model
10{
11    use MassPrunable;
12 
13    /**
14     * Get the prunable model query.
15     */
16    public function prunable(): Builder
17    {
18        return static::where('created_at', '<=', now()->minus(months: 1));
19    }
20}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\MassPrunable;

class Flight extends Model
{
    use MassPrunable;

    /**
     * Get the prunable model query.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->minus(months: 1));
    }
}
```

## Replicating Models

You may create an unsaved copy of an existing model instance using the `replicate` method. This method is particularly useful when you have model instances that share many of the same attributes:

```
 1use App\Models\Address;
 2 
 3$shipping = Address::create([
 4    'type' => 'shipping',
 5    'line_1' => '123 Example Street',
 6    'city' => 'Victorville',
 7    'state' => 'CA',
 8    'postcode' => '90001',
 9]);
10 
11$billing = $shipping->replicate()->fill([
12    'type' => 'billing'
13]);
14 
15$billing->save();

use App\Models\Address;

$shipping = Address::create([
    'type' => 'shipping',
    'line_1' => '123 Example Street',
    'city' => 'Victorville',
    'state' => 'CA',
    'postcode' => '90001',
]);

$billing = $shipping->replicate()->fill([
    'type' => 'billing'
]);

$billing->save();
```

To exclude one or more attributes from being replicated to the new model, you may pass an array to the `replicate` method:

```
 1$flight = Flight::create([
 2    'destination' => 'LAX',
 3    'origin' => 'LHR',
 4    'last_flown' => '2020-03-04 11:00:00',
 5    'last_pilot_id' => 747,
 6]);
 7 
 8$flight = $flight->replicate([
 9    'last_flown',
10    'last_pilot_id'
11]);

$flight = Flight::create([
    'destination' => 'LAX',
    'origin' => 'LHR',
    'last_flown' => '2020-03-04 11:00:00',
    'last_pilot_id' => 747,
]);

$flight = $flight->replicate([
    'last_flown',
    'last_pilot_id'
]);
```

## Query Scopes

### Global Scopes

Global scopes allow you to add constraints to all queries for a given model. Laravel's own soft delete functionality utilizes global scopes to only retrieve "non-deleted" models from the database. Writing your own global scopes can provide a convenient, easy way to make sure every query for a given model receives certain constraints.

#### Generating Scopes

To generate a new global scope, you may invoke the `make:scope` Artisan command, which will place the generated scope in your application's `app/Models/Scopes` directory:

```
1php artisan make:scope AncientScope

php artisan make:scope AncientScope
```

#### Writing Global Scopes

Writing a global scope is simple. First, use the `make:scope` command to generate a class that implements the `Illuminate\Database\Eloquent\Scope` interface. The `Scope` interface requires you to implement one method: `apply`. The `apply` method may add `where` constraints or other types of clauses to the query as needed:

```
 1<?php
 2 
 3namespace App\Models\Scopes;
 4 
 5use Illuminate\Database\Eloquent\Builder;
 6use Illuminate\Database\Eloquent\Model;
 7use Illuminate\Database\Eloquent\Scope;
 8 
 9class AncientScope implements Scope
10{
11    /**
12     * Apply the scope to a given Eloquent query builder.
13     */
14    public function apply(Builder $builder, Model $model): void
15    {
16        $builder->where('created_at', '<', now()->minus(years: 2000));
17    }
18}

<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class AncientScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('created_at', '<', now()->minus(years: 2000));
    }
}
```

 If your global scope is adding columns to the select clause of the query, you should use the `addSelect` method instead of `select`. This will prevent the unintentional replacement of the query's existing select clause.

#### Applying Global Scopes

To assign a global scope to a model, you may simply place the `ScopedBy` attribute on the model:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use App\Models\Scopes\AncientScope;
 6use Illuminate\Database\Eloquent\Attributes\ScopedBy;
 7 
 8#[ScopedBy([AncientScope::class])]
 9class User extends Model
10{
11    //
12}

<?php

namespace App\Models;

use App\Models\Scopes\AncientScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

#[ScopedBy([AncientScope::class])]
class User extends Model
{
    //
}
```

Or, you may manually register the global scope by overriding the model's `booted` method and invoke the model's `addGlobalScope` method. The `addGlobalScope` method accepts an instance of your scope as its only argument:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use App\Models\Scopes\AncientScope;
 6use Illuminate\Database\Eloquent\Model;
 7 
 8class User extends Model
 9{
10    /**
11     * The "booted" method of the model.
12     */
13    protected static function booted(): void
14    {
15        static::addGlobalScope(new AncientScope);
16    }
17}

<?php

namespace App\Models;

use App\Models\Scopes\AncientScope;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new AncientScope);
    }
}
```

After adding the scope in the example above to the `App\Models\User` model, a call to the `User::all()` method will execute the following SQL query:

```
1select * from `users` where `created_at` < 0021-02-18 00:00:00

select * from `users` where `created_at` < 0021-02-18 00:00:00
```

#### Anonymous Global Scopes

Eloquent also allows you to define global scopes using closures, which is particularly useful for simple scopes that do not warrant a separate class of their own. When defining a global scope using a closure, you should provide a scope name of your own choosing as the first argument to the `addGlobalScope` method:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Builder;
 6use Illuminate\Database\Eloquent\Model;
 7 
 8class User extends Model
 9{
10    /**
11     * The "booted" method of the model.
12     */
13    protected static function booted(): void
14    {
15        static::addGlobalScope('ancient', function (Builder $builder) {
16            $builder->where('created_at', '<', now()->minus(years: 2000));
17        });
18    }
19}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('ancient', function (Builder $builder) {
            $builder->where('created_at', '<', now()->minus(years: 2000));
        });
    }
}
```

#### Removing Global Scopes

If you would like to remove a global scope for a given query, you may use the `withoutGlobalScope` method. This method accepts the class name of the global scope as its only argument:

```
1User::withoutGlobalScope(AncientScope::class)->get();

User::withoutGlobalScope(AncientScope::class)->get();
```

Or, if you defined the global scope using a closure, you should pass the string name that you assigned to the global scope:

```
1User::withoutGlobalScope('ancient')->get();

User::withoutGlobalScope('ancient')->get();
```

If you would like to remove several or even all of the query's global scopes, you may use the `withoutGlobalScopes` and `withoutGlobalScopesExcept` methods:

```
 1// Remove all of the global scopes...
 2User::withoutGlobalScopes()->get();
 3 
 4// Remove some of the global scopes...
 5User::withoutGlobalScopes([
 6    FirstScope::class, SecondScope::class
 7])->get();
 8 
 9// Remove all global scopes except the given ones...
10User::withoutGlobalScopesExcept([
11    SecondScope::class,
12])->get();

// Remove all of the global scopes...
User::withoutGlobalScopes()->get();

// Remove some of the global scopes...
User::withoutGlobalScopes([
    FirstScope::class, SecondScope::class
])->get();

// Remove all global scopes except the given ones...
User::withoutGlobalScopesExcept([
    SecondScope::class,
])->get();
```

### Local Scopes

Local scopes allow you to define common sets of query constraints that you may easily re-use throughout your application. For example, you may need to frequently retrieve all users that are considered "popular". To define a scope, add the `Scope` attribute to an Eloquent method.

Scopes should always return the same query builder instance or `void`:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Attributes\Scope;
 6use Illuminate\Database\Eloquent\Builder;
 7use Illuminate\Database\Eloquent\Model;
 8 
 9class User extends Model
10{
11    /**
12     * Scope a query to only include popular users.
13     */
14    #[Scope]
15    protected function popular(Builder $query): void
16    {
17        $query->where('votes', '>', 100);
18    }
19 
20    /**
21     * Scope a query to only include active users.
22     */
23    #[Scope]
24    protected function active(Builder $query): void
25    {
26        $query->where('active', 1);
27    }
28}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    /**
     * Scope a query to only include popular users.
     */
    #[Scope]
    protected function popular(Builder $query): void
    {
        $query->where('votes', '>', 100);
    }

    /**
     * Scope a query to only include active users.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('active', 1);
    }
}
```

#### Utilizing a Local Scope

Once the scope has been defined, you may call the scope methods when querying the model. You can even chain calls to various scopes:

```
1use App\Models\User;
2 
3$users = User::popular()->active()->orderBy('created_at')->get();

use App\Models\User;

$users = User::popular()->active()->orderBy('created_at')->get();
```

Combining multiple Eloquent model scopes via an `or` query operator may require the use of closures to achieve the correct [logical grouping](https://laravel.com/framework/docs/queries#logical-grouping):

```
1$users = User::popular()->orWhere(function (Builder $query) {
2    $query->active();
3})->get();

$users = User::popular()->orWhere(function (Builder $query) {
    $query->active();
})->get();
```

However, since this can be cumbersome, Laravel provides a "higher order" `orWhere` method that allows you to fluently chain scopes together without the use of closures:

```
1$users = User::popular()->orWhere->active()->get();

$users = User::popular()->orWhere->active()->get();
```

#### Dynamic Scopes

Sometimes you may wish to define a scope that accepts parameters. To get started, just add your additional parameters to your scope method's signature. Scope parameters should be defined after the `$query` parameter:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Attributes\Scope;
 6use Illuminate\Database\Eloquent\Builder;
 7use Illuminate\Database\Eloquent\Model;
 8 
 9class User extends Model
10{
11    /**
12     * Scope a query to only include users of a given type.
13     */
14    #[Scope]
15    protected function ofType(Builder $query, string $type): void
16    {
17        $query->where('type', $type);
18    }
19}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    /**
     * Scope a query to only include users of a given type.
     */
    #[Scope]
    protected function ofType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }
}
```

Once the expected arguments have been added to your scope method's signature, you may pass the arguments when calling the scope:

```
1$users = User::ofType('admin')->get();

$users = User::ofType('admin')->get();
```

Attributed scope methods should be `protected`. When calling an attributed scope from within the model class, call the scope through a query builder instance, such as `static::query()->ofType('admin')`, to ensure the call is routed through Eloquent's scope handling.

### Pending Attributes

If you would like to use scopes to create models that have the same attributes as those used to constrain the scope, you may use the `withAttributes` method when building the scope query:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Attributes\Scope;
 6use Illuminate\Database\Eloquent\Builder;
 7use Illuminate\Database\Eloquent\Model;
 8 
 9class Post extends Model
10{
11    /**
12     * Scope the query to only include drafts.
13     */
14    #[Scope]
15    protected function draft(Builder $query): void
16    {
17        $query->withAttributes([
18            'hidden' => true,
19        ]);
20    }
21}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    /**
     * Scope the query to only include drafts.
     */
    #[Scope]
    protected function draft(Builder $query): void
    {
        $query->withAttributes([
            'hidden' => true,
        ]);
    }
}
```

The `withAttributes` method will add `where` conditions to the query using the given attributes, and it will also add the given attributes to any models created via the scope:

```
1$draft = Post::draft()->create(['title' => 'In Progress']);
2 
3$draft->hidden; // true

$draft = Post::draft()->create(['title' => 'In Progress']);

$draft->hidden; // true
```

To instruct the `withAttributes` method to not add `where` conditions to the query, you may set the `asConditions` argument to `false`:

```
1$query->withAttributes([
2    'hidden' => true,
3], asConditions: false);

$query->withAttributes([
    'hidden' => true,
], asConditions: false);
```

## Comparing Models

Sometimes you may need to determine if two models are the "same" or not. The `is` and `isNot` methods may be used to quickly verify two models have the same primary key, table, and database connection or not:

```
1if ($post->is($anotherPost)) {
2    // ...
3}
4 
5if ($post->isNot($anotherPost)) {
6    // ...
7}

if ($post->is($anotherPost)) {
    // ...
}

if ($post->isNot($anotherPost)) {
    // ...
}
```

The `is` and `isNot` methods are also available when using the `belongsTo`, `hasOne`, `morphTo`, and `morphOne` [relationships](https://laravel.com/framework/docs/eloquent-relationships). This method is particularly helpful when you would like to compare a related model without issuing a query to retrieve that model:

```
1if ($post->author()->is($user)) {
2    // ...
3}

if ($post->author()->is($user)) {
    // ...
}
```

## Events

 Want to broadcast your Eloquent events directly to your client-side application? Check out Laravel's [model event broadcasting](https://laravel.com/framework/docs/broadcasting#model-broadcasting).

Eloquent models dispatch several events, allowing you to hook into the following moments in a model's lifecycle: `retrieved`, `creating`, `created`, `updating`, `updated`, `saving`, `saved`, `deleting`, `deleted`, `trashed`, `forceDeleting`, `forceDeleted`, `restoring`, `restored`, and `replicating`.

The `retrieved` event will dispatch when an existing model is retrieved from the database. When a new model is saved for the first time, the `creating` and `created` events will dispatch. The `updating` / `updated` events will dispatch when an existing model is modified and the `save` method is called. The `saving` / `saved` events will dispatch when a model is created or updated - even if the model's attributes have not been changed. Event names ending with `-ing` are dispatched before any changes to the model are persisted, while events ending with `-ed` are dispatched after the changes to the model are persisted.

To start listening to model events, define a `$dispatchesEvents` property on your Eloquent model. This property maps various points of the Eloquent model's lifecycle to your own [event classes](https://laravel.com/framework/docs/events). Each model event class should expect to receive an instance of the affected model via its constructor:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use App\Events\UserDeleted;
 6use App\Events\UserSaved;
 7use Illuminate\Foundation\Auth\User as Authenticatable;
 8use Illuminate\Notifications\Notifiable;
 9 
10class User extends Authenticatable
11{
12    use Notifiable;
13 
14    /**
15     * The event map for the model.
16     *
17     * @var array<string, string>
18     */
19    protected $dispatchesEvents = [
20        'saved' => UserSaved::class,
21        'deleted' => UserDeleted::class,
22    ];
23}

<?php

namespace App\Models;

use App\Events\UserDeleted;
use App\Events\UserSaved;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'saved' => UserSaved::class,
        'deleted' => UserDeleted::class,
    ];
}
```

After defining and mapping your Eloquent events, you may use [event listeners](https://laravel.com/framework/docs/events#defining-listeners) to handle the events.

 When issuing a mass update or delete query via Eloquent, the `saved`, `updated`, `deleting`, and `deleted` model events will not be dispatched for the affected models. This is because the models are never actually retrieved when performing mass updates or deletes.

### Using Closures

Instead of using custom event classes, you may register closures that execute when various model events are dispatched. Typically, you should register these closures in the `booted` method of your model:

```
 1<?php
 2 
 3namespace App\Models;
 4 
 5use Illuminate\Database\Eloquent\Model;
 6 
 7class User extends Model
 8{
 9    /**
10     * The "booted" method of the model.
11     */
12    protected static function booted(): void
13    {
14        static::created(function (User $user) {
15            // ...
16        });
17    }
18}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            // ...
        });
    }
}
```

If needed, you may utilize [queueable anonymous event listeners](https://laravel.com/framework/docs/events#queueable-anonymous-event-listeners) when registering model events. This will instruct Laravel to execute the model event listener in the background using your application's [queue](https://laravel.com/framework/docs/queues):

```
1use function Illuminate\Events\queueable;
2 
3static::created(queueable(function (User $user) {
4    // ...
5}));

use function Illuminate\Events\queueable;

static::created(queueable(function (User $user) {
    // ...
}));
```

### Observers

#### Defining Observers

If you are listening for many events on a given model, you may use observers to group all of your listeners into a single class. Observer classes have method names which reflect the Eloquent events you wish to listen for. Each of these methods receives the affected model as their only argument. The `make:observer` Artisan command is the easiest way to create a new observer class:

```
1php artisan make:observer UserObserver --model=User

php artisan make:observer UserObserver --model=User
```

This command will place the new observer in your `app/Observers` directory. If this directory does not exist, Artisan will create it for you. Your fresh observer will look like the following:

```
 1<?php
 2 
 3namespace App\Observers;
 4 
 5use App\Models\User;
 6 
 7class UserObserver
 8{
 9    /**
10     * Handle the User "created" event.
11     */
12    public function created(User $user): void
13    {
14        // ...
15    }
16 
17    /**
18     * Handle the User "updated" event.
19     */
20    public function updated(User $user): void
21    {
22        // ...
23    }
24 
25    /**
26     * Handle the User "deleted" event.
27     */
28    public function deleted(User $user): void
29    {
30        // ...
31    }
32 
33    /**
34     * Handle the User "restored" event.
35     */
36    public function restored(User $user): void
37    {
38        // ...
39    }
40 
41    /**
42     * Handle the User "forceDeleted" event.
43     */
44    public function forceDeleted(User $user): void
45    {
46        // ...
47    }
48}

<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // ...
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // ...
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        // ...
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        // ...
    }

    /**
     * Handle the User "forceDeleted" event.
     */
    public function forceDeleted(User $user): void
    {
        // ...
    }
}
```

To register an observer, you may place the `ObservedBy` attribute on the corresponding model:

```
1use App\Observers\UserObserver;
2use Illuminate\Database\Eloquent\Attributes\ObservedBy;
3 
4#[ObservedBy([UserObserver::class])]
5class User extends Authenticatable
6{
7    //
8}

use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([UserObserver::class])]
class User extends Authenticatable
{
    //
}
```

Or, you may manually register an observer by invoking the `observe` method on the model you wish to observe. You may register observers in the `boot` method of your application's `AppServiceProvider` class:

```
 1use App\Models\User;
 2use App\Observers\UserObserver;
 3 
 4/**
 5 * Bootstrap any application services.
 6 */
 7public function boot(): void
 8{
 9    User::observe(UserObserver::class);
10}

use App\Models\User;
use App\Observers\UserObserver;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    User::observe(UserObserver::class);
}
```

 There are additional events an observer can listen to, such as `saving` and `retrieved`. These events are described within the events documentation.

#### Observers and Database Transactions

When models are being created within a database transaction, you may want to instruct an observer to only execute its event handlers after the database transaction is committed. You may accomplish this by implementing the `ShouldHandleEventsAfterCommit` interface on your observer. If a database transaction is not in progress, the event handlers will execute immediately:

```
 1<?php
 2 
 3namespace App\Observers;
 4 
 5use App\Models\User;
 6use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
 7 
 8class UserObserver implements ShouldHandleEventsAfterCommit
 9{
10    /**
11     * Handle the User "created" event.
12     */
13    public function created(User $user): void
14    {
15        // ...
16    }
17}

<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class UserObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // ...
    }
}
```

### Muting Events

You may occasionally need to temporarily "mute" all events fired by a model. You may achieve this using the `withoutEvents` method. The `withoutEvents` method accepts a closure as its only argument. Any code executed within this closure will not dispatch model events, and any value returned by the closure will be returned by the `withoutEvents` method:

```
1use App\Models\User;
2 
3$user = User::withoutEvents(function () {
4    User::findOrFail(1)->delete();
5 
6    return User::find(2);
7});

use App\Models\User;

$user = User::withoutEvents(function () {
    User::findOrFail(1)->delete();

    return User::find(2);
});
```

#### Saving a Single Model Without Events

Sometimes you may wish to "save" a given model without dispatching any events. You may accomplish this using the `saveQuietly` method:

```
1$user = User::findOrFail(1);
2 
3$user->name = 'Victoria Faith';
4 
5$user->saveQuietly();

$user = User::findOrFail(1);

$user->name = 'Victoria Faith';

$user->saveQuietly();
```

You may also "update", "delete", "soft delete", "restore", and "replicate" a given model without dispatching any events:

```
1$user->deleteQuietly();
2$user->forceDeleteQuietly();
3$user->restoreQuietly();

$user->deleteQuietly();
$user->forceDeleteQuietly();
$user->restoreQuietly();
```
