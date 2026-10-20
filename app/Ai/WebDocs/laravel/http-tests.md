---
source: laravel
page: http-tests
url: https://laravel.com/framework/docs/13.x/http-tests
fetched_at: 2026-10-20T16:27:52+00:00
---

# HTTP Tests

- Introduction
- Making Requests
   - Customizing Request Headers
   - Cookies
   - Session / Authentication
   - Debugging Responses
   - Exception Handling
- Testing JSON APIs
   - Fluent JSON Testing
- Testing File Uploads
- Testing Views
   - Rendering Blade and Components
- Caching Routes
- Available Assertions
   - Response Assertions
   - Authentication Assertions
   - Validation Assertions

## Introduction

Laravel provides a very fluent API for making HTTP requests to your application and examining the responses. For example, take a look at the feature test defined below:

```
1<?php
2 
3test('the application returns a successful response', function () {
4    $response = $this->get('/');
5 
6    $response->assertStatus(200);
7});

<?php

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use Tests\TestCase;
 6 
 7class ExampleTest extends TestCase
 8{
 9    /**
10     * A basic test example.
11     */
12    public function test_the_application_returns_a_successful_response(): void
13    {
14        $response = $this->get('/');
15 
16        $response->assertStatus(200);
17    }
18}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
```

The `get` method makes a `GET` request into the application, while the `assertStatus` method asserts that the returned response should have the given HTTP status code. In addition to this simple assertion, Laravel also contains a variety of assertions for inspecting the response headers, content, JSON structure, and more.

## Making Requests

To make a request to your application, you may invoke the `get`, `post`, `put`, `patch`, or `delete` methods within your test. These methods do not actually issue a "real" HTTP request to your application. Instead, the entire network request is simulated internally.

Instead of returning an `Illuminate\Http\Response` instance, test request methods return an instance of `Illuminate\Testing\TestResponse`, which provides a variety of helpful assertions that allow you to inspect your application's responses:

```
1<?php
2 
3test('basic request', function () {
4    $response = $this->get('/');
5 
6    $response->assertStatus(200);
7});

<?php

test('basic request', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use Tests\TestCase;
 6 
 7class ExampleTest extends TestCase
 8{
 9    /**
10     * A basic test example.
11     */
12    public function test_a_basic_request(): void
13    {
14        $response = $this->get('/');
15 
16        $response->assertStatus(200);
17    }
18}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_a_basic_request(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
```

In general, each of your tests should only make one request to your application. Unexpected behavior may occur if multiple requests are executed within a single test method.

 For convenience, the CSRF middleware is automatically disabled when running tests.

### Customizing Request Headers

You may use the `withHeaders` method to customize the request's headers before it is sent to the application. This method allows you to add any custom headers you would like to the request:

```
1<?php
2 
3test('interacting with headers', function () {
4    $response = $this->withHeaders([
5        'X-Header' => 'Value',
6    ])->post('/user', ['name' => 'Sally']);
7 
8    $response->assertStatus(201);
9});

<?php

test('interacting with headers', function () {
    $response = $this->withHeaders([
        'X-Header' => 'Value',
    ])->post('/user', ['name' => 'Sally']);

    $response->assertStatus(201);
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use Tests\TestCase;
 6 
 7class ExampleTest extends TestCase
 8{
 9    /**
10     * A basic functional test example.
11     */
12    public function test_interacting_with_headers(): void
13    {
14        $response = $this->withHeaders([
15            'X-Header' => 'Value',
16        ])->post('/user', ['name' => 'Sally']);
17 
18        $response->assertStatus(201);
19    }
20}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic functional test example.
     */
    public function test_interacting_with_headers(): void
    {
        $response = $this->withHeaders([
            'X-Header' => 'Value',
        ])->post('/user', ['name' => 'Sally']);

        $response->assertStatus(201);
    }
}
```

### Cookies

You may use the `withCookie` or `withCookies` methods to set cookie values before making a request. The `withCookie` method accepts a cookie name and value as its two arguments, while the `withCookies` method accepts an array of name / value pairs:

```
 1<?php
 2 
 3test('interacting with cookies', function () {
 4    $response = $this->withCookie('color', 'blue')->get('/');
 5 
 6    $response = $this->withCookies([
 7        'color' => 'blue',
 8        'name' => 'Taylor',
 9    ])->get('/');
10 
11    //
12});

<?php

test('interacting with cookies', function () {
    $response = $this->withCookie('color', 'blue')->get('/');

    $response = $this->withCookies([
        'color' => 'blue',
        'name' => 'Taylor',
    ])->get('/');

    //
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use Tests\TestCase;
 6 
 7class ExampleTest extends TestCase
 8{
 9    public function test_interacting_with_cookies(): void
10    {
11        $response = $this->withCookie('color', 'blue')->get('/');
12 
13        $response = $this->withCookies([
14            'color' => 'blue',
15            'name' => 'Taylor',
16        ])->get('/');
17 
18        //
19    }
20}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_interacting_with_cookies(): void
    {
        $response = $this->withCookie('color', 'blue')->get('/');

        $response = $this->withCookies([
            'color' => 'blue',
            'name' => 'Taylor',
        ])->get('/');

        //
    }
}
```

### Session / Authentication

Laravel provides several helpers for interacting with the session during HTTP testing. First, you may set the session data to a given array using the `withSession` method. This is useful for loading the session with data before issuing a request to your application:

```
1<?php
2 
3test('interacting with the session', function () {
4    $response = $this->withSession(['banned' => false])->get('/');
5 
6    //
7});

<?php

test('interacting with the session', function () {
    $response = $this->withSession(['banned' => false])->get('/');

    //
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use Tests\TestCase;
 6 
 7class ExampleTest extends TestCase
 8{
 9    public function test_interacting_with_the_session(): void
10    {
11        $response = $this->withSession(['banned' => false])->get('/');
12 
13        //
14    }
15}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_interacting_with_the_session(): void
    {
        $response = $this->withSession(['banned' => false])->get('/');

        //
    }
}
```

Laravel's session is typically used to maintain state for the currently authenticated user. Therefore, the `actingAs` helper method provides a simple way to authenticate a given user as the current user. For example, we may use a [model factory](https://laravel.com/framework/docs/eloquent-factories) to generate and authenticate a user:

```
 1<?php
 2 
 3use App\Models\User;
 4 
 5test('an action that requires authentication', function () {
 6    $user = User::factory()->create();
 7 
 8    $response = $this->actingAs($user)
 9        ->withSession(['banned' => false])
10        ->get('/');
11 
12    //
13});

<?php

use App\Models\User;

test('an action that requires authentication', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['banned' => false])
        ->get('/');

    //
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use App\Models\User;
 6use Tests\TestCase;
 7 
 8class ExampleTest extends TestCase
 9{
10    public function test_an_action_that_requires_authentication(): void
11    {
12        $user = User::factory()->create();
13 
14        $response = $this->actingAs($user)
15            ->withSession(['banned' => false])
16            ->get('/');
17 
18        //
19    }
20}

<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_an_action_that_requires_authentication(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['banned' => false])
            ->get('/');

        //
    }
}
```

You may also specify which guard should be used to authenticate the given user by passing the guard name as the second argument to the `actingAs` method. The guard that is provided to the `actingAs` method will also become the default guard for the duration of the test:

```
1$this->actingAs($user, 'web');

$this->actingAs($user, 'web');
```

If you would like to ensure the request is unauthenticated, you may use the `actingAsGuest` method:

```
1$this->actingAsGuest();

$this->actingAsGuest();
```

### Debugging Responses

After making a test request to your application, the `dump`, `dumpHeaders`, and `dumpSession` methods may be used to examine and debug the response contents:

```
1<?php
2 
3test('basic test', function () {
4    $response = $this->get('/');
5 
6    $response->dump();
7    $response->dumpHeaders();
8    $response->dumpSession();
9});

<?php

test('basic test', function () {
    $response = $this->get('/');

    $response->dump();
    $response->dumpHeaders();
    $response->dumpSession();
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use Tests\TestCase;
 6 
 7class ExampleTest extends TestCase
 8{
 9    /**
10     * A basic test example.
11     */
12    public function test_basic_test(): void
13    {
14        $response = $this->get('/');
15 
16        $response->dump();
17        $response->dumpHeaders();
18        $response->dumpSession();
19    }
20}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_basic_test(): void
    {
        $response = $this->get('/');

        $response->dump();
        $response->dumpHeaders();
        $response->dumpSession();
    }
}
```

