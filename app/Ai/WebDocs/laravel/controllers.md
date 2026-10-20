---
source: laravel
page: controllers
url: https://laravel.com/framework/docs/13.x/controllers
fetched_at: 2026-10-20T16:27:42+00:00
---

# Controllers

- Introduction
- Writing Controllers
   - Basic Controllers
   - Single Action Controllers
- Controller Middleware
   - Middleware Attributes
   - Authorization Attributes
- Resource Controllers
   - Partial Resource Routes
   - Nested Resources
   - Naming Resource Routes
   - Naming Resource Route Parameters
   - Scoping Resource Routes
   - Localizing Resource URIs
   - Supplementing Resource Controllers
   - Singleton Resource Controllers
   - Middleware and Resource Controllers
- Dependency Injection and Controllers

## Introduction

Instead of defining all of your request handling logic as closures in your route files, you may wish to organize this behavior using "controller" classes. Controllers can group related request handling logic into a single class. For example, a `UserController` class might handle all incoming requests related to users, including showing, creating, updating, and deleting users. By default, controllers are stored in the `app/Http/Controllers` directory.

## Writing Controllers

### Basic Controllers

To quickly generate a new controller, you may run the `make:controller` Artisan command. By default, all of the controllers for your application are stored in the `app/Http/Controllers` directory:

```
1php artisan make:controller UserController

php artisan make:controller UserController
```

Let's take a look at an example of a basic controller. A controller may have any number of public methods which will respond to incoming HTTP requests:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use App\Models\User;
 6use Illuminate\View\View;
 7 
 8class UserController extends Controller
 9{
10    /**
11     * Show the profile for a given user.
12     */
13    public function show(string $id): View
14    {
15        return view('user.profile', [
16            'user' => User::findOrFail($id)
17        ]);
18    }
19}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Show the profile for a given user.
     */
    public function show(string $id): View
    {
        return view('user.profile', [
            'user' => User::findOrFail($id)
        ]);
    }
}
```

Once you have written a controller class and method, you may define a route to the controller method like so:

```
1use App\Http\Controllers\UserController;
2 
3Route::get('/user/{id}', [UserController::class, 'show']);

use App\Http\Controllers\UserController;

Route::get('/user/{id}', [UserController::class, 'show']);
```

When an incoming request matches the specified route URI, the `show` method on the `App\Http\Controllers\UserController` class will be invoked and the route parameters will be passed to the method.

 Controllers are not **required** to extend a base class. However, it is sometimes convenient to extend a base controller class that contains methods that should be shared across all of your controllers.

### Single Action Controllers

If a controller action is particularly complex, you might find it convenient to dedicate an entire controller class to that single action. To accomplish this, you may define a single `__invoke` method within the controller:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5class ProvisionServer extends Controller
 6{
 7    /**
 8     * Provision a new web server.
 9     */
10    public function __invoke()
11    {
12        // ...
13    }
14}

<?php

namespace App\Http\Controllers;

class ProvisionServer extends Controller
{
    /**
     * Provision a new web server.
     */
    public function __invoke()
    {
        // ...
    }
}
```

When registering routes for single action controllers, you do not need to specify a controller method. Instead, you may simply pass the name of the controller to the router:

```
1use App\Http\Controllers\ProvisionServer;
2 
3Route::post('/server', ProvisionServer::class);

use App\Http\Controllers\ProvisionServer;

Route::post('/server', ProvisionServer::class);
```

You may generate an invokable controller by using the `--invokable` option of the `make:controller` Artisan command:

