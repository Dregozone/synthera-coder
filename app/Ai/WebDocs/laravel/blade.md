---
source: laravel
page: blade
url: https://laravel.com/framework/docs/13.x/blade
fetched_at: 2026-10-20T16:27:45+00:00
---

# Blade Templates

- Introduction
   - Supercharging Blade With Livewire
- Displaying Data
   - HTML Entity Encoding
   - Blade and JavaScript Frameworks
- Blade Directives
   - If Statements
   - Switch Statements
   - Loops
   - The Loop Variable
   - Conditional Classes
   - Additional Attributes
   - Including Subviews
   - The `@once` Directive
   - Raw PHP
   - Fonts
   - Comments
- Components
   - Rendering Components
   - Index Components
   - Passing Data to Components
   - Component Attributes
   - Reserved Keywords
   - Slots
   - Inline Component Views
   - Dynamic Components
   - Manually Registering Components
- Anonymous Components
   - Anonymous Index Components
   - Data Properties / Attributes
   - Accessing Parent Data
   - Anonymous Component Paths
- Building Layouts
   - Layouts Using Components
   - Layouts Using Template Inheritance
- Forms
   - CSRF Field
   - Method Field
   - Validation Errors
- Stacks
- Service Injection
- Rendering Inline Blade Templates
- Rendering Blade Fragments
- Extending Blade
   - Custom Echo Handlers
   - Custom If Statements

## Introduction

Blade is the simple, yet powerful templating engine that is included with Laravel. Unlike some PHP templating engines, Blade does not restrict you from using plain PHP code in your templates. In fact, all Blade templates are compiled into plain PHP code and cached until they are modified, meaning Blade adds essentially zero overhead to your application. Blade template files use the `.blade.php` file extension and are typically stored in the `resources/views` directory.

