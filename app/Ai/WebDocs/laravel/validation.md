---
source: laravel
page: validation
url: https://laravel.com/framework/docs/13.x/validation
fetched_at: 2026-10-20T16:27:44+00:00
---

# Validation

- Introduction
- Validation Quickstart
   - Defining the Routes
   - Creating the Controller
   - Writing the Validation Logic
   - Displaying the Validation Errors
   - Repopulating Forms
   - A Note on Optional Fields
   - Validation Error Response Format
- Form Request Validation
   - Creating Form Requests
   - Authorizing Form Requests
   - Customizing the Error Messages
   - Preparing Input for Validation
- Manually Creating Validators
   - Automatic Redirection
   - Named Error Bags
   - Customizing the Error Messages
   - Performing Additional Validation
- Working With Validated Input
- Working With Error Messages
   - Specifying Custom Messages in Language Files
   - Specifying Attributes in Language Files
   - Specifying Values in Language Files
- Available Validation Rules
   - Rule Builders
- Conditionally Adding Rules
- Validating Arrays
   - Validating Nested Array Input
   - Error Message Indexes and Positions
- Validating Files
- Validating Passwords
- Custom Validation Rules
   - Using Rule Objects
   - Using Closures
   - Implicit Rules

## Introduction

Laravel provides several different approaches to validate your application's incoming data. It is most common to use the `validate` method available on all incoming HTTP requests. However, we will discuss other approaches to validation as well.

Laravel includes a wide variety of convenient validation rules that you may apply to data, even providing the ability to validate if values are unique in a given database table. We'll cover each of these validation rules in detail so that you are familiar with all of Laravel's validation features.

## Validation Quickstart

To learn about Laravel's powerful validation features, let's look at a complete example of validating a form and displaying the error messages back to the user. By reading this high-level overview, you'll be able to gain a good general understanding of how to validate incoming request data using Laravel:

### Defining the Routes

First, let's assume we have the following routes defined in our `routes/web.php` file:

```
1use App\Http\Controllers\PostController;
2 
3Route::get('/post/create', [PostController::class, 'create']);
4Route::post('/post', [PostController::class, 'store']);

use App\Http\Controllers\PostController;

Route::get('/post/create', [PostController::class, 'create']);
Route::post('/post', [PostController::class, 'store']);
```

The `GET` route will display a form for the user to create a new blog post, while the `POST` route will store the new blog post in the database.

### Creating the Controller

Next, let's take a look at a simple controller that handles incoming requests to these routes. We'll leave the `store` method empty for now:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use Illuminate\Http\RedirectResponse;
 6use Illuminate\Http\Request;
 7use Illuminate\View\View;
 8 
 9class PostController extends Controller
10{
11    /**
12     * Show the form to create a new blog post.
13     */
14    public function create(): View
15    {
16        return view('post.create');
17    }
18 
19    /**
20     * Store a new blog post.
21     */
22    public function store(Request $request): RedirectResponse
23    {
24        // Validate and store the blog post...
25 
26        $post = /** ... */
27 
28        return to_route('post.show', ['post' => $post->id]);
29    }
30}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    /**
     * Show the form to create a new blog post.
     */
    public function create(): View
    {
        return view('post.create');
    }

    /**
     * Store a new blog post.
     */
    public function store(Request $request): RedirectResponse
    {
        // Validate and store the blog post...

        $post = /** ... */

        return to_route('post.show', ['post' => $post->id]);
    }
}
```

### Writing the Validation Logic

Now we are ready to fill in our `store` method with the logic to validate the new blog post. To do this, we will use the `validate` method provided by the `Illuminate\Http\Request` object. If the validation rules pass, your code will keep executing normally; however, if validation fails, an `Illuminate\Validation\ValidationException` exception will be thrown and the proper error response will automatically be sent back to the user.

If validation fails during a traditional HTTP request, a redirect response to the previous URL will be generated. If the incoming request is an XHR request, a JSON response containing the validation error messages will be returned.

To get a better understanding of the `validate` method, let's jump back into the `store` method:

```
 1/**
 2 * Store a new blog post.
 3 */
 4public function store(Request $request): RedirectResponse
 5{
 6    $validated = $request->validate([
 7        'title' => ['required', 'unique:posts', 'max:255'],
 8        'body' => ['required'],
 9    ]);
10 
11    // The blog post is valid...
12 
13    return redirect('/posts');
14}

/**
 * Store a new blog post.
 */
public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'title' => ['required', 'unique:posts', 'max:255'],
        'body' => ['required'],
    ]);

    // The blog post is valid...

    return redirect('/posts');
}
```

As you can see, the validation rules are passed into the `validate` method. Don't worry - all available validation rules are documented. Again, if the validation fails, the proper response will automatically be generated. If the validation passes, our controller will continue executing normally.

In addition, you may use the `validateWithBag` method to validate a request and store any error messages within a named error bag:

```
1$validated = $request->validateWithBag('post', [
2    'title' => ['required', 'unique:posts', 'max:255'],
3    'body' => ['required'],
4]);

$validated = $request->validateWithBag('post', [
    'title' => ['required', 'unique:posts', 'max:255'],
    'body' => ['required'],
]);
```

#### Stopping on First Validation Failure

Sometimes you may wish to stop running validation rules on an attribute after the first validation failure. To do so, assign the `bail` rule to the attribute:

```
1$request->validate([
2    'title' => ['bail', 'required', 'unique:posts', 'max:255'],
3    'body' => ['required'],
4]);

$request->validate([
    'title' => ['bail', 'required', 'unique:posts', 'max:255'],
    'body' => ['required'],
]);
```

In this example, if the `unique` rule on the `title` attribute fails, the `max` rule will not be checked. Rules will be validated in the order they are assigned.

#### A Note on Nested Attributes

If the incoming HTTP request contains "nested" field data, you may specify these fields in your validation rules using "dot" syntax:

```
1$request->validate([
2    'title' => ['required', 'unique:posts', 'max:255'],
3    'author.name' => ['required'],
4    'author.description' => ['required'],
5]);

$request->validate([
    'title' => ['required', 'unique:posts', 'max:255'],
    'author.name' => ['required'],
    'author.description' => ['required'],
]);
```

On the other hand, if your field name contains a literal period, you can explicitly prevent this from being interpreted as "dot" syntax by escaping the period with a backslash:

```
1$request->validate([
2    'title' => ['required', 'unique:posts', 'max:255'],
3    'v1\.0' => ['required'],
4]);

$request->validate([
    'title' => ['required', 'unique:posts', 'max:255'],
    'v1\.0' => ['required'],
]);
```

### Displaying the Validation Errors

So, what if the incoming request fields do not pass the given validation rules? As mentioned previously, Laravel will automatically redirect the user back to their previous location. In addition, all of the validation errors and [request input](https://laravel.com/framework/docs/requests#retrieving-old-input) will automatically be [flashed to the session](https://laravel.com/framework/docs/session#flash-data).

An `$errors` variable is shared with all of your application's views by the `Illuminate\View\Middleware\ShareErrorsFromSession` middleware, which is provided by the `web` middleware group. When this middleware is applied an `$errors` variable will always be available in your views, allowing you to conveniently assume the `$errors` variable is always defined and can be safely used. The `$errors` variable will be an instance of `Illuminate\Support\MessageBag`. For more information on working with this object, check out its documentation.

So, in our example, the user will be redirected to our controller's `create` method when validation fails, allowing us to display the error messages in the view:

```
 1<!-- /resources/views/post/create.blade.php -->
 2 
 3<h1>Create Post</h1>
 4 
 5@if ($errors->any())
 6    <div class="alert alert-danger">
 7        <ul>
 8            @foreach ($errors->all() as $error)
 9                <li>{{ $error }}</li>
10            @endforeach
11        </ul>
12    </div>
13@endif
14 
15<!-- Create Post Form -->

<!-- /resources/views/post/create.blade.php -->

<h1>Create Post</h1>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<!-- Create Post Form -->
```

#### Customizing the Error Messages

Laravel's built-in validation rules each have an error message that is located in your application's `lang/en/validation.php` file. If your application does not have a `lang` directory, you may instruct Laravel to create it using the `lang:publish` Artisan command.

Within the `lang/en/validation.php` file, you will find a translation entry for each validation rule. You are free to change or modify these messages based on the needs of your application.

In addition, you may copy this file to another language directory to translate the messages for your application's language. To learn more about Laravel localization, check out the complete [localization documentation](https://laravel.com/framework/docs/localization).

 By default, the Laravel application skeleton does not include the `lang` directory. If you would like to customize Laravel's language files, you may publish them via the `lang:publish` Artisan command.

#### XHR Requests and Validation

In this example, we used a traditional form to send data to the application. However, many applications receive XHR requests from a JavaScript powered frontend. When using the `validate` method during an XHR request, Laravel will not generate a redirect response. Instead, Laravel generates a JSON response containing all of the validation errors. This JSON response will be sent with a 422 HTTP status code.

#### The `@error` Directive

You may use the `@error` [Blade](https://laravel.com/framework/docs/blade) directive to quickly determine if validation error messages exist for a given attribute. Within an `@error` directive, you may echo the `$message` variable to display the error message:

```
 1<!-- /resources/views/post/create.blade.php -->
 2 
 3<label for="title">Post Title</label>
 4 
 5<input
 6    id="title"
 7    type="text"
 8    name="title"
 9    class="@error('title') is-invalid @enderror"
10/>
11 
12@error('title')
13    <div class="alert alert-danger">{{ $message }}</div>
14@enderror

<!-- /resources/views/post/create.blade.php -->

<label for="title">Post Title</label>

<input
    id="title"
    type="text"
    name="title"
    class="@error('title') is-invalid @enderror"
/>

@error('title')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror
```

If you are using named error bags, you may pass the name of the error bag as the second argument to the `@error` directive:

```
1<input ... class="@error('title', 'post') is-invalid @enderror">

<input ... class="@error('title', 'post') is-invalid @enderror">
```

### Repopulating Forms

When Laravel generates a redirect response due to a validation error, the framework will automatically [flash all of the request's input to the session](https://laravel.com/framework/docs/session#flash-data). This is done so that you may conveniently access the input during the next request and repopulate the form that the user attempted to submit.

To retrieve flashed input from the previous request, invoke the `old` method on an instance of `Illuminate\Http\Request`. The `old` method will pull the previously flashed input data from the [session](https://laravel.com/framework/docs/session):

```
1$title = $request->old('title');

$title = $request->old('title');
```

Laravel also provides a global `old` helper. If you are displaying old input within a [Blade template](https://laravel.com/framework/docs/blade), it is more convenient to use the `old` helper to repopulate the form. If no old input exists for the given field, `null` will be returned:

```
1<input type="text" name="title" value="{{ old('title') }}">

<input type="text" name="title" value="{{ old('title') }}">
```

### A Note on Optional Fields

By default, Laravel includes the `TrimStrings` and `ConvertEmptyStringsToNull` middleware in your application's global middleware stack. Because of this, you will often need to mark your "optional" request fields as `nullable` if you do not want the validator to consider `null` values as invalid. For example:

```
1$request->validate([
2    'title' => ['required', 'unique:posts', 'max:255'],
3    'body' => ['required'],
4    'publish_at' => ['nullable', 'date'],
5]);

$request->validate([
    'title' => ['required', 'unique:posts', 'max:255'],
    'body' => ['required'],
    'publish_at' => ['nullable', 'date'],
]);
```

In this example, we are specifying that the `publish_at` field may be either `null` or a valid date representation. If the `nullable` modifier is not added to the rule definition, the validator would consider `null` an invalid date.

### Validation Error Response Format

When your application throws a `Illuminate\Validation\ValidationException` exception and the incoming HTTP request is expecting a JSON response, Laravel will automatically format the error messages for you and return a `422 Unprocessable Entity` HTTP response.

Below, you can review an example of the JSON response format for validation errors. Note that nested error keys are flattened into "dot" notation format:

```
 1{
 2    "message": "The team name field must be a string. (and 4 more errors)",
 3    "errors": {
 4        "team_name": [
 5            "The team name field must be a string.",
 6            "The team name field must be at least 1 characters."
 7        ],
 8        "authorization.role": [
 9            "The selected authorization.role is invalid."
10        ],
11        "users.0.email": [
12            "The users.0.email field is required."
13        ],
14        "users.2.email": [
15            "The users.2.email field must be a valid email address."
16        ]
17    }
18}

{
    "message": "The team name field must be a string. (and 4 more errors)",
    "errors": {
        "team_name": [
            "The team name field must be a string.",
            "The team name field must be at least 1 characters."
        ],
        "authorization.role": [
            "The selected authorization.role is invalid."
        ],
        "users.0.email": [
            "The users.0.email field is required."
        ],
        "users.2.email": [
            "The users.2.email field must be a valid email address."
        ]
    }
}
```

## Form Request Validation

### Creating Form Requests

For more complex validation scenarios, you may wish to create a "form request". Form requests are custom request classes that encapsulate their own validation and authorization logic. To create a form request class, you may use the `make:request` Artisan CLI command:

```
1php artisan make:request StorePostRequest

php artisan make:request StorePostRequest
```

The generated form request class will be placed in the `app/Http/Requests` directory. If this directory does not exist, it will be created when you run the `make:request` command. Each form request generated by Laravel has two methods: `authorize` and `rules`.

As you might have guessed, the `authorize` method is responsible for determining if the currently authenticated user can perform the action represented by the request, while the `rules` method returns the validation rules that should apply to the request's data:

```
 1/**
 2 * Get the validation rules that apply to the request.
 3 *
 4 * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
 5 */
 6public function rules(): array
 7{
 8    return [
 9        'title' => ['required', 'unique:posts', 'max:255'],
10        'body' => ['required'],
11    ];
12}

/**
 * Get the validation rules that apply to the request.
 *
 * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
 */
public function rules(): array
{
    return [
        'title' => ['required', 'unique:posts', 'max:255'],
        'body' => ['required'],
    ];
}
```

 You may type-hint any dependencies you require within the `rules` method's signature. They will automatically be resolved via the Laravel [service container](https://laravel.com/framework/docs/container).

So, how are the validation rules evaluated? All you need to do is type-hint the request on your controller method. The incoming form request is validated before the controller method is called, meaning you do not need to clutter your controller with any validation logic:

```
 1/**
 2 * Store a new blog post.
 3 */
 4public function store(StorePostRequest $request): RedirectResponse
 5{
 6    // The incoming request is valid...
 7 
 8    // Retrieve the validated input data...
 9    $validated = $request->validated();
10 
11    // Retrieve a portion of the validated input data...
12    $validated = $request->safe()->only(['name', 'email']);
13    $validated = $request->safe()->except(['name', 'email']);
14 
15    // Store the blog post...
16 
17    return redirect('/posts');
18}

