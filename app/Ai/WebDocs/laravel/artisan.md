---
source: laravel
page: artisan
url: https://laravel.com/framework/docs/13.x/artisan
fetched_at: 2026-10-20T16:27:50+00:00
---

# Artisan Console

- Introduction
   - Tinker (REPL)
- Writing Commands
   - Generating Commands
   - Command Structure
   - Closure Commands
   - Isolatable Commands
- Defining Input Expectations
   - Arguments
   - Options
   - Input Arrays
   - Input Descriptions
   - Prompting for Missing Input
- Command I/O
   - Retrieving Input
   - Prompting for Input
   - Writing Output
- Registering Commands
- Programmatically Executing Commands
   - Calling Commands From Other Commands
- Signal Handling
- The Dev Command
   - Customizing Dev Processes
   - Filtering Dev Processes
- Stub Customization
- Events

## Introduction

Artisan is the command line interface included with Laravel. Artisan exists at the root of your application as the `artisan` script and provides a number of helpful commands you can use while building your application. To view a list of all available Artisan commands, you may use the `list` command:

```
1php artisan list

php artisan list
```

Every command also includes a "help" screen which displays and describes the command's available arguments and options. To view a help screen, precede the name of the command with `help`:

```
1php artisan help migrate

php artisan help migrate
```

#### Laravel Sail