```
1php artisan make:controller ProvisionServer --invokable

php artisan make:controller ProvisionServer --invokable
```

 Controller stubs may be customized using [stub publishing](https://laravel.com/framework/docs/artisan#stub-customization).

## Controller Middleware

[Middleware](https://laravel.com/framework/docs/middleware) may be assigned to the controller's routes in your route files:

```
1Route::get('/profile', [UserController::class, 'show'])->middleware('auth');

Route::get('/profile', [UserController::class, 'show'])->middleware('auth');
```

Or, you may find it convenient to specify middleware within your controller class. To do so, your controller should implement the `HasMiddleware` interface, which dictates that the controller should have a static `middleware` method. From this method, you may return an array of middleware that should be applied to the controller's actions:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use Illuminate\Routing\Controllers\HasMiddleware;
 6use Illuminate\Routing\Controllers\Middleware;
 7 
 8class UserController implements HasMiddleware
 9{
10    /**
11     * Get the middleware that should be assigned to the controller.
12     */
13    public static function middleware(): array
14    {
15        return [
16            'auth',
17            new Middleware('log', only: ['index']),
18            new Middleware('subscribed', except: ['store']),
19        ];
20    }
21 
22    // ...
23}

<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UserController implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            'auth',
            new Middleware('log', only: ['index']),
            new Middleware('subscribed', except: ['store']),
        ];
    }

    // ...
}
```

You may also define controller middleware as closures, which provides a convenient way to define an inline middleware without writing an entire middleware class:

```
 1use Closure;
 2use Illuminate\Http\Request;
 3 
 4/**
 5 * Get the middleware that should be assigned to the controller.
 6 */
 7public static function middleware(): array
 8{
 9    return [
10        function (Request $request, Closure $next) {
11            return $next($request);
12        },
13    ];
14}

use Closure;
use Illuminate\Http\Request;

/**
 * Get the middleware that should be assigned to the controller.
 */
public static function middleware(): array
{
    return [
        function (Request $request, Closure $next) {
            return $next($request);
        },
    ];
}
```

### Middleware Attributes

You may also assign middleware to controllers using PHP attributes:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use Illuminate\Routing\Attributes\Controllers\Middleware;
 6 
 7#[Middleware('auth')]
 8#[Middleware('log', only: ['index'])]
 9#[Middleware('subscribed', except: ['store'])]
10class UserController
11{
12    // ...
13}

<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth')]
#[Middleware('log', only: ['index'])]
#[Middleware('subscribed', except: ['store'])]
class UserController
{
    // ...
}
```

You may place middleware attributes on individual controller methods as well. Middleware assigned to methods will be merged with middleware assigned at the class level:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use Closure;
 6use Illuminate\Http\Request;
 7use Illuminate\Routing\Attributes\Controllers\Middleware;
 8 
 9#[Middleware('auth')]
10class UserController
11{
12    #[Middleware('log')]
13    #[Middleware('subscribed')]
14    public function index()
15    {
16        // ...
17    }
18 
19    #[Middleware(static function (Request $request, Closure $next) {
20        // ...
21 
22        return $next($request);
23    })]
24    public function store()
25    {
26        // ...
27    }
28}

<?php

namespace App\Http\Controllers;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth')]
class UserController
{
    #[Middleware('log')]
    #[Middleware('subscribed')]
    public function index()
    {
        // ...
    }

    #[Middleware(static function (Request $request, Closure $next) {
        // ...

        return $next($request);
    })]
    public function store()
    {
        // ...
    }
}
```

To exclude middleware from a controller or individual controller methods, use the `WithoutMiddleware` attribute. You may use the `only` and `except` arguments to limit a class-level attribute to particular controller methods:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use App\Http\Middleware\EnsureTokenIsValid;
 6use Illuminate\Routing\Attributes\Controllers\WithoutMiddleware;
 7 
 8#[WithoutMiddleware('subscribed', except: ['index'])]
 9class UserController
10{
11    #[WithoutMiddleware(EnsureTokenIsValid::class)]
12    public function index()
13    {
14        // ...
15    }
16 
17    public function show()
18    {
19        // ...
20    }
21}

<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureTokenIsValid;
use Illuminate\Routing\Attributes\Controllers\WithoutMiddleware;

#[WithoutMiddleware('subscribed', except: ['index'])]
class UserController
{
    #[WithoutMiddleware(EnsureTokenIsValid::class)]
    public function index()
    {
        // ...
    }

    public function show()
    {
        // ...
    }
}
```

