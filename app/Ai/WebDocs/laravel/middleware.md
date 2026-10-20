---
source: laravel
page: middleware
url: https://laravel.com/framework/docs/13.x/middleware
fetched_at: 2026-10-20T16:27:41+00:00
---

# Middleware

- Introduction
- Defining Middleware
- Registering Middleware
   - Global Middleware
   - Assigning Middleware to Routes
   - Middleware Groups
   - Middleware Aliases
   - Sorting Middleware
- Middleware Parameters
- Terminable Middleware

## Introduction

Middleware provide a convenient mechanism for inspecting and filtering HTTP requests entering your application. For example, Laravel includes a middleware that verifies the user of your application is authenticated. If the user is not authenticated, the middleware will redirect the user to your application's login screen. However, if the user is authenticated, the middleware will allow the request to proceed further into the application.

Additional middleware can be written to perform a variety of tasks besides authentication. For example, a logging middleware might log all incoming requests to your application. A variety of middleware are included in Laravel, including middleware for authentication and CSRF protection; however, all user-defined middleware are typically located in your application's `app/Http/Middleware` directory.

## Defining Middleware

To create a new middleware, use the `make:middleware` Artisan command:

```
1php artisan make:middleware EnsureTokenIsValid

php artisan make:middleware EnsureTokenIsValid
```

This command will place a new `EnsureTokenIsValid` class within your `app/Http/Middleware` directory. In this middleware, we will only allow access to the route if the supplied `token` input matches a specified value. Otherwise, we will redirect the users back to the `/home` URI:

```
 1<?php
 2 
 3namespace App\Http\Middleware;
 4 
 5use Closure;
 6use Illuminate\Http\Request;
 7use Symfony\Component\HttpFoundation\Response;
 8 
 9class EnsureTokenIsValid
10{
11    /**
12     * Handle an incoming request.
13     *
14     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
15     */
16    public function handle(Request $request, Closure $next): Response
17    {
18        if ($request->input('token') !== 'my-secret-token') {
19            return redirect('/home');
20        }
21 
22        return $next($request);
23    }
24}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->input('token') !== 'my-secret-token') {
            return redirect('/home');
        }

        return $next($request);
    }
}
```

As you can see, if the given `token` does not match our secret token, the middleware will return an HTTP redirect to the client; otherwise, the request will be passed further into the application. To pass the request deeper into the application (allowing the middleware to "pass"), you should call the `$next` callback with the `$request`.

