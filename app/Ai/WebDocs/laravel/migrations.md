---
source: laravel
page: migrations
url: https://laravel.com/framework/docs/13.x/migrations
fetched_at: 2026-10-20T16:27:48+00:00
---

# Database: Migrations

- Introduction
- Generating Migrations
   - Squashing Migrations
- Migration Structure
- Running Migrations
   - Rolling Back Migrations
- Tables
   - Creating Tables
   - Updating Tables
   - Renaming / Dropping Tables
- Columns
   - Creating Columns
   - Available Column Types
   - Column Modifiers
   - Modifying Columns
   - Renaming Columns
   - Dropping Columns
- Indexes
   - Creating Indexes
   - Renaming Indexes
   - Dropping Indexes
   - Foreign Key Constraints
- Events

## Introduction

Migrations are like version control for your database, allowing your team to define and share the application's database schema definition. If you have ever had to tell a teammate to manually add a column to their local database schema after pulling in your changes from source control, you've faced the problem that database migrations solve.

The Laravel `Schema` [facade](https://laravel.com/framework/docs/facades) provides database agnostic support for creating and manipulating tables across all of Laravel's supported database systems. Typically, migrations will use this facade to create and modify database tables and columns.

## Generating Migrations

You may use the `make:migration` [Artisan command](https://laravel.com/framework/docs/artisan) to generate a database migration. The new migration will be placed in your `database/migrations` directory. Each migration filename contains a timestamp that allows Laravel to determine the order of the migrations:

```
1php artisan make:migration create_flights_table

php artisan make:migration create_flights_table
```

Laravel will use the name of the migration to attempt to guess the name of the table and whether or not the migration will be creating a new table. If Laravel is able to determine the table name from the migration name, Laravel will pre-fill the generated migration file with the specified table. Otherwise, you may simply specify the table in the migration file manually.

If you would like to specify a custom path for the generated migration, you may use the `--path` option when executing the `make:migration` command. The given path should be relative to your application's base path.

 Migration stubs may be customized using [stub publishing](https://laravel.com/framework/docs/artisan#stub-customization).

### Squashing Migrations

As you build your application, you may accumulate more and more migrations over time. This can lead to your `database/migrations` directory becoming bloated with potentially hundreds of migrations. If you would like, you may "squash" your migrations into a single SQL file. To get started, execute the `schema:dump` command:

```
1php artisan schema:dump
2 
3# Dump the current database schema and prune all existing migrations...
4php artisan schema:dump --prune

php artisan schema:dump

# Dump the current database schema and prune all existing migrations...
php artisan schema:dump --prune
```

When you execute this command, Laravel will write a "schema" file to your application's `database/schema` directory. The schema file's name will correspond to the database connection. Now, when you attempt to migrate your database and no other migrations have been executed, Laravel will first execute the SQL statements in the schema file of the database connection you are using. After executing the schema file's SQL statements, Laravel will execute any remaining migrations that were not part of the schema dump.

If your application's tests use a different database connection than the one you typically use during local development, you should ensure you have dumped a schema file using that database connection so that your tests are able to build your database. You may wish to do this after dumping the database connection you typically use during local development:

```
1php artisan schema:dump
2php artisan schema:dump --database=testing --prune

php artisan schema:dump
php artisan schema:dump --database=testing --prune
```

You should commit your database schema file to source control so that other new developers on your team may quickly create your application's initial database structure.

 Migration squashing is only available for the MariaDB, MySQL, PostgreSQL, and SQLite databases and utilizes the database's command-line client.

## Migration Structure

A migration class contains two methods: `up` and `down`. The `up` method is used to add new tables, columns, or indexes to your database, while the `down` method should reverse the operations performed by the `up` method.

Within both of these methods, you may use the Laravel schema builder to expressively create and modify tables. To learn about all of the methods available on the `Schema` builder, check out its documentation. For example, the following migration creates a `flights` table:

```
 1<?php
 2 
 3use Illuminate\Database\Migrations\Migration;
 4use Illuminate\Database\Schema\Blueprint;
 5use Illuminate\Support\Facades\Schema;
 6 
 7return new class extends Migration
 8{
 9    /**
10     * Run the migrations.
11     */
12    public function up(): void
13    {
14        Schema::create('flights', function (Blueprint $table) {
15            $table->id();
16            $table->string('name');
17            $table->string('airline');
18            $table->timestamps();
19        });
20    }
21 
22    /**
23     * Reverse the migrations.
24     */
25    public function down(): void
26    {
27        Schema::drop('flights');
28    }
29};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('airline');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('flights');
    }
};
```

#### Setting the Migration Connection

If your migration will be interacting with a database connection other than your application's default database connection, you should set the `$connection` property of your migration:

```
 1/**
 2 * The database connection that should be used by the migration.
 3 *
 4 * @var string
 5 */
 6protected $connection = 'pgsql';
 7 
 8/**
 9 * Run the migrations.
10 */
11public function up(): void
12{
13    // ...
14}

/**
 * The database connection that should be used by the migration.
 *
 * @var string
 */
protected $connection = 'pgsql';

/**
 * Run the migrations.
 */
public function up(): void
{
    // ...
}
```

#### Skipping Migrations

Sometimes a migration might be meant to support a feature that is not yet active and you do not want it to run yet. In this case you may define a `shouldRun` method on the migration. If the `shouldRun` method returns `false`, the migration will be skipped:

```
 1use App\Models\Flight;
 2use Laravel\Pennant\Feature;
 3 
 4/**
 5 * Determine if this migration should run.
 6 */
 7public function shouldRun(): bool
 8{
 9    return Feature::active(Flight::class);
10}

use App\Models\Flight;
use Laravel\Pennant\Feature;

/**
 * Determine if this migration should run.
 */
public function shouldRun(): bool
{
    return Feature::active(Flight::class);
}
```

## Running Migrations

To run all of your outstanding migrations, execute the `migrate` Artisan command:

```
1php artisan migrate

php artisan migrate
```

If you would like to see which migrations have already run and which are still pending, you may use the `migrate:status` Artisan command:

```
1php artisan migrate:status

php artisan migrate:status
```

If you provide the `--step` option to the `migrate` command, the command will run each migration as its own batch, allowing you to roll back individual migrations later using the `migrate:rollback` command:

```
1php artisan migrate --step

php artisan migrate --step
```

If you would like to see the SQL statements that will be executed by the migrations without actually running them, you may provide the `--pretend` flag to the `migrate` command:

```
1php artisan migrate --pretend

php artisan migrate --pretend
```

#### Isolating Migration Execution

If you are deploying your application across multiple servers and running migrations as part of your deployment process, you likely do not want two servers attempting to migrate the database at the same time. To avoid this, you may use the `isolated` option when invoking the `migrate` command.

When the `isolated` option is provided, Laravel will acquire an atomic lock using your application's cache driver before attempting to run your migrations. All other attempts to run the `migrate` command while that lock is held will not execute; however, the command will still exit with a successful exit status code:

```
1php artisan migrate --isolated

php artisan migrate --isolated
```

 To utilize this feature, your application must be using the `memcached`, `redis`, `dynamodb`, `database`, `file`, or `array` cache driver as your application's default cache driver. In addition, all servers must be communicating with the same central cache server.

#### Forcing Migrations to Run in Production

Some migration operations are destructive, which means they may cause you to lose data. In order to protect you from running these commands against your production database, you will be prompted for confirmation before the commands are executed. To force the commands to run without a prompt, use the `--force` flag:

```
1php artisan migrate --force

php artisan migrate --force
```

### Rolling Back Migrations

To roll back the latest migration operation, you may use the `rollback` Artisan command. This command rolls back the last "batch" of migrations, which may include multiple migration files:

```
1php artisan migrate:rollback

php artisan migrate:rollback
```

You may roll back a limited number of migrations by providing the `step` option to the `rollback` command. For example, the following command will roll back the last five migrations:

```
1php artisan migrate:rollback --step=5

php artisan migrate:rollback --step=5
```

You may roll back a specific "batch" of migrations by providing the `batch` option to the `rollback` command, where the `batch` option corresponds to a batch value within your application's `migrations` database table. For example, the following command will roll back all migrations in batch three:

```
1php artisan migrate:rollback --batch=3

php artisan migrate:rollback --batch=3
```

If you would like to see the SQL statements that will be executed by the migrations without actually running them, you may provide the `--pretend` flag to the `migrate:rollback` command:

```
1php artisan migrate:rollback --pretend

php artisan migrate:rollback --pretend
```

The `migrate:reset` command will roll back all of your application's migrations:

```
1php artisan migrate:reset

php artisan migrate:reset
```

#### Roll Back and Migrate Using a Single Command

The `migrate:refresh` command will roll back all of your migrations and then execute the `migrate` command. This command effectively re-creates your entire database:

```
1php artisan migrate:refresh
2 
3# Refresh the database and run all database seeds...
4php artisan migrate:refresh --seed

php artisan migrate:refresh

# Refresh the database and run all database seeds...
php artisan migrate:refresh --seed
```

You may roll back and re-migrate a limited number of migrations by providing the `step` option to the `refresh` command. For example, the following command will roll back and re-migrate the last five migrations:

```
1php artisan migrate:refresh --step=5

php artisan migrate:refresh --step=5
```

#### Drop All Tables and Migrate

The `migrate:fresh` command will drop all tables from the database and then execute the `migrate` command:

```
1php artisan migrate:fresh
2 
3php artisan migrate:fresh --seed

php artisan migrate:fresh

php artisan migrate:fresh --seed
```

By default, the `migrate:fresh` command only drops tables from the default database connection. However, you may use the `--database` option to specify the database connection that should be migrated. The database connection name should correspond to a connection defined in your application's `database` [configuration file](https://laravel.com/framework/docs/configuration):

```
1php artisan migrate:fresh --database=admin

php artisan migrate:fresh --database=admin
```

 The `migrate:fresh` command will drop all database tables regardless of their prefix. This command should be used with caution when developing on a database that is shared with other applications.

## Tables

### Creating Tables

To create a new database table, use the `create` method on the `Schema` facade. The `create` method accepts two arguments: the first is the name of the table, while the second is a closure which receives a `Blueprint` object that may be used to define the new table:

```
1use Illuminate\Database\Schema\Blueprint;
2use Illuminate\Support\Facades\Schema;
3 
4Schema::create('users', function (Blueprint $table) {
5    $table->id();
6    $table->string('name');
7    $table->string('email');
8    $table->timestamps();
9});

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email');
    $table->timestamps();
});
```

When creating the table, you may use any of the schema builder's column methods to define the table's columns.

#### Determining Table / Column Existence

You may determine the existence of a table, column, or index using the `hasTable`, `hasColumn`, and `hasIndex` methods:

```
 1if (Schema::hasTable('users')) {
 2    // The "users" table exists...
 3}
 4 
 5if (Schema::hasColumn('users', 'email')) {
 6    // The "users" table exists and has an "email" column...
 7}
 8 
 9if (Schema::hasIndex('users', ['email'], 'unique')) {
10    // The "users" table exists and has a unique index on the "email" column...
11}

if (Schema::hasTable('users')) {
    // The "users" table exists...
}

if (Schema::hasColumn('users', 'email')) {
    // The "users" table exists and has an "email" column...
}

if (Schema::hasIndex('users', ['email'], 'unique')) {
    // The "users" table exists and has a unique index on the "email" column...
}
```

#### Database Connection and Table Options

If you want to perform a schema operation on a database connection that is not your application's default connection, use the `connection` method:

```
1Schema::connection('sqlite')->create('users', function (Blueprint $table) {
2    $table->id();
3});

Schema::connection('sqlite')->create('users', function (Blueprint $table) {
    $table->id();
});
```

In addition, a few other properties and methods may be used to define other aspects of the table's creation. The `engine` property may be used to specify the table's storage engine when using MariaDB or MySQL:

```
1Schema::create('users', function (Blueprint $table) {
2    $table->engine('InnoDB');
3 
4    // ...
5});

Schema::create('users', function (Blueprint $table) {
    $table->engine('InnoDB');

    // ...
});
```

The `charset` and `collation` properties may be used to specify the character set and collation for the created table when using MariaDB or MySQL:

```
1Schema::create('users', function (Blueprint $table) {
2    $table->charset('utf8mb4');
3    $table->collation('utf8mb4_unicode_ci');
4 
5    // ...
6});

Schema::create('users', function (Blueprint $table) {
    $table->charset('utf8mb4');
    $table->collation('utf8mb4_unicode_ci');

    // ...
});
```

The `temporary` method may be used to indicate that the table should be "temporary". Temporary tables are only visible to the current connection's database session and are dropped automatically when the connection is closed:

```
1Schema::create('calculations', function (Blueprint $table) {
2    $table->temporary();
3 
4    // ...
5});

Schema::create('calculations', function (Blueprint $table) {
    $table->temporary();

    // ...
});
```

If you would like to add a "comment" to a database table, you may invoke the `comment` method on the table instance. Table comments are currently only supported by MariaDB, MySQL, and PostgreSQL:

```
1Schema::create('calculations', function (Blueprint $table) {
2    $table->comment('Business calculations');
3 
4    // ...
5});

Schema::create('calculations', function (Blueprint $table) {
    $table->comment('Business calculations');

    // ...
});
```

### Updating Tables

The `table` method on the `Schema` facade may be used to update existing tables. Like the `create` method, the `table` method accepts two arguments: the name of the table and a closure that receives a `Blueprint` instance you may use to add columns or indexes to the table:

```
1use Illuminate\Database\Schema\Blueprint;
2use Illuminate\Support\Facades\Schema;
3 
4Schema::table('users', function (Blueprint $table) {
5    $table->integer('votes');
6});

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('users', function (Blueprint $table) {
    $table->integer('votes');
});
```

### Renaming / Dropping Tables

To rename an existing database table, use the `rename` method:

```
1use Illuminate\Support\Facades\Schema;
2 
3Schema::rename($from, $to);

use Illuminate\Support\Facades\Schema;

Schema::rename($from, $to);
```

To drop an existing table, you may use the `drop` or `dropIfExists` methods:

```
1Schema::drop('users');
2 
3Schema::dropIfExists('users');

Schema::drop('users');

Schema::dropIfExists('users');
```

#### Renaming Tables With Foreign Keys

Before renaming a table, you should verify that any foreign key constraints on the table have an explicit name in your migration files instead of letting Laravel assign a convention based name. Otherwise, the foreign key constraint name will refer to the old table name.

## Columns

### Creating Columns

The `table` method on the `Schema` facade may be used to update existing tables. Like the `create` method, the `table` method accepts two arguments: the name of the table and a closure that receives an `Illuminate\Database\Schema\Blueprint` instance you may use to add columns to the table:

```
1use Illuminate\Database\Schema\Blueprint;
2use Illuminate\Support\Facades\Schema;
3 
4Schema::table('users', function (Blueprint $table) {
5    $table->integer('votes');
6});

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('users', function (Blueprint $table) {
    $table->integer('votes');
});
```

### Available Column Types

The schema builder blueprint offers a variety of methods that correspond to the different types of columns you can add to your database tables. Each of the available methods are listed in the table below:

#### Boolean Types

boolean

#### String & Text Types

char longText mediumText string text tinyText

#### Numeric Types

bigIncrements bigInteger decimal double float id increments integer mediumIncrements mediumInteger smallIncrements smallInteger tinyIncrements tinyInteger unsignedBigInteger unsignedInteger unsignedMediumInteger unsignedSmallInteger unsignedTinyInteger

#### Date & Time Types

dateTime dateTimeTz date time timeTz timestamp timestamps timestampsTz softDeletes softDeletesTz year

#### Binary Types

binary

#### Object & Json Types

json jsonb

#### UUID & ULID Types

ulid ulidMorphs uuid uuidMorphs nullableUlidMorphs nullableUuidMorphs

#### Spatial Types

geography geometry

#### Relationship Types

foreignId foreignIdFor foreignUlid foreignUuid foreignUuidFor morphs nullableMorphs

#### Specialty Types

enum set macAddress ipAddress rememberToken vector

#### `bigIncrements()`

The `bigIncrements` method creates an auto-incrementing `UNSIGNED BIGINT` (primary key) equivalent column:

```
1$table->bigIncrements('id');

$table->bigIncrements('id');
```

#### `bigInteger()`

The `bigInteger` method creates a `BIGINT` equivalent column:

```
1$table->bigInteger('votes');

$table->bigInteger('votes');
```

#### `binary()`

The `binary` method creates a `BLOB` equivalent column:

```
1$table->binary('photo');

$table->binary('photo');
```

When utilizing MySQL, MariaDB, or SQL Server, you may pass `length` and `fixed` arguments to create `VARBINARY` or `BINARY` equivalent column:

```
1$table->binary('data', length: 16); // VARBINARY(16)
2 
3$table->binary('data', length: 16, fixed: true); // BINARY(16)

$table->binary('data', length: 16); // VARBINARY(16)

$table->binary('data', length: 16, fixed: true); // BINARY(16)
```

#### `boolean()`

The `boolean` method creates a `BOOLEAN` equivalent column:

```
1$table->boolean('confirmed');

$table->boolean('confirmed');
```

#### `char()`

The `char` method creates a `CHAR` equivalent column with of a given length:

```
1$table->char('name', length: 100);

$table->char('name', length: 100);
```

#### `dateTimeTz()`

The `dateTimeTz` method creates a `DATETIME` (with timezone) equivalent column with an optional fractional seconds precision:

```
1$table->dateTimeTz('created_at', precision: 0);

$table->dateTimeTz('created_at', precision: 0);
```

#### `dateTime()`

The `dateTime` method creates a `DATETIME` equivalent column with an optional fractional seconds precision:

```
1$table->dateTime('created_at', precision: 0);

$table->dateTime('created_at', precision: 0);
```

#### `date()`

The `date` method creates a `DATE` equivalent column:

```
1$table->date('created_at');

$table->date('created_at');
```

#### `decimal()`

The `decimal` method creates a `DECIMAL` equivalent column with the given precision (total digits) and scale (decimal digits):

```
1$table->decimal('amount', total: 8, places: 2);

$table->decimal('amount', total: 8, places: 2);
```

#### `double()`

The `double` method creates a `DOUBLE` equivalent column:

```
1$table->double('amount');

$table->double('amount');
```

#### `enum()`

The `enum` method creates a `ENUM` equivalent column with the given valid values:

```
1$table->enum('difficulty', ['easy', 'hard']);

$table->enum('difficulty', ['easy', 'hard']);
```

Of course, you may use the `Enum::cases()` method instead of manually defining an array of allowed values:

```
1use App\Enums\Difficulty;
2 
3$table->enum('difficulty', Difficulty::cases());

use App\Enums\Difficulty;

$table->enum('difficulty', Difficulty::cases());
```

#### `float()`

The `float` method creates a `FLOAT` equivalent column with the given precision:

```
1$table->float('amount', precision: 53);

$table->float('amount', precision: 53);
```

#### `foreignId()`

The `foreignId` method creates an `UNSIGNED BIGINT` equivalent column:

```
1$table->foreignId('user_id');

$table->foreignId('user_id');
```

#### `foreignIdFor()`

The `foreignIdFor` method adds a `{column}_id` equivalent column for a given model class. The column type will be `UNSIGNED BIGINT`, `CHAR(36)`, or `CHAR(26)` depending on the model key type:

```
1$table->foreignIdFor(User::class);

$table->foreignIdFor(User::class);
```

#### `foreignUlid()`

The `foreignUlid` method creates a `ULID` equivalent column:

```
1$table->foreignUlid('user_id');

$table->foreignUlid('user_id');
```

#### `foreignUuid()`

The `foreignUuid` method creates a `UUID` equivalent column:

```
1$table->foreignUuid('user_id');

$table->foreignUuid('user_id');
```

#### `foreignUuidFor()`

The `foreignUuidFor` method adds a `{column}_id` UUID equivalent column for a given model class:

```
1$table->foreignUuidFor(User::class);

$table->foreignUuidFor(User::class);
```

#### `geography()`

The `geography` method creates a `GEOGRAPHY` equivalent column with the given spatial type and SRID (Spatial Reference System Identifier):

```
1$table->geography('coordinates', subtype: 'point', srid: 4326);

$table->geography('coordinates', subtype: 'point', srid: 4326);
```

 Support for spatial types depends on your database driver. Please refer to your database's documentation. If your application is utilizing a PostgreSQL database, you must install the [PostGIS](https://postgis.net) extension before the `geography` method may be used.

#### `geometry()`

The `geometry` method creates a `GEOMETRY` equivalent column with the given spatial type and SRID (Spatial Reference System Identifier):

```
1$table->geometry('positions', subtype: 'point', srid: 0);

$table->geometry('positions', subtype: 'point', srid: 0);
```

 Support for spatial types depends on your database driver. Please refer to your database's documentation. If your application is utilizing a PostgreSQL database, you must install the [PostGIS](https://postgis.net) extension before the `geometry` method may be used.

#### `id()`

The `id` method is an alias of the `bigIncrements` method. By default, the method will create an `id` column; however, you may pass a column name if you would like to assign a different name to the column:

```
1$table->id();

$table->id();
```

#### `increments()`

The `increments` method creates an auto-incrementing `UNSIGNED INTEGER` equivalent column as a primary key:

```
1$table->increments('id');

$table->increments('id');
```

#### `integer()`

The `integer` method creates an `INTEGER` equivalent column:

```
1$table->integer('votes');

$table->integer('votes');
```

#### `ipAddress()`

The `ipAddress` method creates a `VARCHAR` equivalent column:

```
1$table->ipAddress('visitor');

$table->ipAddress('visitor');
```

When using PostgreSQL, an `INET` column will be created.

#### `json()`

The `json` method creates a `JSON` equivalent column:

```
1$table->json('options');

$table->json('options');
```

When using SQLite, a `TEXT` column will be created.

#### `jsonb()`

The `jsonb` method creates a `JSONB` equivalent column:

```
1$table->jsonb('options');

$table->jsonb('options');
```

When using SQLite, a `TEXT` column will be created.

#### `longText()`

The `longText` method creates a `LONGTEXT` equivalent column:

```
1$table->longText('description');

$table->longText('description');
```

When utilizing MySQL or MariaDB, you may apply a `binary` character set to the column in order to create a `LONGBLOB` equivalent column:

```
1$table->longText('data')->charset('binary'); // LONGBLOB

$table->longText('data')->charset('binary'); // LONGBLOB
```

#### `macAddress()`

The `macAddress` method creates a column that is intended to hold a MAC address. Some database systems, such as PostgreSQL, have a dedicated column type for this type of data. Other database systems will use a string equivalent column:

```
1$table->macAddress('device');

$table->macAddress('device');
```

#### `mediumIncrements()`

The `mediumIncrements` method creates an auto-incrementing `UNSIGNED MEDIUMINT` equivalent column as a primary key:

```
1$table->mediumIncrements('id');

$table->mediumIncrements('id');
```

#### `mediumInteger()`

The `mediumInteger` method creates a `MEDIUMINT` equivalent column:

```
1$table->mediumInteger('votes');

$table->mediumInteger('votes');
```

#### `mediumText()`

The `mediumText` method creates a `MEDIUMTEXT` equivalent column:

```
1$table->mediumText('description');

$table->mediumText('description');
```

When utilizing MySQL or MariaDB, you may apply a `binary` character set to the column in order to create a `MEDIUMBLOB` equivalent column:

```
1$table->mediumText('data')->charset('binary'); // MEDIUMBLOB

$table->mediumText('data')->charset('binary'); // MEDIUMBLOB
```

#### `morphs()`

The `morphs` method is a convenience method that adds a `{column}_type` `VARCHAR` equivalent column and a `{column}_id` equivalent column. The column type for the `{column}_id` will be `UNSIGNED BIGINT`, `CHAR(36)`, or `CHAR(26)` depending on the model key type.

This method is intended to be used when defining the columns necessary for a polymorphic [Eloquent relationship](https://laravel.com/framework/docs/eloquent-relationships). In the following example, `taggable_type` and `taggable_id` columns would be created:

```
1$table->morphs('taggable');

$table->morphs('taggable');
```

#### `nullableMorphs()`

The method is similar to the morphs method; however, the columns that are created will be "nullable":

```
1$table->nullableMorphs('taggable');

$table->nullableMorphs('taggable');
```

#### `nullableUlidMorphs()`

The method is similar to the ulidMorphs method; however, the columns that are created will be "nullable":

```
1$table->nullableUlidMorphs('taggable');

$table->nullableUlidMorphs('taggable');
```

#### `nullableUuidMorphs()`

The method is similar to the uuidMorphs method; however, the columns that are created will be "nullable":

```
1$table->nullableUuidMorphs('taggable');

$table->nullableUuidMorphs('taggable');
```

#### `rememberToken()`

The `rememberToken` method creates a nullable, `VARCHAR(100)` equivalent column that is intended to store the current "remember me" [authentication token](https://laravel.com/framework/docs/authentication#remembering-users):

```
1$table->rememberToken();

$table->rememberToken();
```

#### `set()`

The `set` method creates a `SET` equivalent column with the given list of valid values:

```
1$table->set('flavors', ['strawberry', 'vanilla']);

$table->set('flavors', ['strawberry', 'vanilla']);
```

#### `smallIncrements()`

The `smallIncrements` method creates an auto-incrementing `UNSIGNED SMALLINT` equivalent column as a primary key:

```
1$table->smallIncrements('id');

$table->smallIncrements('id');
```

#### `smallInteger()`

The `smallInteger` method creates a `SMALLINT` equivalent column:

```
1$table->smallInteger('votes');

$table->smallInteger('votes');
```

#### `softDeletesTz()`

The `softDeletesTz` method adds a nullable `deleted_at` `TIMESTAMP` (with timezone) equivalent column with an optional fractional seconds precision. This column is intended to store the `deleted_at` timestamp needed for Eloquent's "soft delete" functionality:

```
1$table->softDeletesTz('deleted_at', precision: 0);

$table->softDeletesTz('deleted_at', precision: 0);
```

#### `softDeletes()`

The `softDeletes` method adds a nullable `deleted_at` `TIMESTAMP` equivalent column with an optional fractional seconds precision. This column is intended to store the `deleted_at` timestamp needed for Eloquent's "soft delete" functionality:

```
1$table->softDeletes('deleted_at', precision: 0);

$table->softDeletes('deleted_at', precision: 0);
```

#### `string()`

The `string` method creates a `VARCHAR` equivalent column of the given length:

```
1$table->string('name', length: 100);

$table->string('name', length: 100);
```

#### `text()`

The `text` method creates a `TEXT` equivalent column:

```
1$table->text('description');

$table->text('description');
```

When utilizing MySQL or MariaDB, you may apply a `binary` character set to the column in order to create a `BLOB` equivalent column:

```
1$table->text('data')->charset('binary'); // BLOB

$table->text('data')->charset('binary'); // BLOB
```

#### `timeTz()`

The `timeTz` method creates a `TIME` (with timezone) equivalent column with an optional fractional seconds precision:

```
1$table->timeTz('sunrise', precision: 0);

$table->timeTz('sunrise', precision: 0);
```

#### `time()`

The `time` method creates a `TIME` equivalent column with an optional fractional seconds precision:

```
1$table->time('sunrise', precision: 0);

$table->time('sunrise', precision: 0);
```

#### `timestampTz()`

The `timestampTz` method creates a `TIMESTAMP` (with timezone) equivalent column with an optional fractional seconds precision:

```
1$table->timestampTz('added_at', precision: 0);

$table->timestampTz('added_at', precision: 0);
```

#### `timestamp()`

The `timestamp` method creates a `TIMESTAMP` equivalent column with an optional fractional seconds precision:

```
1$table->timestamp('added_at', precision: 0);

$table->timestamp('added_at', precision: 0);
```

#### `timestampsTz()`

The `timestampsTz` method creates `created_at` and `updated_at` `TIMESTAMP` (with timezone) equivalent columns with an optional fractional seconds precision:

```
1$table->timestampsTz(precision: 0);

$table->timestampsTz(precision: 0);
```

#### `timestamps()`

The `timestamps` method creates `created_at` and `updated_at` `TIMESTAMP` equivalent columns with an optional fractional seconds precision:

```
1$table->timestamps(precision: 0);

$table->timestamps(precision: 0);
```

#### `tinyIncrements()`

The `tinyIncrements` method creates an auto-incrementing `UNSIGNED TINYINT` equivalent column as a primary key:

```
1$table->tinyIncrements('id');

$table->tinyIncrements('id');
```

#### `tinyInteger()`

The `tinyInteger` method creates a `TINYINT` equivalent column:

```
1$table->tinyInteger('votes');

$table->tinyInteger('votes');
```

#### `tinyText()`

The `tinyText` method creates a `TINYTEXT` equivalent column:

```
1$table->tinyText('notes');

$table->tinyText('notes');
```

When utilizing MySQL or MariaDB, you may apply a `binary` character set to the column in order to create a `TINYBLOB` equivalent column:

```
1$table->tinyText('data')->charset('binary'); // TINYBLOB

$table->tinyText('data')->charset('binary'); // TINYBLOB
```

#### `unsignedBigInteger()`

The `unsignedBigInteger` method creates an `UNSIGNED BIGINT` equivalent column:

```
1$table->unsignedBigInteger('votes');

$table->unsignedBigInteger('votes');
```

#### `unsignedInteger()`

The `unsignedInteger` method creates an `UNSIGNED INTEGER` equivalent column:

```
1$table->unsignedInteger('votes');

$table->unsignedInteger('votes');
```

#### `unsignedMediumInteger()`

The `unsignedMediumInteger` method creates an `UNSIGNED MEDIUMINT` equivalent column:

```
1$table->unsignedMediumInteger('votes');

$table->unsignedMediumInteger('votes');
```

#### `unsignedSmallInteger()`

The `unsignedSmallInteger` method creates an `UNSIGNED SMALLINT` equivalent column:

```
1$table->unsignedSmallInteger('votes');

$table->unsignedSmallInteger('votes');
```

#### `unsignedTinyInteger()`

The `unsignedTinyInteger` method creates an `UNSIGNED TINYINT` equivalent column:

```
1$table->unsignedTinyInteger('votes');

$table->unsignedTinyInteger('votes');
```

#### `ulidMorphs()`

The `ulidMorphs` method is a convenience method that adds a `{column}_type` `VARCHAR` equivalent column and a `{column}_id` `CHAR(26)` equivalent column.

This method is intended to be used when defining the columns necessary for a polymorphic [Eloquent relationship](https://laravel.com/framework/docs/eloquent-relationships) that use ULID identifiers. In the following example, `taggable_type` and `taggable_id` columns would be created:

```
1$table->ulidMorphs('taggable');

$table->ulidMorphs('taggable');
```

#### `uuidMorphs()`

The `uuidMorphs` method is a convenience method that adds a `{column}_type` `VARCHAR` equivalent column and a `{column}_id` `CHAR(36)` equivalent column.

This method is intended to be used when defining the columns necessary for a [polymorphic Eloquent relationship](https://laravel.com/framework/docs/eloquent-relationships#polymorphic-relationships) that use UUID identifiers. In the following example, `taggable_type` and `taggable_id` columns would be created:

```
1$table->uuidMorphs('taggable');

$table->uuidMorphs('taggable');
```

#### `ulid()`

The `ulid` method creates a `ULID` equivalent column:

```
1$table->ulid('id');

$table->ulid('id');
```

#### `uuid()`

The `uuid` method creates a `UUID` equivalent column:

```
1$table->uuid('id');

$table->uuid('id');
```

#### `vector()`

The `vector` method creates a `vector` equivalent column:

```
1$table->vector('embedding', dimensions: 1536);

$table->vector('embedding', dimensions: 1536);
```

Vector columns are supported on PostgreSQL connections using the `pgvector` extension and MariaDB 11.7 or later. When utilizing PostgreSQL, `pgvector` must be loaded before `vector` columns can be created:

```
1Schema::ensureVectorExtensionExists();

Schema::ensureVectorExtensionExists();
```

To speed up [vector similarity queries](https://laravel.com/framework/docs/queries#vector-similarity-clauses), you may add a vector index to the column. Calling the `index` method on a `vector` column creates a vector index using cosine distance:

```
1$table->vector('embedding', dimensions: 1536)->index();

$table->vector('embedding', dimensions: 1536)->index();
```

#### `year()`

The `year` method creates a `YEAR` equivalent column:

```
1$table->year('birth_year');

$table->year('birth_year');
```

### Column Modifiers

In addition to the column types listed above, there are several column "modifiers" you may use when adding a column to a database table. For example, to make the column "nullable", you may use the `nullable` method:

```
1use Illuminate\Database\Schema\Blueprint;
2use Illuminate\Support\Facades\Schema;
3 
4Schema::table('users', function (Blueprint $table) {
5    $table->string('email')->nullable();
6});

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('users', function (Blueprint $table) {
    $table->string('email')->nullable();
});
```

The following table contains all of the available column modifiers. This list does not include index modifiers:

| Modifier | Description |
| --- | --- |
| `->after('column')` | Place the column "after" another column (MariaDB / MySQL). |
| `->autoIncrement()` | Set `INTEGER` columns as auto-incrementing (primary key). |
| `->charset('utf8mb4')` | Specify a character set for the column (MariaDB / MySQL). |
| `->collation('utf8mb4_unicode_ci')` | Specify a collation for the column. |
| `->comment('my comment')` | Add a comment to a column (MariaDB / MySQL / PostgreSQL). |
| `->default($value)` | Specify a "default" value for the column. |
| `->first()` | Place the column "first" in the table (MariaDB / MySQL). |
| `->from($integer)` | Set the starting value of an auto-incrementing field (MariaDB / MySQL / PostgreSQL). |
| `->instant()` | Add or modify the column using an instant operation (MySQL). |
| `->invisible()` | Make the column "invisible" to `SELECT *` queries (MariaDB / MySQL). |
| `->lock($mode)` | Specify a lock mode for the column operation (MySQL). |
| `->nullable($value = true)` | Allow `NULL` values to be inserted into the column. |
| `->storedAs($expression)` | Create a stored generated column (MariaDB / MySQL / PostgreSQL / SQLite). |
| `->unsigned()` | Set `INTEGER` columns as `UNSIGNED` (MariaDB / MySQL). |
| `->using($expression)` | Specify a casting expression when changing the column type (PostgreSQL). |
| `->useCurrent()` | Set `TIMESTAMP` columns to use `CURRENT_TIMESTAMP` as default value. |
| `->useCurrentOnUpdate()` | Set `TIMESTAMP` columns to use `CURRENT_TIMESTAMP` when a record is updated (MariaDB / MySQL). |
| `->virtualAs($expression)` | Create a virtual generated column (MariaDB / MySQL / SQLite). |
| `->generatedAs($expression)` | Create an identity column with specified sequence options (PostgreSQL). |
| `->always()` | Defines the precedence of sequence values over input for an identity column (PostgreSQL). |

#### Default Expressions

The `default` modifier accepts a value or an `Illuminate\Database\Query\Expression` instance. Using an `Expression` instance will prevent Laravel from wrapping the value in quotes and allow you to use database specific functions. One situation where this is particularly useful is when you need to assign default values to JSON columns:

```
 1<?php
 2 
 3use Illuminate\Support\Facades\Schema;
 4use Illuminate\Database\Schema\Blueprint;
 5use Illuminate\Database\Query\Expression;
 6use Illuminate\Database\Migrations\Migration;
 7 
 8return new class extends Migration
 9{
10    /**
11     * Run the migrations.
12     */
13    public function up(): void
14    {
15        Schema::create('flights', function (Blueprint $table) {
16            $table->id();
17            $table->json('movies')->default(new Expression('(JSON_ARRAY())'));
18            $table->timestamps();
19        });
20    }
21};

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->json('movies')->default(new Expression('(JSON_ARRAY())'));
            $table->timestamps();
        });
    }
};
```

 Support for default expressions depends on your database driver, database version, and the field type. Please refer to your database's documentation.

#### Column Order

When using the MariaDB or MySQL database, the `after` method may be used to add columns after an existing column in the schema:

```
1$table->after('password', function (Blueprint $table) {
2    $table->string('address_line1');
3    $table->string('address_line2');
4    $table->string('city');
5});

$table->after('password', function (Blueprint $table) {
    $table->string('address_line1');
    $table->string('address_line2');
    $table->string('city');
});
```

#### Instant Column Operations

When using MySQL, you may chain the `instant` modifier onto a column definition to indicate that the column should be added or modified using MySQL's "instant" algorithm. This algorithm allows certain schema changes to be performed without a full table rebuild, making them nearly instantaneous regardless of table size:

```
1$table->string('name')->nullable()->instant();

$table->string('name')->nullable()->instant();
```

Instant column additions can only append columns to the end of the table, so the `instant` modifier cannot be combined with the `after` or `first` modifiers. In addition, the algorithm does not support all column types or operations. If the requested operation is incompatible, MySQL will raise an error.

Please refer to [MySQL's documentation](https://dev.mysql.com/doc/refman/8.0/en/innodb-online-ddl-operations.html) to determine which operations are compatible with instant column modifications.

#### DDL Locking

When using MySQL, you may chain the `lock` modifier onto column, index, or foreign key definitions to control table locking during schema operations. MySQL supports several lock modes: `none` allows concurrent reads and writes, `shared` allows concurrent reads but blocks writes, `exclusive` blocks all concurrent access, and `default` lets MySQL choose the most appropriate mode:

```
1$table->string('name')->lock('none');
2 
3$table->index('email')->lock('shared');

$table->string('name')->lock('none');

$table->index('email')->lock('shared');
```

If the requested lock mode is incompatible with the operation, MySQL will raise an error. The `lock` modifier may be combined with the `instant` modifier to further optimize schema changes:

```
1$table->string('name')->instant()->lock('none');

$table->string('name')->instant()->lock('none');
```

### Modifying Columns

The `change` method allows you to modify the type and attributes of existing columns. For example, you may wish to increase the size of a `string` column. To see the `change` method in action, let's increase the size of the `name` column from 25 to 50. To accomplish this, we simply define the new state of the column and then call the `change` method:

```
1Schema::table('users', function (Blueprint $table) {
2    $table->string('name', 50)->change();
3});

Schema::table('users', function (Blueprint $table) {
    $table->string('name', 50)->change();
});
```

When modifying a column, you must explicitly include all the modifiers you want to keep on the column definition - any missing attribute will be dropped. For example, to retain the `unsigned`, `default`, and `comment` attributes, you must call each modifier explicitly when changing the column:

```
1Schema::table('users', function (Blueprint $table) {
2    $table->integer('votes')->unsigned()->default(1)->comment('my comment')->change();
3});

Schema::table('users', function (Blueprint $table) {
    $table->integer('votes')->unsigned()->default(1)->comment('my comment')->change();
});
```

The `change` method does not change the indexes of the column. Therefore, you may use index modifiers to explicitly add or drop an index when modifying the column:

```
1// Add an index...
2$table->bigIncrements('id')->primary()->change();
3 
4// Drop an index...
5$table->char('postal_code', 10)->unique(false)->change();

// Add an index...
$table->bigIncrements('id')->primary()->change();

// Drop an index...
$table->char('postal_code', 10)->unique(false)->change();
```

#### PostgreSQL Column Modifications

When changing a column's type on PostgreSQL, you may use the `using` modifier to specify the expression used to cast the existing values:

```
1Schema::table('users', function (Blueprint $table) {
2    $table->date('birthday')->using('birthday::date')->change();
3});

Schema::table('users', function (Blueprint $table) {
    $table->date('birthday')->using('birthday::date')->change();
});
```

### Renaming Columns

To rename a column, you may use the `renameColumn` method provided by the schema builder:

```
1Schema::table('users', function (Blueprint $table) {
2    $table->renameColumn('from', 'to');
3});

Schema::table('users', function (Blueprint $table) {
    $table->renameColumn('from', 'to');
});
```

### Dropping Columns

To drop a column, you may use the `dropColumn` method on the schema builder:

```
1Schema::table('users', function (Blueprint $table) {
2    $table->dropColumn('votes');
3});

Schema::table('users', function (Blueprint $table) {
    $table->dropColumn('votes');
});
```

You may drop multiple columns from a table by passing an array of column names to the `dropColumn` method:

```
1Schema::table('users', function (Blueprint $table) {
2    $table->dropColumn(['votes', 'avatar', 'location']);
3});

Schema::table('users', function (Blueprint $table) {
    $table->dropColumn(['votes', 'avatar', 'location']);
});
```

#### Available Command Aliases

Laravel provides several convenient methods related to dropping common types of columns. Each of these methods is described in the table below:

| Command | Description |
| --- | --- |
| `$table->dropMorphs('morphable');` | Drop the `morphable_type` and `morphable_id` columns. |
| `$table->dropRememberToken();` | Drop the `remember_token` column. |
| `$table->dropSoftDeletes();` | Drop the `deleted_at` column. |
| `$table->dropSoftDeletesTz();` | Alias of `dropSoftDeletes()` method. |
| `$table->dropTimestamps();` | Drop the `created_at` and `updated_at` columns. |
| `$table->dropTimestampsTz();` | Alias of `dropTimestamps()` method. |

## Indexes

### Creating Indexes

The Laravel schema builder supports several types of indexes. The following example creates a new `email` column and specifies that its values should be unique. To create the index, we can chain the `unique` method onto the column definition:

```
1use Illuminate\Database\Schema\Blueprint;
2use Illuminate\Support\Facades\Schema;
3 
4Schema::table('users', function (Blueprint $table) {
5    $table->string('email')->unique();
6});

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('users', function (Blueprint $table) {
    $table->string('email')->unique();
});
```

Alternatively, you may create the index after defining the column. To do so, you should call the `unique` method on the schema builder blueprint. This method accepts the name of the column that should receive a unique index:

```
1$table->unique('email');

$table->unique('email');
```

You may even pass an array of columns to an index method to create a compound (or composite) index:

```
1$table->index(['account_id', 'created_at']);

$table->index(['account_id', 'created_at']);
```

When creating an index, Laravel will automatically generate an index name based on the table, column names, and the index type, but you may pass a second argument to the method to specify the index name yourself:

```
1$table->unique('email', 'unique_email');

$table->unique('email', 'unique_email');
```

#### Available Index Types

Laravel's schema builder blueprint class provides methods for creating each type of index supported by Laravel. Each index method accepts an optional second argument to specify the name of the index. If omitted, the name will be derived from the names of the table and column(s) used for the index, as well as the index type. Each of the available index methods is described in the table below:

| Command | Description |
| --- | --- |
| `$table->primary('id');` | Adds a primary key. |
| `$table->primary(['id', 'parent_id']);` | Adds composite keys. |
| `$table->unique('email');` | Adds a unique index. |
| `$table->index('state');` | Adds an index. |
| `$table->fullText('body');` | Adds a full text index (MariaDB / MySQL / PostgreSQL). |
| `$table->fullText('body')->language('english');` | Adds a full text index of the specified language (PostgreSQL). |
| `$table->spatialIndex('location');` | Adds a spatial index (except SQLite). |
| `$table->vectorIndex('embedding');` | Adds a vector index (MariaDB / PostgreSQL). |

#### Online Index Creation

By default, creating an index on a large table can lock the table and block reads or writes while the index is being built. When using PostgreSQL or SQL Server, you may chain the `online` method onto an index definition to create the index without locking the table, allowing your application to continue reading and writing data during index creation:

```
1$table->unique('email')->online();

$table->unique('email')->online();
```

When using PostgreSQL, this adds the `CONCURRENTLY` option to the index creation statement. When using SQL Server, this adds the `WITH (online = on)` option.

When using MySQL, you may chain the `inplace` modifier onto an index or foreign key definition to specify that the operation should use the `INPLACE` algorithm:

```
1$table->index('email')->inplace();
2 
3$table->foreign('user_id')->references('id')->on('users')->inplace();

$table->index('email')->inplace();

$table->foreign('user_id')->references('id')->on('users')->inplace();
```

The `inplace` modifier may be combined with the `lock` modifier to control table locking during the operation:

```
1$table->index('email')->inplace()->lock('none');

$table->index('email')->inplace()->lock('none');
```

When using the `inplace` modifier for a foreign key operation, foreign key checks must be disabled.

Refer to [MySQL's documentation](https://dev.mysql.com/doc/refman/8.0/en/innodb-online-ddl-operations.html) to determine which operations support the `INPLACE` algorithm and lock modes.

### Renaming Indexes

To rename an index, you may use the `renameIndex` method provided by the schema builder blueprint. This method accepts the current index name as its first argument and the desired name as its second argument:

```
1$table->renameIndex('from', 'to');

$table->renameIndex('from', 'to');
```

### Dropping Indexes

To drop an index, you must specify the index's name. By default, Laravel automatically assigns an index name based on the table name, the name of the indexed column, and the index type. Here are some examples:

| Command | Description |
| --- | --- |
| `$table->dropPrimary('users_id_primary');` | Drop a primary key from the "users" table. |
| `$table->dropUnique('users_email_unique');` | Drop a unique index from the "users" table. |
| `$table->dropIndex('geo_state_index');` | Drop a basic index from the "geo" table. |
| `$table->dropFullText('posts_body_fulltext');` | Drop a full text index from the "posts" table. |
| `$table->dropSpatialIndex('geo_location_spatialindex');` | Drop a spatial index from the "geo" table (except SQLite). |
| `$table->dropVectorIndex('documents_embedding_vectorindex');` | Drop a vector index from the "documents" table. |

If you pass an array of columns into a method that drops indexes, the conventional index name will be generated based on the table name, columns, and index type:

```
1Schema::table('geo', function (Blueprint $table) {
2    $table->dropIndex(['state']); // Drops index 'geo_state_index'
3});

Schema::table('geo', function (Blueprint $table) {
    $table->dropIndex(['state']); // Drops index 'geo_state_index'
});
```

### Foreign Key Constraints

Laravel also provides support for creating foreign key constraints, which are used to force referential integrity at the database level. For example, let's define a `user_id` column on the `posts` table that references the `id` column on a `users` table:

```
1use Illuminate\Database\Schema\Blueprint;
2use Illuminate\Support\Facades\Schema;
3 
4Schema::table('posts', function (Blueprint $table) {
5    $table->unsignedBigInteger('user_id');
6 
7    $table->foreign('user_id')->references('id')->on('users');
8});

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('posts', function (Blueprint $table) {
    $table->unsignedBigInteger('user_id');

    $table->foreign('user_id')->references('id')->on('users');
});
```

Since this syntax is rather verbose, Laravel provides additional, terser methods that use conventions to provide a better developer experience. When using the `foreignId` method to create your column, the example above can be rewritten like so:

```
1Schema::table('posts', function (Blueprint $table) {
2    $table->foreignId('user_id')->constrained();
3});

Schema::table('posts', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained();
});
```

The `foreignId` method creates an `UNSIGNED BIGINT` equivalent column, while the `constrained` method will use conventions to determine the table and column being referenced. If your table name does not match Laravel's conventions, you may manually provide it to the `constrained` method. In addition, the name that should be assigned to the generated index may be specified as well:

```
1Schema::table('posts', function (Blueprint $table) {
2    $table->foreignId('user_id')->constrained(
3        table: 'users', indexName: 'posts_user_id'
4    );
5});

Schema::table('posts', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained(
        table: 'users', indexName: 'posts_user_id'
    );
});
```

You may also specify the desired action for the "on delete" and "on update" properties of the constraint:

```
1$table->foreignId('user_id')
2    ->constrained()
3    ->onUpdate('cascade')
4    ->onDelete('cascade');

$table->foreignId('user_id')
    ->constrained()
    ->onUpdate('cascade')
    ->onDelete('cascade');
```

An alternative, expressive syntax is also provided for these actions:

| Method | Description |
| --- | --- |
| `$table->cascadeOnUpdate();` | Updates should cascade. |
| `$table->restrictOnUpdate();` | Updates should be restricted. |
| `$table->nullOnUpdate();` | Updates should set the foreign key value to null. |
| `$table->noActionOnUpdate();` | No action on updates. |
| `$table->cascadeOnDelete();` | Deletes should cascade. |
| `$table->restrictOnDelete();` | Deletes should be restricted. |
| `$table->nullOnDelete();` | Deletes should set the foreign key value to null. |
| `$table->noActionOnDelete();` | Prevents deletes if child records exist. |

Any additional column modifiers must be called before the `constrained` method:

```
1$table->foreignId('user_id')
2    ->nullable()
3    ->constrained();

$table->foreignId('user_id')
    ->nullable()
    ->constrained();
```

#### Dropping Foreign Keys

To drop a foreign key, you may use the `dropForeign` method, passing the name of the foreign key constraint to be deleted as an argument. Foreign key constraints use the same naming convention as indexes. In other words, the foreign key constraint name is based on the name of the table and the columns in the constraint, followed by a "_foreign" suffix:

```
1$table->dropForeign('posts_user_id_foreign');

$table->dropForeign('posts_user_id_foreign');
```

Alternatively, you may pass an array containing the column name that holds the foreign key to the `dropForeign` method. The array will be converted to a foreign key constraint name using Laravel's constraint naming conventions:

```
1$table->dropForeign(['user_id']);

$table->dropForeign(['user_id']);
```

#### Toggling Foreign Key Constraints

You may enable or disable foreign key constraints within your migrations by using the following methods:

```
1Schema::enableForeignKeyConstraints();
2 
3Schema::disableForeignKeyConstraints();
4 
5Schema::withoutForeignKeyConstraints(function () {
6    // Constraints disabled within this closure...
7});

Schema::enableForeignKeyConstraints();

Schema::disableForeignKeyConstraints();

Schema::withoutForeignKeyConstraints(function () {
    // Constraints disabled within this closure...
});
```

 SQLite disables foreign key constraints by default. When using SQLite, make sure to [enable foreign key support](https://laravel.com/framework/docs/database#configuration) in your database configuration before attempting to create them in your migrations.

## Events

For convenience, each migration operation will dispatch an [event](https://laravel.com/framework/docs/events). With the exception of `SchemaDumped` and `SchemaLoaded`, all of the following events implement the `Illuminate\Contracts\Database\Events\MigrationEvent` interface:

| Class | Description |
| --- | --- |
| `Illuminate\Database\Events\DatabaseRefreshed` | The `migrate:fresh` or `migrate:refresh` command has finished. |
| `Illuminate\Database\Events\MigrationsStarted` | A batch of migrations is about to be executed. |
| `Illuminate\Database\Events\MigrationsEnded` | A batch of migrations has finished. |
| `Illuminate\Database\Events\MigrationStarted` | A single migration is about to be executed. |
| `Illuminate\Database\Events\MigrationEnded` | A single migration has finished. |
| `Illuminate\Database\Events\NoPendingMigrations` | A migration command found no pending migrations. |
| `Illuminate\Database\Events\SchemaDumped` | A database schema dump has finished. |
| `Illuminate\Database\Events\SchemaLoaded` | An existing database schema dump has loaded. |