Class-level `WithoutMiddleware` attributes are inherited by child controllers. The attribute can only remove route middleware and does not apply to [global middleware](https://laravel.com/framework/docs/middleware#global-middleware).

### Authorization Attributes

If you are authorizing controller actions via policies, you may use the `Authorize` attribute as a convenient shortcut for the `can` middleware:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use App\Models\Comment;
 6use App\Models\Post;
 7use Illuminate\Routing\Attributes\Controllers\Authorize;
 8 
 9class CommentController
10{
11    #[Authorize('create', [Comment::class, 'post'])]
12    public function store(Post $post)
13    {
14        // ...
15    }
16 
17    #[Authorize('delete', 'comment')]
18    public function destroy(Comment $comment)
19    {
20        // ...
21    }
22}

<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class CommentController
{
    #[Authorize('create', [Comment::class, 'post'])]
    public function store(Post $post)
    {
        // ...
    }

    #[Authorize('delete', 'comment')]
    public function destroy(Comment $comment)
    {
        // ...
    }
}
```

The first argument is the ability you wish to authorize. The second argument is the model class, route parameter, or parameters that should be passed to the policy.

## Resource Controllers

If you think of each Eloquent model in your application as a "resource", it is typical to perform the same sets of actions against each resource in your application. For example, imagine your application contains a `Photo` model and a `Movie` model. It is likely that users can create, read, update, or delete these resources.

Because of this common use case, Laravel resource routing assigns the typical create, read, update, and delete ("CRUD") routes to a controller with a single line of code. To get started, we can use the `make:controller` Artisan command's `--resource` option to quickly create a controller to handle these actions:

```
1php artisan make:controller PhotoController --resource

php artisan make:controller PhotoController --resource
```

This command will generate a controller at `app/Http/Controllers/PhotoController.php`. The controller will contain a method for each of the available resource operations. Next, you may register a resource route that points to the controller:

```
1use App\Http\Controllers\PhotoController;
2 
3Route::resource('photos', PhotoController::class);

use App\Http\Controllers\PhotoController;

Route::resource('photos', PhotoController::class);
```

This single route declaration creates multiple routes to handle a variety of actions on the resource. The generated controller will already have methods stubbed for each of these actions. Remember, you can always get a quick overview of your application's routes by running the `route:list` Artisan command.

You may even register many resource controllers at once by passing an array to the `resources` method:

```
1Route::resources([
2    'photos' => PhotoController::class,
3    'posts' => PostController::class,
4]);

Route::resources([
    'photos' => PhotoController::class,
    'posts' => PostController::class,
]);
```

The `softDeletableResources` method registers many resources controllers that all use the `withTrashed` method:

```
1Route::softDeletableResources([
2    'photos' => PhotoController::class,
3    'posts' => PostController::class,
4]);

Route::softDeletableResources([
    'photos' => PhotoController::class,
    'posts' => PostController::class,
]);
```

#### Actions Handled by Resource Controllers

| Verb | URI | Action | Route Name |
| --- | --- | --- | --- |
| GET | `/photos` | index | photos.index |
| GET | `/photos/create` | create | photos.create |
| POST | `/photos` | store | photos.store |
| GET | `/photos/{photo}` | show | photos.show |
| GET | `/photos/{photo}/edit` | edit | photos.edit |
| PUT/PATCH | `/photos/{photo}` | update | photos.update |
| DELETE | `/photos/{photo}` | destroy | photos.destroy |

#### Customizing Missing Model Behavior

Typically, a 404 HTTP response will be generated if an implicitly bound resource model is not found. However, you may customize this behavior by calling the `missing` method when defining your resource route. The `missing` method accepts a closure that will be invoked if an implicitly bound model cannot be found for any of the resource's routes:

```
1use App\Http\Controllers\PhotoController;
2use Illuminate\Http\Request;
3use Illuminate\Support\Facades\Redirect;
4 
5Route::resource('photos', PhotoController::class)
6    ->missing(function (Request $request) {
7        return Redirect::route('photos.index');
8    });

use App\Http\Controllers\PhotoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

Route::resource('photos', PhotoController::class)
    ->missing(function (Request $request) {
        return Redirect::route('photos.index');
    });
```

#### Soft Deleted Models

Typically, implicit model binding will not retrieve models that have been [soft deleted](https://laravel.com/framework/docs/eloquent#soft-deleting), and will instead return a 404 HTTP response. However, you can instruct the framework to allow soft deleted models by invoking the `withTrashed` method when defining your resource route:

```
1use App\Http\Controllers\PhotoController;
2 
3Route::resource('photos', PhotoController::class)->withTrashed();

use App\Http\Controllers\PhotoController;

Route::resource('photos', PhotoController::class)->withTrashed();
```

Calling `withTrashed` with no arguments will allow soft deleted models for the `show`, `edit`, and `update` resource routes. You may specify a subset of these routes by passing an array to the `withTrashed` method:

```
1Route::resource('photos', PhotoController::class)->withTrashed(['show']);

Route::resource('photos', PhotoController::class)->withTrashed(['show']);
```

#### Specifying the Resource Model

If you are using [route model binding](https://laravel.com/framework/docs/routing#route-model-binding) and would like the resource controller's methods to type-hint a model instance, you may use the `--model` option when generating the controller:

```
1php artisan make:controller PhotoController --model=Photo --resource

php artisan make:controller PhotoController --model=Photo --resource
```

#### Generating Form Requests

You may provide the `--requests` option when generating a resource controller to instruct Artisan to generate [form request classes](https://laravel.com/framework/docs/validation#form-request-validation) for the controller's storage and update methods:

```
1php artisan make:controller PhotoController --model=Photo --resource --requests

php artisan make:controller PhotoController --model=Photo --resource --requests
```

### Partial Resource Routes

When declaring a resource route, you may specify a subset of actions the controller should handle instead of the full set of default actions:

```
1use App\Http\Controllers\PhotoController;
2 
3Route::resource('photos', PhotoController::class)->only([
4    'index', 'show'
5]);
6 
7Route::resource('photos', PhotoController::class)->except([
8    'create', 'store', 'update', 'destroy'
9]);

use App\Http\Controllers\PhotoController;

Route::resource('photos', PhotoController::class)->only([
    'index', 'show'
]);

Route::resource('photos', PhotoController::class)->except([
    'create', 'store', 'update', 'destroy'
]);
```

#### API Resource Routes

When declaring resource routes that will be consumed by APIs, you will commonly want to exclude routes that present HTML templates such as `create` and `edit`. For convenience, you may use the `apiResource` method to automatically exclude these two routes:

```
1use App\Http\Controllers\PhotoController;
2 
3Route::apiResource('photos', PhotoController::class);

use App\Http\Controllers\PhotoController;

Route::apiResource('photos', PhotoController::class);
```

You may register many API resource controllers at once by passing an array to the `apiResources` method:

```
1use App\Http\Controllers\PhotoController;
2use App\Http\Controllers\PostController;
3 
4Route::apiResources([
5    'photos' => PhotoController::class,
6    'posts' => PostController::class,
7]);

use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PostController;

Route::apiResources([
    'photos' => PhotoController::class,
    'posts' => PostController::class,
]);
```

To quickly generate an API resource controller that does not include the `create` or `edit` methods, use the `--api` switch when executing the `make:controller` command:

```
1php artisan make:controller PhotoController --api

php artisan make:controller PhotoController --api
```

### Nested Resources

Sometimes you may need to define routes to a nested resource. For example, a photo resource may have multiple comments that may be attached to the photo. To nest the resource controllers, you may use "dot" notation in your route declaration:

```
1use App\Http\Controllers\PhotoCommentController;
2 
3Route::resource('photos.comments', PhotoCommentController::class);

use App\Http\Controllers\PhotoCommentController;

Route::resource('photos.comments', PhotoCommentController::class);
```

This route will register a nested resource that may be accessed with URIs like the following:

```
1/photos/{photo}/comments/{comment}

/photos/{photo}/comments/{comment}
```

#### Scoping Nested Resources

Laravel's [implicit model binding](https://laravel.com/framework/docs/routing#implicit-model-binding-scoping) feature can automatically scope nested bindings such that the resolved child model is confirmed to belong to the parent model. By using the `scoped` method when defining your nested resource, you may enable automatic scoping as well as instruct Laravel which field the child resource should be retrieved by. For more information on how to accomplish this, please see the documentation on scoping resource routes.

#### Shallow Nesting

Often, it is not entirely necessary to have both the parent and the child IDs within a URI since the child ID is already a unique identifier. When using unique identifiers such as auto-incrementing primary keys to identify your models in URI segments, you may choose to use "shallow nesting":

```
1use App\Http\Controllers\CommentController;
2 
3Route::resource('photos.comments', CommentController::class)->shallow();

use App\Http\Controllers\CommentController;

Route::resource('photos.comments', CommentController::class)->shallow();
```

This route definition will define the following routes:

| Verb | URI | Action | Route Name |
| --- | --- | --- | --- |
| GET | `/photos/{photo}/comments` | index | photos.comments.index |
| GET | `/photos/{photo}/comments/create` | create | photos.comments.create |
| POST | `/photos/{photo}/comments` | store | photos.comments.store |
| GET | `/comments/{comment}` | show | comments.show |
| GET | `/comments/{comment}/edit` | edit | comments.edit |
| PUT/PATCH | `/comments/{comment}` | update | comments.update |
| DELETE | `/comments/{comment}` | destroy | comments.destroy |

### Naming Resource Routes

By default, all resource controller actions have a route name; however, you can override these names by passing a `names` array with your desired route names:

```
1use App\Http\Controllers\PhotoController;
2 
3Route::resource('photos', PhotoController::class)->names([
4    'create' => 'photos.build'
5]);

use App\Http\Controllers\PhotoController;

Route::resource('photos', PhotoController::class)->names([
    'create' => 'photos.build'
]);
```

### Naming Resource Route Parameters

By default, `Route::resource` will create the route parameters for your resource routes based on the "singularized" version of the resource name. You can easily override this on a per resource basis using the `parameters` method. The array passed into the `parameters` method should be an associative array of resource names and parameter names:

```
1use App\Http\Controllers\AdminUserController;
2 
3Route::resource('users', AdminUserController::class)->parameters([
4    'users' => 'admin_user'
5]);

use App\Http\Controllers\AdminUserController;

Route::resource('users', AdminUserController::class)->parameters([
    'users' => 'admin_user'
]);
```

The example above generates the following URI for the resource's `show` route:

```
1/users/{admin_user}

/users/{admin_user}
```

### Scoping Resource Routes

Laravel's [scoped implicit model binding](https://laravel.com/framework/docs/routing#implicit-model-binding-scoping) feature can automatically scope nested bindings such that the resolved child model is confirmed to belong to the parent model. By using the `scoped` method when defining your nested resource, you may enable automatic scoping as well as instruct Laravel which field the child resource should be retrieved by:

```
1use App\Http\Controllers\PhotoCommentController;
2 
3Route::resource('photos.comments', PhotoCommentController::class)->scoped([
4    'comment' => 'slug',
5]);

use App\Http\Controllers\PhotoCommentController;

Route::resource('photos.comments', PhotoCommentController::class)->scoped([
    'comment' => 'slug',
]);
```

This route will register a scoped nested resource that may be accessed with URIs like the following:

```
1/photos/{photo}/comments/{comment:slug}

/photos/{photo}/comments/{comment:slug}
```

When using a custom keyed implicit binding as a nested route parameter, Laravel will automatically scope the query to retrieve the nested model by its parent using conventions to guess the relationship name on the parent. In this case, it will be assumed that the `Photo` model has a relationship named `comments` (the plural of the route parameter name) which can be used to retrieve the `Comment` model.

### Localizing Resource URIs

By default, `Route::resource` will create resource URIs using English verbs and plural rules. If you need to localize the `create` and `edit` action verbs, you may use the `Route::resourceVerbs` method. This may be done at the beginning of the `boot` method within your application's `App\Providers\AppServiceProvider`:

```
 1/**
 2 * Bootstrap any application services.
 3 */
 4public function boot(): void
 5{
 6    Route::resourceVerbs([
 7        'create' => 'crear',
 8        'edit' => 'editar',
 9    ]);
10}

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Route::resourceVerbs([
        'create' => 'crear',
        'edit' => 'editar',
    ]);
}
```

Laravel's pluralizer supports [several different languages which you may configure based on your needs](https://laravel.com/framework/docs/localization#pluralization-language). Once the verbs and pluralization language have been customized, a resource route registration such as `Route::resource('publicacion', PublicacionController::class)` will produce the following URIs:

```
1/publicacion/crear
2
3/publicacion/{publicacion}/editar