Blade views may be returned from routes or controllers using the global `view` helper. Of course, as mentioned in the documentation on [views](https://laravel.com/framework/docs/views), data may be passed to the Blade view using the `view` helper's second argument:

```
1Route::get('/', function () {
2    return view('greeting', ['name' => 'Finn']);
3});

Route::get('/', function () {
    return view('greeting', ['name' => 'Finn']);
});
```

### Supercharging Blade With Livewire

Want to take your Blade templates to the next level and build dynamic interfaces with ease? Check out [Laravel Livewire](https://livewire.laravel.com). Livewire allows you to write Blade components that are augmented with dynamic functionality that would typically only be possible via frontend frameworks like React, Svelte, or Vue, providing a great approach to building modern, reactive frontends without the complexities, client-side rendering, or build steps of many JavaScript frameworks.

## Displaying Data

You may display data that is passed to your Blade views by wrapping the variable in curly braces. For example, given the following route:

```
1Route::get('/', function () {
2    return view('welcome', ['name' => 'Samantha']);
3});

Route::get('/', function () {
    return view('welcome', ['name' => 'Samantha']);
});
```

You may display the contents of the `name` variable like so:

```
1Hello, {{ $name }}.

Hello, {{ $name }}.
```

 Blade's `{{ }}` echo statements are automatically sent through PHP's `htmlspecialchars` function to prevent XSS attacks.

You are not limited to displaying the contents of the variables passed to the view. You may also echo the results of any PHP function. In fact, you can put any PHP code you wish inside of a Blade echo statement:

```
1The current UNIX timestamp is {{ time() }}.

The current UNIX timestamp is {{ time() }}.
```

### HTML Entity Encoding

By default, Blade (and the Laravel `e` function) will double encode HTML entities. If you would like to disable double encoding, call the `Blade::withoutDoubleEncoding` method from the `boot` method of your `AppServiceProvider`:

```
 1<?php
 2 
 3namespace App\Providers;
 4 
 5use Illuminate\Support\Facades\Blade;
 6use Illuminate\Support\ServiceProvider;
 7 
 8class AppServiceProvider extends ServiceProvider
 9{
10    /**
11     * Bootstrap any application services.
12     */
13    public function boot(): void
14    {
15        Blade::withoutDoubleEncoding();
16    }
17}

<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::withoutDoubleEncoding();
    }
}
```

#### Displaying Unescaped Data

By default, Blade `{{ }}` statements are automatically sent through PHP's `htmlspecialchars` function to prevent XSS attacks. If you do not want your data to be escaped, you may use the following syntax:

```
1Hello, {!! $name !!}.

Hello, {!! $name !!}.
```

 Be very careful when echoing content that is supplied by users of your application. You should typically use the escaped, double curly brace syntax to prevent XSS attacks when displaying user supplied data.

### Blade and JavaScript Frameworks

Since many JavaScript frameworks also use "curly" braces to indicate a given expression should be displayed in the browser, you may use the `@` symbol to inform the Blade rendering engine an expression should remain untouched. For example:

```
1<h1>Laravel</h1>
2 
3Hello, @{{ name }}.

<h1>Laravel</h1>

Hello, @{{ name }}.
```

In this example, the `@` symbol will be removed by Blade; however, the `{{ name }}` expression will remain untouched by the Blade engine, allowing it to be rendered by your JavaScript framework.

The `@` symbol may also be used to escape Blade directives:

```
1{{-- Blade template --}}
2@@if()
3 
4<!-- HTML output -->
5@if()

{{-- Blade template --}}
@@if()

<!-- HTML output -->
@if()
```

#### Rendering JSON

Sometimes you may pass an array to your view with the intention of rendering it as JSON in order to initialize a JavaScript variable. For example:

```
1<script>
2    var app = <?php echo json_encode($array); ?>;
3</script>

<script>
    var app = <?php echo json_encode($array); ?>;
</script>
```

However, instead of manually calling `json_encode`, you may use the `Illuminate\Support\Js::from` method. The `from` method accepts the same arguments as PHP's `json_encode` function; however, it will ensure that the resulting JSON has been properly escaped for inclusion within HTML quotes. The `from` method will return a string `JSON.parse` JavaScript statement that will convert the given object or array into a valid JavaScript object:

```
1<script>
2    var app = {{ Illuminate\Support\Js::from($array) }};
3</script>

<script>
    var app = {{ Illuminate\Support\Js::from($array) }};
</script>
```

The latest versions of the Laravel application skeleton include a `Js` facade, which provides convenient access to this functionality within your Blade templates:

```
1<script>
2    var app = {{ Js::from($array) }};
3</script>

<script>
    var app = {{ Js::from($array) }};
</script>
```

 You should only use the `Js::from` method to render existing variables as JSON. The Blade templating is based on regular expressions and attempts to pass a complex expression to the directive may cause unexpected failures.

#### The `@verbatim` Directive

If you are displaying JavaScript variables in a large portion of your template, you may wrap the HTML in the `@verbatim` directive so that you do not have to prefix each Blade echo statement with an `@` symbol:

```
1@verbatim
2    <div class="container">
3        Hello, {{ name }}.
4    </div>
5@endverbatim

@verbatim
    <div class="container">
        Hello, {{ name }}.
    </div>
@endverbatim
```

## Blade Directives

In addition to template inheritance and displaying data, Blade also provides convenient shortcuts for common PHP control structures, such as conditional statements and loops. These shortcuts provide a very clean, terse way of working with PHP control structures while also remaining familiar to their PHP counterparts.

### If Statements

You may construct `if` statements using the `@if`, `@elseif`, `@else`, and `@endif` directives. These directives function identically to their PHP counterparts:

```
1@if (count($records) === 1)
2    I have one record!
3@elseif (count($records) > 1)
4    I have multiple records!
5@else
6    I don't have any records!
7@endif

@if (count($records) === 1)
    I have one record!
@elseif (count($records) > 1)
    I have multiple records!
@else
    I don't have any records!
@endif
```

For convenience, Blade also provides an `@unless` directive:

```
1@unless (Auth::check())
2    You are not signed in.
3@endunless

@unless (Auth::check())
    You are not signed in.
@endunless
```

In addition to the conditional directives already discussed, the `@isset` and `@empty` directives may be used as convenient shortcuts for their respective PHP functions:

```
1@isset($records)
2    // $records is defined and is not null...
3@endisset
4 
5@empty($records)
6    // $records is "empty"...
7@endempty

@isset($records)
    // $records is defined and is not null...
@endisset

@empty($records)
    // $records is "empty"...
@endempty
```

#### Authentication Directives

The `@auth` and `@guest` directives may be used to quickly determine if the current user is [authenticated](https://laravel.com/framework/docs/authentication) or is a guest:

```
1@auth
2    // The user is authenticated...
3@endauth
4 
5@guest
6    // The user is not authenticated...
7@endguest

@auth
    // The user is authenticated...
@endauth

@guest
    // The user is not authenticated...
@endguest
```

If needed, you may specify the authentication guard that should be checked when using the `@auth` and `@guest` directives:

```
1@auth('admin')
2    // The user is authenticated...
3@endauth
4 
5@guest('admin')
6    // The user is not authenticated...
7@endguest

@auth('admin')
    // The user is authenticated...
@endauth

@guest('admin')
    // The user is not authenticated...
@endguest
```

#### Environment Directives

You may check if the application is running in the production environment using the `@production` directive:

```
1@production
2    // Production specific content...
3@endproduction

@production
    // Production specific content...
@endproduction
```

Or, you may determine if the application is running in a specific environment using the `@env` directive:

```
1@env('staging')
2    // The application is running in "staging"...
3@endenv
4 
5@env(['staging', 'production'])
6    // The application is running in "staging" or "production"...
7@endenv

@env('staging')
    // The application is running in "staging"...
@endenv

@env(['staging', 'production'])
    // The application is running in "staging" or "production"...
@endenv
```

#### Section Directives

You may determine if a template inheritance section has content using the `@hasSection` directive:

```
1@hasSection('navigation')
2    <div class="pull-right">
3        @yield('navigation')
4    </div>
5 
6    <div class="clearfix"></div>
7@endif

@hasSection('navigation')
    <div class="pull-right">
        @yield('navigation')
    </div>

    <div class="clearfix"></div>
@endif
```

You may use the `sectionMissing` directive to determine if a section does not have content:

```
1@sectionMissing('navigation')
2    <div class="pull-right">
3        @include('default-navigation')
4    </div>
5@endif

@sectionMissing('navigation')
    <div class="pull-right">
        @include('default-navigation')
    </div>
@endif
```

#### Session Directives

The `@session` directive may be used to determine if a [session](https://laravel.com/framework/docs/session) value exists. If the session value exists, the template contents within the `@session` and `@endsession` directives will be evaluated. Within the `@session` directive's contents, you may echo the `$value` variable to display the session value:

```
1@session('status')
2    <div class="p-4 bg-green-100">
3        {{ $value }}
4    </div>
5@endsession

@session('status')
    <div class="p-4 bg-green-100">
        {{ $value }}
    </div>
@endsession
```

#### Context Directives

The `@context` directive may be used to determine if a [context](https://laravel.com/framework/docs/context) value exists. If the context value exists, the template contents within the `@context` and `@endcontext` directives will be evaluated. Within the `@context` directive's contents, you may echo the `$value` variable to display the context value:

```
1@context('canonical')
2    <link href="{{ $value }}" rel="canonical">
3@endcontext

@context('canonical')
    <link href="{{ $value }}" rel="canonical">
@endcontext
```

### Switch Statements

Switch statements can be constructed using the `@switch`, `@case`, `@break`, `@default` and `@endswitch` directives:

```
 1@switch($i)
 2    @case(1)
 3        First case...
 4        @break
 5 
 6    @case(2)
 7        Second case...
 8        @break
 9 
10    @default
11        Default case...
12@endswitch

@switch($i)
    @case(1)
        First case...
        @break

    @case(2)
        Second case...
        @break

    @default
        Default case...
@endswitch
```

### Loops

In addition to conditional statements, Blade provides simple directives for working with PHP's loop structures. Again, each of these directives functions identically to their PHP counterparts:

```
 1@for ($i = 0; $i < 10; $i++)
 2    The current value is {{ $i }}
 3@endfor
 4 
 5@foreach ($users as $user)
 6    <p>This is user {{ $user->id }}</p>
 7@endforeach
 8 
 9@forelse ($users as $user)
10    <li>{{ $user->name }}</li>
11@empty
12    <p>No users</p>
13@endforelse
14 
15@while (true)
16    <p>I'm looping forever.</p>
17@endwhile

@for ($i = 0; $i < 10; $i++)
    The current value is {{ $i }}
@endfor

@foreach ($users as $user)
    <p>This is user {{ $user->id }}</p>
@endforeach

@forelse ($users as $user)
    <li>{{ $user->name }}</li>
@empty
    <p>No users</p>
@endforelse

@while (true)
    <p>I'm looping forever.</p>
@endwhile
```

 While iterating through a `foreach` loop, you may use the loop variable to gain valuable information about the loop, such as whether you are in the first or last iteration through the loop.

When using loops you may also skip the current iteration or end the loop using the `@continue` and `@break` directives:

```
 1@foreach ($users as $user)
 2    @if ($user->type == 1)
 3        @continue
 4    @endif
 5 
 6    <li>{{ $user->name }}</li>
 7 
 8    @if ($user->number == 5)
 9        @break
10    @endif
11@endforeach

@foreach ($users as $user)
    @if ($user->type == 1)
        @continue
    @endif

    <li>{{ $user->name }}</li>

    @if ($user->number == 5)
        @break
    @endif
@endforeach
```

You may also include the continuation or break condition within the directive declaration:

```
1@foreach ($users as $user)
2    @continue($user->type == 1)
3 
4    <li>{{ $user->name }}</li>
5 
6    @break($user->number == 5)
7@endforeach

@foreach ($users as $user)
    @continue($user->type == 1)

    <li>{{ $user->name }}</li>

    @break($user->number == 5)
@endforeach
```

### The Loop Variable

While iterating through a `foreach` loop, a `$loop` variable will be available inside of your loop. This variable provides access to some useful bits of information such as the current loop index and whether this is the first or last iteration through the loop:

```
 1@foreach ($users as $user)
 2    @if ($loop->first)
 3        This is the first iteration.
 4    @endif
 5 
 6    @if ($loop->last)
 7        This is the last iteration.
 8    @endif
 9 
10    <p>This is user {{ $user->id }}</p>
11@endforeach

@foreach ($users as $user)
    @if ($loop->first)
        This is the first iteration.
    @endif

    @if ($loop->last)
        This is the last iteration.
    @endif

    <p>This is user {{ $user->id }}</p>
@endforeach
```

If you are in a nested loop, you may access the parent loop's `$loop` variable via the `parent` property:

```
1@foreach ($users as $user)
2    @foreach ($user->posts as $post)
3        @if ($loop->parent->first)
4            This is the first iteration of the parent loop.
5        @endif
6    @endforeach
7@endforeach

@foreach ($users as $user)
    @foreach ($user->posts as $post)
        @if ($loop->parent->first)
            This is the first iteration of the parent loop.
        @endif
    @endforeach
@endforeach
```

The `$loop` variable also contains a variety of other useful properties:

| Property | Description |
| --- | --- |
| `$loop->index` | The index of the current loop iteration (starts at 0). |
| `$loop->iteration` | The current loop iteration (starts at 1). |
| `$loop->remaining` | The iterations remaining in the loop. |
| `$loop->count` | The total number of items in the array being iterated. |
| `$loop->first` | Whether this is the first iteration through the loop. |
| `$loop->last` | Whether this is the last iteration through the loop. |
| `$loop->even` | Whether this is an even iteration through the loop. |
| `$loop->odd` | Whether this is an odd iteration through the loop. |
| `$loop->depth` | The nesting level of the current loop. |
| `$loop->parent` | When in a nested loop, the parent's loop variable. |

### Conditional Classes & Styles

The `@class` directive conditionally compiles a CSS class string. The directive accepts an array of classes where the array key contains the class or classes you wish to add, while the value is a boolean expression. If the array element has a numeric key, it will always be included in the rendered class list:

```
 1@php
 2    $isActive = false;
 3    $hasError = true;
 4@endphp
 5 
 6<span @class([
 7    'p-4',
 8    'font-bold' => $isActive,
 9    'text-gray-500' => ! $isActive,
10    'bg-red' => $hasError,
11])></span>
12 
13<span class="p-4 text-gray-500 bg-red"></span>

@php
    $isActive = false;
    $hasError = true;
@endphp

<span @class([
    'p-4',
    'font-bold' => $isActive,
    'text-gray-500' => ! $isActive,
    'bg-red' => $hasError,
])></span>

<span class="p-4 text-gray-500 bg-red"></span>
```

Likewise, the `@style` directive may be used to conditionally add inline CSS styles to an HTML element:

```
 1@php
 2    $isActive = true;
 3@endphp
 4 
 5<span @style([
 6    'background-color: red',
 7    'font-weight: bold' => $isActive,
 8])></span>
 9 
10<span style="background-color: red; font-weight: bold;"></span>

@php
    $isActive = true;
@endphp

<span @style([
    'background-color: red',
    'font-weight: bold' => $isActive,
])></span>

<span style="background-color: red; font-weight: bold;"></span>
```

### Additional Attributes

For convenience, you may use the `@checked` directive to easily indicate if a given HTML checkbox input is "checked". This directive will echo `checked` if the provided condition evaluates to `true`:

```
1<input
2    type="checkbox"
3    name="active"
4    value="active"
5    @checked(old('active', $user->active))
6/>

<input
    type="checkbox"
    name="active"
    value="active"
    @checked(old('active', $user->active))
/>
```

Likewise, the `@selected` directive may be used to indicate if a given select option should be "selected":

```
1<select name="version">
2    @foreach ($product->versions as $version)
3        <option value="{{ $version }}" @selected(old('version') == $version)>
4            {{ $version }}
5        </option>
6    @endforeach
7</select>

<select name="version">
    @foreach ($product->versions as $version)
        <option value="{{ $version }}" @selected(old('version') == $version)>
            {{ $version }}
        </option>
    @endforeach
</select>
```

Additionally, the `@disabled` directive may be used to indicate if a given element should be "disabled":

```
1<button type="submit" @disabled($errors->isNotEmpty())>Submit</button>

<button type="submit" @disabled($errors->isNotEmpty())>Submit</button>
```

Moreover, the `@readonly` directive may be used to indicate if a given element should be "readonly":

```
1<input
2    type="email"
3    name="email"
4    value="[email protected]"
5    @readonly($user->isNotAdmin())
6/>

<input
    type="email"
    name="email"
    value="[email protected]"
    @readonly($user->isNotAdmin())
/>
```

In addition, the `@required` directive may be used to indicate if a given element should be "required":

```
1<input
2    type="text"
3    name="title"
4    value="title"
5    @required($user->isAdmin())
6/>

<input
    type="text"
    name="title"
    value="title"
    @required($user->isAdmin())
/>
```

### Including Subviews

 While you're free to use the `@include` directive, Blade components provide similar functionality and offer several benefits over the `@include` directive such as data and attribute binding.

Blade's `@include` directive allows you to include a Blade view from within another view. All variables that are available to the parent view will be made available to the included view:

```
1<div>
2    @include('shared.errors')
3 
4    <form>
5        <!-- Form Contents -->
6    </form>
7</div>

<div>
    @include('shared.errors')

    <form>
        <!-- Form Contents -->
    </form>
</div>
```

Even though the included view will inherit all data available in the parent view, you may also pass an array of additional data that should be made available to the included view:

```
1@include('view.name', ['status' => 'complete'])

@include('view.name', ['status' => 'complete'])
```

If you attempt to `@include` a view which does not exist, Laravel will throw an error. If you would like to include a view that may or may not be present, you should use the `@includeIf` directive:

```
1@includeIf('view.name', ['status' => 'complete'])

@includeIf('view.name', ['status' => 'complete'])
```

If you would like to `@include` a view if a given boolean expression evaluates to `true` or `false`, you may use the `@includeWhen` and `@includeUnless` directives:

```
1@includeWhen($boolean, 'view.name', ['status' => 'complete'])
2 
3@includeUnless($boolean, 'view.name', ['status' => 'complete'])

@includeWhen($boolean, 'view.name', ['status' => 'complete'])

@includeUnless($boolean, 'view.name', ['status' => 'complete'])
```

To include the first view that exists from a given array of views, you may use the `includeFirst` directive:

```
1@includeFirst(['custom.admin', 'admin'], ['status' => 'complete'])

@includeFirst(['custom.admin', 'admin'], ['status' => 'complete'])
```

If you would like to include a view without inheriting any variables from the parent view, you may use the `@includeIsolated` directive. The included view will only have access to variables you explicitly pass:

```
1@includeIsolated('view.name', ['user' => $user])

@includeIsolated('view.name', ['user' => $user])
```

 You should avoid using the `__DIR__` and `__FILE__` constants in your Blade views, since they will refer to the location of the cached, compiled view.

#### Rendering Views for Collections

You may combine loops and includes into one line with Blade's `@each` directive:

```
1@each('view.name', $jobs, 'job')

@each('view.name', $jobs, 'job')
```

The `@each` directive's first argument is the view to render for each element in the array or collection. The second argument is the array or collection you wish to iterate over, while the third argument is the variable name that will be assigned to the current iteration within the view. So, for example, if you are iterating over an array of `jobs`, typically you will want to access each job as a `job` variable within the view. The array key for the current iteration will be available as the `key` variable within the view.

You may also pass a fourth argument to the `@each` directive. This argument determines the view that will be rendered if the given array is empty.

```
1@each('view.name', $jobs, 'job', 'view.empty')

@each('view.name', $jobs, 'job', 'view.empty')
```

 Views rendered via `@each` do not inherit the variables from the parent view. If the child view requires these variables, you should use the `@foreach` and `@include` directives instead.

### The `@once` Directive

The `@once` directive allows you to define a portion of the template that will only be evaluated once per rendering cycle. This may be useful for pushing a given piece of JavaScript into the page's header using stacks. For example, if you are rendering a given component within a loop, you may wish to only push the JavaScript to the header the first time the component is rendered:

```
1@once
2    @push('scripts')
3        <script>
4            // Your custom JavaScript...
5        </script>
6    @endpush
7@endonce

@once
    @push('scripts')
        <script>
            // Your custom JavaScript...
        </script>
    @endpush
@endonce
```

Since the `@once` directive is often used in conjunction with the `@push` or `@prepend` directives, the `@pushOnce` and `@prependOnce` directives are available for your convenience:

```
1@pushOnce('scripts')
2    <script>
3        // Your custom JavaScript...
4    </script>
5@endPushOnce

@pushOnce('scripts')
    <script>
        // Your custom JavaScript...
    </script>
@endPushOnce
```

If you are pushing duplicate content from two separate Blade templates, you should provide a unique identifier as the second argument to the `@pushOnce` directive to ensure the content is only rendered once:

```
1<!-- pie-chart.blade.php -->
2@pushOnce('scripts', 'chart.js')
3    <script src="/chart.js"></script>
4@endPushOnce
5 
6<!-- line-chart.blade.php -->
7@pushOnce('scripts', 'chart.js')
8    <script src="/chart.js"></script>
9@endPushOnce

<!-- pie-chart.blade.php -->
@pushOnce('scripts', 'chart.js')
    <script src="/chart.js"></script>
@endPushOnce

<!-- line-chart.blade.php -->
@pushOnce('scripts', 'chart.js')
    <script src="/chart.js"></script>
@endPushOnce
```

### Raw PHP

In some situations, it's useful to embed PHP code into your views. You can use the Blade `@php` directive to execute a block of plain PHP within your template:

```
1@php
2    $counter = 1;
3@endphp

@php
    $counter = 1;
@endphp
```

Or, if you only need to use PHP to import a class, you may use the `@use` directive:

```
1@use('App\Models\Flight')

@use('App\Models\Flight')
```

A second argument may be provided to the `@use` directive to alias the imported class:

```
1@use('App\Models\Flight', 'FlightModel')

@use('App\Models\Flight', 'FlightModel')
```

If you have multiple classes within the same namespace, you may group the imports of those classes:

```
1@use('App\Models\{Flight, Airport}')

@use('App\Models\{Flight, Airport}')
```

The `@use` directive also supports importing PHP functions and constants by prefixing the import path with the `function` or `const` modifiers:

```
1@use(function App\Helpers\format_currency)
2@use(const App\Constants\MAX_ATTEMPTS)

@use(function App\Helpers\format_currency)
@use(const App\Constants\MAX_ATTEMPTS)
```

Just like class imports, aliases are supported for functions and constants as well:

```
1@use(function App\Helpers\format_currency, 'formatMoney')
2@use(const App\Constants\MAX_ATTEMPTS, 'MAX_TRIES')

@use(function App\Helpers\format_currency, 'formatMoney')
@use(const App\Constants\MAX_ATTEMPTS, 'MAX_TRIES')
```

Grouped imports are also supported with both function and const modifiers, allowing you to import multiple symbols from the same namespace in a single directive:

```
1@use(function App\Helpers\{format_currency, format_date})
2@use(const App\Constants\{MAX_ATTEMPTS, DEFAULT_TIMEOUT})

@use(function App\Helpers\{format_currency, format_date})
@use(const App\Constants\{MAX_ATTEMPTS, DEFAULT_TIMEOUT})
```

### Fonts

When using [Laravel's Vite font optimization](https://laravel.com/framework/docs/vite#working-with-fonts), you may use the `@fonts` directive to render your configured font preload links and inline font CSS in your application's layout:

```
1<!doctype html>
2<head>
3    {{-- ... --}}
4 
5    @fonts
6    @vite('resources/js/app.js')
7</head>

<!doctype html>
<head>
    {{-- ... --}}

    @fonts
    @vite('resources/js/app.js')
</head>
```

The `@fonts` directive renders all font families configured in your `vite.config.js` file. The directive should typically be placed in the `<head>` of your application's root layout before any content that uses those fonts.

If a page only needs some of your configured fonts, you may pass one or more font aliases to the directive:

```
1{{-- Load a single font alias... --}}
2@fonts('sans')
3 
4{{-- Load multiple font aliases... --}}
5@fonts(['sans', 'mono'])

{{-- Load a single font alias... --}}
@fonts('sans')

{{-- Load multiple font aliases... --}}
@fonts(['sans', 'mono'])
```

Font aliases are configured using the `alias` option when defining fonts in your Vite configuration. The `@fonts` directive calls the `fonts` method provided by the `Vite` facade, which may also be invoked directly:

```
1{{ Vite::fonts(['sans', 'mono']) }}

{{ Vite::fonts(['sans', 'mono']) }}
```

### Comments

Blade also allows you to define comments in your views. However, unlike HTML comments, Blade comments are not included in the HTML returned by your application:

```
1{{-- This comment will not be present in the rendered HTML --}}

{{-- This comment will not be present in the rendered HTML --}}
```

## Components

Components and slots provide similar benefits to sections, layouts, and includes; however, some may find the mental model of components and slots easier to understand. There are two approaches to writing components: class-based components and anonymous components.

To create a class-based component, you may use the `make:component` Artisan command. To illustrate how to use components, we will create a simple `Alert` component. The `make:component` command will place the component in the `app/View/Components` directory:

```
1php artisan make:component Alert

php artisan make:component Alert
```

The `make:component` command will also create a view template for the component. The view will be placed in the `resources/views/components` directory. When writing components for your own application, components are automatically discovered within the `app/View/Components` directory and `resources/views/components` directory, so no further component registration is typically required.

You may also create components within subdirectories:

```
1php artisan make:component Forms/Input

php artisan make:component Forms/Input
```

The command above will create an `Input` component in the `app/View/Components/Forms` directory and the view will be placed in the `resources/views/components/forms` directory.

#### Manually Registering Package Components

When writing components for your own application, components are automatically discovered within the `app/View/Components` directory and `resources/views/components` directory.

However, if you are building a package that utilizes Blade components, you will need to manually register your component class and its HTML tag alias. You should typically register your components in the `boot` method of your package's service provider:

```
1use Illuminate\Support\Facades\Blade;
2 
3/**
4 * Bootstrap your package's services.
5 */
6public function boot(): void
7{
8    Blade::component('package-alert', Alert::class);
9}

use Illuminate\Support\Facades\Blade;

/**
 * Bootstrap your package's services.
 */
public function boot(): void
{
    Blade::component('package-alert', Alert::class);
}
```

Once your component has been registered, it may be rendered using its tag alias:

```
1<x-package-alert/>

<x-package-alert/>
```

Alternatively, you may use the `componentNamespace` method to autoload component classes by convention. For example, a `Nightshade` package might have `Calendar` and `ColorPicker` components that reside within the `Nightshade\Views\Components` namespace:

```
1use Illuminate\Support\Facades\Blade;
2 
3/**
4 * Bootstrap your package's services.
5 */
6public function boot(): void
7{
8    Blade::componentNamespace('Nightshade\\Views\\Components', 'nightshade');
9}

use Illuminate\Support\Facades\Blade;

/**
 * Bootstrap your package's services.
 */
public function boot(): void
{
    Blade::componentNamespace('Nightshade\\Views\\Components', 'nightshade');
}
```

This will allow the usage of package components by their vendor namespace using the `package-name::` syntax:

```
1<x-nightshade::calendar />
2<x-nightshade::color-picker />

<x-nightshade::calendar />
<x-nightshade::color-picker />
```

Blade will automatically detect the class that's linked to this component by pascal-casing the component name. Subdirectories are also supported using "dot" notation.

### Rendering Components

To display a component, you may use a Blade component tag within one of your Blade templates. Blade component tags start with the string `x-` followed by the kebab case name of the component class:

```
1<x-alert/>
2 
3<x-user-profile/>

<x-alert/>

<x-user-profile/>
```

If the component class is nested deeper within the `app/View/Components` directory, you may use the `.` character to indicate directory nesting. For example, if we assume a component is located at `app/View/Components/Inputs/Button.php`, we may render it like so:

```
1<x-inputs.button/>

<x-inputs.button/>
```

If you would like to conditionally render your component, you may define a `shouldRender` method on your component class. If the `shouldRender` method returns `false` the component will not be rendered:

```
1use Illuminate\Support\Str;
2 
3/**
4 * Whether the component should be rendered
5 */
6public function shouldRender(): bool
7{
8    return Str::length($this->message) > 0;
9}

use Illuminate\Support\Str;

/**
 * Whether the component should be rendered
 */
public function shouldRender(): bool
{
    return Str::length($this->message) > 0;
}
```

### Index Components

Sometimes components are part of a component group and you may wish to group the related components within a single directory. For example, imagine a "card" component with the following class structure:

```
1App\View\Components\Card\Card
2App\View\Components\Card\Header
3App\View\Components\Card\Body

App\View\Components\Card\Card
App\View\Components\Card\Header
App\View\Components\Card\Body
```

Since the root `Card` component is nested within a `Card` directory, you might expect that you would need to render the component via `<x-card.card>`. However, when a component's file name matches the name of the component's directory, Laravel automatically assumes that component is the "root" component and allows you to render the component without repeating the directory name:

```
1<x-card>
2    <x-card.header>...</x-card.header>
3    <x-card.body>...</x-card.body>
4</x-card>

<x-card>
    <x-card.header>...</x-card.header>
    <x-card.body>...</x-card.body>
</x-card>
```

### Passing Data to Components

You may pass data to Blade components using HTML attributes. Hard-coded, primitive values may be passed to the component using simple HTML attribute strings. PHP expressions and variables should be passed to the component via attributes that use the `:` character as a prefix:

```
1<x-alert type="error" :message="$message"/>

<x-alert type="error" :message="$message"/>
```

You should define all of the component's data attributes in its class constructor. All public properties on a component will automatically be made available to the component's view. It is not necessary to pass the data to the view from the component's `render` method:

```
 1<?php
 2 
 3namespace App\View\Components;
 4 
 5use Illuminate\View\Component;
 6use Illuminate\View\View;
 7 
 8class Alert extends Component
 9{
10    /**
11     * Create the component instance.
12     */
13    public function __construct(
14        public string $type,
15        public string $message,
16    ) {}
17 
18    /**
19     * Get the view / contents that represent the component.
20     */
21    public function render(): View
22    {
23        return view('components.alert');
24    }
25}

<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Alert extends Component
{
    /**
     * Create the component instance.
     */
    public function __construct(
        public string $type,
        public string $message,
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.alert');
    }
}
```

When your component is rendered, you may display the contents of your component's public variables by echoing the variables by name:

```
1<div class="alert alert-{{ $type }}">
2    {{ $message }}
3</div>

<div class="alert alert-{{ $type }}">
    {{ $message }}
</div>
```

#### Casing

Component constructor arguments should be specified using `camelCase`, while `kebab-case` should be used when referencing the argument names in your HTML attributes. For example, given the following component constructor:

```
1/**
2 * Create the component instance.
3 */
4public function __construct(
5    public string $alertType,
6) {}

/**
 * Create the component instance.
 */
public function __construct(
    public string $alertType,
) {}
```

The `$alertType` argument may be provided to the component like so:

```
1<x-alert alert-type="danger" />

<x-alert alert-type="danger" />
```

#### Short Attribute Syntax

When passing attributes to components, you may also use a "short attribute" syntax. This is often convenient since attribute names frequently match the variable names they correspond to:

```
1{{-- Short attribute syntax... --}}
2<x-profile :$userId :$name />
3 
4{{-- Is equivalent to... --}}
5<x-profile :user-id="$userId" :name="$name" />

{{-- Short attribute syntax... --}}
<x-profile :$userId :$name />

{{-- Is equivalent to... --}}
<x-profile :user-id="$userId" :name="$name" />
```

#### Escaping Attribute Rendering

Since some JavaScript frameworks such as Alpine.js also use colon-prefixed attributes, you may use a double colon (`::`) prefix to inform Blade that the attribute is not a PHP expression. For example, given the following component:

```
1<x-button ::class="{ danger: isDeleting }">
2    Submit
3</x-button>

<x-button ::class="{ danger: isDeleting }">
    Submit
</x-button>
```

The following HTML will be rendered by Blade:

```
1<button :class="{ danger: isDeleting }">
2    Submit
3</button>

<button :class="{ danger: isDeleting }">
    Submit
</button>
```

#### Component Methods

In addition to public variables being available to your component template, any public methods on the component may be invoked. For example, imagine a component that has an `isSelected` method:

```
1/**
2 * Determine if the given option is the currently selected option.
3 */
4public function isSelected(string $option): bool
5{
6    return $option === $this->selected;
7}

/**
 * Determine if the given option is the currently selected option.
 */
public function isSelected(string $option): bool
{
    return $option === $this->selected;
}
```

You may execute this method from your component template by invoking the variable matching the name of the method:

```
1<option {{ $isSelected($value) ? 'selected' : '' }} value="{{ $value }}">
2    {{ $label }}
3</option>

<option {{ $isSelected($value) ? 'selected' : '' }} value="{{ $value }}">
    {{ $label }}
</option>
```

#### Accessing Attributes and Slots Within Component Classes

Blade components also allow you to access the component name, attributes, and slot inside the class's render method. However, in order to access this data, you should return a closure from your component's `render` method:

```
 1use Closure;
 2 
 3/**
 4 * Get the view / contents that represent the component.
 5 */
 6public function render(): Closure
 7{
 8    return function () {
 9        return '<div {{ $attributes }}>Components content</div>';
10    };
11}

use Closure;

/**
 * Get the view / contents that represent the component.
 */
public function render(): Closure
{
    return function () {
        return '<div {{ $attributes }}>Components content</div>';
    };
}
```

The closure returned by your component's `render` method may also receive a `$data` array as its only argument. This array will contain several elements that provide information about the component:

```
1return function (array $data) {
2    // $data['componentName'];
3    // $data['attributes'];
4    // $data['slot'];
5 
6    return '<div {{ $attributes }}>Components content</div>';
7};

return function (array $data) {
    // $data['componentName'];
    // $data['attributes'];
    // $data['slot'];

    return '<div {{ $attributes }}>Components content</div>';
};
```

 The elements in the `$data` array should never be directly embedded into the Blade string returned by your `render` method, as doing so could allow remote code execution via malicious attribute content.

The `componentName` is equal to the name used in the HTML tag after the `x-` prefix. So `<x-alert />`'s `componentName` will be `alert`. The `attributes` element will contain all of the attributes that were present on the HTML tag. The `slot` element is an `Illuminate\View\ComponentSlot` instance with the contents of the component's slot.

The closure should return a string. If the returned string corresponds to an existing view, that view will be rendered; otherwise, the returned string will be evaluated as an inline Blade view.

#### Additional Dependencies

If your component requires dependencies from Laravel's [service container](https://laravel.com/framework/docs/container), you may list them before any of the component's data attributes and they will automatically be injected by the container:

```
 1use App\Services\AlertCreator;
 2 
 3/**
 4 * Create the component instance.
 5 */
 6public function __construct(
 7    public AlertCreator $creator,
 8    public string $type,
 9    public string $message,
10) {}

use App\Services\AlertCreator;

/**
 * Create the component instance.
 */
public function __construct(
    public AlertCreator $creator,
    public string $type,
    public string $message,
) {}
```

#### Hiding Attributes / Methods

If you would like to prevent some public methods or properties from being exposed as variables to your component template, you may add them to an `$except` array property on your component:

```
 1<?php
 2 
 3namespace App\View\Components;
 4 
 5use Illuminate\View\Component;
 6 
 7class Alert extends Component
 8{
 9    /**
10     * The properties / methods that should not be exposed to the component template.
11     *
12     * @var array
13     */
14    protected $except = ['type'];
15 
16    /**
17     * Create the component instance.
18     */
19    public function __construct(
20        public string $type,
21    ) {}
22}

<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Alert extends Component
{
    /**
     * The properties / methods that should not be exposed to the component template.
     *
     * @var array
     */
    protected $except = ['type'];

    /**
     * Create the component instance.
     */
    public function __construct(
        public string $type,
    ) {}
}
```

### Component Attributes

We've already examined how to pass data attributes to a component; however, sometimes you may need to specify additional HTML attributes, such as `class`, that are not part of the data required for a component to function. Typically, you want to pass these additional attributes down to the root element of the component template. For example, imagine we want to render an `alert` component like so:

```
1<x-alert type="error" :message="$message" class="mt-4"/>

<x-alert type="error" :message="$message" class="mt-4"/>
```

All of the attributes that are not part of the component's constructor will automatically be added to the component's "attribute bag". This attribute bag is automatically made available to the component via the `$attributes` variable. All of the attributes may be rendered within the component by echoing this variable:

```
1<div {{ $attributes }}>
2    <!-- Component content -->
3</div>

<div {{ $attributes }}>
    <!-- Component content -->
</div>
```

 Using directives such as `@env` within component tags is not supported at this time. For example, `<x-alert :live="@env('production')"/>` will not be compiled.

#### Default / Merged Attributes

Sometimes you may need to specify default values for attributes or merge additional values into some of the component's attributes. To accomplish this, you may use the attribute bag's `merge` method. This method is particularly useful for defining a set of default CSS classes that should always be applied to a component:

```
1<div {{ $attributes->merge(['class' => 'alert alert-'.$type]) }}>
2    {{ $message }}
3</div>

<div {{ $attributes->merge(['class' => 'alert alert-'.$type]) }}>
    {{ $message }}
</div>
```

If we assume this component is utilized like so:

```
1<x-alert type="error" :message="$message" class="mb-4"/>

<x-alert type="error" :message="$message" class="mb-4"/>
```

The final, rendered HTML of the component will appear like the following:

```
1<div class="alert alert-error mb-4">
2    <!-- Contents of the $message variable -->
3</div>

<div class="alert alert-error mb-4">
    <!-- Contents of the $message variable -->
</div>
```

#### Conditionally Merge Classes

Sometimes you may wish to merge classes if a given condition is `true`. You can accomplish this via the `class` method, which accepts an array of classes where the array key contains the class or classes you wish to add, while the value is a boolean expression. If the array element has a numeric key, it will always be included in the rendered class list:

```
1<div {{ $attributes->class(['p-4', 'bg-red' => $hasError]) }}>
2    {{ $message }}
3</div>

<div {{ $attributes->class(['p-4', 'bg-red' => $hasError]) }}>
    {{ $message }}
</div>
```

If you need to merge other attributes onto your component, you can chain the `merge` method onto the `class` method:

```
1<button {{ $attributes->class(['p-4'])->merge(['type' => 'button']) }}>
2    {{ $slot }}
3</button>

<button {{ $attributes->class(['p-4'])->merge(['type' => 'button']) }}>
    {{ $slot }}
</button>
```

 If you need to conditionally compile classes on other HTML elements that shouldn't receive merged attributes, you can use the @class directive.

#### Non-Class Attribute Merging

When merging attributes that are not `class` attributes, the values provided to the `merge` method will be considered the "default" values of the attribute. However, unlike the `class` attribute, these attributes will not be merged with injected attribute values. Instead, they will be overwritten. For example, a `button` component's implementation may look like the following:

```
1<button {{ $attributes->merge(['type' => 'button']) }}>
2    {{ $slot }}
3</button>

<button {{ $attributes->merge(['type' => 'button']) }}>
    {{ $slot }}
</button>
```

To render the button component with a custom `type`, it may be specified when consuming the component. If no type is specified, the `button` type will be used:

```
1<x-button type="submit">
2    Submit
3</x-button>

<x-button type="submit">
    Submit
</x-button>
```

The rendered HTML of the `button` component in this example would be:

```
1<button type="submit">
2    Submit
3</button>

<button type="submit">
    Submit
</button>
```

If you would like an attribute other than `class` to have its default value and injected values joined together, you may use the `prepends` method. In this example, the `data-controller` attribute will always begin with `profile-controller` and any additional injected `data-controller` values will be placed after this default value:

```
1<div {{ $attributes->merge(['data-controller' => $attributes->prepends('profile-controller')]) }}>
2    {{ $slot }}
3</div>

<div {{ $attributes->merge(['data-controller' => $attributes->prepends('profile-controller')]) }}>
    {{ $slot }}
</div>
```

#### Retrieving and Filtering Attributes

You may filter attributes using the `filter` method. This method accepts a closure which should return `true` if you wish to retain the attribute in the attribute bag:

```
1{{ $attributes->filter(fn (string $value, string $key) => $key == 'foo') }}

{{ $attributes->filter(fn (string $value, string $key) => $key == 'foo') }}
```

For convenience, you may use the `whereStartsWith` method to retrieve all attributes whose keys begin with a given string:

```
1{{ $attributes->whereStartsWith('wire:model') }}

{{ $attributes->whereStartsWith('wire:model') }}
```

Conversely, the `whereDoesntStartWith` method may be used to exclude all attributes whose keys begin with a given string:

```
1{{ $attributes->whereDoesntStartWith('wire:model') }}

{{ $attributes->whereDoesntStartWith('wire:model') }}
```

Using the `first` method, you may render the first attribute in a given attribute bag:

```
1{{ $attributes->whereStartsWith('wire:model')->first() }}

{{ $attributes->whereStartsWith('wire:model')->first() }}
```

If you would like to check if an attribute is present on the component, you may use the `has` method. This method accepts the attribute name as its only argument and returns a boolean indicating whether or not the attribute is present:

```
1@if ($attributes->has('class'))
2    <div>Class attribute is present</div>
3@endif

@if ($attributes->has('class'))
    <div>Class attribute is present</div>
@endif
```

If an array is passed to the `has` method, the method will determine if all of the given attributes are present on the component:

```
1@if ($attributes->has(['name', 'class']))
2    <div>All of the attributes are present</div>
3@endif

@if ($attributes->has(['name', 'class']))
    <div>All of the attributes are present</div>
@endif
```

The `hasAny` method may be used to determine if any of the given attributes are present on the component:

```
1@if ($attributes->hasAny(['href', ':href', 'v-bind:href']))
2    <div>One of the attributes is present</div>
3@endif

@if ($attributes->hasAny(['href', ':href', 'v-bind:href']))
    <div>One of the attributes is present</div>
@endif
```

You may retrieve a specific attribute's value using the `get` method:

```
1{{ $attributes->get('class') }}

{{ $attributes->get('class') }}
```

The `only` method may be used to retrieve only the attributes with the given keys:

```
1{{ $attributes->only(['class']) }}

{{ $attributes->only(['class']) }}
```

The `except` method may be used to retrieve all attributes except those with the given keys:

```
1{{ $attributes->except(['class']) }}

{{ $attributes->except(['class']) }}
```

### Reserved Keywords

By default, some keywords are reserved for Blade's internal use in order to render components. The following keywords cannot be defined as public properties or method names within your components:

- `data`
- `render`
- `resolve`
- `resolveView`
- `shouldRender`
- `view`
- `withAttributes`
- `withName`

### Slots

You will often need to pass additional content to your component via "slots". Component slots are rendered by echoing the `$slot` variable. To explore this concept, let's imagine that an `alert` component has the following markup:

```
1<!-- /resources/views/components/alert.blade.php -->
2 
3<div class="alert alert-danger">
4    {{ $slot }}
5</div>

<!-- /resources/views/components/alert.blade.php -->

<div class="alert alert-danger">
    {{ $slot }}
</div>
```

We may pass content to the `slot` by injecting content into the component:

```
1<x-alert>
2    <strong>Whoops!</strong> Something went wrong!
3</x-alert>

<x-alert>
    <strong>Whoops!</strong> Something went wrong!
</x-alert>
```

Sometimes a component may need to render multiple different slots in different locations within the component. Let's modify our alert component to allow for the injection of a "title" slot:

```
1<!-- /resources/views/components/alert.blade.php -->
2 
3<span class="alert-title">{{ $title }}</span>
4 
5<div class="alert alert-danger">
6    {{ $slot }}
7</div>

<!-- /resources/views/components/alert.blade.php -->

<span class="alert-title">{{ $title }}</span>

<div class="alert alert-danger">
    {{ $slot }}
</div>
```

You may define the content of the named slot using the `x-slot` tag. Any content not within an explicit `x-slot` tag will be passed to the component in the `$slot` variable:

```
1<x-alert>
2    <x-slot:title>
3        Server Error
4    </x-slot>
5 
6    <strong>Whoops!</strong> Something went wrong!
7</x-alert>

<x-alert>
    <x-slot:title>
        Server Error
    </x-slot>

    <strong>Whoops!</strong> Something went wrong!
</x-alert>
```

You may invoke a slot's `isEmpty` method to determine if the slot contains content:

```
1<span class="alert-title">{{ $title }}</span>
2 
3<div class="alert alert-danger">
4    @if ($slot->isEmpty())
5        This is default content if the slot is empty.
6    @else
7        {{ $slot }}
8    @endif
9</div>

<span class="alert-title">{{ $title }}</span>

<div class="alert alert-danger">
    @if ($slot->isEmpty())
        This is default content if the slot is empty.
    @else
        {{ $slot }}
    @endif
</div>
```

Additionally, the `hasActualContent` method may be used to determine if the slot contains any "actual" content that is not an HTML comment:

```
1@if ($slot->hasActualContent())
2    The scope has non-comment content.
3@endif

@if ($slot->hasActualContent())
    The scope has non-comment content.
@endif
```

#### Scoped Slots

If you have used a JavaScript framework such as Vue, you may be familiar with "scoped slots", which allow you to access data or methods from the component within your slot. You may achieve similar behavior in Laravel by defining public methods or properties on your component and accessing the component within your slot via the `$component` variable. In this example, we will assume that the `x-alert` component has a public `formatAlert` method defined on its component class:

```
1<x-alert>
2    <x-slot:title>
3        {{ $component->formatAlert('Server Error') }}
4    </x-slot>
5 
6    <strong>Whoops!</strong> Something went wrong!
7</x-alert>

<x-alert>
    <x-slot:title>
        {{ $component->formatAlert('Server Error') }}
    </x-slot>

    <strong>Whoops!</strong> Something went wrong!
</x-alert>
```

#### Slot Attributes

Like Blade components, you may assign additional attributes to slots such as CSS class names:

```
 1<x-card class="shadow-sm">
 2    <x-slot:heading class="font-bold">
 3        Heading
 4    </x-slot>
 5 
 6    Content
 7 
 8    <x-slot:footer class="text-sm">
 9        Footer
10    </x-slot>
11</x-card>

<x-card class="shadow-sm">
    <x-slot:heading class="font-bold">
        Heading
    </x-slot>

    Content

    <x-slot:footer class="text-sm">
        Footer
    </x-slot>
</x-card>
```

To interact with slot attributes, you may access the `attributes` property of the slot's variable. For more information on how to interact with attributes, please consult the documentation on component attributes:

```
 1@props([
 2    'heading',
 3    'footer',
 4])
 5 
 6<div {{ $attributes->class(['border']) }}>
 7    <h1 {{ $heading->attributes->class(['text-lg']) }}>
 8        {{ $heading }}
 9    </h1>
10 
11    {{ $slot }}
12 
13    <footer {{ $footer->attributes->class(['text-gray-700']) }}>
14        {{ $footer }}
15    </footer>
16</div>

@props([
    'heading',
    'footer',
])

<div {{ $attributes->class(['border']) }}>
    <h1 {{ $heading->attributes->class(['text-lg']) }}>
        {{ $heading }}
    </h1>

    {{ $slot }}

    <footer {{ $footer->attributes->class(['text-gray-700']) }}>
        {{ $footer }}
    </footer>
</div>
```

### Inline Component Views

For very small components, it may feel cumbersome to manage both the component class and the component's view template. For this reason, you may return the component's markup directly from the `render` method:

```
 1/**
 2 * Get the view / contents that represent the component.
 3 */
 4public function render(): string
 5{
 6    return <<<'blade'
 7        <div class="alert alert-danger">
 8            {{ $slot }}
 9        </div>
10    blade;
11}

/**
 * Get the view / contents that represent the component.
 */
public function render(): string
{
    return <<<'blade'
        <div class="alert alert-danger">
            {{ $slot }}
        </div>
    blade;
}
```

#### Generating Inline View Components

To create a component that renders an inline view, you may use the `inline` option when executing the `make:component` command:

```
1php artisan make:component Alert --inline

php artisan make:component Alert --inline
```

### Dynamic Components

Sometimes you may need to render a component but not know which component should be rendered until runtime. In this situation, you may use Laravel's built-in `dynamic-component` component to render the component based on a runtime value or variable:

```
1// $componentName = "secondary-button";
2 
3<x-dynamic-component :component="$componentName" class="mt-4" />

// $componentName = "secondary-button";

<x-dynamic-component :component="$componentName" class="mt-4" />
```

### Manually Registering Components

 The following documentation on manually registering components is primarily applicable to those who are writing Laravel packages that include view components. If you are not writing a package, this portion of the component documentation may not be relevant to you.

When writing components for your own application, components are automatically discovered within the `app/View/Components` directory and `resources/views/components` directory.

However, if you are building a package that utilizes Blade components or placing components in non-conventional directories, you will need to manually register your component class and its HTML tag alias so that Laravel knows where to find the component. You should typically register your components in the `boot` method of your package's service provider:

```
 1use Illuminate\Support\Facades\Blade;
 2use VendorPackage\View\Components\AlertComponent;
 3 
 4/**
 5 * Bootstrap your package's services.
 6 */
 7public function boot(): void
 8{
 9    Blade::component('package-alert', AlertComponent::class);
10}

use Illuminate\Support\Facades\Blade;
use VendorPackage\View\Components\AlertComponent;

/**
 * Bootstrap your package's services.
 */
public function boot(): void
{
    Blade::component('package-alert', AlertComponent::class);
}
```

Once your component has been registered, it may be rendered using its tag alias:

```
1<x-package-alert/>

<x-package-alert/>
```

#### Autoloading Package Components

Alternatively, you may use the `componentNamespace` method to autoload component classes by convention. For example, a `Nightshade` package might have `Calendar` and `ColorPicker` components that reside within the `Nightshade\Views\Components` namespace:

```
1use Illuminate\Support\Facades\Blade;
2 
3/**
4 * Bootstrap your package's services.
5 */
6public function boot(): void
7{
8    Blade::componentNamespace('Nightshade\\Views\\Components', 'nightshade');
9}

use Illuminate\Support\Facades\Blade;

/**
 * Bootstrap your package's services.
 */
public function boot(): void
{
    Blade::componentNamespace('Nightshade\\Views\\Components', 'nightshade');
}
```

This will allow the usage of package components by their vendor namespace using the `package-name::` syntax:

```
1<x-nightshade::calendar />
2<x-nightshade::color-picker />

<x-nightshade::calendar />
<x-nightshade::color-picker />
```

Blade will automatically detect the class that's linked to this component by pascal-casing the component name. Subdirectories are also supported using "dot" notation.

## Anonymous Components

Similar to inline components, anonymous components provide a mechanism for managing a component via a single file. However, anonymous components utilize a single view file and have no associated class. To define an anonymous component, you only need to place a Blade template within your `resources/views/components` directory. For example, assuming you have defined a component at `resources/views/components/alert.blade.php`, you may simply render it like so:

```
1<x-alert/>

<x-alert/>
```

You may use the `.` character to indicate if a component is nested deeper inside the `components` directory. For example, assuming the component is defined at `resources/views/components/inputs/button.blade.php`, you may render it like so:

```
1<x-inputs.button/>

<x-inputs.button/>
```

To create an anonymous component via Artisan, you may use the `--view` flag when invoking the `make:component` command:

```
1php artisan make:component forms.input --view

php artisan make:component forms.input --view
```

The command above will create a Blade file at `resources/views/components/forms/input.blade.php` which can be rendered as a component via `<x-forms.input />`.

### Anonymous Index Components

Sometimes, when a component is made up of many Blade templates, you may wish to group the given component's templates within a single directory. For example, imagine an "accordion" component with the following directory structure:

```
1/resources/views/components/accordion.blade.php
2/resources/views/components/accordion/item.blade.php

/resources/views/components/accordion.blade.php
/resources/views/components/accordion/item.blade.php
```

This directory structure allows you to render the accordion component and its item like so:

```
1<x-accordion>
2    <x-accordion.item>
3        ...
4    </x-accordion.item>
5</x-accordion>

<x-accordion>
    <x-accordion.item>
        ...
    </x-accordion.item>
</x-accordion>
```

However, in order to render the accordion component via `x-accordion`, we were forced to place the "index" accordion component template in the `resources/views/components` directory instead of nesting it within the `accordion` directory with the other accordion related templates.

Thankfully, Blade allows you to place a file matching the component's directory name within the component's directory itself. When this template exists, it can be rendered as the "root" element of the component even though it is nested within a directory. So, we can continue to use the same Blade syntax given in the example above; however, we will adjust our directory structure like so:

```
1/resources/views/components/accordion/accordion.blade.php
2/resources/views/components/accordion/item.blade.php

/resources/views/components/accordion/accordion.blade.php
/resources/views/components/accordion/item.blade.php
```

### Data Properties / Attributes

Since anonymous components do not have any associated class, you may wonder how you may differentiate which data should be passed to the component as variables and which attributes should be placed in the component's attribute bag.

You may specify which attributes should be considered data variables using the `@props` directive at the top of your component's Blade template. All other attributes on the component will be available via the component's attribute bag. If you wish to give a data variable a default value, you may specify the variable's name as the array key and the default value as the array value:

```
1<!-- /resources/views/components/alert.blade.php -->
2 
3@props(['type' => 'info', 'message'])
4 
5<div {{ $attributes->merge(['class' => 'alert alert-'.$type]) }}>
6    {{ $message }}
7</div>

<!-- /resources/views/components/alert.blade.php -->

@props(['type' => 'info', 'message'])

<div {{ $attributes->merge(['class' => 'alert alert-'.$type]) }}>
    {{ $message }}
</div>
```

Given the component definition above, we may render the component like so:

```
1<x-alert type="error" :message="$message" class="mb-4"/>

<x-alert type="error" :message="$message" class="mb-4"/>
```

### Accessing Parent Data

Sometimes you may want to access data from a parent component inside a child component. In these cases, you may use the `@aware` directive. For example, imagine we are building a complex menu component consisting of a parent `<x-menu>` and child `<x-menu.item>`:

```
1<x-menu color="purple">
2    <x-menu.item>...</x-menu.item>
3    <x-menu.item>...</x-menu.item>
4</x-menu>

<x-menu color="purple">
    <x-menu.item>...</x-menu.item>
    <x-menu.item>...</x-menu.item>
</x-menu>
```

The `<x-menu>` component may have an implementation like the following:

```
1<!-- /resources/views/components/menu/index.blade.php -->
2 
3@props(['color' => 'gray'])
4 
5<ul {{ $attributes->merge(['class' => 'bg-'.$color.'-200']) }}>
6    {{ $slot }}
7</ul>

<!-- /resources/views/components/menu/index.blade.php -->

@props(['color' => 'gray'])

<ul {{ $attributes->merge(['class' => 'bg-'.$color.'-200']) }}>
    {{ $slot }}
</ul>
```

Because the `color` prop was only passed into the parent (`<x-menu>`), it won't be available inside `<x-menu.item>`. However, if we use the `@aware` directive, we can make it available inside `<x-menu.item>` as well:

```
1<!-- /resources/views/components/menu/item.blade.php -->
2 
3@aware(['color' => 'gray'])
4 
5<li {{ $attributes->merge(['class' => 'text-'.$color.'-800']) }}>
6    {{ $slot }}
7</li>

<!-- /resources/views/components/menu/item.blade.php -->

@aware(['color' => 'gray'])

<li {{ $attributes->merge(['class' => 'text-'.$color.'-800']) }}>
    {{ $slot }}
</li>
```

 The `@aware` directive cannot access parent data that is not explicitly passed to the parent component via HTML attributes. Default `@props` values that are not explicitly passed to the parent component cannot be accessed by the `@aware` directive.

### Anonymous Component Paths

As previously discussed, anonymous components are typically defined by placing a Blade template within your `resources/views/components` directory. However, you may occasionally want to register other anonymous component paths with Laravel in addition to the default path.

The `anonymousComponentPath` method accepts the "path" to the anonymous component location as its first argument and an optional "namespace" that components should be placed under as its second argument. Typically, this method should be called from the `boot` method of one of your application's [service providers](https://laravel.com/framework/docs/providers):

```
1/**
2 * Bootstrap any application services.
3 */
4public function boot(): void
5{
6    Blade::anonymousComponentPath(__DIR__.'/../components');
7}

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Blade::anonymousComponentPath(__DIR__.'/../components');
}
```

When component paths are registered without a specified prefix as in the example above, they may be rendered in your Blade components without a corresponding prefix as well. For example, if a `panel.blade.php` component exists in the path registered above, it may be rendered like so:

```
1<x-panel />

<x-panel />
```

Prefix "namespaces" may be provided as the second argument to the `anonymousComponentPath` method:

```
1Blade::anonymousComponentPath(__DIR__.'/../components', 'dashboard');

Blade::anonymousComponentPath(__DIR__.'/../components', 'dashboard');
```

When a prefix is provided, components within that "namespace" may be rendered by prefixing the component's namespace to the component name when the component is rendered:

```
1<x-dashboard::panel />

<x-dashboard::panel />
```

## Building Layouts

### Layouts Using Components

Most web applications maintain the same general layout across various pages. It would be incredibly cumbersome and hard to maintain our application if we had to repeat the entire layout HTML in every view we create. Thankfully, it's convenient to define this layout as a single Blade component and then use it throughout our application.

#### Defining the Layout Component

For example, imagine we are building a "todo" list application. We might define a `layout` component that looks like the following:

```
 1<!-- resources/views/components/layout.blade.php -->
 2 
 3<html>
 4    <head>
 5        <title>{{ $title ?? 'Todo Manager' }}</title>
 6    </head>
 7    <body>
 8        <h1>Todos</h1>
 9        <hr/>
10        {{ $slot }}
11    </body>
12</html>

<!-- resources/views/components/layout.blade.php -->

<html>
    <head>
        <title>{{ $title ?? 'Todo Manager' }}</title>
    </head>
    <body>
        <h1>Todos</h1>
        <hr/>
        {{ $slot }}
    </body>
</html>
```

#### Applying the Layout Component

Once the `layout` component has been defined, we may create a Blade view that utilizes the component. In this example, we will define a simple view that displays our task list:

```
1<!-- resources/views/tasks.blade.php -->
2 
3<x-layout>
4    @foreach ($tasks as $task)
5        <div>{{ $task }}</div>
6    @endforeach
7</x-layout>

<!-- resources/views/tasks.blade.php -->

<x-layout>
    @foreach ($tasks as $task)
        <div>{{ $task }}</div>
    @endforeach
</x-layout>
```

Remember, content that is injected into a component will be supplied to the default `$slot` variable within our `layout` component. As you may have noticed, our `layout` also respects a `$title` slot if one is provided; otherwise, a default title is shown. We may inject a custom title from our task list view using the standard slot syntax discussed in the component documentation:

```
 1<!-- resources/views/tasks.blade.php -->
 2 
 3<x-layout>
 4    <x-slot:title>
 5        Custom Title
 6    </x-slot>
 7 
 8    @foreach ($tasks as $task)
 9        <div>{{ $task }}</div>
10    @endforeach
11</x-layout>

<!-- resources/views/tasks.blade.php -->

<x-layout>
    <x-slot:title>
        Custom Title
    </x-slot>

    @foreach ($tasks as $task)
        <div>{{ $task }}</div>
    @endforeach
</x-layout>
```

Now that we have defined our layout and task list views, we just need to return the `task` view from a route:

```
1use App\Models\Task;
2 
3Route::get('/tasks', function () {
4    return view('tasks', ['tasks' => Task::all()]);
5});

use App\Models\Task;

Route::get('/tasks', function () {
    return view('tasks', ['tasks' => Task::all()]);
});
```

### Layouts Using Template Inheritance

#### Defining a Layout

Layouts may also be created via "template inheritance". This was the primary way of building applications prior to the introduction of components.

To get started, let's take a look at a simple example. First, we will examine a page layout. Since most web applications maintain the same general layout across various pages, it's convenient to define this layout as a single Blade view:

```
 1<!-- resources/views/layouts/app.blade.php -->
 2 
 3<html>
 4    <head>
 5        <title>App Name - @yield('title')</title>
 6    </head>
 7    <body>
 8        @section('sidebar')
 9            This is the master sidebar.
10        @show
11 
12        <div class="container">
13            @yield('content')
14        </div>
15    </body>
16</html>

<!-- resources/views/layouts/app.blade.php -->

<html>
    <head>
        <title>App Name - @yield('title')</title>
    </head>
    <body>
        @section('sidebar')
            This is the master sidebar.
        @show

        <div class="container">
            @yield('content')
        </div>
    </body>
</html>
```

As you can see, this file contains typical HTML mark-up. However, take note of the `@section` and `@yield` directives. The `@section` directive, as the name implies, defines a section of content, while the `@yield` directive is used to display the contents of a given section.

Now that we have defined a layout for our application, let's define a child page that inherits the layout.

#### Extending a Layout

When defining a child view, use the `@extends` Blade directive to specify which layout the child view should "inherit". Views which extend a Blade layout may inject content into the layout's sections using `@section` directives. Remember, as seen in the example above, the contents of these sections will be displayed in the layout using `@yield`:

```
 1<!-- resources/views/child.blade.php -->
 2 
 3@extends('layouts.app')
 4 
 5@section('title', 'Page Title')
 6 
 7@section('sidebar')
 8    @@parent
 9 
10    <p>This is appended to the master sidebar.</p>
11@endsection
12 
13@section('content')
14    <p>This is my body content.</p>
15@endsection

<!-- resources/views/child.blade.php -->

@extends('layouts.app')

@section('title', 'Page Title')

@section('sidebar')
    @@parent

    <p>This is appended to the master sidebar.</p>
@endsection

@section('content')
    <p>This is my body content.</p>
@endsection
```

In this example, the `sidebar` section is utilizing the `@@parent` directive to append (rather than overwriting) content to the layout's sidebar. The `@@parent` directive will be replaced by the content of the layout when the view is rendered.

 Contrary to the previous example, this `sidebar` section ends with `@endsection` instead of `@show`. The `@endsection` directive will only define a section while `@show` will define and **immediately yield** the section.

The `@yield` directive also accepts a default value as its second parameter. This value will be rendered if the section being yielded is undefined:

```
1@yield('content', 'Default content')

@yield('content', 'Default content')
```

## Forms

### CSRF Field

Anytime you define an HTML form in your application, you should include a hidden CSRF token field in the form so that [the CSRF protection](https://laravel.com/framework/docs/csrf) middleware can validate the request. You may use the `@csrf` Blade directive to generate the token field:

```
1<form method="POST" action="/profile">
2    @csrf
3 
4    ...
5</form>

<form method="POST" action="/profile">
    @csrf

    ...
</form>
```

### Method Field

Since HTML forms can't make `PUT`, `PATCH`, or `DELETE` requests, you will need to add a hidden `_method` field to spoof these HTTP verbs. The `@method` Blade directive can create this field for you:

```
1<form action="/foo/bar" method="POST">
2    @method('PUT')
3 
4    ...
5</form>

<form action="/foo/bar" method="POST">
    @method('PUT')

    ...
</form>
```

### Validation Errors

The `@error` directive may be used to quickly check if [validation error messages](https://laravel.com/framework/docs/validation#quick-displaying-the-validation-errors) exist for a given attribute. Within an `@error` directive, you may echo the `$message` variable to display the error message:

```
 1<!-- /resources/views/post/create.blade.php -->
 2 
 3<label for="title">Post Title</label>
 4 
 5<input
 6    id="title"
 7    type="text"
 8    class="@error('title') is-invalid @enderror"
 9/>
10 
11@error('title')
12    <div class="alert alert-danger">{{ $message }}</div>
13@enderror

<!-- /resources/views/post/create.blade.php -->

<label for="title">Post Title</label>

<input
    id="title"
    type="text"
    class="@error('title') is-invalid @enderror"
/>

@error('title')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror
```

Since the `@error` directive compiles to an "if" statement, you may use the `@else` directive to render content when there is not an error for an attribute:

```
1<!-- /resources/views/auth.blade.php -->
2 
3<label for="email">Email address</label>
4 
5<input
6    id="email"
7    type="email"
8    class="@error('email') is-invalid @else is-valid @enderror"
9/>

<!-- /resources/views/auth.blade.php -->

<label for="email">Email address</label>

<input
    id="email"
    type="email"
    class="@error('email') is-invalid @else is-valid @enderror"
/>
```

You may pass [the name of a specific error bag](https://laravel.com/framework/docs/validation#named-error-bags) as the second parameter to the `@error` directive to retrieve validation error messages on pages containing multiple forms:

```
 1<!-- /resources/views/auth.blade.php -->
 2 
 3<label for="email">Email address</label>
 4 
 5<input
 6    id="email"
 7    type="email"
 8    class="@error('email', 'login') is-invalid @enderror"
 9/>
10 
11@error('email', 'login')
12    <div class="alert alert-danger">{{ $message }}</div>
13@enderror

<!-- /resources/views/auth.blade.php -->

<label for="email">Email address</label>

<input
    id="email"
    type="email"
    class="@error('email', 'login') is-invalid @enderror"
/>

@error('email', 'login')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror
```

## Stacks

Blade allows you to push to named stacks which can be rendered somewhere else in another view or layout. This can be particularly useful for specifying any JavaScript libraries required by your child views:

```
1@push('scripts')
2    <script src="/example.js"></script>
3@endpush

@push('scripts')
    <script src="/example.js"></script>
@endpush
```

If you would like to `@push` content if a given boolean expression evaluates to `true`, you may use the `@pushIf` directive:

```
1@pushIf($shouldPush, 'scripts')
2    <script src="/example.js"></script>
3@endPushIf

@pushIf($shouldPush, 'scripts')
    <script src="/example.js"></script>
@endPushIf
```

You may push to a stack as many times as needed. To render the complete stack contents, pass the name of the stack to the `@stack` directive:

```
1<head>
2    <!-- Head Contents -->
3 
4    @stack('scripts')
5</head>

<head>
    <!-- Head Contents -->

    @stack('scripts')
</head>
```

The `@stack` directive renders the content that has been pushed at the moment it is reached. Content pushed afterwards, such as from a component rendered below the `@stack` directive in the same layout component, will not be included. If you need to push from such components, place the `@stack` directive after them, for example at the end of the `<body>` tag.

If you would like to prepend content onto the beginning of a stack, you should use the `@prepend` directive:

```
1@push('scripts')
2    This will be second...
3@endpush
4 
5// Later...
6 
7@prepend('scripts')
8    This will be first...
9@endprepend

@push('scripts')
    This will be second...
@endpush

// Later...

@prepend('scripts')
    This will be first...
@endprepend
```

The `@hasstack` directive may be used to determine if a stack is empty:

```
1@hasstack('list')
2    <ul>
3        @stack('list')
4    </ul>
5@endif

@hasstack('list')
    <ul>
        @stack('list')
    </ul>
@endif
```

## Service Injection

The `@inject` directive may be used to retrieve a service from the Laravel [service container](https://laravel.com/framework/docs/container). The first argument passed to `@inject` is the name of the variable the service will be placed into, while the second argument is the class or interface name of the service you wish to resolve:

```
1@inject('metrics', 'App\Services\MetricsService')
2 
3<div>
4    Monthly Revenue: {{ $metrics->monthlyRevenue() }}.
5</div>

@inject('metrics', 'App\Services\MetricsService')

<div>
    Monthly Revenue: {{ $metrics->monthlyRevenue() }}.
</div>
```

## Rendering Inline Blade Templates

Sometimes you may need to transform a raw Blade template string into valid HTML. You may accomplish this using the `render` method provided by the `Blade` facade. The `render` method accepts the Blade template string and an optional array of data to provide to the template:

```
1use Illuminate\Support\Facades\Blade;
2 
3return Blade::render('Hello, {{ $name }}', ['name' => 'Julian Bashir']);

use Illuminate\Support\Facades\Blade;

return Blade::render('Hello, {{ $name }}', ['name' => 'Julian Bashir']);
```

Laravel renders inline Blade templates by writing them to the `storage/framework/views` directory. If you would like Laravel to remove these temporary files after rendering the Blade template, you may provide the `deleteCachedView` argument to the method:

```
1return Blade::render(
2    'Hello, {{ $name }}',
3    ['name' => 'Julian Bashir'],
4    deleteCachedView: true
5);

return Blade::render(
    'Hello, {{ $name }}',
    ['name' => 'Julian Bashir'],
    deleteCachedView: true
);
```

## Rendering Blade Fragments

When using frontend frameworks such as [Turbo](https://turbo.hotwired.dev/) and [htmx](https://htmx.org/), you may occasionally need to only return a portion of a Blade template within your HTTP response. Blade "fragments" allow you to do just that. To get started, place a portion of your Blade template within `@fragment` and `@endfragment` directives:

```
1@fragment('user-list')
2    <ul>
3        @foreach ($users as $user)
4            <li>{{ $user->name }}</li>
5        @endforeach
6    </ul>
7@endfragment

@fragment('user-list')
    <ul>
        @foreach ($users as $user)
            <li>{{ $user->name }}</li>
        @endforeach
    </ul>
@endfragment
```

Then, when rendering the view that utilizes this template, you may invoke the `fragment` method to specify that only the specified fragment should be included in the outgoing HTTP response:

```
1return view('dashboard', ['users' => $users])->fragment('user-list');

return view('dashboard', ['users' => $users])->fragment('user-list');
```

The `fragmentIf` method allows you to conditionally return a fragment of a view based on a given condition. Otherwise, the entire view will be returned:

```
1return view('dashboard', ['users' => $users])
2    ->fragmentIf($request->hasHeader('HX-Request'), 'user-list');

return view('dashboard', ['users' => $users])
    ->fragmentIf($request->hasHeader('HX-Request'), 'user-list');
```

The `fragments` and `fragmentsIf` methods allow you to return multiple view fragments in the response. The fragments will be concatenated together:

```
1view('dashboard', ['users' => $users])
2    ->fragments(['user-list', 'comment-list']);
3 
4view('dashboard', ['users' => $users])
5    ->fragmentsIf(
6        $request->hasHeader('HX-Request'),
7        ['user-list', 'comment-list']
8    );

view('dashboard', ['users' => $users])
    ->fragments(['user-list', 'comment-list']);

view('dashboard', ['users' => $users])
    ->fragmentsIf(
        $request->hasHeader('HX-Request'),
        ['user-list', 'comment-list']
    );
```

## Extending Blade

Blade allows you to define your own custom directives using the `directive` method. When the Blade compiler encounters the custom directive, it will call the provided callback with the expression that the directive contains.

The following example creates a `@datetime($var)` directive which formats a given `$var`, which should be an instance of `DateTime`:

```
 1<?php
 2 
 3namespace App\Providers;
 4 
 5use Illuminate\Support\Facades\Blade;
 6use Illuminate\Support\ServiceProvider;
 7 
 8class AppServiceProvider extends ServiceProvider
 9{
10    /**
11     * Register any application services.
12     */
13    public function register(): void
14    {
15        // ...
16    }
17 
18    /**
19     * Bootstrap any application services.
20     */
21    public function boot(): void
22    {
23        Blade::directive('datetime', function (string $expression) {
24            return "<?php echo ($expression)->format('m/d/Y H:i'); ?>";
25        });
26    }
27}

<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

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
        Blade::directive('datetime', function (string $expression) {
            return "<?php echo ($expression)->format('m/d/Y H:i'); ?>";
        });
    }
}
```

As you can see, we will chain the `format` method onto whatever expression is passed into the directive. So, in this example, the final PHP generated by this directive will be:

```
1<?php echo ($var)->format('m/d/Y H:i'); ?>

<?php echo ($var)->format('m/d/Y H:i'); ?>
```

 After updating the logic of a Blade directive, you will need to delete all of the cached Blade views. The cached Blade views may be removed using the `view:clear` Artisan command.

### Custom Echo Handlers

If you attempt to "echo" an object using Blade, the object's `__toString` method will be invoked. The [__toString](https://www.php.net/manual/en/language.oop5.magic.php#object.tostring) method is one of PHP's built-in "magic methods". However, sometimes you may not have control over the `__toString` method of a given class, such as when the class that you are interacting with belongs to a third-party library.

In these cases, Blade allows you to register a custom echo handler for that particular type of object. To accomplish this, you should invoke Blade's `stringable` method. The `stringable` method accepts a closure. This closure should type-hint the type of object that it is responsible for rendering. Typically, the `stringable` method should be invoked within the `boot` method of your application's `AppServiceProvider` class:

```
 1use Illuminate\Support\Facades\Blade;
 2use Money\Money;
 3 
 4/**
 5 * Bootstrap any application services.
 6 */
 7public function boot(): void
 8{
 9    Blade::stringable(function (Money $money) {
10        return $money->formatTo('en_GB');
11    });
12}

use Illuminate\Support\Facades\Blade;
use Money\Money;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Blade::stringable(function (Money $money) {
        return $money->formatTo('en_GB');
    });
}
```

Once your custom echo handler has been defined, you may simply echo the object in your Blade template:

```
1Cost: {{ $money }}

Cost: {{ $money }}
```

### Custom If Statements

Programming a custom directive is sometimes more complex than necessary when defining simple, custom conditional statements. For that reason, Blade provides a `Blade::if` method which allows you to quickly define custom conditional directives using closures. For example, let's define a custom conditional that checks the configured default "disk" for the application. We may do this in the `boot` method of our `AppServiceProvider`:

```
 1use Illuminate\Support\Facades\Blade;
 2 
 3/**
 4 * Bootstrap any application services.
 5 */
 6public function boot(): void
 7{
 8    Blade::if('disk', function (string $value) {
 9        return config('filesystems.default') === $value;
10    });
11}

use Illuminate\Support\Facades\Blade;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Blade::if('disk', function (string $value) {
        return config('filesystems.default') === $value;
    });
}
```

Once the custom conditional has been defined, you can use it within your templates:

```
 1@disk('local')
 2    <!-- The application is using the local disk... -->
 3@elsedisk('s3')
 4    <!-- The application is using the s3 disk... -->
 5@else
 6    <!-- The application is using some other disk... -->
 7@enddisk
 8 
 9@unlessdisk('local')
10    <!-- The application is not using the local disk... -->
11@enddisk

@disk('local')
    <!-- The application is using the local disk... -->
@elsedisk('s3')
    <!-- The application is using the s3 disk... -->
@else
    <!-- The application is using some other disk... -->
@enddisk

@unlessdisk('local')
    <!-- The application is not using the local disk... -->
@enddisk
```