/**
 * Store a new blog post.
 */
public function store(StorePostRequest $request): RedirectResponse
{
    // The incoming request is valid...

    // Retrieve the validated input data...
    $validated = $request->validated();

    // Retrieve a portion of the validated input data...
    $validated = $request->safe()->only(['name', 'email']);
    $validated = $request->safe()->except(['name', 'email']);

    // Store the blog post...

    return redirect('/posts');
}
```

If validation fails, a redirect response will be generated to send the user back to their previous location. The errors will also be flashed to the session so they are available for display. If the request was an XHR request, an HTTP response with a 422 status code will be returned to the user including a JSON representation of the validation errors.

 Need to add real-time form request validation to your Inertia powered Laravel frontend? Check out [Laravel Precognition](https://laravel.com/framework/docs/precognition).

#### Performing Additional Validation

Sometimes you need to perform additional validation after your initial validation is complete. You can accomplish this using the form request's `after` method.

The `after` method should return an array of callables or closures which will be invoked after validation is complete. The given callables will receive an `Illuminate\Validation\Validator` instance, allowing you to raise additional error messages if necessary:

```
 1use Illuminate\Validation\Validator;
 2 
 3/**
 4 * Get the "after" validation callables for the request.
 5 */
 6public function after(): array
 7{
 8    return [
 9        function (Validator $validator) {
10            if ($this->somethingElseIsInvalid()) {
11                $validator->errors()->add(
12                    'field',
13                    'Something is wrong with this field!'
14                );
15            }
16        }
17    ];
18}

use Illuminate\Validation\Validator;

/**
 * Get the "after" validation callables for the request.
 */
public function after(): array
{
    return [
        function (Validator $validator) {
            if ($this->somethingElseIsInvalid()) {
                $validator->errors()->add(
                    'field',
                    'Something is wrong with this field!'
                );
            }
        }
    ];
}
```

As noted, the array returned by the `after` method may also contain invokable classes. The `__invoke` method of these classes will receive an `Illuminate\Validation\Validator` instance:

```
 1use App\Validation\ValidateShippingTime;
 2use App\Validation\ValidateUserStatus;
 3use Illuminate\Validation\Validator;
 4 
 5/**
 6 * Get the "after" validation callables for the request.
 7 */
 8public function after(): array
 9{
10    return [
11        new ValidateUserStatus,
12        new ValidateShippingTime,
13        function (Validator $validator) {
14            //
15        }
16    ];
17}

use App\Validation\ValidateShippingTime;
use App\Validation\ValidateUserStatus;
use Illuminate\Validation\Validator;

/**
 * Get the "after" validation callables for the request.
 */
public function after(): array
{
    return [
        new ValidateUserStatus,
        new ValidateShippingTime,
        function (Validator $validator) {
            //
        }
    ];
}
```

#### Stopping on the First Validation Failure

By adding the `StopOnFirstFailure` attribute to your request class, you may inform the validator that it should stop validating all attributes once a single validation failure has occurred:

```
 1<?php
 2 
 3namespace App\Http\Requests;
 4 
 5use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
 6use Illuminate\Foundation\Http\FormRequest;
 7 
 8#[StopOnFirstFailure]
 9class StorePostRequest extends FormRequest
10{
11    // ...
12}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;

#[StopOnFirstFailure]
class StorePostRequest extends FormRequest
{
    // ...
}
```

#### Failing on Unknown Fields

By adding the `FailOnUnknownFields` attribute to your request class, you may instruct Laravel to reject any incoming fields that are not defined by your request's validation rules:

```
 1<?php
 2 
 3namespace App\Http\Requests;
 4 
 5use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
 6use Illuminate\Foundation\Http\FormRequest;
 7 
 8#[FailOnUnknownFields]
 9class StorePostRequest extends FormRequest
10{
11    public function rules(): array
12    {
13        return [
14            'title' => ['required', 'string'],
15            'body' => ['required', 'string'],
16        ];
17    }
18}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'body' => ['required', 'string'],
        ];
    }
}
```

You may also enable this behavior globally for all form requests from your `AppServiceProvider`:

```
1use Illuminate\Foundation\Http\FormRequest;
2 
3/**
4 * Bootstrap any application services.
5 */
6public function boot(): void
7{
8    FormRequest::failOnUnknownFields();
9}

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    FormRequest::failOnUnknownFields();
}
```

If needed, you may disable this behavior for a specific request by passing `false` to the attribute:

```
1#[FailOnUnknownFields(false)]
2class PublicWebhookRequest extends FormRequest
3{
4    // ...
5}

#[FailOnUnknownFields(false)]
class PublicWebhookRequest extends FormRequest
{
    // ...
}
```

Rejecting unknown fields can provide additional protection against mass-assignment style issues by preventing unexpected input keys from flowing deeper into your application. However, you should still configure your model's `$fillable` / `$guarded` properties and only persist trusted, validated input.

#### Customizing the Redirect Location

When form request validation fails, a redirect response will be generated to send the user back to their previous location. However, you are free to customize this behavior. To do so, you may use the `RedirectTo` attribute on your form request:

```
 1<?php
 2 
 3namespace App\Http\Requests;
 4 
 5use Illuminate\Foundation\Http\Attributes\RedirectTo;
 6use Illuminate\Foundation\Http\FormRequest;
 7 
 8#[RedirectTo('/dashboard')]
 9class StorePostRequest extends FormRequest
10{
11    // ...
12}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\Attributes\RedirectTo;
use Illuminate\Foundation\Http\FormRequest;

#[RedirectTo('/dashboard')]
class StorePostRequest extends FormRequest
{
    // ...
}
```

Or, if you would like to redirect users to a named route, you may use the `RedirectToRoute` attribute instead:

```
 1<?php
 2 
 3namespace App\Http\Requests;
 4 
 5use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
 6use Illuminate\Foundation\Http\FormRequest;
 7 
 8#[RedirectToRoute('dashboard')]
 9class StorePostRequest extends FormRequest
10{
11    // ...
12}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Foundation\Http\FormRequest;

#[RedirectToRoute('dashboard')]
class StorePostRequest extends FormRequest
{
    // ...
}
```

#### Customizing the Error Bag

When form request validation fails, the errors are flashed to the `default` error bag. If you need to store the errors in a different named error bag, you may use the `ErrorBag` attribute on your form request:

```
 1<?php
 2 
 3namespace App\Http\Requests;
 4 
 5use Illuminate\Foundation\Http\Attributes\ErrorBag;
 6use Illuminate\Foundation\Http\FormRequest;
 7 
 8#[ErrorBag('login')]
 9class LoginRequest extends FormRequest
10{
11    // ...
12}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\Attributes\ErrorBag;
use Illuminate\Foundation\Http\FormRequest;

#[ErrorBag('login')]
class LoginRequest extends FormRequest
{
    // ...
}
```

### Authorizing Form Requests

The form request class also contains an `authorize` method. Within this method, you may determine if the authenticated user actually has the authority to update a given resource. For example, you may determine if a user actually owns a blog comment they are attempting to update. Most likely, you will interact with your [authorization gates and policies](https://laravel.com/framework/docs/authorization) within this method:

```
 1use App\Models\Comment;
 2 
 3/**
 4 * Determine if the user is authorized to make this request.
 5 */
 6public function authorize(): bool
 7{
 8    $comment = Comment::find($this->route('comment'));
 9 
10    return $comment && $this->user()->can('update', $comment);
11}

use App\Models\Comment;

/**
 * Determine if the user is authorized to make this request.
 */
public function authorize(): bool
{
    $comment = Comment::find($this->route('comment'));

    return $comment && $this->user()->can('update', $comment);
}
```

Since all form requests extend the base Laravel request class, we may use the `user` method to access the currently authenticated user. Also, note the call to the `route` method in the example above. This method grants you access to the URI parameters defined on the route being called, such as the `{comment}` parameter in the example below:

```
1Route::post('/comment/{comment}');

Route::post('/comment/{comment}');
```

Therefore, if your application is taking advantage of [route model binding](https://laravel.com/framework/docs/routing#route-model-binding), your code may be made even more succinct by accessing the resolved model as a property of the request:

```
1return $this->user()->can('update', $this->comment);

return $this->user()->can('update', $this->comment);
```

If the `authorize` method returns `false`, an HTTP response with a 403 status code will automatically be returned and your controller method will not execute.

If you plan to handle authorization logic for the request in another part of your application, you may remove the `authorize` method completely, or simply return `true`:

```
1/**
2 * Determine if the user is authorized to make this request.
3 */
4public function authorize(): bool
5{
6    return true;
7}

/**
 * Determine if the user is authorized to make this request.
 */
public function authorize(): bool
{
    return true;
}
```

 You may type-hint any dependencies you need within the `authorize` method's signature. They will automatically be resolved via the Laravel [service container](https://laravel.com/framework/docs/container).

### Customizing the Error Messages

You may customize the error messages used by the form request by overriding the `messages` method. This method should return an array of attribute / rule pairs and their corresponding error messages:

```
 1/**
 2 * Get the error messages for the defined validation rules.
 3 *
 4 * @return array<string, string>
 5 */
 6public function messages(): array
 7{
 8    return [
 9        'title.required' => 'A title is required',
10        'body.required' => 'A message is required',
11    ];
12}

/**
 * Get the error messages for the defined validation rules.
 *
 * @return array<string, string>
 */
public function messages(): array
{
    return [
        'title.required' => 'A title is required',
        'body.required' => 'A message is required',
    ];
}
```

#### Customizing the Validation Attributes

Many of Laravel's built-in validation rule error messages contain an `:attribute` placeholder. If you would like the `:attribute` placeholder of your validation message to be replaced with a custom attribute name, you may specify the custom names by overriding the `attributes` method. This method should return an array of attribute / name pairs:

```
 1/**
 2 * Get custom attributes for validator errors.
 3 *
 4 * @return array<string, string>
 5 */
 6public function attributes(): array
 7{
 8    return [
 9        'email' => 'email address',
10    ];
11}

/**
 * Get custom attributes for validator errors.
 *
 * @return array<string, string>
 */
public function attributes(): array
{
    return [
        'email' => 'email address',
    ];
}
```

### Preparing Input for Validation

If you need to prepare or sanitize any data from the request before you apply your validation rules, you may use the `prepareForValidation` method:

```
 1use Illuminate\Support\Str;
 2 
 3/**
 4 * Prepare the data for validation.
 5 */
 6protected function prepareForValidation(): void
 7{
 8    $this->merge([
 9        'slug' => Str::slug($this->slug),
10    ]);
11}

use Illuminate\Support\Str;

/**
 * Prepare the data for validation.
 */
protected function prepareForValidation(): void
{
    $this->merge([
        'slug' => Str::slug($this->slug),
    ]);
}
```

Likewise, if you need to normalize any request data after validation is complete, you may use the `passedValidation` method:

```
1/**
2 * Handle a passed validation attempt.
3 */
4protected function passedValidation(): void
5{
6    $this->replace(['name' => 'Taylor']);
7}

/**
 * Handle a passed validation attempt.
 */
protected function passedValidation(): void
{
    $this->replace(['name' => 'Taylor']);
}
```

## Manually Creating Validators

If you do not want to use the `validate` method on the request, you may create a validator instance manually using the `Validator` [facade](https://laravel.com/framework/docs/facades). The `make` method on the facade generates a new validator instance:

```
 1<?php
 2 
 3namespace App\Http\Controllers;
 4 
 5use Illuminate\Http\RedirectResponse;
 6use Illuminate\Http\Request;
 7use Illuminate\Support\Facades\Validator;
 8 
 9class PostController extends Controller
10{
11    /**
12     * Store a new blog post.
13     */
14    public function store(Request $request): RedirectResponse
15    {
16        $validator = Validator::make($request->all(), [
17            'title' => ['required', 'unique:posts', 'max:255'],
18            'body' => ['required'],
19        ]);
20 
21        if ($validator->fails()) {
22            return redirect('/post/create')
23                ->withErrors($validator)
24                ->withInput();
25        }
26 
27        // Retrieve the validated input...
28        $validated = $validator->validated();
29 
30        // Retrieve a portion of the validated input...
31        $validated = $validator->safe()->only(['name', 'email']);
32        $validated = $validator->safe()->except(['name', 'email']);
33 
34        // Store the blog post...
35 
36        return redirect('/posts');
37    }
38}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    /**
     * Store a new blog post.
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'unique:posts', 'max:255'],
            'body' => ['required'],
        ]);

        if ($validator->fails()) {
            return redirect('/post/create')
                ->withErrors($validator)
                ->withInput();
        }

        // Retrieve the validated input...
        $validated = $validator->validated();

        // Retrieve a portion of the validated input...
        $validated = $validator->safe()->only(['name', 'email']);
        $validated = $validator->safe()->except(['name', 'email']);

        // Store the blog post...

        return redirect('/posts');
    }
}
```

The first argument passed to the `make` method is the data under validation. The second argument is an array of the validation rules that should be applied to the data.

After determining whether the request validation failed, you may use the `withErrors` method to flash the error messages to the session. When using this method, the `$errors` variable will automatically be shared with your views after redirection, allowing you to easily display them back to the user. The `withErrors` method accepts a validator, a `MessageBag`, or a PHP `array`.

#### Stopping on First Validation Failure

The `stopOnFirstFailure` method will inform the validator that it should stop validating all attributes once a single validation failure has occurred:

```
1if ($validator->stopOnFirstFailure()->fails()) {
2    // ...
3}

if ($validator->stopOnFirstFailure()->fails()) {
    // ...
}
```

### Automatic Redirection

If you would like to create a validator instance manually but still take advantage of the automatic redirection offered by the HTTP request's `validate` method, you may call the `validate` method on an existing validator instance. If validation fails, the user will automatically be redirected or, in the case of an XHR request, a JSON response will be returned:

```
1Validator::make($request->all(), [
2    'title' => ['required', 'unique:posts', 'max:255'],
3    'body' => ['required'],
4])->validate();

Validator::make($request->all(), [
    'title' => ['required', 'unique:posts', 'max:255'],
    'body' => ['required'],
])->validate();
```

You may use the `validateWithBag` method to store the error messages in a named error bag if validation fails:

```
1Validator::make($request->all(), [
2    'title' => ['required', 'unique:posts', 'max:255'],
3    'body' => ['required'],
4])->validateWithBag('post');

Validator::make($request->all(), [
    'title' => ['required', 'unique:posts', 'max:255'],
    'body' => ['required'],
])->validateWithBag('post');
```

### Named Error Bags

If you have multiple forms on a single page, you may wish to name the `MessageBag` containing the validation errors, allowing you to retrieve the error messages for a specific form. To achieve this, pass a name as the second argument to `withErrors`:

```
1return redirect('/register')->withErrors($validator, 'login');

return redirect('/register')->withErrors($validator, 'login');
```

You may then access the named `MessageBag` instance from the `$errors` variable:

```
1{{ $errors->login->first('email') }}

{{ $errors->login->first('email') }}
```

### Customizing the Error Messages

If needed, you may provide custom error messages that a validator instance should use instead of the default error messages provided by Laravel. There are several ways to specify custom messages. First, you may pass the custom messages as the third argument to the `Validator::make` method:

```
1$validator = Validator::make($input, $rules, $messages = [
2    'required' => 'The :attribute field is required.',
3]);

$validator = Validator::make($input, $rules, $messages = [
    'required' => 'The :attribute field is required.',
]);
```

In this example, the `:attribute` placeholder will be replaced by the actual name of the field under validation. You may also utilize other placeholders in validation messages. For example:

```
1$messages = [
2    'same' => 'The :attribute and :other must match.',
3    'size' => 'The :attribute must be exactly :size.',
4    'between' => 'The :attribute value :input is not between :min - :max.',
5    'in' => 'The :attribute must be one of the following types: :values',
6];

$messages = [
    'same' => 'The :attribute and :other must match.',
    'size' => 'The :attribute must be exactly :size.',
    'between' => 'The :attribute value :input is not between :min - :max.',
    'in' => 'The :attribute must be one of the following types: :values',
];
```

#### Specifying a Custom Message for a Given Attribute

Sometimes you may wish to specify a custom error message only for a specific attribute. You may do so using "dot" notation. Specify the attribute's name first, followed by the rule:

```
1$messages = [
2    'email.required' => 'We need to know your email address!',
3];

$messages = [
    'email.required' => 'We need to know your email address!',
];
```

#### Specifying Custom Attribute Values

Many of Laravel's built-in error messages include an `:attribute` placeholder that is replaced with the name of the field or attribute under validation. To customize the values used to replace these placeholders for specific fields, you may pass an array of custom attributes as the fourth argument to the `Validator::make` method:

```
1$validator = Validator::make($input, $rules, $messages, [
2    'email' => 'email address',
3]);

$validator = Validator::make($input, $rules, $messages, [
    'email' => 'email address',
]);
```

### Performing Additional Validation

Sometimes you need to perform additional validation after your initial validation is complete. You can accomplish this using the validator's `after` method. The `after` method accepts a closure or an array of callables which will be invoked after validation is complete. The given callables will receive an `Illuminate\Validation\Validator` instance, allowing you to raise additional error messages if necessary:

```
 1use Illuminate\Support\Facades\Validator;
 2 
 3$validator = Validator::make(/* ... */);
 4 
 5$validator->after(function ($validator) {
 6    if ($this->somethingElseIsInvalid()) {
 7        $validator->errors()->add(
 8            'field', 'Something is wrong with this field!'
 9        );
10    }
11});
12 
13if ($validator->fails()) {
14    // ...
15}