/publicacion/crear

/publicacion/{publicacion}/editar
```

### Supplementing Resource Controllers

If you need to add additional routes to a resource controller beyond the default set of resource routes, you should define those routes before your call to the `Route::resource` method; otherwise, the routes defined by the `resource` method may unintentionally take precedence over your supplemental routes:

```
1use App\Http\Controllers\PhotoController;
2 
3Route::get('/photos/popular', [PhotoController::class, 'popular']);
4Route::resource('photos', PhotoController::class);

use App\Http\Controllers\PhotoController;

Route::get('/photos/popular', [PhotoController::class, 'popular']);
Route::resource('photos', PhotoController::class);
```

 Remember to keep your controllers focused. If you find yourself routinely needing methods outside of the typical set of resource actions, consider splitting your controller into two, smaller controllers.

### Singleton Resource Controllers

Sometimes, your application will have resources that may only have a single instance. For example, a user's "profile" can be edited or updated, but a user may not have more than one "profile". Likewise, an image may have a single "thumbnail". These resources are called "singleton resources", meaning one and only one instance of the resource may exist. In these scenarios, you may register a "singleton" resource controller:

```
1use App\Http\Controllers\ProfileController;
2use Illuminate\Support\Facades\Route;
3 
4Route::singleton('profile', ProfileController::class);

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::singleton('profile', ProfileController::class);
```

The singleton resource definition above will register the following routes. As you can see, "creation" routes are not registered for singleton resources, and the registered routes do not accept an identifier since only one instance of the resource may exist:

| Verb | URI | Action | Route Name |
| --- | --- | --- | --- |
| GET | `/profile` | show | profile.show |
| GET | `/profile/edit` | edit | profile.edit |
| PUT/PATCH | `/profile` | update | profile.update |

Singleton resources may also be nested within a standard resource:

```
1Route::singleton('photos.thumbnail', ThumbnailController::class);