Alternatively, you may use the `dd`, `ddHeaders`, `ddBody`, `ddJson`, and `ddSession` methods to dump information about the response and then stop execution:

```
 1<?php
 2 
 3test('basic test', function () {
 4    $response = $this->get('/');
 5 
 6    $response->dd();
 7    $response->ddHeaders();
 8    $response->ddBody();
 9    $response->ddJson();
10    $response->ddSession();
11});

<?php

test('basic test', function () {
    $response = $this->get('/');

    $response->dd();
    $response->ddHeaders();
    $response->ddBody();
    $response->ddJson();
    $response->ddSession();
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use Tests\TestCase;
 6 
 7class ExampleTest extends TestCase
 8{
 9    /**
10     * A basic test example.
11     */
12    public function test_basic_test(): void
13    {
14        $response = $this->get('/');
15 
16        $response->dd();
17        $response->ddHeaders();
18        $response->ddBody();
19        $response->ddJson();
20        $response->ddSession();
21    }
22}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_basic_test(): void
    {
        $response = $this->get('/');

        $response->dd();
        $response->ddHeaders();
        $response->ddBody();
        $response->ddJson();
        $response->ddSession();
    }
}
```

### Exception Handling

Sometimes you may need to test that your application is throwing a specific exception. To accomplish this, you may "fake" the exception handler via the `Exceptions` facade. Once the exception handler has been faked, you may utilize the `assertReported` and `assertNotReported` methods to make assertions against exceptions that were thrown during the request:

```
 1<?php
 2 
 3use App\Exceptions\InvalidOrderException;
 4use Illuminate\Support\Facades\Exceptions;
 5 
 6test('exception is thrown', function () {
 7    Exceptions::fake();
 8 
 9    $response = $this->get('/order/1');
10 
11    // Assert an exception was thrown...
12    Exceptions::assertReported(InvalidOrderException::class);
13 
14    // Assert against the exception...
15    Exceptions::assertReported(function (InvalidOrderException $e) {
16        return $e->getMessage() === 'The order was invalid.';
17    });
18});

<?php

use App\Exceptions\InvalidOrderException;
use Illuminate\Support\Facades\Exceptions;

test('exception is thrown', function () {
    Exceptions::fake();

    $response = $this->get('/order/1');

    // Assert an exception was thrown...
    Exceptions::assertReported(InvalidOrderException::class);

    // Assert against the exception...
    Exceptions::assertReported(function (InvalidOrderException $e) {
        return $e->getMessage() === 'The order was invalid.';
    });
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use App\Exceptions\InvalidOrderException;
 6use Illuminate\Support\Facades\Exceptions;
 7use Tests\TestCase;
 8 
 9class ExampleTest extends TestCase
10{
11    /**
12     * A basic test example.
13     */
14    public function test_exception_is_thrown(): void
15    {
16        Exceptions::fake();
17 
18        $response = $this->get('/');
19 
20        // Assert an exception was thrown...
21        Exceptions::assertReported(InvalidOrderException::class);
22 
23        // Assert against the exception...
24        Exceptions::assertReported(function (InvalidOrderException $e) {
25            return $e->getMessage() === 'The order was invalid.';
26        });
27    }
28}

<?php

namespace Tests\Feature;

use App\Exceptions\InvalidOrderException;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_exception_is_thrown(): void
    {
        Exceptions::fake();

        $response = $this->get('/');

        // Assert an exception was thrown...
        Exceptions::assertReported(InvalidOrderException::class);

        // Assert against the exception...
        Exceptions::assertReported(function (InvalidOrderException $e) {
            return $e->getMessage() === 'The order was invalid.';
        });
    }
}
```

The `assertNotReported` and `assertNothingReported` methods may be used to assert that a given exception was not thrown during the request or that no exceptions were thrown:

```
1Exceptions::assertNotReported(InvalidOrderException::class);
2 
3Exceptions::assertNothingReported();

Exceptions::assertNotReported(InvalidOrderException::class);

Exceptions::assertNothingReported();
```

You may totally disable exception handling for a given request by invoking the `withoutExceptionHandling` method before making your request:

```
1$response = $this->withoutExceptionHandling()->get('/');

$response = $this->withoutExceptionHandling()->get('/');
```

In addition, if you would like to ensure that your application is not utilizing features that have been deprecated by the PHP language or the libraries your application is using, you may invoke the `withoutDeprecationHandling` method before making your request. When deprecation handling is disabled, deprecation warnings will be converted to exceptions, thus causing your test to fail:

```
1$response = $this->withoutDeprecationHandling()->get('/');

$response = $this->withoutDeprecationHandling()->get('/');
```

The `assertThrows` method may be used to assert that code within a given closure throws an exception of the specified type:

```
1$this->assertThrows(
2    fn () => (new ProcessOrder)->execute(),
3    OrderInvalid::class
4);

$this->assertThrows(
    fn () => (new ProcessOrder)->execute(),
    OrderInvalid::class
);
```

If you would like to inspect and make assertions against the exception that is thrown, you may provide a closure as the second argument to the `assertThrows` method:

```
1$this->assertThrows(
2    fn () => (new ProcessOrder)->execute(),
3    fn (OrderInvalid $e) => $e->orderId() === 123
4);

$this->assertThrows(
    fn () => (new ProcessOrder)->execute(),
    fn (OrderInvalid $e) => $e->orderId() === 123
);
```

The `assertDoesntThrow` method may be used to assert that the code within a given closure does not throw any exceptions:

```
1$this->assertDoesntThrow(fn () => (new ProcessOrder)->execute());

$this->assertDoesntThrow(fn () => (new ProcessOrder)->execute());
```

## Testing JSON APIs

Laravel also provides several helpers for testing JSON APIs and their responses. For example, the `json`, `getJson`, `postJson`, `putJson`, `patchJson`, `deleteJson`, and `optionsJson` methods may be used to issue JSON requests with various HTTP verbs. You may also easily pass data and headers to these methods. To get started, let's write a test to make a `POST` request to `/api/user` and assert that the expected JSON data was returned:

```
 1<?php
 2 
 3test('making an api request', function () {
 4    $response = $this->postJson('/api/user', ['name' => 'Sally']);
 5 
 6    $response
 7        ->assertStatus(201)
 8        ->assertJson([
 9            'created' => true,
10        ]);
11});

<?php

test('making an api request', function () {
    $response = $this->postJson('/api/user', ['name' => 'Sally']);

    $response
        ->assertStatus(201)
        ->assertJson([
            'created' => true,
        ]);
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use Tests\TestCase;
 6 
 7class ExampleTest extends TestCase
 8{
 9    /**
10     * A basic functional test example.
11     */
12    public function test_making_an_api_request(): void
13    {
14        $response = $this->postJson('/api/user', ['name' => 'Sally']);
15 
16        $response
17            ->assertStatus(201)
18            ->assertJson([
19                'created' => true,
20            ]);
21    }
22}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic functional test example.
     */
    public function test_making_an_api_request(): void
    {
        $response = $this->postJson('/api/user', ['name' => 'Sally']);

        $response
            ->assertStatus(201)
            ->assertJson([
                'created' => true,
            ]);
    }
}
```

In addition, JSON response data may be accessed as array variables on the response, making it convenient for you to inspect the individual values returned within a JSON response:

```
1expect($response['created'])->toBeTrue();

expect($response['created'])->toBeTrue();
```

```
1$this->assertTrue($response['created']);

$this->assertTrue($response['created']);
```

 The `assertJson` method converts the response to an array to verify that the given array exists within the JSON response returned by the application. So, if there are other properties in the JSON response, this test will still pass as long as the given fragment is present.

#### Asserting Exact JSON Matches

As previously mentioned, the `assertJson` method may be used to assert that a fragment of JSON exists within the JSON response. If you would like to verify that a given array **exactly matches** the JSON returned by your application, you should use the `assertExactJson` method:

```
 1<?php
 2 
 3test('asserting an exact json match', function () {
 4    $response = $this->postJson('/user', ['name' => 'Sally']);
 5 
 6    $response
 7        ->assertStatus(201)
 8        ->assertExactJson([
 9            'created' => true,
10        ]);
11});

<?php

test('asserting an exact json match', function () {
    $response = $this->postJson('/user', ['name' => 'Sally']);

    $response
        ->assertStatus(201)
        ->assertExactJson([
            'created' => true,
        ]);
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use Tests\TestCase;
 6 
 7class ExampleTest extends TestCase
 8{
 9    /**
10     * A basic functional test example.
11     */
12    public function test_asserting_an_exact_json_match(): void
13    {
14        $response = $this->postJson('/user', ['name' => 'Sally']);
15 
16        $response
17            ->assertStatus(201)
18            ->assertExactJson([
19                'created' => true,
20            ]);
21    }
22}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic functional test example.
     */
    public function test_asserting_an_exact_json_match(): void
    {
        $response = $this->postJson('/user', ['name' => 'Sally']);

        $response
            ->assertStatus(201)
            ->assertExactJson([
                'created' => true,
            ]);
    }
}
```

#### Asserting on JSON Paths

If you would like to verify that the JSON response contains the given data at a specified path, you should use the `assertJsonPath` method:

```
1<?php
2 
3test('asserting a json path value', function () {
4    $response = $this->postJson('/user', ['name' => 'Sally']);
5 
6    $response
7        ->assertStatus(201)
8        ->assertJsonPath('team.owner.name', 'Darian');
9});

<?php

test('asserting a json path value', function () {
    $response = $this->postJson('/user', ['name' => 'Sally']);

    $response
        ->assertStatus(201)
        ->assertJsonPath('team.owner.name', 'Darian');
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use Tests\TestCase;
 6 
 7class ExampleTest extends TestCase
 8{
 9    /**
10     * A basic functional test example.
11     */
12    public function test_asserting_a_json_paths_value(): void
13    {
14        $response = $this->postJson('/user', ['name' => 'Sally']);
15 
16        $response
17            ->assertStatus(201)
18            ->assertJsonPath('team.owner.name', 'Darian');
19    }
20}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic functional test example.
     */
    public function test_asserting_a_json_paths_value(): void
    {
        $response = $this->postJson('/user', ['name' => 'Sally']);

        $response
            ->assertStatus(201)
            ->assertJsonPath('team.owner.name', 'Darian');
    }
}
```

The `assertJsonPath` method also accepts a closure, which may be used to dynamically determine if the assertion should pass:

```
1$response->assertJsonPath('team.owner.name', fn (string $name) => strlen($name) >= 3);

$response->assertJsonPath('team.owner.name', fn (string $name) => strlen($name) >= 3);
```

If you need to assert multiple JSON paths at once, you may use the `assertJsonPaths` method. The expected value for each path may also be a closure:

```
1$response->assertJsonPaths([
2    'team.owner.name' => 'Darian',
3    'team.owner.email' => fn (string $email) => str($email)->is('*@laravel.com'),
4    'team.members.0.name' => 'Sally',
5]);

$response->assertJsonPaths([
    'team.owner.name' => 'Darian',
    'team.owner.email' => fn (string $email) => str($email)->is('*@laravel.com'),
    'team.members.0.name' => 'Sally',
]);
```

You may use the `assertJsonMissingPaths` method to assert that multiple JSON paths are missing from the response:

```
1$response->assertJsonMissingPaths([
2    'team.owner.password',
3    'team.members.0.api_token',
4]);

$response->assertJsonMissingPaths([
    'team.owner.password',
    'team.members.0.api_token',
]);
```

### Fluent JSON Testing

Laravel also offers a beautiful way to fluently test your application's JSON responses. To get started, pass a closure to the `assertJson` method. This closure will be invoked with an instance of `Illuminate\Testing\Fluent\AssertableJson` which can be used to make assertions against the JSON that was returned by your application. The `where` method may be used to make assertions against a particular attribute of the JSON, while the `missing` method may be used to assert that a particular attribute is missing from the JSON:

```
 1use Illuminate\Testing\Fluent\AssertableJson;
 2 
 3test('fluent json', function () {
 4    $response = $this->getJson('/users/1');
 5 
 6    $response
 7        ->assertJson(fn (AssertableJson $json) =>
 8            $json->where('id', 1)
 9                ->where('name', 'Victoria Faith')
10                ->where('email', fn (string $email) => str($email)->is('[email protected]'))
11                ->whereNot('status', 'pending')
12                ->missing('password')
13                ->etc()
14        );
15});

use Illuminate\Testing\Fluent\AssertableJson;

test('fluent json', function () {
    $response = $this->getJson('/users/1');

    $response
        ->assertJson(fn (AssertableJson $json) =>
            $json->where('id', 1)
                ->where('name', 'Victoria Faith')
                ->where('email', fn (string $email) => str($email)->is('[email protected]'))
                ->whereNot('status', 'pending')
                ->missing('password')
                ->etc()
        );
});
```

```
 1use Illuminate\Testing\Fluent\AssertableJson;
 2 
 3/**
 4 * A basic functional test example.
 5 */
 6public function test_fluent_json(): void
 7{
 8    $response = $this->getJson('/users/1');
 9 
10    $response
11        ->assertJson(fn (AssertableJson $json) =>
12            $json->where('id', 1)
13                ->where('name', 'Victoria Faith')
14                ->where('email', fn (string $email) => str($email)->is('[email protected]'))
15                ->whereNot('status', 'pending')
16                ->missing('password')
17                ->etc()
18        );
19}

use Illuminate\Testing\Fluent\AssertableJson;

/**
 * A basic functional test example.
 */
public function test_fluent_json(): void
{
    $response = $this->getJson('/users/1');

    $response
        ->assertJson(fn (AssertableJson $json) =>
            $json->where('id', 1)
                ->where('name', 'Victoria Faith')
                ->where('email', fn (string $email) => str($email)->is('[email protected]'))
                ->whereNot('status', 'pending')
                ->missing('password')
                ->etc()
        );
}
```

#### Understanding the `etc` Method

In the example above, you may have noticed we invoked the `etc` method at the end of our assertion chain. This method informs Laravel that there may be other attributes present on the JSON object. If the `etc` method is not used, the test will fail if other attributes that you did not make assertions against exist on the JSON object.

The intention behind this behavior is to protect you from unintentionally exposing sensitive information in your JSON responses by forcing you to either explicitly make an assertion against the attribute or explicitly allow additional attributes via the `etc` method.

However, you should be aware that not including the `etc` method in your assertion chain does not ensure that additional attributes are not being added to arrays that are nested within your JSON object. The `etc` method only ensures that no additional attributes exist at the nesting level in which the `etc` method is invoked.

#### Asserting Attribute Presence / Absence

To assert that an attribute is present or absent, you may use the `has` and `missing` methods:

```
1$response->assertJson(fn (AssertableJson $json) =>
2    $json->has('data')
3        ->missing('message')
4);

$response->assertJson(fn (AssertableJson $json) =>
    $json->has('data')
        ->missing('message')
);
```

In addition, the `hasAll` and `missingAll` methods allow asserting the presence or absence of multiple attributes simultaneously:

```
1$response->assertJson(fn (AssertableJson $json) =>
2    $json->hasAll(['status', 'data'])
3        ->missingAll(['message', 'code'])
4);

$response->assertJson(fn (AssertableJson $json) =>
    $json->hasAll(['status', 'data'])
        ->missingAll(['message', 'code'])
);
```

You may use the `hasAny` method to determine if at least one of a given list of attributes is present:

```
1$response->assertJson(fn (AssertableJson $json) =>
2    $json->has('status')
3        ->hasAny('data', 'message', 'code')
4);

$response->assertJson(fn (AssertableJson $json) =>
    $json->has('status')
        ->hasAny('data', 'message', 'code')
);
```

#### Asserting Against JSON Collections

Often, your route will return a JSON response that contains multiple items, such as multiple users:

```
1Route::get('/users', function () {
2    return User::all();
3});

Route::get('/users', function () {
    return User::all();
});
```