use Illuminate\Support\Facades\Validator;

$validator = Validator::make(/* ... */);

$validator->after(function ($validator) {
    if ($this->somethingElseIsInvalid()) {
        $validator->errors()->add(
            'field', 'Something is wrong with this field!'
        );
    }
});

if ($validator->fails()) {
    // ...
}
```

As noted, the `after` method also accepts an array of callables, which is particularly convenient if your "after validation" logic is encapsulated in invokable classes, which will receive an `Illuminate\Validation\Validator` instance via their `__invoke` method:

```
 1use App\Validation\ValidateShippingTime;
 2use App\Validation\ValidateUserStatus;
 3 
 4$validator->after([
 5    new ValidateUserStatus,
 6    new ValidateShippingTime,
 7    function ($validator) {
 8        // ...
 9    },
10]);

use App\Validation\ValidateShippingTime;
use App\Validation\ValidateUserStatus;

$validator->after([
    new ValidateUserStatus,
    new ValidateShippingTime,
    function ($validator) {
        // ...
    },
]);
```

## Working With Validated Input

After validating incoming request data using a form request or a manually created validator instance, you may wish to retrieve the incoming request data that actually underwent validation. This can be accomplished in several ways. First, you may call the `validated` method on a form request or validator instance. This method returns an array of the data that was validated:

```
1$validated = $request->validated();
2 
3$validated = $validator->validated();

$validated = $request->validated();

$validated = $validator->validated();
```

Alternatively, you may call the `safe` method on a form request or validator instance. This method returns an instance of `Illuminate\Support\ValidatedInput`. This object exposes `only`, `except`, and `all` methods to retrieve a subset of the validated data or the entire array of validated data:

```
1$validated = $request->safe()->only(['name', 'email']);
2 
3$validated = $request->safe()->except(['name', 'email']);
4 
5$validated = $request->safe()->all();

$validated = $request->safe()->only(['name', 'email']);

$validated = $request->safe()->except(['name', 'email']);

$validated = $request->safe()->all();
```

In addition, the `Illuminate\Support\ValidatedInput` instance may be iterated over and accessed like an array:

```
1// Validated data may be iterated...
2foreach ($request->safe() as $key => $value) {
3    // ...
4}
5 
6// Validated data may be accessed as an array...
7$validated = $request->safe();
8 
9$email = $validated['email'];

// Validated data may be iterated...
foreach ($request->safe() as $key => $value) {
    // ...
}

// Validated data may be accessed as an array...
$validated = $request->safe();

$email = $validated['email'];
```

If you would like to add additional fields to the validated data, you may call the `merge` method:

```
1$validated = $request->safe()->merge(['name' => 'Taylor Otwell']);

$validated = $request->safe()->merge(['name' => 'Taylor Otwell']);
```

If you would like to retrieve the validated data as a [collection](https://laravel.com/framework/docs/collections) instance, you may call the `collect` method:

```
1$collection = $request->safe()->collect();

$collection = $request->safe()->collect();
```

## Working With Error Messages

After calling the `errors` method on a `Validator` instance, you will receive an `Illuminate\Support\MessageBag` instance, which has a variety of convenient methods for working with error messages. The `$errors` variable that is automatically made available to all views is also an instance of the `MessageBag` class.

#### Retrieving the First Error Message for a Field

To retrieve the first error message for a given field, use the `first` method:

```
1$errors = $validator->errors();
2 
3echo $errors->first('email');

$errors = $validator->errors();

echo $errors->first('email');
```

#### Retrieving All Error Messages for a Field

If you need to retrieve an array of all the messages for a given field, use the `get` method:

```
1foreach ($errors->get('email') as $message) {
2    // ...
3}

foreach ($errors->get('email') as $message) {
    // ...
}
```

If you are validating an array form field, you may retrieve all of the messages for each of the array elements using the `*` character:

```
1foreach ($errors->get('attachments.*') as $message) {
2    // ...
3}

foreach ($errors->get('attachments.*') as $message) {
    // ...
}
```

#### Retrieving All Error Messages for All Fields

To retrieve an array of all messages for all fields, use the `all` method:

```
1foreach ($errors->all() as $message) {
2    // ...
3}

foreach ($errors->all() as $message) {
    // ...
}
```

#### Determining if Messages Exist for a Field

The `has` method may be used to determine if any error messages exist for a given field:

```
1if ($errors->has('email')) {
2    // ...
3}

if ($errors->has('email')) {
    // ...
}
```

### Specifying Custom Messages in Language Files

Laravel's built-in validation rules each have an error message that is located in your application's `lang/en/validation.php` file. If your application does not have a `lang` directory, you may instruct Laravel to create it using the `lang:publish` Artisan command.

Within the `lang/en/validation.php` file, you will find a translation entry for each validation rule. You are free to change or modify these messages based on the needs of your application.

In addition, you may copy this file to another language directory to translate the messages for your application's language. To learn more about Laravel localization, check out the complete [localization documentation](https://laravel.com/framework/docs/localization).

 By default, the Laravel application skeleton does not include the `lang` directory. If you would like to customize Laravel's language files, you may publish them via the `lang:publish` Artisan command.

#### Custom Messages for Specific Attributes

You may customize the error messages used for specified attribute and rule combinations within your application's validation language files. To do so, add your message customizations to the `custom` array of your application's `lang/xx/validation.php` language file:

```
1'custom' => [
2    'email' => [
3        'required' => 'We need to know your email address!',
4        'max' => 'Your email address is too long!'
5    ],
6],

'custom' => [
    'email' => [
        'required' => 'We need to know your email address!',
        'max' => 'Your email address is too long!'
    ],
],
```

### Specifying Attributes in Language Files

Many of Laravel's built-in error messages include an `:attribute` placeholder that is replaced with the name of the field or attribute under validation. If you would like the `:attribute` portion of your validation message to be replaced with a custom value, you may specify the custom attribute name in the `attributes` array of your `lang/xx/validation.php` language file:

```
1'attributes' => [
2    'email' => 'email address',
3],

'attributes' => [
    'email' => 'email address',
],
```

 By default, the Laravel application skeleton does not include the `lang` directory. If you would like to customize Laravel's language files, you may publish them via the `lang:publish` Artisan command.

### Specifying Values in Language Files

Some of Laravel's built-in validation rule error messages contain a `:value` placeholder that is replaced with the current value of the request attribute. However, you may occasionally need the `:value` portion of your validation message to be replaced with a custom representation of the value. For example, consider the following rule that specifies that a credit card number is required if the `payment_type` has a value of `cc`:

```
1Validator::make($request->all(), [
2    'credit_card_number' => ['required_if:payment_type,cc']
3]);

Validator::make($request->all(), [
    'credit_card_number' => ['required_if:payment_type,cc']
]);
```

If this validation rule fails, it will produce the following error message:

```
1The credit card number field is required when payment type is cc.

The credit card number field is required when payment type is cc.
```

Instead of displaying `cc` as the payment type value, you may specify a more user-friendly value representation in your `lang/xx/validation.php` language file by defining a `values` array:

```
1'values' => [
2    'payment_type' => [
3        'cc' => 'credit card'
4    ],
5],

'values' => [
    'payment_type' => [
        'cc' => 'credit card'
    ],
],
```

 By default, the Laravel application skeleton does not include the `lang` directory. If you would like to customize Laravel's language files, you may publish them via the `lang:publish` Artisan command.

After defining this value, the validation rule will produce the following error message:

```
1The credit card number field is required when payment type is credit card.

The credit card number field is required when payment type is credit card.
```

## Available Validation Rules

Below is a list of all available validation rules and their function:

#### Booleans

Accepted Accepted If Boolean Declined Declined If

#### Strings

Active URL Alpha Alpha Dash Alpha Numeric Ascii Confirmed Current Password Different Doesnt Start With Doesnt End With Email Ends With Enum Hex Color In IP Address JSON Lowercase MAC Address Max Min Not In Regular Expression Not Regular Expression Same Size Starts With String Uppercase URL ULID UUID

#### Numbers

Between Decimal Different Digits Digits Between Greater Than Greater Than Or Equal Integer Less Than Less Than Or Equal Max Max Digits Min Min Digits Multiple Of Numeric Same Size

#### Arrays

Array Array Keys Between Contains Doesnt Contain Distinct In Array In Array Keys List Max Min Size

#### Dates

After After Or Equal Before Before Or Equal Date Date Equals Date Format Different Timezone

#### Files

Between Dimensions Encoding Extensions File Image Max Min MIME Types MIME Type By File Extension Size

#### Database

Exists Unique

#### Utilities

Any Of Bail Exclude Exclude If Exclude Unless Exclude With Exclude Without Filled Missing Missing If Missing Unless Missing With Missing With All Nullable Present Present If Present Unless Present With Present With All Prohibited Prohibited If Prohibited If Accepted Prohibited If Declined Prohibited Unless Prohibits Required Required If Required If Accepted Required If Declined Required Unless Required With Required With All Required Without Required Without All Required Array Keys Sometimes

### Rule Builders

In addition to strings, many validation rules may be defined using methods on the `Illuminate\Validation\Rule` class. These methods offer IDE autocompletion and may be combined with string rules:

```
1use Illuminate\Validation\Rule;
2 
3'name' => [Rule::required(), Rule::string()->max(255)],
4'terms' => [Rule::accepted()],
5'enabled' => [Rule::boolean()],
6'website' => [Rule::nullable(), Rule::activeUrl()],

use Illuminate\Validation\Rule;

'name' => [Rule::required(), Rule::string()->max(255)],
'terms' => [Rule::accepted()],
'enabled' => [Rule::boolean()],
'website' => [Rule::nullable(), Rule::activeUrl()],
```

Method names use camel case, such as `Rule::hexColor()`, `Rule::macAddress()`, and `Rule::requiredArrayKeys(['name', 'email'])`. Rules that depend on other fields accept the field names or comparison values as arguments:

```
1'terms' => [Rule::acceptedIf('account_type', ['personal', 'business'])],
2'nickname' => [Rule::excludeWith('name')],
3'email' => [Rule::requiredWithout(['phone', 'mobile'])],
4'settings' => [Rule::presentIf('account_type', 'business')],

'terms' => [Rule::acceptedIf('account_type', ['personal', 'business'])],
'nickname' => [Rule::excludeWith('name')],
'email' => [Rule::requiredWithout(['phone', 'mobile'])],
'settings' => [Rule::presentIf('account_type', 'business')],
```

The `acceptedIf`, `declinedIf`, `missingIf`, `missingUnless`, `presentIf`, and `presentUnless` methods accept a comparison value or an array of comparison values. Methods such as `requiredWith`, `requiredWithAll`, `requiredWithout`, `requiredWithoutAll`, `missingWith`, `missingWithAll`, `presentWith`, `presentWithAll`, and `prohibits` accept an array of field names or multiple field name arguments.

Regular expressions may also be defined using the `regex` and `notRegex` methods:

```
1'username' => [Rule::required(), Rule::regex('/^[a-z]+$/i')],
2'code' => [Rule::notRegex('/^test-/i')],

'username' => [Rule::required(), Rule::regex('/^[a-z]+$/i')],
'code' => [Rule::notRegex('/^test-/i')],
```

#### accepted

The field under validation must be `"yes"`, `"on"`, `1`, `"1"`, `true`, or `"true"`. This is useful for validating "Terms of Service" acceptance or similar fields.

#### accepted_if:anotherfield,value,...

The field under validation must be `"yes"`, `"on"`, `1`, `"1"`, `true`, or `"true"` if another field under validation is equal to a specified value. This is useful for validating "Terms of Service" acceptance or similar fields.

#### active_url

The field under validation must have a valid A or AAAA record according to the `dns_get_record` PHP function. The hostname of the provided URL is extracted using the `parse_url` PHP function before being passed to `dns_get_record`.

When testing validation rules that perform DNS lookups, such as `active_url` and `email:dns`, you may use the `Validator::fakeDnsLookups` method. This fakes DNS lookups while preserving the rules' other validation behavior:

```
1use Illuminate\Support\Facades\Validator;
2 
3Validator::fakeDnsLookups();