Route::singleton('photos.thumbnail', ThumbnailController::class);
```

In this example, the `photos` resource would receive all of the standard resource routes; however, the `thumbnail` resource would be a singleton resource with the following routes:

| Verb | URI | Action | Route Name |
| --- | --- | --- | --- |
| GET | `/photos/{photo}/thumbnail` | show | photos.thumbnail.show |
| GET | `/photos/{photo}/thumbnail/edit` | edit | photos.thumbnail.edit |
| PUT/PATCH | `/photos/{photo}/thumbnail` | update | photos.thumbnail.update |

#### Creatable Singleton Resources

Occasionally, you may want to define creation and storage routes for a singleton resource. To accomplish this, you may invoke the `creatable` method when registering the singleton resource route:

```
1Route::singleton('photos.thumbnail', ThumbnailController::class)->creatable();

Route::singleton('photos.thumbnail', ThumbnailController::class)->creatable();
```

In this example, the following routes will be registered. As you can see, a `DELETE` route will also be registered for creatable singleton resources:

| Verb | URI | Action | Route Name |
| --- | --- | --- | --- |
| GET | `/photos/{photo}/thumbnail/create` | create | photos.thumbnail.create |
| POST | `/photos/{photo}/thumbnail` | store | photos.thumbnail.store |
| GET | `/photos/{photo}/thumbnail` | show | photos.thumbnail.show |
| GET | `/photos/{photo}/thumbnail/edit` | edit | photos.thumbnail.edit |
| PUT/PATCH | `/photos/{photo}/thumbnail` | update | photos.thumbnail.update |
| DELETE | `/photos/{photo}/thumbnail` | destroy | photos.thumbnail.destroy |

If you would like Laravel to register the `DELETE` route for a singleton resource but not register the creation or storage routes, you may utilize the `destroyable` method:

```
1Route::singleton(...)->destroyable();