In these situations, we may use the fluent JSON object's `has` method to make assertions against the users included in the response. For example, let's assert that the JSON response contains three users. Next, we'll make some assertions about the first user in the collection using the `first` method. The `first` method accepts a closure which receives another assertable JSON string that we can use to make assertions about the first object in the JSON collection:

```
 1$response
 2    ->assertJson(fn (AssertableJson $json) =>
 3        $json->has(3)
 4            ->first(fn (AssertableJson $json) =>
 5                $json->where('id', 1)
 6                    ->where('name', 'Victoria Faith')
 7                    ->where('email', fn (string $email) => str($email)->is('[email protected]'))
 8                    ->missing('password')
 9                    ->etc()
10            )
11    );

$response
    ->assertJson(fn (AssertableJson $json) =>
        $json->has(3)
            ->first(fn (AssertableJson $json) =>
                $json->where('id', 1)
                    ->where('name', 'Victoria Faith')
                    ->where('email', fn (string $email) => str($email)->is('[email protected]'))
                    ->missing('password')
                    ->etc()
            )
    );
```

If you would like to make the same assertions against every item in a JSON collection, you may use the `each` method:

```
 1$response
 2  ->assertJson(fn (AssertableJson $json) =>
 3      $json->has(3)
 4          ->each(fn (AssertableJson $json) =>
 5              $json->whereType('id', 'integer')
 6                  ->whereType('name', 'string')
 7                  ->whereType('email', 'string')
 8                  ->missing('password')
 9                  ->etc()
10          )
11  );

$response
  ->assertJson(fn (AssertableJson $json) =>
      $json->has(3)
          ->each(fn (AssertableJson $json) =>
              $json->whereType('id', 'integer')
                  ->whereType('name', 'string')
                  ->whereType('email', 'string')
                  ->missing('password')
                  ->etc()
          )
  );
```

#### Scoping JSON Collection Assertions

Sometimes, your application's routes will return JSON collections that are assigned named keys:

```
1Route::get('/users', function () {
2    return [
3        'meta' => [...],
4        'users' => User::all(),
5    ];
6});

Route::get('/users', function () {
    return [
        'meta' => [...],
        'users' => User::all(),
    ];
});
```

When testing these routes, you may use the `has` method to assert against the number of items in the collection. In addition, you may use the `has` method to scope a chain of assertions:

```
 1$response
 2    ->assertJson(fn (AssertableJson $json) =>
 3        $json->has('meta')
 4            ->has('users', 3)
 5            ->has('users.0', fn (AssertableJson $json) =>
 6                $json->where('id', 1)
 7                    ->where('name', 'Victoria Faith')
 8                    ->where('email', fn (string $email) => str($email)->is('[email protected]'))
 9                    ->missing('password')
10                    ->etc()
11            )
12    );

$response
    ->assertJson(fn (AssertableJson $json) =>
        $json->has('meta')
            ->has('users', 3)
            ->has('users.0', fn (AssertableJson $json) =>
                $json->where('id', 1)
                    ->where('name', 'Victoria Faith')
                    ->where('email', fn (string $email) => str($email)->is('[email protected]'))
                    ->missing('password')
                    ->etc()
            )
    );
```

However, instead of making two separate calls to the `has` method to assert against the `users` collection, you may make a single call which provides a closure as its third parameter. When doing so, the closure will automatically be invoked and scoped to the first item in the collection:

```
 1$response
 2    ->assertJson(fn (AssertableJson $json) =>
 3        $json->has('meta')
 4            ->has('users', 3, fn (AssertableJson $json) =>
 5                $json->where('id', 1)
 6                    ->where('name', 'Victoria Faith')
 7                    ->where('email', fn (string $email) => str($email)->is('[email protected]'))
 8                    ->missing('password')
 9                    ->etc()
10            )
11    );

$response
    ->assertJson(fn (AssertableJson $json) =>
        $json->has('meta')
            ->has('users', 3, fn (AssertableJson $json) =>
                $json->where('id', 1)
                    ->where('name', 'Victoria Faith')
                    ->where('email', fn (string $email) => str($email)->is('[email protected]'))
                    ->missing('password')
                    ->etc()
            )
    );
```

#### Asserting JSON Types

You may only want to assert that the properties in the JSON response are of a certain type. The `Illuminate\Testing\Fluent\AssertableJson` class provides the `whereType` and `whereAllType` methods for doing just that:

```
1$response->assertJson(fn (AssertableJson $json) =>
2    $json->whereType('id', 'integer')
3        ->whereAllType([
4            'users.0.name' => 'string',
5            'meta' => 'array'
6        ])
7);

$response->assertJson(fn (AssertableJson $json) =>
    $json->whereType('id', 'integer')
        ->whereAllType([
            'users.0.name' => 'string',
            'meta' => 'array'
        ])
);
```

You may specify multiple types using the `|` character, or passing an array of types as the second parameter to the `whereType` method. The assertion will be successful if the response value is any of the listed types:

```
1$response->assertJson(fn (AssertableJson $json) =>
2    $json->whereType('name', 'string|null')
3        ->whereType('id', ['string', 'integer'])
4);

$response->assertJson(fn (AssertableJson $json) =>
    $json->whereType('name', 'string|null')
        ->whereType('id', ['string', 'integer'])
);
```

The `whereType` and `whereAllType` methods recognize the following types: `string`, `integer`, `double`, `boolean`, `array`, and `null`.

## Testing File Uploads

The `Illuminate\Http\UploadedFile` class provides a `fake` method which may be used to generate dummy files or images for testing. This, combined with the `Storage` facade's `fake` method, greatly simplifies the testing of file uploads. For example, you may combine these two features to easily test an avatar upload form:

```
 1<?php
 2 
 3use Illuminate\Http\UploadedFile;
 4use Illuminate\Support\Facades\Storage;
 5 
 6test('avatars can be uploaded', function () {
 7    Storage::fake('avatars');
 8 
 9    $file = UploadedFile::fake()->image('avatar.jpg');
10 
11    $response = $this->post('/avatar', [
12        'avatar' => $file,
13    ]);
14 
15    Storage::disk('avatars')->assertExists($file->hashName());
16});

<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('avatars can be uploaded', function () {
    Storage::fake('avatars');

    $file = UploadedFile::fake()->image('avatar.jpg');

    $response = $this->post('/avatar', [
        'avatar' => $file,
    ]);

    Storage::disk('avatars')->assertExists($file->hashName());
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use Illuminate\Http\UploadedFile;
 6use Illuminate\Support\Facades\Storage;
 7use Tests\TestCase;
 8 
 9class ExampleTest extends TestCase
10{
11    public function test_avatars_can_be_uploaded(): void
12    {
13        Storage::fake('avatars');
14 
15        $file = UploadedFile::fake()->image('avatar.jpg');
16 
17        $response = $this->post('/avatar', [
18            'avatar' => $file,
19        ]);
20 
21        Storage::disk('avatars')->assertExists($file->hashName());
22    }
23}

<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_avatars_can_be_uploaded(): void
    {
        Storage::fake('avatars');

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->post('/avatar', [
            'avatar' => $file,
        ]);

        Storage::disk('avatars')->assertExists($file->hashName());
    }
}
```

If you would like to assert that a given file does not exist, you may use the `assertMissing` method provided by the `Storage` facade:

```
1Storage::fake('avatars');
2 
3// ...
4 
5Storage::disk('avatars')->assertMissing('missing.jpg');

Storage::fake('avatars');

// ...

Storage::disk('avatars')->assertMissing('missing.jpg');
```

#### Fake File Customization

When creating files using the `fake` method provided by the `UploadedFile` class, you may specify the width, height, and size of the image (in kilobytes) in order to better test your application's validation rules:

```
1UploadedFile::fake()->image('avatar.jpg', $width, $height)->size(100);

UploadedFile::fake()->image('avatar.jpg', $width, $height)->size(100);
```

In addition to creating images, you may create files of any other type using the `create` method:

```
1UploadedFile::fake()->create('document.pdf', $sizeInKilobytes);

UploadedFile::fake()->create('document.pdf', $sizeInKilobytes);
```

If needed, you may pass a `$mimeType` argument to the method to explicitly define the MIME type that should be returned by the file:

```
1UploadedFile::fake()->create(
2    'document.pdf', $sizeInKilobytes, 'application/pdf'
3);

UploadedFile::fake()->create(
    'document.pdf', $sizeInKilobytes, 'application/pdf'
);
```