use Illuminate\Support\Facades\Validator;

Validator::fakeDnsLookups();
```

#### after:_date_

The field under validation must be a value after a given date. The dates will be passed into the `strtotime` PHP function in order to be converted to a valid `DateTime` instance:

```
1'start_date' => ['required', 'date', 'after:tomorrow']

'start_date' => ['required', 'date', 'after:tomorrow']
```

Instead of passing a date string to be evaluated by `strtotime`, you may specify another field to compare against the date:

```
1'finish_date' => ['required', 'date', 'after:start_date']

'finish_date' => ['required', 'date', 'after:start_date']
```

For convenience, date-based rules may be constructed using the fluent `date` rule builder:

```
1use Illuminate\Validation\Rule;
2 
3'start_date' => [
4    'required',
5    Rule::date()->after(today()->addDays(7)),
6],

use Illuminate\Validation\Rule;

'start_date' => [
    'required',
    Rule::date()->after(today()->addDays(7)),
],
```

The `afterToday` and `todayOrAfter` methods may be used to fluently express the date and must be after today, or today or after, respectively:

```
1'start_date' => [
2    'required',
3    Rule::date()->afterToday(),
4],

'start_date' => [
    'required',
    Rule::date()->afterToday(),
],
```

#### after_or_equal:_date_

The field under validation must be a value after or equal to the given date. For more information, see the after rule.

For convenience, date-based rules may be constructed using the fluent `date` rule builder:

```
1use Illuminate\Validation\Rule;
2 
3'start_date' => [
4    'required',
5    Rule::date()->afterOrEqual(today()->addDays(7)),
6],

use Illuminate\Validation\Rule;

'start_date' => [
    'required',
    Rule::date()->afterOrEqual(today()->addDays(7)),
],
```

#### anyOf

The `Rule::anyOf` validation rule allows you to specify that the field under validation must satisfy any of the given validation rulesets. For example, the following rule will validate that the `username` field is either an email address or an alpha-numeric string (including dashes) that is at least 6 characters long:

```
1use Illuminate\Validation\Rule;
2 
3'username' => [
4    'required',
5    Rule::anyOf([
6        ['string', 'email'],
7        ['string', 'alpha_dash', 'min:6'],
8    ]),
9],

use Illuminate\Validation\Rule;

'username' => [
    'required',
    Rule::anyOf([
        ['string', 'email'],
        ['string', 'alpha_dash', 'min:6'],
    ]),
],
```

#### alpha

The field under validation must be entirely Unicode alphabetic characters contained in [\p{L}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AL%3A%5D&g=&i=) and [\p{M}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AM%3A%5D&g=&i=).

To restrict this validation rule to characters in the ASCII range (`a-z` and `A-Z`), you may provide the `ascii` option to the validation rule:

```
1'username' => ['alpha:ascii'],

'username' => ['alpha:ascii'],
```

#### alpha_dash

The field under validation must be entirely Unicode alpha-numeric characters contained in [\p{L}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AL%3A%5D&g=&i=), [\p{M}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AM%3A%5D&g=&i=), [\p{N}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AN%3A%5D&g=&i=), as well as ASCII dashes (`-`) and ASCII underscores (`_`).

To restrict this validation rule to characters in the ASCII range (`a-z`, `A-Z`, and `0-9`), you may provide the `ascii` option to the validation rule:

```
1'username' => ['alpha_dash:ascii'],

'username' => ['alpha_dash:ascii'],
```

#### alpha_num

The field under validation must be entirely Unicode alpha-numeric characters contained in [\p{L}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AL%3A%5D&g=&i=), [\p{M}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AM%3A%5D&g=&i=), and [\p{N}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AN%3A%5D&g=&i=).

To restrict this validation rule to characters in the ASCII range (`a-z`, `A-Z`, and `0-9`), you may provide the `ascii` option to the validation rule:

```
1'username' => ['alpha_num:ascii'],

'username' => ['alpha_num:ascii'],
```

#### array

The field under validation must be a PHP `array`.

When additional values are provided to the `array` rule, each key in the input array must be present within the list of values provided to the rule. In the following example, the `admin` key in the input array is invalid since it is not contained in the list of values provided to the `array` rule:

```
 1use Illuminate\Support\Facades\Validator;
 2 
 3$input = [
 4    'user' => [
 5        'name' => 'Taylor Otwell',
 6        'username' => 'taylorotwell',
 7        'admin' => true,
 8    ],
 9];
10 
11Validator::make($input, [
12    'user' => ['array:name,username'],
13]);

use Illuminate\Support\Facades\Validator;

$input = [
    'user' => [
        'name' => 'Taylor Otwell',
        'username' => 'taylorotwell',
        'admin' => true,
    ],
];

Validator::make($input, [
    'user' => ['array:name,username'],
]);
```

In general, you should always specify the array keys that are allowed to be present within your array.

#### array_keys:_foo_,_bar_,...

The field under validation must be a PHP `array` whose keys are all included in the given list. At least one key must be provided:

```
1'user' => ['array_keys:name,username'],

'user' => ['array_keys:name,username'],
```

For convenience, you may use the `Rule::arrayKeys` method:

```
1'user' => [Rule::arrayKeys('name', 'username')],

'user' => [Rule::arrayKeys('name', 'username')],
```

#### ascii

The field under validation must be entirely 7-bit ASCII characters.

#### bail

Stop running validation rules for the field after the first validation failure.

While the `bail` rule will only stop validating a specific field when it encounters a validation failure, the `stopOnFirstFailure` method will inform the validator that it should stop validating all attributes once a single validation failure has occurred:

```
1if ($validator->stopOnFirstFailure()->fails()) {
2    // ...
3}

if ($validator->stopOnFirstFailure()->fails()) {
    // ...
}
```

#### before:_date_

The field under validation must be a value preceding the given date. The dates will be passed into the PHP `strtotime` function in order to be converted into a valid `DateTime` instance. In addition, like the after rule, the name of another field under validation may be supplied as the value of `date`.

For convenience, date-based rules may also be constructed using the fluent `date` rule builder:

```
1use Illuminate\Validation\Rule;
2 
3'start_date' => [
4    'required',
5    Rule::date()->before(today()->subDays(7)),
6],

use Illuminate\Validation\Rule;

'start_date' => [
    'required',
    Rule::date()->before(today()->subDays(7)),
],
```

The `beforeToday` and `todayOrBefore` methods may be used to fluently express the date and must be before today, or today or before, respectively:

```
1'start_date' => [
2    'required',
3    Rule::date()->beforeToday(),
4],

'start_date' => [
    'required',
    Rule::date()->beforeToday(),
],
```

#### before_or_equal:_date_

The field under validation must be a value preceding or equal to the given date. The dates will be passed into the PHP `strtotime` function in order to be converted into a valid `DateTime` instance. In addition, like the after rule, the name of another field under validation may be supplied as the value of `date`.

For convenience, date-based rules may also be constructed using the fluent `date` rule builder:

```
1use Illuminate\Validation\Rule;
2 
3'start_date' => [
4    'required',
5    Rule::date()->beforeOrEqual(today()->subDays(7)),
6],

use Illuminate\Validation\Rule;

'start_date' => [
    'required',
    Rule::date()->beforeOrEqual(today()->subDays(7)),
],
```

#### between:_min_,_max_

The field under validation must have a size between the given _min_ and _max_ (inclusive). Strings, numerics, arrays, and files are evaluated in the same fashion as the size rule.

#### boolean

The field under validation must be able to be cast as a boolean. Accepted input are `true`, `false`, `1`, `0`, `"1"`, and `"0"`.

You may use the `strict` parameter to only consider the field valid if its value is `true` or `false`:

```
1'foo' => ['boolean:strict']

'foo' => ['boolean:strict']
```

Alternatively, you may use the `Rule::boolean` method, passing `true` to enable strict validation:

```
1'foo' => [Rule::boolean(true)],

'foo' => [Rule::boolean(true)],
```

#### confirmed

The field under validation must have a matching field of `{field}_confirmation`. For example, if the field under validation is `password`, a matching `password_confirmation` field must be present in the input.

You may also pass a custom confirmation field name. For example, `confirmed:repeat_username` will expect the field `repeat_username` to match the field under validation.

You may also use the `Rule::confirmed` method, specifying a custom confirmation field via the `customField` method:

```
1'username' => [Rule::confirmed()->customField('repeat_username')],