Route::singleton(...)->destroyable();
```

#### API Singleton Resources

The `apiSingleton` method may be used to register a singleton resource that will be manipulated via an API, thus rendering the `create` and `edit` routes unnecessary:

```
1Route::apiSingleton('profile', ProfileController::class);

Route::apiSingleton('profile', ProfileController::class);
```

Of course, API singleton resources may also be `creatable`, which will register `store` and `destroy` routes for the resource:

```
1Route::apiSingleton('photos.thumbnail', ProfileController::class)->creatable();

Route::apiSingleton('photos.thumbnail', ProfileController::class)->creatable();
```

### Middleware and Resource Controllers

Laravel allows you to assign middleware to all, or only specific, methods of resource routes using the `middleware`, `middlewareFor`, and `withoutMiddlewareFor` methods. These methods provide fine-grained control over which middleware is applied to each resource action.

#### Applying Middleware to all Methods

You may use the `middleware` method to assign middleware to all routes generated by a resource or singleton resource route:

```
1Route::resource('users', UserController::class)
2    ->middleware(['auth', 'verified']);
3 
4Route::singleton('profile', ProfileController::class)
5    ->middleware('auth');

Route::resource('users', UserController::class)
    ->middleware(['auth', 'verified']);

Route::singleton('profile', ProfileController::class)
    ->middleware('auth');
```

#### Applying Middleware to Specific Methods

You may use the `middlewareFor` method to assign middleware to one or more specific methods of a given resource controller:

```
 1Route::resource('users', UserController::class)
 2    ->middlewareFor('show', 'auth');
 3 
 4Route::apiResource('users', UserController::class)
 5    ->middlewareFor(['show', 'update'], 'auth');
 6 
 7Route::resource('users', UserController::class)
 8    ->middlewareFor('show', 'auth')
 9    ->middlewareFor('update', 'auth');