It's best to envision middleware as a series of "layers" HTTP requests must pass through before they hit your application. Each layer can examine the request and even reject it entirely.

 All middleware are resolved via the [service container](https://laravel.com/framework/docs/container), so you may type-hint any dependencies you need within a middleware's constructor.

#### Middleware and Responses

Of course, a middleware can perform tasks before or after passing the request deeper into the application. For example, the following middleware would perform some task **before** the request is handled by the application:

```
 1<?php
 2 
 3namespace App\Http\Middleware;
 4 
 5use Closure;
 6use Illuminate\Http\Request;
 7use Symfony\Component\HttpFoundation\Response;
 8 
 9class BeforeMiddleware
10{
11    public function handle(Request $request, Closure $next): Response
12    {
13        // Perform action
14 
15        return $next($request);
16    }
17}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BeforeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Perform action

        return $next($request);
    }
}
```

However, this middleware would perform its task **after** the request is handled by the application:

```
 1<?php
 2 
 3namespace App\Http\Middleware;
 4 
 5use Closure;
 6use Illuminate\Http\Request;
 7use Symfony\Component\HttpFoundation\Response;
 8 
 9class AfterMiddleware
10{
11    public function handle(Request $request, Closure $next): Response
12    {
13        $response = $next($request);
14 
15        // Perform action
16 
17        return $response;
18    }
19}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AfterMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Perform action

        return $response;
    }
}
```

## Registering Middleware

### Global Middleware

If you want a middleware to run during every HTTP request to your application, you may append it to the global middleware stack in your application's `bootstrap/app.php` file:

```
1use App\Http\Middleware\EnsureTokenIsValid;
2 
3->withMiddleware(function (Middleware $middleware): void {
4     $middleware->append(EnsureTokenIsValid::class);
5})

use App\Http\Middleware\EnsureTokenIsValid;

->withMiddleware(function (Middleware $middleware): void {
     $middleware->append(EnsureTokenIsValid::class);
})
```

The `$middleware` object provided to the `withMiddleware` closure is an instance of `Illuminate\Foundation\Configuration\Middleware` and is responsible for managing the middleware assigned to your application's routes. The `append` method adds the middleware to the end of the list of global middleware. If you would like to add a middleware to the beginning of the list, you should use the `prepend` method.

#### Manually Managing Laravel's Default Global Middleware

If you would like to manage Laravel's global middleware stack manually, you may provide Laravel's default stack of global middleware to the `use` method. Then, you may adjust the default middleware stack as necessary:

```
 1->withMiddleware(function (Middleware $middleware): void {
 2    $middleware->use([
 3        \Illuminate\Http\Middleware\ValidatePathEncoding::class,
 4        \Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks::class,
 5        // \Illuminate\Http\Middleware\TrustHosts::class,
 6        \Illuminate\Http\Middleware\TrustProxies::class,
 7        \Illuminate\Http\Middleware\HandleCors::class,
 8        \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
 9        \Illuminate\Http\Middleware\ValidatePostSize::class,
10        \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
11        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
12    ]);
13})

->withMiddleware(function (Middleware $middleware): void {
    $middleware->use([
        \Illuminate\Http\Middleware\ValidatePathEncoding::class,
        \Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks::class,
        // \Illuminate\Http\Middleware\TrustHosts::class,
        \Illuminate\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Http\Middleware\ValidatePostSize::class,
        \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ]);
})
```

### Assigning Middleware to Routes

If you would like to assign middleware to specific routes, you may invoke the `middleware` method when defining the route:

```
1use App\Http\Middleware\EnsureTokenIsValid;
2 
3Route::get('/profile', function () {
4    // ...
5})->middleware(EnsureTokenIsValid::class);

use App\Http\Middleware\EnsureTokenIsValid;

Route::get('/profile', function () {
    // ...
})->middleware(EnsureTokenIsValid::class);
```

You may assign multiple middleware to the route by passing an array of middleware names to the `middleware` method:

```
1Route::get('/', function () {
2    // ...
3})->middleware([First::class, Second::class]);

Route::get('/', function () {
    // ...
})->middleware([First::class, Second::class]);
```

#### Excluding Middleware

When assigning middleware to a group of routes, you may occasionally need to prevent the middleware from being applied to an individual route within the group. You may accomplish this using the `withoutMiddleware` method:

```
 1use App\Http\Middleware\EnsureTokenIsValid;
 2 
 3Route::middleware([EnsureTokenIsValid::class])->group(function () {
 4    Route::get('/', function () {
 5        // ...
 6    });
 7 
 8    Route::get('/profile', function () {
 9        // ...
10    })->withoutMiddleware([EnsureTokenIsValid::class]);
11});

use App\Http\Middleware\EnsureTokenIsValid;

Route::middleware([EnsureTokenIsValid::class])->group(function () {
    Route::get('/', function () {
        // ...
    });

    Route::get('/profile', function () {
        // ...
    })->withoutMiddleware([EnsureTokenIsValid::class]);
});
```

You may also exclude a given set of middleware from an entire [group](https://laravel.com/framework/docs/routing#route-groups) of route definitions:

```
1use App\Http\Middleware\EnsureTokenIsValid;
2 
3Route::withoutMiddleware([EnsureTokenIsValid::class])->group(function () {
4    Route::get('/profile', function () {
5        // ...
6    });
7});

use App\Http\Middleware\EnsureTokenIsValid;

Route::withoutMiddleware([EnsureTokenIsValid::class])->group(function () {
    Route::get('/profile', function () {
        // ...
    });
});
```

The `withoutMiddleware` method can only remove route middleware and does not apply to global middleware.

### Middleware Groups

Sometimes you may want to group several middleware under a single key to make them easier to assign to routes. You may accomplish this using the `appendToGroup` method within your application's `bootstrap/app.php` file:

```
 1use App\Http\Middleware\First;
 2use App\Http\Middleware\Second;
 3 
 4->withMiddleware(function (Middleware $middleware): void {
 5    $middleware->appendToGroup('group-name', [
 6        First::class,
 7        Second::class,
 8    ]);
 9 
10    $middleware->prependToGroup('group-name', [
11        First::class,
12        Second::class,
13    ]);
14})

use App\Http\Middleware\First;
use App\Http\Middleware\Second;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->appendToGroup('group-name', [
        First::class,
        Second::class,
    ]);

    $middleware->prependToGroup('group-name', [
        First::class,
        Second::class,
    ]);
})
```

Middleware groups may be assigned to routes and controller actions using the same syntax as individual middleware:

```
1Route::get('/', function () {
2    // ...
3})->middleware('group-name');
4 
5Route::middleware(['group-name'])->group(function () {
6    // ...
7});

Route::get('/', function () {
    // ...
})->middleware('group-name');

Route::middleware(['group-name'])->group(function () {
    // ...
});
```

#### Laravel's Default Middleware Groups

Laravel includes predefined `web` and `api` middleware groups that contain common middleware you may want to apply to your web and API routes. Remember, Laravel automatically applies these middleware groups to the corresponding `routes/web.php` and `routes/api.php` files:

| The `web` Middleware Group |
| --- |
| `Illuminate\Cookie\Middleware\EncryptCookies` |
| `Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse` |
| `Illuminate\Session\Middleware\StartSession` |
| `Illuminate\View\Middleware\ShareErrorsFromSession` |
| `Illuminate\Foundation\Http\Middleware\PreventRequestForgery` |
| `Illuminate\Routing\Middleware\SubstituteBindings` |

| The `api` Middleware Group |
| --- |
| `Illuminate\Routing\Middleware\SubstituteBindings` |

If you would like to append or prepend middleware to these groups, you may use the `web` and `api` methods within your application's `bootstrap/app.php` file. The `web` and `api` methods are convenient alternatives to the `appendToGroup` method:

```
 1use App\Http\Middleware\EnsureTokenIsValid;
 2use App\Http\Middleware\EnsureUserIsSubscribed;
 3 
 4->withMiddleware(function (Middleware $middleware): void {
 5    $middleware->web(append: [
 6        EnsureUserIsSubscribed::class,
 7    ]);
 8 
 9    $middleware->api(prepend: [
10        EnsureTokenIsValid::class,
11    ]);
12})

use App\Http\Middleware\EnsureTokenIsValid;
use App\Http\Middleware\EnsureUserIsSubscribed;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        EnsureUserIsSubscribed::class,
    ]);

    $middleware->api(prepend: [
        EnsureTokenIsValid::class,
    ]);
})
```

You may even replace one of Laravel's default middleware group entries with a custom middleware of your own:

```
1use App\Http\Middleware\StartCustomSession;
2use Illuminate\Session\Middleware\StartSession;
3 
4$middleware->web(replace: [
5    StartSession::class => StartCustomSession::class,
6]);

use App\Http\Middleware\StartCustomSession;
use Illuminate\Session\Middleware\StartSession;

$middleware->web(replace: [
    StartSession::class => StartCustomSession::class,
]);
```

Or, you may remove a middleware entirely:

```
1$middleware->web(remove: [
2    StartSession::class,
3]);

$middleware->web(remove: [
    StartSession::class,
]);
```

#### Manually Managing Laravel's Default Middleware Groups

If you would like to manually manage all of the middleware within Laravel's default `web` and `api` middleware groups, you may redefine the groups entirely. The example below will define the `web` and `api` middleware groups with their default middleware, allowing you to customize them as necessary:

```
 1->withMiddleware(function (Middleware $middleware): void {
 2    $middleware->group('web', [
 3        \Illuminate\Cookie\Middleware\EncryptCookies::class,
 4        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
 5        \Illuminate\Session\Middleware\StartSession::class,
 6        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
 7        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
 8        \Illuminate\Routing\Middleware\SubstituteBindings::class,
 9        // \Illuminate\Session\Middleware\AuthenticateSession::class,
10    ]);
11 
12    $middleware->group('api', [
13        // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
14        // 'throttle:api',
15        \Illuminate\Routing\Middleware\SubstituteBindings::class,
16    ]);
17})

->withMiddleware(function (Middleware $middleware): void {
    $middleware->group('web', [
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        // \Illuminate\Session\Middleware\AuthenticateSession::class,
    ]);

    $middleware->group('api', [
        // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        // 'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ]);
})
```

 By default, the `web` and `api` middleware groups are automatically applied to your application's corresponding `routes/web.php` and `routes/api.php` files by the `bootstrap/app.php` file.

### Middleware Aliases

You may assign aliases to middleware in your application's `bootstrap/app.php` file. Middleware aliases allow you to define a short alias for a given middleware class, which can be especially useful for middleware with long class names:

```
1use App\Http\Middleware\EnsureUserIsSubscribed;
2 
3->withMiddleware(function (Middleware $middleware): void {
4    $middleware->alias([
5        'subscribed' => EnsureUserIsSubscribed::class
6    ]);
7})

use App\Http\Middleware\EnsureUserIsSubscribed;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'subscribed' => EnsureUserIsSubscribed::class
    ]);
})
```

Once the middleware alias has been defined in your application's `bootstrap/app.php` file, you may use the alias when assigning the middleware to routes:

```
1Route::get('/profile', function () {
2    // ...
3})->middleware('subscribed');

Route::get('/profile', function () {
    // ...
})->middleware('subscribed');
```

For convenience, some of Laravel's built-in middleware are aliased by default. For example, the `auth` middleware is an alias for the `Illuminate\Auth\Middleware\Authenticate` middleware. Below is a list of the default middleware aliases:

| Alias | Middleware |
| --- | --- |
| `auth` | `Illuminate\Auth\Middleware\Authenticate` |
| `auth.basic` | `Illuminate\Auth\Middleware\AuthenticateWithBasicAuth` |
| `auth.session` | `Illuminate\Session\Middleware\AuthenticateSession` |
| `cache.headers` | `Illuminate\Http\Middleware\SetCacheHeaders` |
| `can` | `Illuminate\Auth\Middleware\Authorize` |
| `guest` | `Illuminate\Auth\Middleware\RedirectIfAuthenticated` |
| `password.confirm` | `Illuminate\Auth\Middleware\RequirePassword` |
| `precognitive` | `Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests` |
| `signed` | `Illuminate\Routing\Middleware\ValidateSignature` |
| `subscribed` | `\Spark\Http\Middleware\VerifyBillableIsSubscribed` |
| `throttle` | `Illuminate\Routing\Middleware\ThrottleRequests` or `Illuminate\Routing\Middleware\ThrottleRequestsWithRedis` |
| `verified` | `Illuminate\Auth\Middleware\EnsureEmailIsVerified` |

### Sorting Middleware

Rarely, you may need your middleware to execute in a specific order but not have control over their order when they are assigned to the route. In these situations, you may specify your middleware priority using the `priority` method in your application's `bootstrap/app.php` file:

```
 1->withMiddleware(function (Middleware $middleware): void {
 2    $middleware->priority([
 3        \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
 4        \Illuminate\Cookie\Middleware\EncryptCookies::class,
 5        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
 6        \Illuminate\Session\Middleware\StartSession::class,
 7        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
 8        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
 9        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
10        \Illuminate\Routing\Middleware\ThrottleRequests::class,
11        \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
12        \Illuminate\Routing\Middleware\SubstituteBindings::class,
13        \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
14        \Illuminate\Auth\Middleware\Authorize::class,
15    ]);
16})

->withMiddleware(function (Middleware $middleware): void {
    $middleware->priority([
        \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        \Illuminate\Routing\Middleware\ThrottleRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
        \Illuminate\Auth\Middleware\Authorize::class,
    ]);
})
```

If you would like to add middleware to the existing priority list without replacing it, you may use the `prependToPriorityList` or `appendToPriorityList` methods. The `prependToPriorityList` method inserts the given middleware before another middleware, while the `appendToPriorityList` method inserts it after another middleware:

```
 1->withMiddleware(function (Middleware $middleware): void {
 2    $middleware->prependToPriorityList(
 3        before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
 4        prepend: \App\Http\Middleware\EnsureTokenIsValid::class,
 5    );
 6 
 7    $middleware->appendToPriorityList(
 8        after: \Illuminate\Routing\Middleware\SubstituteBindings::class,
 9        append: \App\Http\Middleware\EnsureUserIsSubscribed::class,
10    );
11})

->withMiddleware(function (Middleware $middleware): void {
    $middleware->prependToPriorityList(
        before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
        prepend: \App\Http\Middleware\EnsureTokenIsValid::class,
    );

    $middleware->appendToPriorityList(
        after: \Illuminate\Routing\Middleware\SubstituteBindings::class,
        append: \App\Http\Middleware\EnsureUserIsSubscribed::class,
    );
})
```

The `before` and `after` arguments may also be an array of middleware classes.

## Middleware Parameters

Middleware can also receive additional parameters. For example, if your application needs to verify that the authenticated user has a given "role" before performing a given action, you could create an `EnsureUserHasRole` middleware that receives a role name as an additional argument.

Additional middleware parameters will be passed to the middleware after the `$next` argument:

```
 1<?php
 2 
 3namespace App\Http\Middleware;
 4 
 5use Closure;
 6use Illuminate\Http\Request;
 7use Symfony\Component\HttpFoundation\Response;
 8 
 9class EnsureUserHasRole
10{
11    /**
12     * Handle an incoming request.
13     *
14     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
15     */
16    public function handle(Request $request, Closure $next, string $role): Response
17    {
18        if (! $request->user()->hasRole($role)) {
19            // Redirect...
20        }
21 
22        return $next($request);
23    }
24}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user()->hasRole($role)) {
            // Redirect...
        }

        return $next($request);
    }
}
```

Middleware parameters may be specified when defining the route by separating the middleware name and parameters with a `:`:

```
1use App\Http\Middleware\EnsureUserHasRole;
2 
3Route::put('/post/{id}', function (string $id) {
4    // ...
5})->middleware(EnsureUserHasRole::class.':editor');

use App\Http\Middleware\EnsureUserHasRole;

Route::put('/post/{id}', function (string $id) {
    // ...
})->middleware(EnsureUserHasRole::class.':editor');
```

Multiple parameters may be delimited by commas:

```
1Route::put('/post/{id}', function (string $id) {
2    // ...
3})->middleware(EnsureUserHasRole::class.':editor,publisher');

Route::put('/post/{id}', function (string $id) {
    // ...
})->middleware(EnsureUserHasRole::class.':editor,publisher');
```

## Terminable Middleware

Sometimes a middleware may need to do some work after the HTTP response has been sent to the browser. If you define a `terminate` method on your middleware and your web server is using [FastCGI](https://www.php.net/manual/en/install.fpm.php), the `terminate` method will automatically be called after the response is sent to the browser:

```
 1<?php
 2 
 3namespace App\Http\Middleware;
 4 
 5use Closure;
 6use Illuminate\Http\Request;
 7use Symfony\Component\HttpFoundation\Response;
 8 
 9class TerminatingMiddleware
10{
11    /**
12     * Handle an incoming request.
13     *
14     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
15     */
16    public function handle(Request $request, Closure $next): Response
17    {
18        return $next($request);
19    }
20 
21    /**
22     * Handle tasks after the response has been sent to the browser.
23     */
24    public function terminate(Request $request, Response $response): void
25    {
26        // ...
27    }
28}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TerminatingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        // ...
    }
}
```

The `terminate` method should receive both the request and the response. Once you have defined a terminable middleware, you should add it to the list of routes or global middleware in your application's `bootstrap/app.php` file.

When calling the `terminate` method on your middleware, Laravel will resolve a fresh instance of the middleware from the [service container](https://laravel.com/framework/docs/container). If you would like to use the same middleware instance when the `handle` and `terminate` methods are called, register the middleware with the container using the container's `singleton` method. Typically this should be done in the `register` method of your `AppServiceProvider`:

```
1use App\Http\Middleware\TerminatingMiddleware;
2 
3/**
4 * Register any application services.
5 */
6public function register(): void
7{
8    $this->app->singleton(TerminatingMiddleware::class);
9}

use App\Http\Middleware\TerminatingMiddleware;

/**
 * Register any application services.
 */
public function register(): void
{
    $this->app->singleton(TerminatingMiddleware::class);
}
```