## Testing Views

Laravel also allows you to render a view without making a simulated HTTP request to the application. To accomplish this, you may call the `view` method within your test. The `view` method accepts the view name and an optional array of data. The method returns an instance of `Illuminate\Testing\TestView`, which offers several methods to conveniently make assertions about the view's contents:

```
1<?php
2 
3test('a welcome view can be rendered', function () {
4    $view = $this->view('welcome', ['name' => 'Taylor']);
5 
6    $view->assertSee('Taylor');
7});

<?php

test('a welcome view can be rendered', function () {
    $view = $this->view('welcome', ['name' => 'Taylor']);

    $view->assertSee('Taylor');
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use Tests\TestCase;
 6 
 7class ExampleTest extends TestCase
 8{
 9    public function test_a_welcome_view_can_be_rendered(): void
10    {
11        $view = $this->view('welcome', ['name' => 'Taylor']);
12 
13        $view->assertSee('Taylor');
14    }
15}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_a_welcome_view_can_be_rendered(): void
    {
        $view = $this->view('welcome', ['name' => 'Taylor']);

        $view->assertSee('Taylor');
    }
}
```

The `TestView` class provides the following assertion methods: `assertSee`, `assertSeeInOrder`, `assertSeeText`, `assertSeeTextInOrder`, `assertDontSee`, and `assertDontSeeText`.

If needed, you may get the raw, rendered view contents by casting the `TestView` instance to a string:

```
1$contents = (string) $this->view('welcome');

$contents = (string) $this->view('welcome');
```

#### Sharing Errors