10 
11Route::apiResource('users', UserController::class)
12    ->middlewareFor(['show', 'update'], ['auth', 'verified']);

Route::resource('users', UserController::class)
    ->middlewareFor('show', 'auth');

Route::apiResource('users', UserController::class)
    ->middlewareFor(['show', 'update'], 'auth');

Route::resource('users', UserController::class)
    ->middlewareFor('show', 'auth')
    ->middlewareFor('update', 'auth');

Route::apiResource('users', UserController::class)
    ->middlewareFor(['show', 'update'], ['auth', 'verified']);
```

The `middlewareFor` method may also be used in conjunction with singleton and API singleton resource controllers:

```
1Route::singleton('profile', ProfileController::class)
2    ->middlewareFor('show', 'auth');
3 
4Route::apiSingleton('profile', ProfileController::class)
5    ->middlewareFor(['show', 'update'], 'auth');

Route::singleton('profile', ProfileController::class)
    ->middlewareFor('show', 'auth');

Route::apiSingleton('profile', ProfileController::class)
    ->middlewareFor(['show', 'update'], 'auth');
```

#### Excluding Middleware from Specific Methods

You may use the `withoutMiddlewareFor` method to exclude middleware from specific methods of a resource controller:

```
1Route::middleware(['auth', 'verified', 'subscribed'])->group(function () {
2    Route::resource('users', UserController::class)
3        ->withoutMiddlewareFor('index', ['auth', 'verified'])
4        ->withoutMiddlewareFor(['create', 'store'], 'verified')
5        ->withoutMiddlewareFor('destroy', 'subscribed');
6});

Route::middleware(['auth', 'verified', 'subscribed'])->group(function () {
    Route::resource('users', UserController::class)
        ->withoutMiddlewareFor('index', ['auth', 'verified'])
        ->withoutMiddlewareFor(['create', 'store'], 'verified')
        ->withoutMiddlewareFor('destroy', 'subscribed');
});
```

## Dependency Injection and Controllers

#### Constructor Injection

The Laravel [service container](https://laravel.com/framework/docs/container) is used to resolve all Laravel controllers. As a result, you are able to type-hint any dependencies your controller may need in its constructor. The declared dependencies will automatically be resolved and injected into the controller instance:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use App\Repositories\UserRepository;
 6 
 7class UserController extends Controller
 8{
 9    /**
10     * Create a new controller instance.
11     */
12    public function __construct(
13        protected UserRepository $users,
14    ) {}
15}

<?php

namespace App\Http\Controllers;

use App\Repositories\UserRepository;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected UserRepository $users,
    ) {}
}
```

#### Method Injection

In addition to constructor injection, you may also type-hint dependencies on your controller's methods. A common use-case for method injection is injecting the `Illuminate\Http\Request` instance into your controller methods:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use Illuminate\Http\RedirectResponse;
 6use Illuminate\Http\Request;
 7 
 8class UserController extends Controller
 9{
10    /**
11     * Store a new user.
12     */
13    public function store(Request $request): RedirectResponse
14    {
15        $name = $request->name;
16 
17        // Store the user...
18 
19        return redirect('/users');
20    }
21}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Store a new user.
     */
    public function store(Request $request): RedirectResponse
    {
        $name = $request->name;

        // Store the user...

        return redirect('/users');
    }
}
```

If your controller method is also expecting input from a route parameter, list your route arguments after your other dependencies. For example, if your route is defined like so:

```
1use App\Http\Controllers\UserController;
2 
3Route::put('/user/{id}', [UserController::class, 'update']);

use App\Http\Controllers\UserController;

Route::put('/user/{id}', [UserController::class, 'update']);
```

You may still type-hint the `Illuminate\Http\Request` and access your `id` parameter by defining your controller method as follows:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use Illuminate\Http\RedirectResponse;
 6use Illuminate\Http\Request;
 7 
 8class UserController extends Controller
 9{
10    /**
11     * Update the given user.
12     */
13    public function update(Request $request, string $id): RedirectResponse
14    {
15        // Update the user...
16 
17        return redirect('/users');
18    }
19}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Update the given user.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        // Update the user...

        return redirect('/users');
    }
}
```