If you are using [Laravel Sail](https://laravel.com/framework/docs/sail) as your local development environment, remember to use the `sail` command line to invoke Artisan commands. Sail will execute your Artisan commands within your application's Docker containers:

```
1./vendor/bin/sail artisan list

./vendor/bin/sail artisan list
```

### Tinker (REPL)

[Laravel Tinker](https://github.com/laravel/tinker) is a powerful REPL for the Laravel framework, powered by the [PsySH](https://github.com/bobthecow/psysh) package.

#### Installation

All Laravel applications include Tinker by default. However, you may install Tinker using Composer if you have previously removed it from your application:

```
1composer require laravel/tinker

composer require laravel/tinker
```

 Looking for hot reloading, multiline code editing, and autocompletion when interacting with your Laravel application? Check out [Tinkerwell](https://tinkerwell.app)!

#### Usage

Tinker allows you to interact with your entire Laravel application on the command line, including your Eloquent models, jobs, events, and more. To enter the Tinker environment, run the `tinker` Artisan command:

```
1php artisan tinker

php artisan tinker
```

You can publish Tinker's configuration file using the `vendor:publish` command:

```
1php artisan vendor:publish --provider="Laravel\Tinker\TinkerServiceProvider"

php artisan vendor:publish --provider="Laravel\Tinker\TinkerServiceProvider"
```

 The `dispatch` helper function and `dispatch` method on the `Dispatchable` class depend on garbage collection to place the job on the queue. Therefore, when using Tinker, you should use `Bus::dispatch` or `Queue::push` to dispatch jobs.

#### Command Allow List

Tinker utilizes an "allow" list to determine which Artisan commands are allowed to be run within its shell. By default, you may run the `clear-compiled`, `down`, `env`, `inspire`, `migrate`, `migrate:install`, `up`, and `optimize` commands. If you would like to allow more commands you may add them to the `commands` array in your `tinker.php` configuration file:

```
1'commands' => [
2    // App\Console\Commands\ExampleCommand::class,
3],

'commands' => [
    // App\Console\Commands\ExampleCommand::class,
],
```

#### Classes That Should Not Be Aliased

Typically, Tinker automatically aliases classes as you interact with them in Tinker. However, you may wish to never alias some classes. You may accomplish this by listing the classes in the `dont_alias` array of your `tinker.php` configuration file:

```
1'dont_alias' => [
2    App\Models\User::class,
3],

'dont_alias' => [
    App\Models\User::class,
],
```

## Writing Commands

In addition to the commands provided with Artisan, you may build your own custom commands. Commands are typically stored in the `app/Console/Commands` directory; however, you are free to choose your own storage location as long as you instruct Laravel to scan other directories for Artisan commands.

### Generating Commands

To create a new command, you may use the `make:command` Artisan command. This command will create a new command class in the `app/Console/Commands` directory. Don't worry if this directory does not exist in your application - it will be created the first time you run the `make:command` Artisan command:

```
1php artisan make:command SendEmails

php artisan make:command SendEmails
```

### Command Structure

After generating your command, you should define the command's signature and description using the `Signature` and `Description` attributes. The `Signature` attribute also allows you to define your command's input expectations. The `handle` method will be called when your command is executed. You may place your command logic in this method.

Let's take a look at an example command. Note that we are able to request any dependencies we need via the command's `handle` method. The Laravel [service container](https://laravel.com/framework/docs/container) will automatically inject all dependencies that are type-hinted in this method's signature:

```
 1<?php
 2 
 3namespace App\Console\Commands;
 4 
 5use App\Models\User;
 6use App\Support\DripEmailer;
 7use Illuminate\Console\Attributes\Description;
 8use Illuminate\Console\Attributes\Signature;
 9use Illuminate\Console\Command;
10 
11#[Signature('mail:send {user}')]
12#[Description('Send a marketing email to a user')]
13class SendEmails extends Command
14{
15    /**
16     * Execute the console command.
17     */
18    public function handle(DripEmailer $drip): void
19    {
20        $drip->send(User::find($this->argument('user')));
21    }
22}

<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\DripEmailer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mail:send {user}')]
#[Description('Send a marketing email to a user')]
class SendEmails extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DripEmailer $drip): void
    {
        $drip->send(User::find($this->argument('user')));
    }
}
```

 For greater code reuse, it is good practice to keep your console commands light and let them defer to application services to accomplish their tasks. In the example above, note that we inject a service class to do the "heavy lifting" of sending the emails.

#### Exit Codes

If nothing is returned from the `handle` method and the command executes successfully, the command will exit with a `0` exit code, indicating success. However, the `handle` method may optionally return an integer to manually specify the command's exit code:

```
1$this->error('Something went wrong.');
2 
3return 1;

$this->error('Something went wrong.');

return 1;
```

If you would like to "fail" the command from any method within the command, you may utilize the `fail` method. The `fail` method will immediately terminate execution of the command and return an exit code of `1`:

```
1$this->fail('Something went wrong.');

$this->fail('Something went wrong.');
```

### Closure Commands

Closure-based commands provide an alternative to defining console commands as classes. In the same way that route closures are an alternative to controllers, think of command closures as an alternative to command classes.

Even though the `routes/console.php` file does not define HTTP routes, it defines console-based entry points (routes) into your application. Within this file, you may define all of your closure-based console commands using the `Artisan::command` method. The `command` method accepts two arguments: the command signature and a closure which receives the command's arguments and options:

```
1Artisan::command('mail:send {user}', function (string $user) {
2    $this->info("Sending email to: {$user}!");
3});

Artisan::command('mail:send {user}', function (string $user) {
    $this->info("Sending email to: {$user}!");
});
```

The closure is bound to the underlying command instance, so you have full access to all of the helper methods you would typically be able to access on a full command class.

#### Type-Hinting Dependencies

In addition to receiving your command's arguments and options, command closures may also type-hint additional dependencies that you would like resolved out of the [service container](https://laravel.com/framework/docs/container):

```
1use App\Models\User;
2use App\Support\DripEmailer;
3use Illuminate\Support\Facades\Artisan;
4 
5Artisan::command('mail:send {user}', function (DripEmailer $drip, string $user) {
6    $drip->send(User::find($user));
7});

use App\Models\User;
use App\Support\DripEmailer;
use Illuminate\Support\Facades\Artisan;

Artisan::command('mail:send {user}', function (DripEmailer $drip, string $user) {
    $drip->send(User::find($user));
});
```

#### Closure Command Descriptions

When defining a closure-based command, you may use the `purpose` method to add a description to the command. This description will be displayed when you run the `php artisan list` or `php artisan help` commands:

```
1Artisan::command('mail:send {user}', function (string $user) {
2    // ...
3})->purpose('Send a marketing email to a user');

Artisan::command('mail:send {user}', function (string $user) {
    // ...
})->purpose('Send a marketing email to a user');
```

### Isolatable Commands

 To utilize this feature, your application must be using the `memcached`, `redis`, `dynamodb`, `database`, `file`, or `array` cache driver as your application's default cache driver. In addition, all servers must be communicating with the same central cache server.

Sometimes you may wish to ensure that only one instance of a command can run at a time. To accomplish this, you may implement the `Illuminate\Contracts\Console\Isolatable` interface on your command class:

```
 1<?php
 2 
 3namespace App\Console\Commands;
 4 
 5use Illuminate\Console\Command;
 6use Illuminate\Contracts\Console\Isolatable;
 7 
 8class SendEmails extends Command implements Isolatable
 9{
10    // ...
11}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;

class SendEmails extends Command implements Isolatable
{
    // ...
}
```

When you mark a command as `Isolatable`, Laravel automatically makes the `--isolated` option available for the command without needing to explicitly define it in the command's options. When the command is invoked with that option, Laravel will ensure that no other instances of that command are already running. Laravel accomplishes this by attempting to acquire an atomic lock using your application's default cache driver. If other instances of the command are running, the command will not execute; however, the command will still exit with a successful exit status code:

```
1php artisan mail:send 1 --isolated

php artisan mail:send 1 --isolated
```

If you would like to specify the exit status code that the command should return if it is not able to execute, you may provide the desired status code via the `isolated` option:

```
1php artisan mail:send 1 --isolated=12

php artisan mail:send 1 --isolated=12
```

#### Lock ID

By default, Laravel will use the command's name to generate the string key that is used to acquire the atomic lock in your application's cache. However, you may customize this key by defining an `isolatableId` method on your Artisan command class, allowing you to integrate the command's arguments or options into the key:

```
1/**
2 * Get the isolatable ID for the command.
3 */
4public function isolatableId(): string
5{
6    return $this->argument('user');
7}

/**
 * Get the isolatable ID for the command.
 */
public function isolatableId(): string
{
    return $this->argument('user');
}
```

#### Lock Expiration Time

By default, isolation locks expire after the command is finished. Or, if the command is interrupted and unable to finish, the lock will expire after one hour. However, you may adjust the lock expiration time by defining an `isolationLockExpiresAt` method on your command:

```
 1use DateTimeInterface;
 2use DateInterval;
 3 
 4/**
 5 * Determine when an isolation lock expires for the command.
 6 */
 7public function isolationLockExpiresAt(): DateTimeInterface|DateInterval
 8{
 9    return now()->plus(minutes: 5);
10}

use DateTimeInterface;
use DateInterval;

/**
 * Determine when an isolation lock expires for the command.
 */
public function isolationLockExpiresAt(): DateTimeInterface|DateInterval
{
    return now()->plus(minutes: 5);
}
```

## Defining Input Expectations

When writing console commands, it is common to gather input from the user through arguments or options. Laravel makes it very convenient to define the input you expect from the user using the `signature` property on your commands. The `signature` property allows you to define the name, arguments, and options for the command in a single, expressive, route-like syntax.

### Arguments

All user supplied arguments and options are wrapped in curly braces. In the following example, the command defines one required argument: `user`:

```
1/**
2 * The name and signature of the console command.
3 *
4 * @var string
5 */
6protected $signature = 'mail:send {user}';

/**
 * The name and signature of the console command.
 *
 * @var string
 */
protected $signature = 'mail:send {user}';
```

You may also make arguments optional or define default values for arguments:

```
1// Optional argument...
2'mail:send {user?}'
3 
4// Optional argument with default value...
5'mail:send {user=foo}'

// Optional argument...
'mail:send {user?}'

// Optional argument with default value...
'mail:send {user=foo}'
```

### Options

Options, like arguments, are another form of user input. Options are prefixed by two hyphens (`--`) when they are provided via the command line. There are two types of options: those that receive a value and those that don't. Options that don't receive a value serve as a boolean "switch". Let's take a look at an example of this type of option:

```
1/**
2 * The name and signature of the console command.
3 *
4 * @var string
5 */
6protected $signature = 'mail:send {user} {--queue}';

/**
 * The name and signature of the console command.
 *
 * @var string
 */
protected $signature = 'mail:send {user} {--queue}';
```

In this example, the `--queue` switch may be specified when calling the Artisan command. If the `--queue` switch is passed, the value of the option will be `true`. Otherwise, the value will be `false`:

```
1php artisan mail:send 1 --queue

php artisan mail:send 1 --queue
```

#### Options With Values

Next, let's take a look at an option that expects a value. If the user must specify a value for an option, you should suffix the option name with a `=` sign:

```
1/**
2 * The name and signature of the console command.
3 *
4 * @var string
5 */
6protected $signature = 'mail:send {user} {--queue=}';

/**
 * The name and signature of the console command.
 *
 * @var string
 */
protected $signature = 'mail:send {user} {--queue=}';
```

In this example, the user may pass a value for the option like so. If the option is not specified when invoking the command, its value will be `null`:

```
1php artisan mail:send 1 --queue=default

php artisan mail:send 1 --queue=default
```

You may assign default values to options by specifying the default value after the option name. If no option value is passed by the user, the default value will be used:

```
1'mail:send {user} {--queue=default}'

'mail:send {user} {--queue=default}'
```

#### Option Shortcuts

To assign a shortcut when defining an option, you may specify it before the option name and use the `|` character as a delimiter to separate the shortcut from the full option name:

```
1'mail:send {user} {--Q|queue=}'

'mail:send {user} {--Q|queue=}'
```

When invoking the command on your terminal, option shortcuts should be prefixed with a single hyphen and no `=` character should be included when specifying a value for the option:

```
1php artisan mail:send 1 -Qdefault

php artisan mail:send 1 -Qdefault
```

### Input Arrays

If you would like to define arguments or options to expect multiple input values, you may use the `*` character. First, let's take a look at an example that specifies such an argument:

```
1'mail:send {user*}'

'mail:send {user*}'
```

When running this command, the `user` arguments may be passed in order to the command line. For example, the following command will set the value of `user` to an array with `1` and `2` as its values:

```
1php artisan mail:send 1 2

php artisan mail:send 1 2
```

This `*` character can be combined with an optional argument definition to allow zero or more instances of an argument:

```
1'mail:send {user?*}'

'mail:send {user?*}'
```

#### Option Arrays

When defining an option that expects multiple input values, each option value passed to the command should be prefixed with the option name:

```
1'mail:send {--id=*}'

'mail:send {--id=*}'
```

Such a command may be invoked by passing multiple `--id` arguments:

```
1php artisan mail:send --id=1 --id=2

php artisan mail:send --id=1 --id=2
```

### Input Descriptions

You may assign descriptions to input arguments and options by separating the argument name from the description using a colon. If you need a little extra room to define your command, feel free to spread the definition across multiple lines:

```
1/**
2 * The name and signature of the console command.
3 *
4 * @var string
5 */
6protected $signature = 'mail:send
7                        {user : The ID of the user}
8                        {--queue : Whether the job should be queued}';

/**
 * The name and signature of the console command.
 *
 * @var string
 */
protected $signature = 'mail:send
                        {user : The ID of the user}
                        {--queue : Whether the job should be queued}';
```

### Prompting for Missing Input

If your command contains required arguments, the user will receive an error message when they are not provided. Alternatively, you may configure your command to automatically prompt the user when required arguments are missing by implementing the `PromptsForMissingInput` interface:

```
 1<?php
 2 
 3namespace App\Console\Commands;
 4 
 5use Illuminate\Console\Command;
 6use Illuminate\Contracts\Console\PromptsForMissingInput;
 7 
 8class SendEmails extends Command implements PromptsForMissingInput
 9{
10    /**
11     * The name and signature of the console command.
12     *
13     * @var string
14     */
15    protected $signature = 'mail:send {user}';
16 
17    // ...
18}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;

class SendEmails extends Command implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:send {user}';

    // ...
}
```

If Laravel needs to gather a required argument from the user, it will automatically ask the user for the argument by intelligently phrasing the question using either the argument name or description. If you wish to customize the question used to gather the required argument, you may implement the `promptForMissingArgumentsUsing` method, returning an array of questions keyed by the argument names:

```
 1/**
 2 * Prompt for missing input arguments using the returned questions.
 3 *
 4 * @return array<string, string>
 5 */
 6protected function promptForMissingArgumentsUsing(): array
 7{
 8    return [
 9        'user' => 'Which user ID should receive the mail?',
10    ];
11}

/**
 * Prompt for missing input arguments using the returned questions.
 *
 * @return array<string, string>
 */
protected function promptForMissingArgumentsUsing(): array
{
    return [
        'user' => 'Which user ID should receive the mail?',
    ];
}
```

You may also provide placeholder text by using a tuple containing the question and placeholder:

```
1return [
2    'user' => ['Which user ID should receive the mail?', 'E.g. 123'],
3];

return [
    'user' => ['Which user ID should receive the mail?', 'E.g. 123'],
];
```

If you would like complete control over the prompt, you may provide a closure that should prompt the user and return their answer:

```
 1use App\Models\User;
 2use function Laravel\Prompts\search;
 3 
 4// ...
 5 
 6return [
 7    'user' => fn () => search(
 8        label: 'Search for a user:',
 9        placeholder: 'E.g. Taylor Otwell',
10        options: fn ($value) => strlen($value) > 0
11            ? User::whereLike('name', "%{$value}%")->pluck('name', 'id')->all()
12            : []
13    ),
14];

use App\Models\User;
use function Laravel\Prompts\search;

// ...

return [
    'user' => fn () => search(
        label: 'Search for a user:',
        placeholder: 'E.g. Taylor Otwell',
        options: fn ($value) => strlen($value) > 0
            ? User::whereLike('name', "%{$value}%")->pluck('name', 'id')->all()
            : []
    ),
];
```

 The comprehensive [Laravel Prompts](https://laravel.com/framework/docs/prompts) documentation includes additional information on the available prompts and their usage.

If you wish to prompt the user to select or enter options, you may include prompts in your command's `handle` method. However, if you only wish to prompt the user when they have also been automatically prompted for missing arguments, then you may implement the `afterPromptingForMissingArguments` method:

```
 1use Symfony\Component\Console\Input\InputInterface;
 2use Symfony\Component\Console\Output\OutputInterface;
 3use function Laravel\Prompts\confirm;
 4 
 5// ...
 6 
 7/**
 8 * Perform actions after the user was prompted for missing arguments.
 9 */
10protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
11{
12    $input->setOption('queue', confirm(
13        label: 'Would you like to queue the mail?',
14        default: $this->option('queue')
15    ));
16}

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\confirm;

// ...

/**
 * Perform actions after the user was prompted for missing arguments.
 */
protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
{
    $input->setOption('queue', confirm(
        label: 'Would you like to queue the mail?',
        default: $this->option('queue')
    ));
}
```

## Command I/O

### Retrieving Input

While your command is executing, you will likely need to access the values for the arguments and options accepted by your command. To do so, you may use the `argument` and `option` methods. If an argument or option does not exist, `null` will be returned:

```
1/**
2 * Execute the console command.
3 */
4public function handle(): void
5{
6    $userId = $this->argument('user');
7}

/**
 * Execute the console command.
 */
public function handle(): void
{
    $userId = $this->argument('user');
}
```

If you need to retrieve all of the arguments as an `array`, call the `arguments` method:

```
1$arguments = $this->arguments();

$arguments = $this->arguments();
```

Options may be retrieved just as easily as arguments using the `option` method. To retrieve all of the options as an array, call the `options` method:

```
1// Retrieve a specific option...
2$queueName = $this->option('queue');
3 
4// Retrieve all options as an array...
5$options = $this->options();

// Retrieve a specific option...
$queueName = $this->option('queue');

// Retrieve all options as an array...
$options = $this->options();
```

You may use the `input` method to retrieve a command's arguments and options as an `Illuminate\Console\CommandInput` instance, which provides the same typed accessors that are available on HTTP requests and other data containers:

```
 1use App\Enums\ReportType;
 2 
 3/**
 4 * Execute the console command.
 5 */
 6public function handle(): void
 7{
 8    $input = $this->input()->date('from');
 9 
10    // ...
11}

use App\Enums\ReportType;

/**
 * Execute the console command.
 */
public function handle(): void
{
    $input = $this->input()->date('from');

    // ...
}
```

The `input` method may also be used to retrieve a single input value from either the arguments or options:

```
1$queue = $this->input('queue', 'default');

$queue = $this->input('queue', 'default');
```

### Prompting for Input

 [Laravel Prompts](https://laravel.com/framework/docs/prompts) is a PHP package for adding beautiful and user-friendly forms to your command-line applications, with browser-like features including placeholder text and validation.

In addition to displaying output, you may also ask the user to provide input during the execution of your command. The `ask` method will prompt the user with the given question, accept their input, and then return the user's input back to your command:

```
1/**
2 * Execute the console command.
3 */
4public function handle(): void
5{
6    $name = $this->ask('What is your name?');
7 
8    // ...
9}

/**
 * Execute the console command.
 */
public function handle(): void
{
    $name = $this->ask('What is your name?');

    // ...
}
```

The `ask` method also accepts an optional second argument which specifies the default value that should be returned if no user input is provided:

```
1$name = $this->ask('What is your name?', 'Taylor');

$name = $this->ask('What is your name?', 'Taylor');
```

The `secret` method is similar to `ask`, but the user's input will not be visible to them as they type in the console. This method is useful when asking for sensitive information such as passwords:

```
1$password = $this->secret('What is the password?');

$password = $this->secret('What is the password?');
```

#### Asking for Confirmation

If you need to ask the user for a simple "yes or no" confirmation, you may use the `confirm` method. By default, this method will return `false`. However, if the user enters `y` or `yes` in response to the prompt, the method will return `true`.

```
1if ($this->confirm('Do you wish to continue?')) {
2    // ...
3}

if ($this->confirm('Do you wish to continue?')) {
    // ...
}
```

If necessary, you may specify that the confirmation prompt should return `true` by default by passing `true` as the second argument to the `confirm` method:

```
1if ($this->confirm('Do you wish to continue?', true)) {
2    // ...
3}

if ($this->confirm('Do you wish to continue?', true)) {
    // ...
}
```

#### Auto-Completion

The `anticipate` method can be used to provide auto-completion for possible choices. The user can still provide any answer, regardless of the auto-completion hints:

```
1$name = $this->anticipate('What is your name?', ['Taylor', 'Dayle']);

$name = $this->anticipate('What is your name?', ['Taylor', 'Dayle']);
```

Alternatively, you may pass a closure as the second argument to the `anticipate` method. The closure will be called each time the user types an input character. The closure should accept a string parameter containing the user's input so far, and return an array of options for auto-completion:

```
1use App\Models\Address;
2 
3$name = $this->anticipate('What is your address?', function (string $input) {
4    return Address::whereLike('name', "{$input}%")
5        ->limit(5)
6        ->pluck('name')
7        ->all();
8});

use App\Models\Address;

$name = $this->anticipate('What is your address?', function (string $input) {
    return Address::whereLike('name', "{$input}%")
        ->limit(5)
        ->pluck('name')
        ->all();
});
```

#### Multiple Choice Questions

If you need to give the user a predefined set of choices when asking a question, you may use the `choice` method. You may set the array index of the default value to be returned if no option is chosen by passing the index as the third argument to the method:

```
1$name = $this->choice(
2    'What is your name?',
3    ['Taylor', 'Dayle'],
4    $defaultIndex
5);

$name = $this->choice(
    'What is your name?',
    ['Taylor', 'Dayle'],
    $defaultIndex
);
```

In addition, the `choice` method accepts optional fourth and fifth arguments for determining the maximum number of attempts to select a valid response and whether multiple selections are permitted:

```
1$name = $this->choice(
2    'What is your name?',
3    ['Taylor', 'Dayle'],
4    $defaultIndex,
5    $maxAttempts = null,
6    $allowMultipleSelections = false
7);

$name = $this->choice(
    'What is your name?',
    ['Taylor', 'Dayle'],
    $defaultIndex,
    $maxAttempts = null,
    $allowMultipleSelections = false
);
```

### Writing Output

To send output to the console, you may use the `line`, `newLine`, `info`, `comment`, `question`, `warn`, `alert`, and `error` methods. Each of these methods will use appropriate ANSI colors for their purpose. For example, let's display some general information to the user. Typically, the `info` method will display in the console as green colored text:

```
1/**
2 * Execute the console command.
3 */
4public function handle(): void
5{
6    // ...
7 
8    $this->info('The command was successful!');
9}

/**
 * Execute the console command.
 */
public function handle(): void
{
    // ...

    $this->info('The command was successful!');
}
```

To display an error message, use the `error` method. Error message text is typically displayed in red:

```
1$this->error('Something went wrong!');

$this->error('Something went wrong!');
```

You may use the `line` method to display plain, uncolored text:

```
1$this->line('Display this on the screen');

$this->line('Display this on the screen');
```

You may use the `newLine` method to display a blank line:

```
1// Write a single blank line...
2$this->newLine();
3 
4// Write three blank lines...
5$this->newLine(3);

// Write a single blank line...
$this->newLine();

// Write three blank lines...
$this->newLine(3);
```

#### Tables

The `table` method makes it easy to correctly format multiple rows / columns of data. All you need to do is provide the column names and the data for the table and Laravel will automatically calculate the appropriate width and height of the table for you:

```
1use App\Models\User;
2 
3$this->table(
4    ['Name', 'Email'],
5    User::all(['name', 'email'])->toArray()
6);

use App\Models\User;

$this->table(
    ['Name', 'Email'],
    User::all(['name', 'email'])->toArray()
);
```

#### Progress Bars

For long running tasks, it can be helpful to show a progress bar that informs users how complete the task is. Using the `withProgressBar` method, Laravel will display a progress bar and advance its progress for each iteration over a given iterable value:

```
1use App\Models\User;
2 
3$users = $this->withProgressBar(User::all(), function (User $user) {
4    $this->performTask($user);
5});

use App\Models\User;

$users = $this->withProgressBar(User::all(), function (User $user) {
    $this->performTask($user);
});
```

Sometimes, you may need more manual control over how a progress bar is advanced. First, define the total number of steps the process will iterate through. Then, advance the progress bar after processing each item:

```
 1$users = App\Models\User::all();
 2 
 3$bar = $this->output->createProgressBar(count($users));
 4 
 5$bar->start();
 6 
 7foreach ($users as $user) {
 8    $this->performTask($user);
 9 
10    $bar->advance();
11}
12 
13$bar->finish();

$users = App\Models\User::all();

$bar = $this->output->createProgressBar(count($users));

$bar->start();

foreach ($users as $user) {
    $this->performTask($user);

    $bar->advance();
}

$bar->finish();
```

 For more advanced options, check out the [Symfony Progress Bar component documentation](https://symfony.com/doc/current/components/console/helpers/progressbar.html).

## Registering Commands

By default, Laravel automatically registers all commands within the `app/Console/Commands` directory. However, you can instruct Laravel to scan other directories for Artisan commands using the `withCommands` method in your application's `bootstrap/app.php` file:

```
1->withCommands([
2    __DIR__.'/../app/Domain/Orders/Commands',
3])

->withCommands([
    __DIR__.'/../app/Domain/Orders/Commands',
])
```

If necessary, you may also manually register commands by providing the command's class name to the `withCommands` method:

```
1use App\Domain\Orders\Commands\SendEmails;
2 
3->withCommands([
4    SendEmails::class,
5])

use App\Domain\Orders\Commands\SendEmails;

->withCommands([
    SendEmails::class,
])
```

When Artisan boots, all the commands in your application will be resolved by the [service container](https://laravel.com/framework/docs/container) and registered with Artisan.

## Programmatically Executing Commands

Sometimes you may wish to execute an Artisan command outside of the CLI. For example, you may wish to execute an Artisan command from a route or controller. You may use the `call` method on the `Artisan` facade to accomplish this. The `call` method accepts either the command's signature name or class name as its first argument, and an array of command parameters as the second argument. The exit code will be returned:

```
 1use Illuminate\Support\Facades\Artisan;
 2use Illuminate\Support\Facades\Route;
 3 
 4Route::post('/user/{user}/mail', function (string $user) {
 5    $exitCode = Artisan::call('mail:send', [
 6        'user' => $user, '--queue' => 'default'
 7    ]);
 8 
 9    // ...
10});

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::post('/user/{user}/mail', function (string $user) {
    $exitCode = Artisan::call('mail:send', [
        'user' => $user, '--queue' => 'default'
    ]);

    // ...
});
```

Alternatively, you may pass the entire Artisan command to the `call` method as a string:

```
1Artisan::call('mail:send 1 --queue=default');

Artisan::call('mail:send 1 --queue=default');
```

#### Passing Array Values

If your command defines an option that accepts an array, you may pass an array of values to that option:

```
1use Illuminate\Support\Facades\Artisan;
2use Illuminate\Support\Facades\Route;
3 
4Route::post('/mail', function () {
5    $exitCode = Artisan::call('mail:send', [
6        '--id' => [5, 13]
7    ]);
8});

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::post('/mail', function () {
    $exitCode = Artisan::call('mail:send', [
        '--id' => [5, 13]
    ]);
});
```

#### Passing Boolean Values

If you need to specify the value of an option that does not accept string values, such as the `--force` flag on the `migrate:refresh` command, you should pass `true` or `false` as the value of the option:

```
1$exitCode = Artisan::call('migrate:refresh', [
2    '--force' => true,
3]);

$exitCode = Artisan::call('migrate:refresh', [
    '--force' => true,
]);
```

#### Queueing Artisan Commands

Using the `queue` method on the `Artisan` facade, you may even queue Artisan commands so they are processed in the background by your [queue workers](https://laravel.com/framework/docs/queues). Before using this method, make sure you have configured your queue and are running a queue listener:

```
 1use Illuminate\Support\Facades\Artisan;
 2use Illuminate\Support\Facades\Route;
 3 
 4Route::post('/user/{user}/mail', function (string $user) {
 5    Artisan::queue('mail:send', [
 6        'user' => $user, '--queue' => 'default'
 7    ]);
 8 
 9    // ...
10});

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::post('/user/{user}/mail', function (string $user) {
    Artisan::queue('mail:send', [
        'user' => $user, '--queue' => 'default'
    ]);

    // ...
});
```

Using the `onConnection` and `onQueue` methods, you may specify the connection or queue the Artisan command should be dispatched to:

```
1Artisan::queue('mail:send', [
2    'user' => 1, '--queue' => 'default'
3])->onConnection('redis')->onQueue('commands');

Artisan::queue('mail:send', [
    'user' => 1, '--queue' => 'default'
])->onConnection('redis')->onQueue('commands');
```

### Calling Commands From Other Commands

Sometimes you may wish to call other commands from an existing Artisan command. You may do so using the `call` method. This `call` method accepts the command name and an array of command arguments / options:

```
 1/**
 2 * Execute the console command.
 3 */
 4public function handle(): void
 5{
 6    $this->call('mail:send', [
 7        'user' => 1, '--queue' => 'default'
 8    ]);
 9 
10    // ...
11}

/**
 * Execute the console command.
 */
public function handle(): void
{
    $this->call('mail:send', [
        'user' => 1, '--queue' => 'default'
    ]);

    // ...
}
```

If you would like to call another console command and suppress all of its output, you may use the `callSilently` method. The `callSilently` method has the same signature as the `call` method:

```
1$this->callSilently('mail:send', [
2    'user' => 1, '--queue' => 'default'
3]);

$this->callSilently('mail:send', [
    'user' => 1, '--queue' => 'default'
]);
```

## Signal Handling

As you may know, operating systems allow signals to be sent to running processes. For example, the `SIGTERM` signal is how operating systems ask a program to terminate gracefully. If you wish to listen for signals in your Artisan console commands and execute code when they occur, you may use the `trap` method:

```
 1/**
 2 * Execute the console command.
 3 */
 4public function handle(): void
 5{
 6    $this->trap(SIGTERM, fn () => $this->shouldKeepRunning = false);
 7 
 8    while ($this->shouldKeepRunning) {
 9        // ...
10    }
11}

/**
 * Execute the console command.
 */
public function handle(): void
{
    $this->trap(SIGTERM, fn () => $this->shouldKeepRunning = false);

    while ($this->shouldKeepRunning) {
        // ...
    }
}
```

To listen for multiple signals at once, you may provide an array of signals to the `trap` method:

```
1$this->trap([SIGTERM, SIGQUIT], function (int $signal) {
2    $this->shouldKeepRunning = false;
3 
4    dump($signal); // SIGTERM / SIGQUIT
5});

$this->trap([SIGTERM, SIGQUIT], function (int $signal) {
    $this->shouldKeepRunning = false;

    dump($signal); // SIGTERM / SIGQUIT
});
```

## The Dev Command

The `dev` Artisan command starts all of the processes needed for local development in a single terminal window. By default, it concurrently runs the PHP development server, a queue worker, log tailing via [Pail](https://laravel.com/framework/docs/logging#tailing-log-messages-using-pail), and Vite asset compilation:

```
1php artisan dev

php artisan dev
```

Under the hood, the `dev` command uses the `@laravel/multiplex` npm package to manage the processes, giving each process its own tab with searchable, scrollable output. Each process is labeled and color-coded so you can easily distinguish between them. If a process crashes, it will be restarted automatically, and when you quit, all of the output is written back to your terminal so nothing is lost.

 The `dev` command requires Node 22.13 or later. On Windows, it falls back to the `concurrently` npm package and the tabbed interface is not available.

The default processes are:

| Name | Command |
| --- | --- |
| `server` | `php artisan serve` |
| `queue` | `php artisan queue:listen --tries=1 --timeout=0` |
| `logs` | `php artisan pail --timeout=0` |
| `vite` | `npm run dev` |

 The `vite` process automatically detects your Node package manager (npm, pnpm, Yarn, or Bun) and uses the appropriate run command.

### Customizing Dev Processes

You may customize the processes that the `dev` command runs by using the `DevCommands` class, typically within the `boot` method of your application's `AppServiceProvider`. The `register` method accepts a command string and an optional name:

```
1use Illuminate\Foundation\DevCommands;
2 
3/**
4 * Bootstrap any application services.
5 */
6public function boot(): void
7{
8    DevCommands::register('some-command --flag', 'my-process');
9}

use Illuminate\Foundation\DevCommands;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    DevCommands::register('some-command --flag', 'my-process');
}
```

When registering an Artisan command, you may use the `artisan` method which automatically prefixes the command with `php artisan`:

```
1DevCommands::artisan('horizon', 'horizon');

DevCommands::artisan('horizon', 'horizon');
```

Likewise, the `node` method prefixes the command with your detected package manager's run command (e.g. `npm run`), and the `nodeExec` method prefixes the command with the package manager's exec command (e.g. `npx`):

```
1DevCommands::node('storybook', 'storybook');
2 
3DevCommands::nodeExec('tailwindcss -i resources/css/app.css -o public/css/app.css --watch', 'tailwind');

DevCommands::node('storybook', 'storybook');

DevCommands::nodeExec('tailwindcss -i resources/css/app.css -o public/css/app.css --watch', 'tailwind');
```

If you register a process with the same name as a default process, your process will replace the default. For example, you may customize the server process to use a different port:

```
1DevCommands::artisan('serve --host=localhost --port=9000', 'server');

DevCommands::artisan('serve --host=localhost --port=9000', 'server');
```

You may also customize the color of a process label in your terminal. The available color methods are `blue`, `purple`, `pink`, `orange`, `green`, and `yellow`. You may also pass a custom hex color to the `color` method:

```
1DevCommands::register('my-command', 'my-process')->green();
2 
3DevCommands::register('my-command', 'my-process')->color('#ff6347');

DevCommands::register('my-command', 'my-process')->green();

DevCommands::register('my-command', 'my-process')->color('#ff6347');
```

To see all registered dev processes without starting them, use the `dev:list` command:

```
1php artisan dev:list

php artisan dev:list
```

#### Restarting Failed Processes

If a process crashes, Laravel will restart it after a short delay, up to five times, before marking it as failed. A process that dies within a second of starting is not restarted, since it likely never started successfully in the first place. Restarting a process manually with `r` resets the counter.

You may disable this behavior for a single run using the `--no-restart` option:

```
1php artisan dev --no-restart

php artisan dev --no-restart
```

Or, you may disable it for your entire application using the `disableAutoRestart` method:

```
1DevCommands::disableAutoRestart();

DevCommands::disableAutoRestart();
```

### Filtering Dev Processes

You may instruct the `dev` command to only run specific processes when it is invoked using the `only` method. Similarly, you may exclude specific processes using the `except` method:

```
1// Only run the server and vite processes...
2DevCommands::only('server', 'vite');
3 
4// Run all processes except the queue worker...
5DevCommands::except('queue');

// Only run the server and vite processes...
DevCommands::only('server', 'vite');

// Run all processes except the queue worker...
DevCommands::except('queue');
```

You may exclude commands registered by packages or Laravel's default commands using the `withoutVendorCommands` and `withoutDefaultCommands` methods:

```
1DevCommands::withoutVendorCommands();
2 
3DevCommands::withoutDefaultCommands();

DevCommands::withoutVendorCommands();

DevCommands::withoutDefaultCommands();
```

## Stub Customization

The Artisan console's `make` commands are used to create a variety of classes, such as controllers, jobs, migrations, and tests. These classes are generated using "stub" files that are populated with values based on your input. However, you may want to make small changes to files generated by Artisan. To accomplish this, you may use the `stub:publish` command to publish the most common stubs to your application so that you can customize them:

```
1php artisan stub:publish

php artisan stub:publish
```

The published stubs will be located within a `stubs` directory in the root of your application. Any changes you make to these stubs will be reflected when you generate their corresponding classes using Artisan's `make` commands.

## Events

Artisan dispatches three events when running commands: `Illuminate\Console\Events\ArtisanStarting`, `Illuminate\Console\Events\CommandStarting`, and `Illuminate\Console\Events\CommandFinished`. The `ArtisanStarting` event is dispatched immediately when Artisan starts running. Next, the `CommandStarting` event is dispatched immediately before a command runs. Finally, the `CommandFinished` event is dispatched once a command finishes executing.