Some views may depend on errors shared in the [global error bag provided by Laravel](https://laravel.com/framework/docs/validation#quick-displaying-the-validation-errors). To hydrate the error bag with error messages, you may use the `withViewErrors` method:

```
1$view = $this->withViewErrors([
2    'name' => ['Please provide a valid name.']
3])->view('form');
4 
5$view->assertSee('Please provide a valid name.');

$view = $this->withViewErrors([
    'name' => ['Please provide a valid name.']
])->view('form');

$view->assertSee('Please provide a valid name.');
```

### Rendering Blade and Components

If necessary, you may use the `blade` method to evaluate and render a raw [Blade](https://laravel.com/framework/docs/blade) string. Like the `view` method, the `blade` method returns an instance of `Illuminate\Testing\TestView`:

```
1$view = $this->blade(
2    '<x-component :name="$name" />',
3    ['name' => 'Taylor']
4);
5 
6$view->assertSee('Taylor');

$view = $this->blade(
    '<x-component :name="$name" />',
    ['name' => 'Taylor']
);

$view->assertSee('Taylor');
```

You may use the `component` method to evaluate and render a [Blade component](https://laravel.com/framework/docs/blade#components). The `component` method returns an instance of `Illuminate\Testing\TestComponent`:

```
1$view = $this->component(Profile::class, ['name' => 'Taylor']);
2 
3$view->assertSee('Taylor');

$view = $this->component(Profile::class, ['name' => 'Taylor']);

$view->assertSee('Taylor');
```

## Caching Routes

Before a test runs, Laravel boots a fresh instance of the application, including collecting all defined routes. If your applications have many route files, you may wish to add the `Illuminate\Foundation\Testing\WithCachedRoutes` trait to your test cases. On tests which use this trait, routes are built once and stored in memory, meaning the route collection process is only run once for all tests in your suite:

```
 1<?php
 2 
 3use App\Http\Controllers\UserController;
 4use Illuminate\Foundation\Testing\WithCachedRoutes;
 5 
 6pest()->use(WithCachedRoutes::class);
 7 
 8test('basic example', function () {
 9    $this->get(action([UserController::class, 'index']));
10 
11    // ...
12});

<?php

use App\Http\Controllers\UserController;
use Illuminate\Foundation\Testing\WithCachedRoutes;

pest()->use(WithCachedRoutes::class);

test('basic example', function () {
    $this->get(action([UserController::class, 'index']));

    // ...
});
```

```
 1<?php
 2 
 3namespace Tests\Feature;
 4 
 5use App\Http\Controllers\UserController;
 6use Illuminate\Foundation\Testing\WithCachedRoutes;
 7use Tests\TestCase;
 8 
 9class BasicTest extends TestCase
10{
11    use WithCachedRoutes;
12 
13    /**
14     * A basic functional test example.
15     */
16    public function test_basic_example(): void
17    {
18        $response = $this->get(action([UserController::class, 'index']));
19 
20        // ...
21    }
22}

<?php

namespace Tests\Feature;

use App\Http\Controllers\UserController;
use Illuminate\Foundation\Testing\WithCachedRoutes;
use Tests\TestCase;

class BasicTest extends TestCase
{
    use WithCachedRoutes;

    /**
     * A basic functional test example.
     */
    public function test_basic_example(): void
    {
        $response = $this->get(action([UserController::class, 'index']));

        // ...
    }
}
```

## Available Assertions

### Response Assertions

Laravel's `Illuminate\Testing\TestResponse` class provides a variety of custom assertion methods that you may utilize when testing your application. These assertions may be accessed on the response that is returned by the `json`, `get`, `post`, `put`, and `delete` test methods:

assertAccepted assertBadRequest assertClientError assertConflict assertCookie assertCookieExpired assertCookieNotExpired assertCookieMissing assertCreated assertDontSee assertDontSeeText assertDownload assertExactJson assertExactJsonStructure assertFailedDependency assertForbidden assertFound assertGone assertHeader assertHeaderContains assertHeaderMissing assertInternalServerError assertJson assertJsonCount assertJsonFragment assertJsonIsArray assertJsonIsObject assertJsonMissing assertJsonMissingExact assertJsonMissingValidationErrors assertJsonPath assertJsonPaths assertJsonMissingPath assertJsonMissingPaths assertJsonStructure assertJsonValidationErrors assertJsonValidationErrorFor assertLocation assertMethodNotAllowed assertMovedPermanently assertContent assertNoContent assertStreamed assertStreamedContent assertNotFound assertOk assertPaymentRequired assertPlainCookie assertRedirect assertRedirectBack assertRedirectBackWithErrors assertRedirectBackWithoutErrors assertRedirectContains assertRedirectToRoute assertRedirectToSignedRoute assertRequestTimeout assertSee assertSeeInOrder assertSeeText assertSeeTextInOrder assertServerError assertServiceUnavailable assertSessionHas assertSessionHasInput assertSessionHasAll assertSessionHasErrors assertSessionHasErrorsIn assertSessionHasNoErrors assertSessionDoesntHaveErrors assertSessionMissing assertSessionMissingInput assertStatus assertSuccessful assertTooManyRequests assertUnauthorized assertUnprocessable assertUnsupportedMediaType assertValid assertInvalid assertViewHas assertViewHasAll assertViewIs assertViewMissing

#### assertAccepted

Assert that the response has an accepted (202) HTTP status code:

```
1$response->assertAccepted();

$response->assertAccepted();
```

#### assertBadRequest

Assert that the response has a bad request (400) HTTP status code:

```
1$response->assertBadRequest();

$response->assertBadRequest();
```

#### assertClientError

Assert that the response has a client error (>= 400, < 500) HTTP status code:

```
1$response->assertClientError();

$response->assertClientError();
```

#### assertConflict

Assert that the response has a conflict (409) HTTP status code:

```
1$response->assertConflict();

$response->assertConflict();
```

#### assertCookie

Assert that the response contains the given cookie:

```
1$response->assertCookie($cookieName, $value = null);

$response->assertCookie($cookieName, $value = null);
```

#### assertCookieExpired

Assert that the response contains the given cookie and it is expired:

```
1$response->assertCookieExpired($cookieName);

$response->assertCookieExpired($cookieName);
```

#### assertCookieNotExpired

Assert that the response contains the given cookie and it is not expired:

```
1$response->assertCookieNotExpired($cookieName);

$response->assertCookieNotExpired($cookieName);
```

#### assertCookieMissing

Assert that the response does not contain the given cookie:

```
1$response->assertCookieMissing($cookieName);

$response->assertCookieMissing($cookieName);
```

#### assertCreated

Assert that the response has a 201 HTTP status code:

```
1$response->assertCreated();

$response->assertCreated();
```

#### assertDontSee

Assert that the given string is not contained within the response returned by the application. This assertion will automatically escape the given string unless you pass a second argument of `false`:

```
1$response->assertDontSee($value, $escape = true);

$response->assertDontSee($value, $escape = true);
```

#### assertDontSeeText

Assert that the given string is not contained within the response text. This assertion will automatically escape the given string unless you pass a second argument of `false`. This method will pass the response content to the `strip_tags` PHP function before making the assertion:

```
1$response->assertDontSeeText($value, $escape = true);

$response->assertDontSeeText($value, $escape = true);
```

#### assertDownload

Assert that the response is a "download". Typically, this means the invoked route that returned the response returned a `Response::download` response, `BinaryFileResponse`, or `Storage::download` response:

```
1$response->assertDownload();

$response->assertDownload();
```

If you wish, you may assert that the downloadable file was assigned a given file name:

```
1$response->assertDownload('image.jpg');

$response->assertDownload('image.jpg');
```

#### assertExactJson

Assert that the response contains an exact match of the given JSON data:

```
1$response->assertExactJson(array $data);

$response->assertExactJson(array $data);
```

#### assertExactJsonStructure

Assert that the response contains an exact match of the given JSON structure:

```
1$response->assertExactJsonStructure(array $data);

$response->assertExactJsonStructure(array $data);
```

This method is a more strict variant of assertJsonStructure. In contrast with `assertJsonStructure`, this method will fail if the response contains any keys that aren't explicitly included in the expected JSON structure.

#### assertFailedDependency

Assert that the response has a failed dependency (424) HTTP status code:

```
1$response->assertFailedDependency();

$response->assertFailedDependency();
```

#### assertForbidden

Assert that the response has a forbidden (403) HTTP status code:

```
1$response->assertForbidden();

$response->assertForbidden();
```

#### assertFound

Assert that the response has a found (302) HTTP status code:

```
1$response->assertFound();

$response->assertFound();
```

#### assertGone

Assert that the response has a gone (410) HTTP status code:

```
1$response->assertGone();

$response->assertGone();
```

#### assertHeader

Assert that the given header and value is present on the response:

```
1$response->assertHeader($headerName, $value = null);

$response->assertHeader($headerName, $value = null);
```

#### assertHeaderContains

Assert that the given header contains a given substring value:

```
1$response->assertHeaderContains($headerName, $value);

$response->assertHeaderContains($headerName, $value);
```

#### assertHeaderMissing

Assert that the given header is not present on the response:

```
1$response->assertHeaderMissing($headerName);

$response->assertHeaderMissing($headerName);
```

#### assertInternalServerError

Assert that the response has an "Internal Server Error" (500) HTTP status code:

```
1$response->assertInternalServerError();

$response->assertInternalServerError();
```

#### assertJson

Assert that the response contains the given JSON data:

```
1$response->assertJson(array $data, $strict = false);

$response->assertJson(array $data, $strict = false);
```

The `assertJson` method converts the response to an array to verify that the given array exists within the JSON response returned by the application. So, if there are other properties in the JSON response, this test will still pass as long as the given fragment is present.

#### assertJsonCount

Assert that the response JSON has an array with the expected number of items at the given key:

```
1$response->assertJsonCount($count, $key = null);

$response->assertJsonCount($count, $key = null);
```

#### assertJsonFragment

Assert that the response contains the given JSON data anywhere in the response:

```
 1Route::get('/users', function () {
 2    return [
 3        'users' => [
 4            [
 5                'name' => 'Taylor Otwell',
 6            ],
 7        ],
 8    ];
 9});
10 
11$response->assertJsonFragment(['name' => 'Taylor Otwell']);

Route::get('/users', function () {
    return [
        'users' => [
            [
                'name' => 'Taylor Otwell',
            ],
        ],
    ];
});

$response->assertJsonFragment(['name' => 'Taylor Otwell']);
```

#### assertJsonIsArray

Assert that the response JSON is an array:

```
1$response->assertJsonIsArray();

$response->assertJsonIsArray();
```

#### assertJsonIsObject

Assert that the response JSON is an object:

```
1$response->assertJsonIsObject();

$response->assertJsonIsObject();
```

#### assertJsonMissing

Assert that the response does not contain the given JSON data:

```
1$response->assertJsonMissing(array $data);

$response->assertJsonMissing(array $data);
```

#### assertJsonMissingExact

Assert that the response does not contain the exact JSON data:

```
1$response->assertJsonMissingExact(array $data);

$response->assertJsonMissingExact(array $data);
```

#### assertJsonMissingValidationErrors

Assert that the response has no JSON validation errors for the given keys:

```
1$response->assertJsonMissingValidationErrors($keys);

$response->assertJsonMissingValidationErrors($keys);
```

 The more generic assertValid method may be used to assert that a response does not have validation errors that were returned as JSON **and** that no errors were flashed to session storage.

#### assertJsonPath

Assert that the response contains the given data at the specified path:

```
1$response->assertJsonPath($path, $expectedValue);

$response->assertJsonPath($path, $expectedValue);
```

For example, if the following JSON response is returned by your application:

```
1{
2    "user": {
3        "name": "Steve Schoger"
4    }
5}

{
    "user": {
        "name": "Steve Schoger"
    }
}
```

You may assert that the `name` property of the `user` object matches a given value like so:

```
1$response->assertJsonPath('user.name', 'Steve Schoger');

$response->assertJsonPath('user.name', 'Steve Schoger');
```

#### assertJsonPaths

Assert that the response contains the given data at the specified paths:

```
1$response->assertJsonPaths(array $paths);

$response->assertJsonPaths(array $paths);
```

For example, you may assert multiple values within the response at once:

```
1$response->assertJsonPaths([
2    'user.name' => 'Steve Schoger',
3    'user.email' => fn (string $email) => str($email)->endsWith('@laravel.com'),
4]);

$response->assertJsonPaths([
    'user.name' => 'Steve Schoger',
    'user.email' => fn (string $email) => str($email)->endsWith('@laravel.com'),
]);
```

#### assertJsonMissingPath

Assert that the response does not contain the given path:

```
1$response->assertJsonMissingPath($path);

$response->assertJsonMissingPath($path);
```

For example, if the following JSON response is returned by your application:

```
1{
2    "user": {
3        "name": "Steve Schoger"
4    }
5}

{
    "user": {
        "name": "Steve Schoger"
    }
}
```

You may assert that it does not contain the `email` property of the `user` object:

```
1$response->assertJsonMissingPath('user.email');

$response->assertJsonMissingPath('user.email');
```

#### assertJsonMissingPaths

Assert that the response does not contain the given paths:

```
1$response->assertJsonMissingPaths($paths);

$response->assertJsonMissingPaths($paths);
```

For example, you may assert that multiple paths are missing from the response:

```
1$response->assertJsonMissingPaths([
2    'user.email',
3    'user.password',
4]);

$response->assertJsonMissingPaths([
    'user.email',
    'user.password',
]);
```

#### assertJsonStructure

Assert that the response has a given JSON structure:

```
1$response->assertJsonStructure(array $structure);

$response->assertJsonStructure(array $structure);
```

For example, if the JSON response returned by your application contains the following data:

```
1{
2    "user": {
3        "name": "Steve Schoger"
4    }
5}

{
    "user": {
        "name": "Steve Schoger"
    }
}
```

You may assert that the JSON structure matches your expectations like so:

```
1$response->assertJsonStructure([
2    'user' => [
3        'name',
4    ]
5]);

$response->assertJsonStructure([
    'user' => [
        'name',
    ]
]);
```

Sometimes, JSON responses returned by your application may contain arrays of objects:

```
 1{
 2    "user": [
 3        {
 4            "name": "Steve Schoger",
 5            "age": 55,
 6            "location": "Earth"
 7        },
 8        {
 9            "name": "Mary Schoger",
10            "age": 60,
11            "location": "Earth"
12        }
13    ]
14}

{
    "user": [
        {
            "name": "Steve Schoger",
            "age": 55,
            "location": "Earth"
        },
        {
            "name": "Mary Schoger",
            "age": 60,
            "location": "Earth"
        }
    ]
}
```

In this situation, you may use the `*` character to assert against the structure of all of the objects in the array:

```
1$response->assertJsonStructure([
2    'user' => [
3        '*' => [
4             'name',
5             'age',
6             'location'
7        ]
8    ]
9]);

$response->assertJsonStructure([
    'user' => [
        '*' => [
             'name',
             'age',
             'location'
        ]
    ]
]);
```

#### assertJsonValidationErrors

Assert that the response has the given JSON validation errors for the given keys. This method should be used when asserting against responses where the validation errors are returned as a JSON structure instead of being flashed to the session:

```
1$response->assertJsonValidationErrors(array $data, $responseKey = 'errors');

$response->assertJsonValidationErrors(array $data, $responseKey = 'errors');
```

 The more generic assertInvalid method may be used to assert that a response has validation errors returned as JSON **or** that errors were flashed to session storage.

#### assertJsonValidationErrorFor

Assert the response has any JSON validation errors for the given key:

```
1$response->assertJsonValidationErrorFor(string $key, $responseKey = 'errors');

$response->assertJsonValidationErrorFor(string $key, $responseKey = 'errors');
```

#### assertMethodNotAllowed

Assert that the response has a method not allowed (405) HTTP status code:

```
1$response->assertMethodNotAllowed();

$response->assertMethodNotAllowed();
```

#### assertMovedPermanently

Assert that the response has a moved permanently (301) HTTP status code:

```
1$response->assertMovedPermanently();

$response->assertMovedPermanently();
```

#### assertLocation

Assert that the response has the given URI value in the `Location` header:

```
1$response->assertLocation($uri);

$response->assertLocation($uri);
```

#### assertContent

Assert that the given string matches the response content:

```
1$response->assertContent($value);

$response->assertContent($value);
```

#### assertNoContent

Assert that the response has the given HTTP status code and no content:

```
1$response->assertNoContent($status = 204);

$response->assertNoContent($status = 204);
```

#### assertStreamed

Assert that the response was a streamed response:

```
1$response->assertStreamed();

$response->assertStreamed();
```

#### assertStreamedContent

Assert that the given string matches the streamed response content:

```
1$response->assertStreamedContent($value);

$response->assertStreamedContent($value);
```

#### assertNotFound

Assert that the response has a not found (404) HTTP status code:

```
1$response->assertNotFound();

$response->assertNotFound();
```

#### assertOk

Assert that the response has a 200 HTTP status code:

```
1$response->assertOk();

$response->assertOk();
```

#### assertPaymentRequired

Assert that the response has a payment required (402) HTTP status code:

```
1$response->assertPaymentRequired();

$response->assertPaymentRequired();
```

#### assertPlainCookie

Assert that the response contains the given unencrypted cookie:

```
1$response->assertPlainCookie($cookieName, $value = null);

$response->assertPlainCookie($cookieName, $value = null);
```

#### assertRedirect

Assert that the response is a redirect to the given URI:

```
1$response->assertRedirect($uri = null);

$response->assertRedirect($uri = null);
```

#### assertRedirectBack

Assert whether the response is redirecting back to the previous page:

```
1$response->assertRedirectBack();

$response->assertRedirectBack();
```

#### assertRedirectBackWithErrors

Assert whether the response is redirecting back to the previous page and the session has the given errors:

```
1$response->assertRedirectBackWithErrors(
2    array $keys = [], $format = null, $errorBag = 'default'
3);

$response->assertRedirectBackWithErrors(
    array $keys = [], $format = null, $errorBag = 'default'
);
```

#### assertRedirectBackWithoutErrors

Assert whether the response is redirecting back to the previous page and the session does not contain any error messages:

```
1$response->assertRedirectBackWithoutErrors();

$response->assertRedirectBackWithoutErrors();
```

#### assertRedirectContains

Assert whether the response is redirecting to a URI that contains the given string:

```
1$response->assertRedirectContains($string);

$response->assertRedirectContains($string);
```

#### assertRedirectToRoute

Assert that the response is a redirect to the given [named route](https://laravel.com/framework/docs/routing#named-routes):

```
1$response->assertRedirectToRoute($name, $parameters = []);

$response->assertRedirectToRoute($name, $parameters = []);
```

#### assertRedirectToSignedRoute

Assert that the response is a redirect to the given [signed route](https://laravel.com/framework/docs/urls#signed-urls):

```
1$response->assertRedirectToSignedRoute($name = null, $parameters = []);

$response->assertRedirectToSignedRoute($name = null, $parameters = []);
```

#### assertRequestTimeout

Assert that the response has a request timeout (408) HTTP status code:

```
1$response->assertRequestTimeout();

$response->assertRequestTimeout();
```

#### assertSee

Assert that the given string is contained within the response. This assertion will automatically escape the given string unless you pass a second argument of `false`:

```
1$response->assertSee($value, $escape = true);

$response->assertSee($value, $escape = true);
```

#### assertSeeInOrder

Assert that the given strings are contained in order within the response. This assertion will automatically escape the given strings unless you pass a second argument of `false`:

```
1$response->assertSeeInOrder(array $values, $escape = true);

$response->assertSeeInOrder(array $values, $escape = true);
```

#### assertSeeText

Assert that the given string is contained within the response text. This assertion will automatically escape the given string unless you pass a second argument of `false`. The response content will be passed to the `strip_tags` PHP function before the assertion is made:

```
1$response->assertSeeText($value, $escape = true);

$response->assertSeeText($value, $escape = true);
```

#### assertSeeTextInOrder

Assert that the given strings are contained in order within the response text. This assertion will automatically escape the given strings unless you pass a second argument of `false`. The response content will be passed to the `strip_tags` PHP function before the assertion is made:

```
1$response->assertSeeTextInOrder(array $values, $escape = true);

$response->assertSeeTextInOrder(array $values, $escape = true);
```

#### assertServerError

Assert that the response has a server error (>= 500 , < 600) HTTP status code:

```
1$response->assertServerError();

$response->assertServerError();
```

#### assertServiceUnavailable

Assert that the response has a "Service Unavailable" (503) HTTP status code:

```
1$response->assertServiceUnavailable();

$response->assertServiceUnavailable();
```

#### assertSessionHas

Assert that the session contains the given piece of data:

```
1$response->assertSessionHas($key, $value = null);

$response->assertSessionHas($key, $value = null);
```

If needed, a closure can be provided as the second argument to the `assertSessionHas` method. The assertion will pass if the closure returns `true`:

```
1$response->assertSessionHas($key, function (User $value) {
2    return $value->name === 'Taylor Otwell';
3});

$response->assertSessionHas($key, function (User $value) {
    return $value->name === 'Taylor Otwell';
});
```

#### assertSessionHasInput

Assert that the session has a given value in the [flashed input array](https://laravel.com/framework/docs/responses#redirecting-with-flashed-session-data):

```
1$response->assertSessionHasInput($key, $value = null);

$response->assertSessionHasInput($key, $value = null);
```

If needed, a closure can be provided as the second argument to the `assertSessionHasInput` method. The assertion will pass if the closure returns `true`:

```
1use Illuminate\Support\Facades\Crypt;
2 
3$response->assertSessionHasInput($key, function (string $value) {
4    return Crypt::decryptString($value) === 'secret';
5});

use Illuminate\Support\Facades\Crypt;

$response->assertSessionHasInput($key, function (string $value) {
    return Crypt::decryptString($value) === 'secret';
});
```

#### assertSessionHasAll

Assert that the session contains a given array of key / value pairs:

```
1$response->assertSessionHasAll(array $data);

$response->assertSessionHasAll(array $data);
```

For example, if your application's session contains `name` and `status` keys, you may assert that both exist and have the specified values like so:

```
1$response->assertSessionHasAll([
2    'name' => 'Taylor Otwell',
3    'status' => 'active',
4]);

$response->assertSessionHasAll([
    'name' => 'Taylor Otwell',
    'status' => 'active',
]);
```

#### assertSessionHasErrors

Assert that the session contains an error for the given `$keys`. If `$keys` is an associative array, assert that the session contains a specific error message (value) for each field (key). This method should be used when testing routes that flash validation errors to the session instead of returning them as a JSON structure:

```
1$response->assertSessionHasErrors(
2    array $keys = [], $format = null, $errorBag = 'default'
3);

$response->assertSessionHasErrors(
    array $keys = [], $format = null, $errorBag = 'default'
);
```

For example, to assert that the `name` and `email` fields have validation error messages that were flashed to the session, you may invoke the `assertSessionHasErrors` method like so:

```
1$response->assertSessionHasErrors(['name', 'email']);

$response->assertSessionHasErrors(['name', 'email']);
```

Or, you may assert that a given field has a particular validation error message:

```
1$response->assertSessionHasErrors([
2    'name' => 'The given name was invalid.'
3]);

$response->assertSessionHasErrors([
    'name' => 'The given name was invalid.'
]);
```

 The more generic assertInvalid method may be used to assert that a response has validation errors returned as JSON **or** that errors were flashed to session storage.

#### assertSessionHasErrorsIn

Assert that the session contains an error for the given `$keys` within a specific [error bag](https://laravel.com/framework/docs/validation#named-error-bags). If `$keys` is an associative array, assert that the session contains a specific error message (value) for each field (key), within the error bag:

```
1$response->assertSessionHasErrorsIn($errorBag, $keys = [], $format = null);

$response->assertSessionHasErrorsIn($errorBag, $keys = [], $format = null);
```

#### assertSessionHasNoErrors

Assert that the session has no validation errors:

```
1$response->assertSessionHasNoErrors();

$response->assertSessionHasNoErrors();
```

#### assertSessionDoesntHaveErrors

Assert that the session has no validation errors for the given keys:

```
1$response->assertSessionDoesntHaveErrors($keys = [], $format = null, $errorBag = 'default');

$response->assertSessionDoesntHaveErrors($keys = [], $format = null, $errorBag = 'default');
```

 The more generic assertValid method may be used to assert that a response does not have validation errors that were returned as JSON **and** that no errors were flashed to session storage.

#### assertSessionMissing

Assert that the session does not contain the given key:

```
1$response->assertSessionMissing($key);

$response->assertSessionMissing($key);
```

#### assertSessionMissingInput

Assert that the session is missing the given input key in the flashed input array:

```
1$response->assertSessionMissingInput($key);

$response->assertSessionMissingInput($key);
```

#### assertStatus

Assert that the response has a given HTTP status code:

```
1$response->assertStatus($code);

$response->assertStatus($code);
```

#### assertSuccessful

Assert that the response has a successful (>= 200 and < 300) HTTP status code:

```
1$response->assertSuccessful();

$response->assertSuccessful();
```

#### assertTooManyRequests

Assert that the response has a too many requests (429) HTTP status code:

```
1$response->assertTooManyRequests();

$response->assertTooManyRequests();
```

#### assertUnauthorized

Assert that the response has an unauthorized (401) HTTP status code:

```
1$response->assertUnauthorized();

$response->assertUnauthorized();
```

#### assertUnprocessable

Assert that the response has an unprocessable entity (422) HTTP status code:

```
1$response->assertUnprocessable();

$response->assertUnprocessable();
```

#### assertUnsupportedMediaType

Assert that the response has an unsupported media type (415) HTTP status code:

```
1$response->assertUnsupportedMediaType();

$response->assertUnsupportedMediaType();
```

#### assertValid

Assert that the response has no validation errors for the given keys. This method may be used for asserting against responses where the validation errors are returned as a JSON structure or where the validation errors have been flashed to the session:

```
1// Assert that no validation errors are present...
2$response->assertValid();
3 
4// Assert that the given keys do not have validation errors...
5$response->assertValid(['name', 'email']);

// Assert that no validation errors are present...
$response->assertValid();

// Assert that the given keys do not have validation errors...
$response->assertValid(['name', 'email']);
```

#### assertInvalid

Assert that the response has validation errors for the given keys. This method may be used for asserting against responses where the validation errors are returned as a JSON structure or where the validation errors have been flashed to the session:

```
1$response->assertInvalid(['name', 'email']);

$response->assertInvalid(['name', 'email']);
```

You may also assert that a given key has a particular validation error message. When doing so, you may provide the entire message or only a small portion of the message:

```
1$response->assertInvalid([
2    'name' => 'The name field is required.',
3    'email' => 'valid email address',
4]);

$response->assertInvalid([
    'name' => 'The name field is required.',
    'email' => 'valid email address',
]);
```

If you would like to assert that the given fields are the only fields with validation errors, you may use the `assertOnlyInvalid` method:

```
1$response->assertOnlyInvalid(['name', 'email']);

$response->assertOnlyInvalid(['name', 'email']);
```

#### assertViewHas

Assert that the response view contains a given piece of data:

```
1$response->assertViewHas($key, $value = null);

$response->assertViewHas($key, $value = null);
```

Passing a closure as the second argument to the `assertViewHas` method will allow you to inspect and make assertions against a particular piece of view data:

```
1$response->assertViewHas('user', function (User $user) {
2    return $user->name === 'Taylor';
3});

$response->assertViewHas('user', function (User $user) {
    return $user->name === 'Taylor';
});
```

In addition, view data may be accessed as array variables on the response, allowing you to conveniently inspect it:

```
1expect($response['name'])->toBe('Taylor');

expect($response['name'])->toBe('Taylor');
```

```
1$this->assertEquals('Taylor', $response['name']);

$this->assertEquals('Taylor', $response['name']);
```

#### assertViewHasAll

Assert that the response view has a given list of data:

```
1$response->assertViewHasAll(array $data);

$response->assertViewHasAll(array $data);
```

This method may be used to assert that the view simply contains data matching the given keys:

```
1$response->assertViewHasAll([
2    'name',
3    'email',
4]);

$response->assertViewHasAll([
    'name',
    'email',
]);
```

Or, you may assert that the view data is present and has specific values:

```
1$response->assertViewHasAll([
2    'name' => 'Taylor Otwell',
3    'email' => '[email protected],',
4]);

$response->assertViewHasAll([
    'name' => 'Taylor Otwell',
    'email' => '[email protected],',
]);
```

#### assertViewIs

Assert that the given view was returned by the route:

```
1$response->assertViewIs($value);

$response->assertViewIs($value);
```

#### assertViewMissing

Assert that the given data key was not made available to the view returned in the application's response:

```
1$response->assertViewMissing($key);

$response->assertViewMissing($key);
```

### Authentication Assertions

Laravel also provides a variety of authentication related assertions that you may utilize within your application's feature tests. Note that these methods are invoked on the test class itself and not the `Illuminate\Testing\TestResponse` instance returned by methods such as `get` and `post`.

#### assertAuthenticated

Assert that a user is authenticated:

```
1$this->assertAuthenticated($guard = null);

$this->assertAuthenticated($guard = null);
```

#### assertGuest

Assert that a user is not authenticated:

```
1$this->assertGuest($guard = null);

$this->assertGuest($guard = null);
```

#### assertAuthenticatedAs

Assert that a specific user is authenticated:

```
1$this->assertAuthenticatedAs($user, $guard = null);

$this->assertAuthenticatedAs($user, $guard = null);
```

## Validation Assertions

Laravel provides two primary validation related assertions that you may use to ensure the data provided in your request was either valid or invalid.

#### assertValid

Assert that the response has no validation errors for the given keys. This method may be used for asserting against responses where the validation errors are returned as a JSON structure or where the validation errors have been flashed to the session:

```
1// Assert that no validation errors are present...
2$response->assertValid();
3 
4// Assert that the given keys do not have validation errors...
5$response->assertValid(['name', 'email']);

// Assert that no validation errors are present...
$response->assertValid();

// Assert that the given keys do not have validation errors...
$response->assertValid(['name', 'email']);
```

#### assertInvalid

Assert that the response has validation errors for the given keys. This method may be used for asserting against responses where the validation errors are returned as a JSON structure or where the validation errors have been flashed to the session:

```
1$response->assertInvalid(['name', 'email']);

$response->assertInvalid(['name', 'email']);
```

You may also assert that a given key has a particular validation error message. When doing so, you may provide the entire message or only a small portion of the message:

```
1$response->assertInvalid([
2    'name' => 'The name field is required.',
3    'email' => 'valid email address',
4]);

$response->assertInvalid([
    'name' => 'The name field is required.',
    'email' => 'valid email address',
]);
```