'username' => [Rule::confirmed()->customField('repeat_username')],
```

#### contains:_foo_,_bar_,...

The field under validation must be an array that contains all of the given parameter values. Since this rule often requires you to `implode` an array, the `Rule::contains` method may be used to fluently construct the rule:

```
 1use Illuminate\Support\Facades\Validator;
 2use Illuminate\Validation\Rule;
 3 
 4Validator::make($data, [
 5    'roles' => [
 6        'required',
 7        'array',
 8        Rule::contains(['admin', 'editor']),
 9    ],
10]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

Validator::make($data, [
    'roles' => [
        'required',
        'array',
        Rule::contains(['admin', 'editor']),
    ],
]);
```

#### doesnt_contain:_foo_,_bar_,...

The field under validation must be an array that does not contain any of the given parameter values. Since this rule often requires you to `implode` an array, the `Rule::doesntContain` method may be used to fluently construct the rule:

```
 1use Illuminate\Support\Facades\Validator;
 2use Illuminate\Validation\Rule;
 3 
 4Validator::make($data, [
 5    'roles' => [
 6        'required',
 7        'array',
 8        Rule::doesntContain(['admin', 'editor']),
 9    ],
10]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

Validator::make($data, [
    'roles' => [
        'required',
        'array',
        Rule::doesntContain(['admin', 'editor']),
    ],
]);
```

#### current_password

The field under validation must match the authenticated user's password. You may specify an [authentication guard](https://laravel.com/framework/docs/authentication) using the rule's first parameter:

```
1'password' => ['current_password:api']

'password' => ['current_password:api']
```

Alternatively, you may use the `Rule::currentPassword` method and specify the authentication guard via the `guard` method:

```
1'password' => [Rule::currentPassword()->guard('api')],

'password' => [Rule::currentPassword()->guard('api')],
```

#### date

The field under validation must be a valid, non-relative date according to the `strtotime` PHP function.

#### date_equals:_date_

The field under validation must be equal to the given date. The dates will be passed into the PHP `strtotime` function in order to be converted into a valid `DateTime` instance.

For convenience, date-based rules may be constructed using the fluent `date` rule builder:

```
1use Illuminate\Validation\Rule;
2 
3'start_date' => [Rule::date()->equals('2026-01-01')],

use Illuminate\Validation\Rule;

'start_date' => [Rule::date()->equals('2026-01-01')],
```

The `equals` method accepts a date string, a `DateTimeInterface` instance, or the name of another field to compare against.

#### date_format:_format_,...

The field under validation must match one of the given _formats_. You should use **either** `date` or `date_format` when validating a field, not both. This validation rule supports all formats supported by PHP's [DateTime](https://www.php.net/manual/en/class.datetime.php) class.

For convenience, date-based rules may be constructed using the fluent `date` rule builder:

```
1use Illuminate\Validation\Rule;
2 
3'start_date' => [
4    'required',
5    Rule::date()->format('Y-m-d'),
6],

use Illuminate\Validation\Rule;

'start_date' => [
    'required',
    Rule::date()->format('Y-m-d'),
],
```

#### decimal:_min_,_max_

The field under validation must be numeric and must contain the specified number of decimal places:

```
1// Must have exactly two decimal places (9.99)...
2'price' => ['decimal:2']
3 
4// Must have between 2 and 4 decimal places...
5'price' => ['decimal:2,4']

// Must have exactly two decimal places (9.99)...
'price' => ['decimal:2']

// Must have between 2 and 4 decimal places...
'price' => ['decimal:2,4']
```

#### declined

The field under validation must be `"no"`, `"off"`, `0`, `"0"`, `false`, or `"false"`.

#### declined_if:anotherfield,value,...

The field under validation must be `"no"`, `"off"`, `0`, `"0"`, `false`, or `"false"` if another field under validation is equal to a specified value.

#### different:_field_

The field under validation must have a different value than _field_.

#### digits:_value_

The integer under validation must have an exact length of _value_.

#### digits_between:_min_,_max_

The integer under validation must have a length between the given _min_ and _max_.

#### dimensions

The file under validation must be an image meeting the dimension constraints as specified by the rule's parameters:

```
1'avatar' => ['dimensions:min_width=100,min_height=200']

'avatar' => ['dimensions:min_width=100,min_height=200']
```

Available constraints are: _min_width_, _max_width_, _min_height_, _max_height_, _width_, _height_, _ratio_, _min_ratio_, _max_ratio_.

A _ratio_ constraint should be represented as width divided by height. This can be specified either by a fraction like `3/2` or a float like `1.5`:

```
1'avatar' => ['dimensions:ratio=3/2']

'avatar' => ['dimensions:ratio=3/2']
```

The _min_ratio_ and _max_ratio_ constraints may be used to define a range of acceptable aspect ratios:

```
1'avatar' => ['dimensions:min_ratio=1/2,max_ratio=3/2']

'avatar' => ['dimensions:min_ratio=1/2,max_ratio=3/2']
```

Since this rule requires several arguments, it is often more convenient to use the `Rule::dimensions` method to fluently construct the rule:

```
 1use Illuminate\Support\Facades\Validator;
 2use Illuminate\Validation\Rule;
 3 
 4Validator::make($data, [
 5    'avatar' => [
 6        'required',
 7        Rule::dimensions()
 8            ->maxWidth(1000)
 9            ->maxHeight(500)
10            ->ratio(3 / 2),
11    ],
12]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

Validator::make($data, [
    'avatar' => [
        'required',
        Rule::dimensions()
            ->maxWidth(1000)
            ->maxHeight(500)
            ->ratio(3 / 2),
    ],
]);
```

You may also use the `minRatio`, `maxRatio`, and `ratioBetween` methods to fluently define ratio constraints:

```
1Rule::dimensions()->ratioBetween(min: 1 / 2, max: 3 / 2);

Rule::dimensions()->ratioBetween(min: 1 / 2, max: 3 / 2);
```

#### distinct

When validating arrays, the field under validation must not have any duplicate values:

```
1'foo.*.id' => ['distinct']

'foo.*.id' => ['distinct']
```

Distinct uses loose variable comparisons by default. To use strict comparisons, you may add the `strict` parameter to your validation rule definition:

```
1'foo.*.id' => ['distinct:strict']

'foo.*.id' => ['distinct:strict']
```

You may add `ignore_case` to the validation rule's arguments to make the rule ignore capitalization differences:

```
1'foo.*.id' => ['distinct:ignore_case']

'foo.*.id' => ['distinct:ignore_case']
```

Alternatively, you may use the `Rule::distinct` method, chaining the `strict` and `ignoreCase` methods as needed:

```
1'foo.*.id' => [Rule::distinct()->strict()->ignoreCase()],

'foo.*.id' => [Rule::distinct()->strict()->ignoreCase()],
```

#### doesnt_start_with:_foo_,_bar_,...

The field under validation must not start with one of the given values.

#### doesnt_end_with:_foo_,_bar_,...

The field under validation must not end with one of the given values.

#### email

The field under validation must be formatted as an email address. This validation rule utilizes the [egulias/email-validator](https://github.com/egulias/EmailValidator) package for validating the email address. By default, the `RFCValidation` validator is applied, but you can apply other validation styles as well:

```
1'email' => ['email:rfc,dns']

'email' => ['email:rfc,dns']
```

The example above will apply the `RFCValidation` and `DNSCheckValidation` validations. Here's a full list of validation styles you can apply:

- `rfc`: `RFCValidation` - Validate the email address according to [supported RFCs](https://github.com/egulias/EmailValidator?tab=readme-ov-file#supported-rfcs).
- `strict`: `NoRFCWarningsValidation` - Validate the email according to [supported RFCs](https://github.com/egulias/EmailValidator?tab=readme-ov-file#supported-rfcs), failing when warnings are found (e.g. trailing periods and multiple consecutive periods).
- `dns`: `DNSCheckValidation` - Ensure the email address's domain has a valid MX record.
- `spoof`: `SpoofCheckValidation` - Ensure the email address does not contain homograph or deceptive Unicode characters.
- `filter`: `FilterEmailValidation` - Ensure the email address is valid according to PHP's `filter_var` function.
- `filter_unicode`: `FilterEmailValidation::unicode()` - Ensure the email address is valid according to PHP's `filter_var` function, allowing some Unicode characters.

For convenience, email validation rules may be built using the fluent rule builder:

```
 1use Illuminate\Validation\Rule;
 2 
 3$request->validate([
 4    'email' => [
 5        'required',
 6        Rule::email()
 7            ->rfcCompliant(strict: false)
 8            ->validateMxRecord()
 9            ->preventSpoofing()
10    ],
11]);

use Illuminate\Validation\Rule;

$request->validate([
    'email' => [
        'required',
        Rule::email()
            ->rfcCompliant(strict: false)
            ->validateMxRecord()
            ->preventSpoofing()
    ],
]);
```

The `dns` validator performs a real DNS lookup to confirm the address's domain has a valid MX record. It does not determine whether an individual mailbox exists.

Since your tests should not rely on live DNS lookups, you may use the `Validator::fakeDnsLookups` method to fake DNS lookups while any other requested validations, such as `rfc`, continue to run:

```
1use Illuminate\Support\Facades\Validator;
2 
3Validator::fakeDnsLookups();

use Illuminate\Support\Facades\Validator;

Validator::fakeDnsLookups();
```

This allows your application to keep using its existing validation rules while testing:

```
1'email' => ['required', 'email:rfc,dns'],

'email' => ['required', 'email:rfc,dns'],
```

 The `dns` and `spoof` validators require the PHP `intl` extension.

#### encoding:_encoding_type_

The field under validation must match the specified character encoding. This rule uses PHP's `mb_check_encoding` function to verify the encoding of the given file or string value. For convenience, the `encoding` rule may be constructed using Laravel's fluent file rule builder:

```
 1use Illuminate\Support\Facades\Validator;
 2use Illuminate\Validation\Rules\File;
 3 
 4Validator::validate($input, [
 5    'attachment' => [
 6        'required',
 7        File::types(['csv'])
 8            ->encoding('utf-8'),
 9    ],
10]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File;

Validator::validate($input, [
    'attachment' => [
        'required',
        File::types(['csv'])
            ->encoding('utf-8'),
    ],
]);
```

#### ends_with:_foo_,_bar_,...

The field under validation must end with one of the given values.

#### enum

The `Enum` rule is a class-based rule that validates whether the field under validation contains a valid enum value. The `Enum` rule accepts the name of the enum as its only constructor argument. When validating primitive values, a backed Enum should be provided to the `Enum` rule:

```
1use App\Enums\ServerStatus;
2use Illuminate\Validation\Rule;
3 
4$request->validate([
5    'status' => [Rule::enum(ServerStatus::class)],
6]);

use App\Enums\ServerStatus;
use Illuminate\Validation\Rule;

$request->validate([
    'status' => [Rule::enum(ServerStatus::class)],
]);
```

The `Enum` rule's `only` and `except` methods may be used to limit which enum cases should be considered valid:

```
1Rule::enum(ServerStatus::class)
2    ->only([ServerStatus::Pending, ServerStatus::Active]);
3 
4Rule::enum(ServerStatus::class)
5    ->except([ServerStatus::Pending, ServerStatus::Active]);

Rule::enum(ServerStatus::class)
    ->only([ServerStatus::Pending, ServerStatus::Active]);

Rule::enum(ServerStatus::class)
    ->except([ServerStatus::Pending, ServerStatus::Active]);
```

The `when` method may be used to conditionally modify the `Enum` rule:

```
1use Illuminate\Support\Facades\Auth;
2use Illuminate\Validation\Rule;
3 
4Rule::enum(ServerStatus::class)
5    ->when(
6        Auth::user()->isAdmin(),
7        fn ($rule) => $rule->only(...),
8        fn ($rule) => $rule->only(...),
9    );

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

Rule::enum(ServerStatus::class)
    ->when(
        Auth::user()->isAdmin(),
        fn ($rule) => $rule->only(...),
        fn ($rule) => $rule->only(...),
    );
```

#### exclude

The field under validation will be excluded from the request data returned by the `validate` and `validated` methods.

#### exclude_if:_anotherfield_,_value_

The field under validation will be excluded from the request data returned by the `validate` and `validated` methods if the _anotherfield_ field is equal to _value_.

If complex conditional exclusion logic is required, you may utilize the `Rule::excludeIf` method. This method accepts a boolean or a closure. When given a closure, the closure should return `true` or `false` to indicate if the field under validation should be excluded:

```
 1use Illuminate\Support\Facades\Validator;
 2use Illuminate\Validation\Rule;
 3 
 4Validator::make($request->all(), [
 5    'role_id' => [Rule::excludeIf($request->user()->is_admin)],
 6]);
 7 
 8Validator::make($request->all(), [
 9    'role_id' => [Rule::excludeIf(fn () => $request->user()->is_admin)],
10]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

Validator::make($request->all(), [
    'role_id' => [Rule::excludeIf($request->user()->is_admin)],
]);

Validator::make($request->all(), [
    'role_id' => [Rule::excludeIf(fn () => $request->user()->is_admin)],
]);
```

#### exclude_unless:_anotherfield_,_value_

The field under validation will be excluded from the request data returned by the `validate` and `validated` methods unless _anotherfield_'s field is equal to _value_. If _value_ is `null` (`exclude_unless:name,null`), the field under validation will be excluded unless the comparison field is `null` or the comparison field is missing from the request data.

If complex conditional exclusion logic is required, you may utilize the `Rule::excludeUnless` method. This method accepts a boolean or a closure. When given a closure, the closure should return `true` or `false` to indicate if the field under validation should not be excluded:

```
 1use Illuminate\Support\Facades\Validator;
 2use Illuminate\Validation\Rule;
 3 
 4Validator::make($request->all(), [
 5    'role_id' => [Rule::excludeUnless($request->user()->is_admin)],
 6]);
 7 
 8Validator::make($request->all(), [
 9    'role_id' => [Rule::excludeUnless(fn () => $request->user()->is_admin)],
10]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

Validator::make($request->all(), [
    'role_id' => [Rule::excludeUnless($request->user()->is_admin)],
]);

Validator::make($request->all(), [
    'role_id' => [Rule::excludeUnless(fn () => $request->user()->is_admin)],
]);
```

#### exclude_with:_anotherfield_

The field under validation will be excluded from the request data returned by the `validate` and `validated` methods if the _anotherfield_ field is present.

#### exclude_without:_anotherfield_

The field under validation will be excluded from the request data returned by the `validate` and `validated` methods if the _anotherfield_ field is not present.

#### exists:_table_,_column_

The field under validation must exist in a given database table.

#### Basic Usage of Exists Rule

```
1'state' => ['exists:states']

'state' => ['exists:states']
```

If the `column` option is not specified, the field name will be used. So, in this case, the rule will validate that the `states` database table contains a record with a `state` column value matching the request's `state` attribute value.

#### Specifying a Custom Column Name

You may explicitly specify the database column name that should be used by the validation rule by placing it after the database table name:

```
1'state' => ['exists:states,abbreviation']

'state' => ['exists:states,abbreviation']
```

Occasionally, you may need to specify a specific database connection to be used for the `exists` query. You can accomplish this by prepending the connection name to the table name:

```
1'email' => ['exists:connection.staff,email']

'email' => ['exists:connection.staff,email']
```

Instead of specifying the table name directly, you may specify the Eloquent model which should be used to determine the table name:

```
1'user_id' => ['exists:App\Models\User,id']

'user_id' => ['exists:App\Models\User,id']
```

If you would like to customize the query executed by the validation rule, you may use the `Rule` class to fluently define the rule.

```
 1use Illuminate\Database\Query\Builder;
 2use Illuminate\Support\Facades\Validator;
 3use Illuminate\Validation\Rule;
 4 
 5Validator::make($data, [
 6    'email' => [
 7        'required',
 8        Rule::exists('staff')->where(function (Builder $query) {
 9            $query->where('account_id', 1);
10        }),
11    ],
12]);

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

Validator::make($data, [
    'email' => [
        'required',
        Rule::exists('staff')->where(function (Builder $query) {
            $query->where('account_id', 1);
        }),
    ],
]);
```

You may explicitly specify the database column name that should be used by the `exists` rule generated by the `Rule::exists` method by providing the column name as the second argument to the `exists` method:

```
1'state' => [Rule::exists('states', 'abbreviation')],

'state' => [Rule::exists('states', 'abbreviation')],
```

Sometimes, you may wish to validate whether an array of values exists in the database. You can do so by adding both the `exists` and array rules to the field being validated:

```
1'states' => ['array', Rule::exists('states', 'abbreviation')],

'states' => ['array', Rule::exists('states', 'abbreviation')],
```

When both of these rules are assigned to a field, Laravel will automatically build a single query to determine if all of the given values exist in the specified table.

#### extensions:_foo_,_bar_,...

The file under validation must have a user-assigned extension corresponding to one of the listed extensions:

```
1'photo' => ['required', 'extensions:jpg,png'],

'photo' => ['required', 'extensions:jpg,png'],
```

 You should never rely on validating a file by its user-assigned extension alone. This rule should typically always be used in combination with the mimes or mimetypes rules.

#### file

The field under validation must be a successfully uploaded file.

#### filled

The field under validation must not be empty when it is present.

#### gt:_field_

The field under validation must be greater than the given _field_ or _value_. The two fields must be of the same type. Strings, numerics, arrays, and files are evaluated using the same conventions as the size rule.

#### gte:_field_

The field under validation must be greater than or equal to the given _field_ or _value_. The two fields must be of the same type. Strings, numerics, arrays, and files are evaluated using the same conventions as the size rule.

#### hex_color

The field under validation must contain a valid color value in [hexadecimal](https://developer.mozilla.org/en-US/docs/Web/CSS/hex-color) format.

#### image

The file under validation must be an image (jpg, jpeg, png, bmp, gif, webp, avif, heic, or heif).

 By default, the image rule does not allow SVG files due to the possibility of XSS vulnerabilities. If you need to allow SVG files, you may provide the `allow_svg` directive to the `image` rule (`image:allow_svg`).

#### in:_foo_,_bar_,...

The field under validation must be included in the given list of values. Since this rule often requires you to `implode` an array, the `Rule::in` method may be used to fluently construct the rule:

```
1use Illuminate\Support\Facades\Validator;
2use Illuminate\Validation\Rule;
3 
4Validator::make($data, [
5    'zones' => [
6        'required',
7        Rule::in(['first-zone', 'second-zone']),
8    ],
9]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

Validator::make($data, [
    'zones' => [
        'required',
        Rule::in(['first-zone', 'second-zone']),
    ],
]);
```

When the `in` rule is combined with the `array` rule, each value in the input array must be present within the list of values provided to the `in` rule. In the following example, the `LAS` airport code in the input array is invalid since it is not contained in the list of airports provided to the `in` rule:

```
 1use Illuminate\Support\Facades\Validator;
 2use Illuminate\Validation\Rule;
 3 
 4$input = [
 5    'airports' => ['NYC', 'LAS'],
 6];
 7 
 8Validator::make($input, [
 9    'airports' => [
10        'required',
11        'array',
12    ],
13    'airports.*' => Rule::in(['NYC', 'LIT']),
14]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

$input = [
    'airports' => ['NYC', 'LAS'],
];

Validator::make($input, [
    'airports' => [
        'required',
        'array',
    ],
    'airports.*' => Rule::in(['NYC', 'LIT']),
]);
```

#### in_array:_anotherfield_.*

The field under validation must exist in _anotherfield_'s values.

#### in_array_keys:_value_.*

The field under validation must be an array having at least one of the given _values_ as a key within the array:

```
1'config' => ['array', 'in_array_keys:timezone']

'config' => ['array', 'in_array_keys:timezone']
```

#### integer

The field under validation must be an integer.

You may use the `strict` parameter to only consider the field valid if its type is `integer`. Strings with integer values will be considered invalid:

```
1'age' => ['integer:strict']

'age' => ['integer:strict']
```

 This validation rule does not verify that the input is of the "integer" variable type, only that the input is of a type accepted by PHP's `FILTER_VALIDATE_INT` rule. If you need to validate the input as being a number please use this rule in combination with the `numeric` validation rule.

#### ip

The field under validation must be an IP address.

You may also use the `Rule::ip` method, optionally restricting the IP version to `4` or `6`:

```
1'ip_address' => [Rule::ip()],
2'ipv4_address' => [Rule::ip()->version(4)],
3'ipv6_address' => [Rule::ip()->version(6)],

'ip_address' => [Rule::ip()],
'ipv4_address' => [Rule::ip()->version(4)],
'ipv6_address' => [Rule::ip()->version(6)],
```

#### ipv4

The field under validation must be an IPv4 address.

#### ipv6

The field under validation must be an IPv6 address.

#### json

The field under validation must be a valid JSON string.

#### lt:_field_

The field under validation must be less than the given _field_. The two fields must be of the same type. Strings, numerics, arrays, and files are evaluated using the same conventions as the size rule.

#### lte:_field_

The field under validation must be less than or equal to the given _field_. The two fields must be of the same type. Strings, numerics, arrays, and files are evaluated using the same conventions as the size rule.

#### lowercase

The field under validation must be lowercase.

#### list

The field under validation must be an array that is a list. An array is considered a list if its keys consist of consecutive numbers from 0 to `count($array) - 1`.

#### mac_address

The field under validation must be a MAC address.

#### max:_value_

The field under validation must be less than or equal to a maximum _value_. Strings, numerics, arrays, and files are evaluated in the same fashion as the size rule.

#### max_digits:_value_

The integer under validation must have a maximum length of _value_.

#### mimetypes:_text/plain_,...

The file under validation must match one of the given MIME types:

```
1'video' => ['mimetypes:video/avi,video/mpeg,video/quicktime'],
2 
3'media' => ['mimetypes:image/*,video/*'],

'video' => ['mimetypes:video/avi,video/mpeg,video/quicktime'],

'media' => ['mimetypes:image/*,video/*'],
```

To determine the MIME type of the uploaded file, the file's contents will be read and the framework will attempt to guess the MIME type, which may be different from the client's provided MIME type.

#### mimes:_foo_,_bar_,...

The file under validation must have a MIME type corresponding to one of the listed extensions:

```
1'photo' => ['mimes:jpg,bmp,png']

'photo' => ['mimes:jpg,bmp,png']
```

Even though you only need to specify the extensions, this rule actually validates the MIME type of the file by reading the file's contents and guessing its MIME type. A full listing of MIME types and their corresponding extensions may be found at the following location:

[https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types](https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types)

#### MIME Types and Extensions

This validation rule does not verify agreement between the MIME type and the extension the user assigned to the file. For example, the `mimes:png` validation rule would consider a file containing valid PNG content to be a valid PNG image, even if the file is named `photo.txt`. If you would like to validate the user-assigned extension of the file, you may use the extensions rule.

#### min:_value_

The field under validation must have a minimum _value_. Strings, numerics, arrays, and files are evaluated in the same fashion as the size rule.

#### min_digits:_value_

The integer under validation must have a minimum length of _value_.

#### multiple_of:_value_

The field under validation must be a multiple of _value_.

#### missing

The field under validation must not be present in the input data.

#### missing_if:_anotherfield_,_value_,...

The field under validation must not be present if the _anotherfield_ field is equal to any _value_.

#### missing_unless:_anotherfield_,_value_

The field under validation must not be present unless the _anotherfield_ field is equal to any _value_.

#### missing_with:_foo_,_bar_,...

The field under validation must not be present _only if_ any of the other specified fields are present.

#### missing_with_all:_foo_,_bar_,...

The field under validation must not be present _only if_ all of the other specified fields are present.

#### not_in:_foo_,_bar_,...

The field under validation must not be included in the given list of values. The `Rule::notIn` method may be used to fluently construct the rule:

```
1use Illuminate\Validation\Rule;
2 
3Validator::make($data, [
4    'toppings' => [
5        'required',
6        Rule::notIn(['sprinkles', 'cherries']),
7    ],
8]);

use Illuminate\Validation\Rule;

Validator::make($data, [
    'toppings' => [
        'required',
        Rule::notIn(['sprinkles', 'cherries']),
    ],
]);
```

#### not_regex:_pattern_

The field under validation must not match the given regular expression.

Internally, this rule uses the PHP `preg_match` function. The pattern specified should obey the same formatting required by `preg_match` and thus also include valid delimiters. For example: `'email' => ['not_regex:/^.+$/i']`.

#### nullable

The field under validation may be `null`.

#### numeric

The field under validation must be [numeric](https://www.php.net/manual/en/function.is-numeric.php).

You may use the `strict` parameter to only consider the field valid if its value is an integer or float type. Numeric strings will be considered invalid:

```
1'amount' => ['numeric:strict']

'amount' => ['numeric:strict']
```

#### present

The field under validation must exist in the input data.

#### present_if:_anotherfield_,_value_,...

The field under validation must be present if the _anotherfield_ field is equal to any _value_.

#### present_unless:_anotherfield_,_value_

The field under validation must be present unless the _anotherfield_ field is equal to any _value_.

#### present_with:_foo_,_bar_,...

The field under validation must be present _only if_ any of the other specified fields are present.

#### present_with_all:_foo_,_bar_,...

The field under validation must be present _only if_ all of the other specified fields are present.

#### prohibited

The field under validation must be missing or empty. A field is "empty" if it meets one of the following criteria:

- The value is `null`.
- The value is an empty string.
- The value is an empty array or empty `Countable` object.
- The value is an uploaded file with an empty path.

#### prohibited_if:_anotherfield_,_value_,...

The field under validation must be missing or empty if the _anotherfield_ field is equal to any _value_. A field is "empty" if it meets one of the following criteria:

- The value is `null`.
- The value is an empty string.
- The value is an empty array or empty `Countable` object.
- The value is an uploaded file with an empty path.

If complex conditional prohibition logic is required, you may utilize the `Rule::prohibitedIf` method. This method accepts a boolean or a closure. When given a closure, the closure should return `true` or `false` to indicate if the field under validation should be prohibited:

```
 1use Illuminate\Support\Facades\Validator;
 2use Illuminate\Validation\Rule;
 3 
 4Validator::make($request->all(), [
 5    'role_id' => [Rule::prohibitedIf($request->user()->is_admin)],
 6]);
 7 
 8Validator::make($request->all(), [
 9    'role_id' => [Rule::prohibitedIf(fn () => $request->user()->is_admin)],
10]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

Validator::make($request->all(), [
    'role_id' => [Rule::prohibitedIf($request->user()->is_admin)],
]);

Validator::make($request->all(), [
    'role_id' => [Rule::prohibitedIf(fn () => $request->user()->is_admin)],
]);
```

#### prohibited_if_accepted:_anotherfield_,...

The field under validation must be missing or empty if the _anotherfield_ field is equal to `"yes"`, `"on"`, `1`, `"1"`, `true`, or `"true"`.

#### prohibited_if_declined:_anotherfield_,...

The field under validation must be missing or empty if the _anotherfield_ field is equal to `"no"`, `"off"`, `0`, `"0"`, `false`, or `"false"`.

#### prohibited_unless:_anotherfield_,_value_,...

The field under validation must be missing or empty unless the _anotherfield_ field is equal to any _value_. A field is "empty" if it meets one of the following criteria:

- The value is `null`.
- The value is an empty string.
- The value is an empty array or empty `Countable` object.
- The value is an uploaded file with an empty path.

If complex conditional prohibition logic is required, you may utilize the `Rule::prohibitedUnless` method. This method accepts a boolean or a closure. When given a closure, the closure should return `true` or `false` to indicate if the field under validation should not be prohibited:

```
 1use Illuminate\Support\Facades\Validator;
 2use Illuminate\Validation\Rule;
 3 
 4Validator::make($request->all(), [
 5    'role_id' => [Rule::prohibitedUnless($request->user()->is_admin)],
 6]);
 7 
 8Validator::make($request->all(), [
 9    'role_id' => [Rule::prohibitedUnless(fn () => $request->user()->is_admin)],
10]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

Validator::make($request->all(), [
    'role_id' => [Rule::prohibitedUnless($request->user()->is_admin)],
]);

Validator::make($request->all(), [
    'role_id' => [Rule::prohibitedUnless(fn () => $request->user()->is_admin)],
]);
```

#### prohibits:_anotherfield_,...

If the field under validation is not missing or empty, all fields in _anotherfield_ must be missing or empty. A field is "empty" if it meets one of the following criteria:

- The value is `null`.
- The value is an empty string.
- The value is an empty array or empty `Countable` object.
- The value is an uploaded file with an empty path.

#### regex:_pattern_

The field under validation must match the given regular expression.

Internally, this rule uses the PHP `preg_match` function. The pattern specified should obey the same formatting required by `preg_match` and thus also include valid delimiters. For example: `'email' => ['regex:/^.+@.+$/i']`.

#### required

The field under validation must be present in the input data and not empty. A field is "empty" if it meets one of the following criteria:

- The value is `null`.
- The value is an empty string.
- The value is an empty array or empty `Countable` object.
- The value is an uploaded file with no path.

#### required_if:_anotherfield_,_value_,...

The field under validation must be present and not empty if the _anotherfield_ field is equal to any _value_.

If you would like to construct a more complex condition for the `required_if` rule, you may use the `Rule::requiredIf` method. This method accepts a boolean or a closure. When passed a closure, the closure should return `true` or `false` to indicate if the field under validation is required:

```
 1use Illuminate\Support\Facades\Validator;
 2use Illuminate\Validation\Rule;
 3 
 4Validator::make($request->all(), [
 5    'role_id' => [Rule::requiredIf($request->user()->is_admin)],
 6]);
 7 
 8Validator::make($request->all(), [
 9    'role_id' => [Rule::requiredIf(fn () => $request->user()->is_admin)],
10]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

Validator::make($request->all(), [
    'role_id' => [Rule::requiredIf($request->user()->is_admin)],
]);

Validator::make($request->all(), [
    'role_id' => [Rule::requiredIf(fn () => $request->user()->is_admin)],
]);
```

#### required_if_accepted:_anotherfield_,...

The field under validation must be present and not empty if the _anotherfield_ field is equal to `"yes"`, `"on"`, `1`, `"1"`, `true`, or `"true"`.

#### required_if_declined:_anotherfield_,...

The field under validation must be present and not empty if the _anotherfield_ field is equal to `"no"`, `"off"`, `0`, `"0"`, `false`, or `"false"`.

#### required_unless:_anotherfield_,_value_,...

The field under validation must be present and not empty unless the _anotherfield_ field is equal to any _value_. This also means _anotherfield_ must be present in the request data unless _value_ is `null`. If _value_ is `null` (`required_unless:name,null`), the field under validation will be required unless the comparison field is `null` or the comparison field is missing from the request data.

If you would like to construct a more complex condition for the `required_unless` rule, you may use the `Rule::requiredUnless` method. This method accepts a boolean or a closure. When passed a closure, the closure should return `true` or `false` to indicate if the field under validation is not required:

```
 1use Illuminate\Support\Facades\Validator;
 2use Illuminate\Validation\Rule;
 3 
 4Validator::make($request->all(), [
 5    'role_id' => [Rule::requiredUnless($request->user()->is_admin)],
 6]);
 7 
 8Validator::make($request->all(), [
 9    'role_id' => [Rule::requiredUnless(fn () => $request->user()->is_admin)],
10]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

Validator::make($request->all(), [
    'role_id' => [Rule::requiredUnless($request->user()->is_admin)],
]);

Validator::make($request->all(), [
    'role_id' => [Rule::requiredUnless(fn () => $request->user()->is_admin)],
]);
```

#### required_with:_foo_,_bar_,...

The field under validation must be present and not empty _only if_ any of the other specified fields are present and not empty.

#### required_with_all:_foo_,_bar_,...

The field under validation must be present and not empty _only if_ all of the other specified fields are present and not empty.

#### required_without:_foo_,_bar_,...

The field under validation must be present and not empty _only when_ any of the other specified fields are empty or not present.

#### required_without_all:_foo_,_bar_,...

The field under validation must be present and not empty _only when_ all of the other specified fields are empty or not present.

#### required_array_keys:_foo_,_bar_,...

The field under validation must be an array and must contain at least the specified keys.

#### same:_field_

The given _field_ must match the field under validation.

#### size:_value_

The field under validation must have a size matching the given _value_. For string data, _value_ corresponds to the number of characters. For numeric data, _value_ corresponds to a given integer value (the attribute must also have the `numeric` or `integer` rule). For an array, _size_ corresponds to the `count` of the array. For files, _size_ corresponds to the file size in kilobytes. Let's look at some examples:

```
 1// Validate that a string is exactly 12 characters long...
 2'title' => ['size:12'];
 3 
 4// Validate that a provided integer equals 10...
 5'seats' => ['integer', 'size:10'];
 6 
 7// Validate that an array has exactly 5 elements...
 8'tags' => ['array', 'size:5'];
 9 
10// Validate that an uploaded file is exactly 512 kilobytes...
11'image' => ['file', 'size:512'];

// Validate that a string is exactly 12 characters long...
'title' => ['size:12'];

// Validate that a provided integer equals 10...
'seats' => ['integer', 'size:10'];

// Validate that an array has exactly 5 elements...
'tags' => ['array', 'size:5'];

// Validate that an uploaded file is exactly 512 kilobytes...
'image' => ['file', 'size:512'];
```

#### starts_with:_foo_,_bar_,...

The field under validation must start with one of the given values.

#### string

The field under validation must be a string. If you would like to allow the field to also be `null`, you should assign the `nullable` rule to the field.

For convenience, string validation rules may also be constructed using the fluent `Rule::string()` rule builder:

```
1use Illuminate\Validation\Rule;
2 
3'title' => [
4    'required',
5    Rule::string()
6        ->min(3)
7        ->max(255)
8        ->alphaDash(ascii: true),
9],

use Illuminate\Validation\Rule;

'title' => [
    'required',
    Rule::string()
        ->min(3)
        ->max(255)
        ->alphaDash(ascii: true),
],
```

The string rule builder provides methods for common string constraints, including `alpha`, `alphaDash`, `alphaNumeric`, `ascii`, `between`, `doesntEndWith`, `doesntStartWith`, `endsWith`, `exactly`, `lowercase`, `max`, `min`, `startsWith`, and `uppercase`. Since the rule builder is conditionable, you may also use the `when` and `unless` methods to conditionally apply constraints.

#### timezone

The field under validation must be a valid timezone identifier according to the `DateTimeZone::listIdentifiers` method.

The arguments [accepted by the `DateTimeZone::listIdentifiers` method](https://www.php.net/manual/en/datetimezone.listidentifiers.php) may also be provided to this validation rule:

```
1'timezone' => ['required', 'timezone:all'];
2 
3'timezone' => ['required', 'timezone:Africa'];
4 
5'timezone' => ['required', 'timezone:per_country,US'];

'timezone' => ['required', 'timezone:all'];

'timezone' => ['required', 'timezone:Africa'];

'timezone' => ['required', 'timezone:per_country,US'];
```

Alternatively, you may use the `Rule::timezone` method, passing any `listIdentifiers` arguments as an array:

```
1'timezone' => [Rule::timezone(['per_country', 'US'])],

'timezone' => [Rule::timezone(['per_country', 'US'])],
```

#### unique:_table_,_column_

The field under validation must not exist within the given database table.

**Specifying a Custom Table / Column Name:**

Instead of specifying the table name directly, you may specify the Eloquent model which should be used to determine the table name:

```
1'email' => ['unique:App\Models\User,email_address']

'email' => ['unique:App\Models\User,email_address']
```

The `column` option may be used to specify the field's corresponding database column. If the `column` option is not specified, the name of the field under validation will be used.

```
1'email' => ['unique:users,email_address']

'email' => ['unique:users,email_address']
```

**Specifying a Custom Database Connection**

Occasionally, you may need to set a custom connection for database queries made by the Validator. To accomplish this, you may prepend the connection name to the table name:

```
1'email' => ['unique:connection.users,email_address']

'email' => ['unique:connection.users,email_address']
```

**Forcing a Unique Rule to Ignore a Given ID:**

Sometimes, you may wish to ignore a given ID during unique validation. For example, consider an "update profile" screen that includes the user's name, email address, and location. You will probably want to verify that the email address is unique. However, if the user only changes the name field and not the email field, you do not want a validation error to be thrown because the user is already the owner of the email address in question.

To instruct the validator to ignore the user's ID, we'll use the `Rule` class to fluently define the rule.

```
1use Illuminate\Support\Facades\Validator;
2use Illuminate\Validation\Rule;
3 
4Validator::make($data, [
5    'email' => [
6        'required',
7        Rule::unique('users')->ignore($user->id),
8    ],
9]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

Validator::make($data, [
    'email' => [
        'required',
        Rule::unique('users')->ignore($user->id),
    ],
]);
```

 You should never pass any user controlled request input into the `ignore` method. Instead, you should only pass a system generated unique ID such as an auto-incrementing ID or UUID from an Eloquent model instance. Otherwise, your application will be vulnerable to an SQL injection attack.

Instead of passing the model key's value to the `ignore` method, you may also pass the entire model instance. Laravel will automatically extract the key from the model:

```
1Rule::unique('users')->ignore($user);

Rule::unique('users')->ignore($user);
```

If your table uses a primary key column name other than `id`, you may specify the name of the column when calling the `ignore` method:

```
1Rule::unique('users')->ignore($user->id, 'user_id');

Rule::unique('users')->ignore($user->id, 'user_id');
```

By default, the `unique` rule will check the uniqueness of the column matching the name of the attribute being validated. However, you may pass a different column name as the second argument to the `unique` method:

```
1Rule::unique('users', 'email_address')->ignore($user->id);

Rule::unique('users', 'email_address')->ignore($user->id);
```

**Adding Additional Where Clauses:**

You may specify additional query conditions by customizing the query using the `where` method. For example, let's add a query condition that scopes the query to only search records that have an `account_id` column value of `1`:

```
1'email' => Rule::unique('users')->where(fn (Builder $query) => $query->where('account_id', 1))

'email' => Rule::unique('users')->where(fn (Builder $query) => $query->where('account_id', 1))
```

**Ignoring Soft Deleted Records in Unique Checks:**

By default, the unique rule includes soft deleted records when determining uniqueness. To exclude soft deleted records from the uniqueness check, you may invoke the `withoutTrashed` method:

```
1Rule::unique('users')->withoutTrashed();

Rule::unique('users')->withoutTrashed();
```

If your model uses a column name other than `deleted_at` for soft deleted records, you may provide the column name when invoking the `withoutTrashed` method:

```
1Rule::unique('users')->withoutTrashed('was_deleted_at');

Rule::unique('users')->withoutTrashed('was_deleted_at');
```

#### uppercase

The field under validation must be uppercase.

#### url

The field under validation must be a valid URL.

If you would like to specify the URL protocols that should be considered valid, you may pass the protocols as validation rule parameters:

```
1'url' => ['url:http,https'],
2 
3'game' => ['url:minecraft,steam'],

'url' => ['url:http,https'],

'game' => ['url:minecraft,steam'],
```

Alternatively, you may use the `Rule::url` method, specifying the allowed protocols via the `protocols` method:

```
1'url' => [Rule::url()->protocols(['http', 'https'])],

'url' => [Rule::url()->protocols(['http', 'https'])],
```

#### ulid

The field under validation must be a valid [Universally Unique Lexicographically Sortable Identifier](https://github.com/ulid/spec) (ULID).

#### uuid

The field under validation must be a valid RFC 9562 (version 1, 3, 4, 5, 6, 7, or 8) universally unique identifier (UUID).

You may also validate that the given UUID matches a UUID specification by version:

```
1'uuid' => ['uuid:4']

'uuid' => ['uuid:4']
```

Alternatively, you may use the `Rule::uuid` method and specify the UUID version via the `version` method:

```
1'uuid' => [Rule::uuid()->version(4)],

'uuid' => [Rule::uuid()->version(4)],
```

## Conditionally Adding Rules

#### Skipping Validation When Fields Have Certain Values

You may occasionally wish to not validate a given field if another field has a given value. You may accomplish this using the `exclude_if` validation rule. In this example, the `appointment_date` and `doctor_name` fields will not be validated if the `has_appointment` field has a value of `false`:

```
1use Illuminate\Support\Facades\Validator;
2 
3$validator = Validator::make($data, [
4    'has_appointment' => ['required', 'boolean'],
5    'appointment_date' => ['exclude_if:has_appointment,false', 'required', 'date'],
6    'doctor_name' => ['exclude_if:has_appointment,false', 'required', 'string'],
7]);

use Illuminate\Support\Facades\Validator;

$validator = Validator::make($data, [
    'has_appointment' => ['required', 'boolean'],
    'appointment_date' => ['exclude_if:has_appointment,false', 'required', 'date'],
    'doctor_name' => ['exclude_if:has_appointment,false', 'required', 'string'],
]);
```

Alternatively, you may use the `exclude_unless` rule to not validate a given field unless another field has a given value:

```
1$validator = Validator::make($data, [
2    'has_appointment' => ['required', 'boolean'],
3    'appointment_date' => ['exclude_unless:has_appointment,true', 'required', 'date'],
4    'doctor_name' => ['exclude_unless:has_appointment,true', 'required', 'string'],
5]);

$validator = Validator::make($data, [
    'has_appointment' => ['required', 'boolean'],
    'appointment_date' => ['exclude_unless:has_appointment,true', 'required', 'date'],
    'doctor_name' => ['exclude_unless:has_appointment,true', 'required', 'string'],
]);
```

#### Validating When Present

In some situations, you may wish to run validation checks against a field **only** if that field is present in the data being validated. To quickly accomplish this, add the `sometimes` rule to your rule list:

```
1$validator = Validator::make($data, [
2    'email' => ['sometimes', 'required', 'email'],
3]);

$validator = Validator::make($data, [
    'email' => ['sometimes', 'required', 'email'],
]);
```

In the example above, the `email` field will only be validated if it is present in the `$data` array.

 If you are attempting to validate a field that should always be present but may be empty, check out this note on optional fields.

#### Complex Conditional Validation

Sometimes you may wish to add validation rules based on more complex conditional logic. For example, you may wish to require a given field only if another field has a greater value than 100. Or, you may need two fields to have a given value only when another field is present. Adding these validation rules doesn't have to be a pain. First, create a `Validator` instance with your _static rules_ that never change:

```
1use Illuminate\Support\Facades\Validator;
2 
3$validator = Validator::make($request->all(), [
4    'email' => ['required', 'email'],
5    'games' => ['required', 'integer', 'min:0'],
6]);

use Illuminate\Support\Facades\Validator;

$validator = Validator::make($request->all(), [
    'email' => ['required', 'email'],
    'games' => ['required', 'integer', 'min:0'],
]);
```

Let's assume our web application is for game collectors. If a game collector registers with our application and they own more than 100 games, we want them to explain why they own so many games. For example, perhaps they run a game resale shop, or maybe they just enjoy collecting games. To conditionally add this requirement, we can use the `sometimes` method on the `Validator` instance.

```
1use Illuminate\Support\Fluent;
2 
3$validator->sometimes('reason', ['required', 'max:500'], function (Fluent $input) {
4    return $input->games >= 100;
5});

use Illuminate\Support\Fluent;

$validator->sometimes('reason', ['required', 'max:500'], function (Fluent $input) {
    return $input->games >= 100;
});
```

The first argument passed to the `sometimes` method is the name of the field we are conditionally validating. The second argument is a list of the rules we want to add. If the closure passed as the third argument returns `true`, the rules will be added. This method makes it a breeze to build complex conditional validations. You may even add conditional validations for several fields at once:

```
1$validator->sometimes(['reason', 'cost'], 'required', function (Fluent $input) {
2    return $input->games >= 100;
3});

$validator->sometimes(['reason', 'cost'], 'required', function (Fluent $input) {
    return $input->games >= 100;
});
```

 The `$input` parameter passed to your closure will be an instance of `Illuminate\Support\Fluent` and may be used to access your input and files under validation.

#### Complex Conditional Array Validation

Sometimes you may want to validate a field based on another field in the same nested array whose index you do not know. In these situations, you may allow your closure to receive a second argument which will be the current individual item in the array being validated:

```
 1$input = [
 2    'channels' => [
 3        [
 4            'type' => 'email',
 5            'address' => '[email protected]',
 6        ],
 7        [
 8            'type' => 'url',
 9            'address' => 'https://example.com',
10        ],
11    ],
12];
13 
14$validator->sometimes('channels.*.address', 'email', function (Fluent $input, Fluent $item) {
15    return $item->type === 'email';
16});
17 
18$validator->sometimes('channels.*.address', 'url', function (Fluent $input, Fluent $item) {
19    return $item->type !== 'email';
20});

$input = [
    'channels' => [
        [
            'type' => 'email',
            'address' => '[email protected]',
        ],
        [
            'type' => 'url',
            'address' => 'https://example.com',
        ],
    ],
];

$validator->sometimes('channels.*.address', 'email', function (Fluent $input, Fluent $item) {
    return $item->type === 'email';
});

$validator->sometimes('channels.*.address', 'url', function (Fluent $input, Fluent $item) {
    return $item->type !== 'email';
});
```

Like the `$input` parameter passed to the closure, the `$item` parameter is an instance of `Illuminate\Support\Fluent` when the attribute data is an array; otherwise, it is a string.

## Validating Arrays

As discussed in the array validation rule documentation, the `array` rule accepts a list of allowed array keys. If any additional keys are present within the array, validation will fail:

```
 1use Illuminate\Support\Facades\Validator;
 2 
 3$input = [
 4    'user' => [
 5        'name' => 'Taylor Otwell',
 6        'username' => 'taylorotwell',
 7        'admin' => true,
 8    ],
 9];
10 
11Validator::make($input, [
12    'user' => ['array:name,username'],
13]);

use Illuminate\Support\Facades\Validator;

$input = [
    'user' => [
        'name' => 'Taylor Otwell',
        'username' => 'taylorotwell',
        'admin' => true,
    ],
];

Validator::make($input, [
    'user' => ['array:name,username'],
]);
```

In general, you should always specify the array keys that are allowed to be present within your array. Otherwise, the validator's `validate` and `validated` methods will return all of the validated data, including the array and all of its keys, even if those keys were not validated by other nested array validation rules.

### Validating Nested Array Input

Validating nested array-based form input fields doesn't have to be a pain. You may use "dot notation" to validate attributes within an array. For example, if the incoming HTTP request contains a `photos[profile]` field, you may validate it like so:

```
1use Illuminate\Support\Facades\Validator;
2 
3$validator = Validator::make($request->all(), [
4    'photos.profile' => ['required', 'image'],
5]);

use Illuminate\Support\Facades\Validator;

$validator = Validator::make($request->all(), [
    'photos.profile' => ['required', 'image'],
]);
```

You may also validate each element of an array. For example, to validate that each email in a given array input field is unique, you may do the following:

```
1$validator = Validator::make($request->all(), [
2    'users.*.email' => ['email', 'unique:users'],
3    'users.*.first_name' => ['required_with:users.*.last_name'],
4]);

$validator = Validator::make($request->all(), [
    'users.*.email' => ['email', 'unique:users'],
    'users.*.first_name' => ['required_with:users.*.last_name'],
]);
```

Likewise, you may use the `*` character when specifying custom validation messages in your language files, making it a breeze to use a single validation message for array-based fields:

```
1'custom' => [
2    'users.*.email' => [
3        'unique' => 'Each user must have a unique email address',
4    ]
5],

'custom' => [
    'users.*.email' => [
        'unique' => 'Each user must have a unique email address',
    ]
],
```

#### Accessing Nested Array Data

Sometimes you may need to access the value for a given nested array element when assigning validation rules to the attribute. You may accomplish this using the `Rule::forEach` method. The `forEach` method accepts a closure that will be invoked for each iteration of the array attribute under validation and will receive the attribute's value and explicit, fully-expanded attribute name. The closure should return an array of rules to assign to the array element:

```
 1use App\Rules\HasPermission;
 2use Illuminate\Support\Facades\Validator;
 3use Illuminate\Validation\Rule;
 4 
 5$validator = Validator::make($request->all(), [
 6    'companies.*.id' => Rule::forEach(function (string|null $value, string $attribute) {
 7        return [
 8            Rule::exists(Company::class, 'id'),
 9            new HasPermission('manage-company', $value),
10        ];
11    }),
12]);

use App\Rules\HasPermission;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

$validator = Validator::make($request->all(), [
    'companies.*.id' => Rule::forEach(function (string|null $value, string $attribute) {
        return [
            Rule::exists(Company::class, 'id'),
            new HasPermission('manage-company', $value),
        ];
    }),
]);
```

### Error Message Indexes and Positions

When validating arrays, you may want to reference the index or position of a particular item that failed validation within the error message displayed by your application. To accomplish this, you may include the `:index` (starts from `0`), `:position` (starts from `1`), or `:ordinal-position` (starts from `1st`) placeholders within your custom validation message:

```
 1use Illuminate\Support\Facades\Validator;
 2 
 3$input = [
 4    'photos' => [
 5        [
 6            'name' => 'BeachVacation.jpg',
 7            'description' => 'A photo of my beach vacation!',
 8        ],
 9        [
10            'name' => 'GrandCanyon.jpg',
11            'description' => '',
12        ],
13    ],
14];
15 
16Validator::validate($input, [
17    'photos.*.description' => ['required'],
18], [
19    'photos.*.description.required' => 'Please describe photo #:position.',
20]);

use Illuminate\Support\Facades\Validator;

$input = [
    'photos' => [
        [
            'name' => 'BeachVacation.jpg',
            'description' => 'A photo of my beach vacation!',
        ],
        [
            'name' => 'GrandCanyon.jpg',
            'description' => '',
        ],
    ],
];

Validator::validate($input, [
    'photos.*.description' => ['required'],
], [
    'photos.*.description.required' => 'Please describe photo #:position.',
]);
```

Given the example above, validation will fail and the user will be presented with the following error of _"Please describe photo #2."_

If necessary, you may reference more deeply nested indexes and positions via `second-index`, `second-position`, `third-index`, `third-position`, etc.

```
1'photos.*.attributes.*.string' => 'Invalid attribute for photo #:second-position.',

'photos.*.attributes.*.string' => 'Invalid attribute for photo #:second-position.',
```

## Validating Files

Laravel provides a variety of validation rules that may be used to validate uploaded files, such as `mimes`, `image`, `min`, and `max`. While you are free to specify these rules individually when validating files, Laravel also offers a fluent file validation rule builder that you may find convenient:

```
 1use Illuminate\Support\Facades\Validator;
 2use Illuminate\Validation\Rules\File;
 3 
 4Validator::validate($input, [
 5    'attachment' => [
 6        'required',
 7        File::types(['mp3', 'wav'])
 8            ->min(1024)
 9            ->max(12 * 1024),
10    ],
11]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File;

Validator::validate($input, [
    'attachment' => [
        'required',
        File::types(['mp3', 'wav'])
            ->min(1024)
            ->max(12 * 1024),
    ],
]);
```

#### Validating File Types

Even though you only need to specify the extensions when invoking the `types` method, this method actually validates the MIME type of the file by reading the file's contents and guessing its MIME type. A full listing of MIME types and their corresponding extensions may be found at the following location:

[https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types](https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types)

#### Validating File Sizes

For convenience, minimum and maximum file sizes may be specified as a string with a suffix indicating the file size units. The `kb`, `mb`, `gb`, and `tb` suffixes are supported:

```
1File::types(['mp3', 'wav'])
2    ->min('1kb')
3    ->max('10mb');

File::types(['mp3', 'wav'])
    ->min('1kb')
    ->max('10mb');
```

#### Validating Image Files

If your application accepts images uploaded by your users, you may use the `File` rule's `image` constructor method to ensure that the file under validation is an image (jpg, jpeg, png, bmp, gif, webp, avif, heic, or heif).

In addition, the `dimensions` rule may be used to limit the dimensions of the image:

```
 1use Illuminate\Support\Facades\Validator;
 2use Illuminate\Validation\Rule;
 3use Illuminate\Validation\Rules\File;
 4 
 5Validator::validate($input, [
 6    'photo' => [
 7        'required',
 8        File::image()
 9            ->min(1024)
10            ->max(12 * 1024)
11            ->dimensions(Rule::dimensions()->maxWidth(1000)->maxHeight(500)),
12    ],
13]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

Validator::validate($input, [
    'photo' => [
        'required',
        File::image()
            ->min(1024)
            ->max(12 * 1024)
            ->dimensions(Rule::dimensions()->maxWidth(1000)->maxHeight(500)),
    ],
]);
```

 More information regarding validating image dimensions may be found in the dimension rule documentation.

 By default, the `image` rule does not allow SVG files due to the possibility of XSS vulnerabilities. If you need to allow SVG files, you may pass `allowSvg: true` to the `image` rule: `File::image(allowSvg: true)`.

#### Validating Image Dimensions

You may also validate the dimensions of an image. For example, to validate that an uploaded image is at least 1000 pixels wide and 500 pixels tall, you may use the `dimensions` rule:

```
1use Illuminate\Validation\Rule;
2use Illuminate\Validation\Rules\File;
3 
4File::image()->dimensions(
5    Rule::dimensions()
6        ->maxWidth(1000)
7        ->maxHeight(500)
8);

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

File::image()->dimensions(
    Rule::dimensions()
        ->maxWidth(1000)
        ->maxHeight(500)
);
```

 More information regarding validating image dimensions may be found in the dimension rule documentation.

## Validating Passwords

To ensure that passwords have an adequate level of complexity, you may use Laravel's `Password` rule object:

```
1use Illuminate\Support\Facades\Validator;
2use Illuminate\Validation\Rules\Password;
3 
4$validator = Validator::make($request->all(), [
5    'password' => ['required', 'confirmed', Password::min(8)],
6]);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

$validator = Validator::make($request->all(), [
    'password' => ['required', 'confirmed', Password::min(8)],
]);
```

The `Password` rule object allows you to easily customize the password complexity requirements for your application, such as specifying that passwords require at least one letter, number, symbol, or characters with mixed casing:

```
 1// Require at least 8 characters...
 2Password::min(8);
 3 
 4// Require at most 256 characters...
 5Password::min(16)->max(256);
 6 
 7// Require at least one letter...
 8Password::min(8)->letters();
 9 
10// Require at least one uppercase and one lowercase letter...
11Password::min(8)->mixedCase();
12 
13// Require at least one number...
14Password::min(8)->numbers();
15 
16// Require at least one symbol...
17Password::min(8)->symbols();

// Require at least 8 characters...
Password::min(8);

// Require at most 256 characters...
Password::min(16)->max(256);

// Require at least one letter...
Password::min(8)->letters();

// Require at least one uppercase and one lowercase letter...
Password::min(8)->mixedCase();

// Require at least one number...
Password::min(8)->numbers();

// Require at least one symbol...
Password::min(8)->symbols();
```

In addition, you may ensure that a password has not been compromised in a public password data breach leak using the `uncompromised` method:

```
1Password::min(8)->uncompromised();

Password::min(8)->uncompromised();
```

Internally, the `Password` rule object uses the [k-Anonymity](https://en.wikipedia.org/wiki/K-anonymity) model to determine if a password has been leaked via the [haveibeenpwned.com](https://haveibeenpwned.com) service without sacrificing the user's privacy or security.

By default, if a password appears at least once in a data leak, it will be considered compromised. You can customize this threshold using the first argument of the `uncompromised` method:

```
1// Ensure the password appears no more than 3 times in the same data leak...
2Password::min(8)->uncompromised(3);

// Ensure the password appears no more than 3 times in the same data leak...
Password::min(8)->uncompromised(3);
```

Of course, you may chain all the methods in the examples above:

```
1Password::min(8)
2    ->max(256)
3    ->letters()
4    ->mixedCase()
5    ->numbers()
6    ->symbols()
7    ->uncompromised();

Password::min(8)
    ->max(256)
    ->letters()
    ->mixedCase()
    ->numbers()
    ->symbols()
    ->uncompromised();
```

You may convert a `Password` rule object to a string suitable for the HTML `passwordrules` attribute using the `toPasswordRulesString` method:

```
1<input
2    type="password"
3    name="password"
4    autocomplete="new-password"
5    passwordrules="{{ Password::defaults()->toPasswordRulesString() }}"
6/>

<input
    type="password"
    name="password"
    autocomplete="new-password"
    passwordrules="{{ Password::defaults()->toPasswordRulesString() }}"
/>
```

#### Defining Default Password Rules

You may find it convenient to specify the default validation rules for passwords in a single location of your application. You can easily accomplish this using the `Password::defaults` method, which accepts a closure. The closure given to the `defaults` method should return the default configuration of the Password rule. Typically, the `defaults` rule should be called within the `boot` method of one of your application's service providers:

```
 1use Illuminate\Validation\Rules\Password;
 2 
 3/**
 4 * Bootstrap any application services.
 5 */
 6public function boot(): void
 7{
 8    Password::defaults(function () {
 9        $rule = Password::min(8);
10 
11        return $this->app->isProduction()
12            ? $rule->mixedCase()->uncompromised()
13            : $rule;
14    });
15}

use Illuminate\Validation\Rules\Password;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Password::defaults(function () {
        $rule = Password::min(8);

        return $this->app->isProduction()
            ? $rule->mixedCase()->uncompromised()
            : $rule;
    });
}
```

Then, when you would like to apply the default rules to a particular password undergoing validation, you may invoke the `defaults` method with no arguments:

```
1'password' => ['required', Password::defaults()],

'password' => ['required', Password::defaults()],
```

Occasionally, you may want to attach additional validation rules to your default password validation rules. You may use the `rules` method to accomplish this:

```
1use App\Rules\ZxcvbnRule;
2 
3Password::defaults(function () {
4    $rule = Password::min(8)->rules([new ZxcvbnRule]);
5 
6    // ...
7});

use App\Rules\ZxcvbnRule;

Password::defaults(function () {
    $rule = Password::min(8)->rules([new ZxcvbnRule]);

    // ...
});
```

## Custom Validation Rules

### Using Rule Objects

Laravel provides a variety of helpful validation rules; however, you may wish to specify some of your own. One method of registering custom validation rules is using rule objects. To generate a new rule object, you may use the `make:rule` Artisan command. Let's use this command to generate a rule that verifies a string is uppercase. Laravel will place the new rule in the `app/Rules` directory. If this directory does not exist, Laravel will create it when you execute the Artisan command to create your rule:

```
1php artisan make:rule Uppercase

php artisan make:rule Uppercase
```

Once the rule has been created, we are ready to define its behavior. A rule object contains a single method: `validate`. This method receives the attribute name, its value, and a callback that should be invoked on failure with the validation error message:

```
 1<?php
 2 
 3namespace App\Rules;
 4 
 5use Closure;
 6use Illuminate\Contracts\Validation\ValidationRule;
 7 
 8class Uppercase implements ValidationRule
 9{
10    /**
11     * Run the validation rule.
12     */
13    public function validate(string $attribute, mixed $value, Closure $fail): void
14    {
15        if (strtoupper($value) !== $value) {
16            $fail('The :attribute must be uppercase.');
17        }
18    }
19}

<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Uppercase implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strtoupper($value) !== $value) {
            $fail('The :attribute must be uppercase.');
        }
    }
}
```

Once the rule has been defined, you may attach it to a validator by passing an instance of the rule object with your other validation rules:

```
1use App\Rules\Uppercase;
2 
3$request->validate([
4    'name' => ['required', 'string', new Uppercase],
5]);

use App\Rules\Uppercase;

$request->validate([
    'name' => ['required', 'string', new Uppercase],
]);
```

#### Translating Validation Messages

Instead of providing a literal error message to the `$fail` closure, you may also provide a [translation string key](https://laravel.com/framework/docs/localization) and instruct Laravel to translate the error message:

```
1if (strtoupper($value) !== $value) {
2    $fail('validation.uppercase')->translate();
3}

if (strtoupper($value) !== $value) {
    $fail('validation.uppercase')->translate();
}
```

If necessary, you may provide placeholder replacements and the preferred language as the first and second arguments to the `translate` method:

```
1$fail('validation.location')->translate([
2    'value' => $this->value,
3], 'fr');

$fail('validation.location')->translate([
    'value' => $this->value,
], 'fr');
```

#### Accessing Additional Data

If your custom validation rule class needs to access all of the other data undergoing validation, your rule class may implement the `Illuminate\Contracts\Validation\DataAwareRule` interface. This interface requires your class to define a `setData` method. This method will automatically be invoked by Laravel (before validation proceeds) with all of the data under validation:

```
 1<?php
 2 
 3namespace App\Rules;
 4 
 5use Illuminate\Contracts\Validation\DataAwareRule;
 6use Illuminate\Contracts\Validation\ValidationRule;
 7 
 8class Uppercase implements DataAwareRule, ValidationRule
 9{
10    /**
11     * All of the data under validation.
12     *
13     * @var array<string, mixed>
14     */
15    protected $data = [];
16 
17    // ...
18 
19    /**
20     * Set the data under validation.
21     *
22     * @param  array<string, mixed>  $data
23     */
24    public function setData(array $data): static
25    {
26        $this->data = $data;
27 
28        return $this;
29    }
30}

<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class Uppercase implements DataAwareRule, ValidationRule
{
    /**
     * All of the data under validation.
     *
     * @var array<string, mixed>
     */
    protected $data = [];

    // ...

    /**
     * Set the data under validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }
}
```

Or, if your validation rule requires access to the validator instance performing the validation, you may implement the `ValidatorAwareRule` interface:

```
 1<?php
 2 
 3namespace App\Rules;
 4 
 5use Illuminate\Contracts\Validation\ValidationRule;
 6use Illuminate\Contracts\Validation\ValidatorAwareRule;
 7use Illuminate\Validation\Validator;
 8 
 9class Uppercase implements ValidationRule, ValidatorAwareRule
10{
11    /**
12     * The validator instance.
13     *
14     * @var \Illuminate\Validation\Validator
15     */
16    protected $validator;
17 
18    // ...
19 
20    /**
21     * Set the current validator.
22     */
23    public function setValidator(Validator $validator): static
24    {
25        $this->validator = $validator;
26 
27        return $this;
28    }
29}

<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;

class Uppercase implements ValidationRule, ValidatorAwareRule
{
    /**
     * The validator instance.
     *
     * @var \Illuminate\Validation\Validator
     */
    protected $validator;

    // ...

    /**
     * Set the current validator.
     */
    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }
}
```

### Using Closures

If you only need the functionality of a custom rule once throughout your application, you may use a closure instead of a rule object. The closure receives the attribute's name, the attribute's value, and a `$fail` callback that should be called if validation fails:

```
 1use Illuminate\Support\Facades\Validator;
 2use Closure;
 3 
 4$validator = Validator::make($request->all(), [
 5    'title' => [
 6        'required',
 7        'max:255',
 8        function (string $attribute, mixed $value, Closure $fail) {
 9            if ($value === 'foo') {
10                $fail("The {$attribute} is invalid.");
11            }
12        },
13    ],
14]);

use Illuminate\Support\Facades\Validator;
use Closure;

$validator = Validator::make($request->all(), [
    'title' => [
        'required',
        'max:255',
        function (string $attribute, mixed $value, Closure $fail) {
            if ($value === 'foo') {
                $fail("The {$attribute} is invalid.");
            }
        },
    ],
]);
```

### Implicit Rules

By default, when an attribute being validated is not present or contains an empty string, normal validation rules, including custom rules, are not run. For example, the unique rule will not be run against an empty string:

```
1use Illuminate\Support\Facades\Validator;
2 
3$rules = ['name' => ['unique:users,name']];
4 
5$input = ['name' => ''];
6 
7Validator::make($input, $rules)->passes(); // true

use Illuminate\Support\Facades\Validator;

$rules = ['name' => ['unique:users,name']];

$input = ['name' => ''];

Validator::make($input, $rules)->passes(); // true
```

For a custom rule to run even when an attribute is empty, the rule must imply that the attribute is required. To quickly generate a new implicit rule object, you may use the `make:rule` Artisan command with the `--implicit` option:

```
1php artisan make:rule Uppercase --implicit

php artisan make:rule Uppercase --implicit
```

 An "implicit" rule only _implies_ that the attribute is required. Whether it actually invalidates a missing or empty attribute is up to you.
