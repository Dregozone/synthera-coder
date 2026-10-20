---
source: livewire
page: pages
url: https://livewire.laravel.com/docs/4.x/pages
fetched_at: 2026-10-20T16:27:53+00:00
---

# Pages

 [The official Livewire UI kit Build polished Livewire apps faster. Explore Flux Pro](https://fluxui.dev?utm_source=livewire&utm_medium=docs&utm_campaign=flux-pro-banner)

Livewire components can be used as entire pages by assigning them directly to routes. This allows you to build complete application pages using Livewire components, with additional capabilities like custom layouts, page titles, and route parameters.

## Routing to components

To route to a component, use the `Route::livewire()` method in your `routes/web.php` file:

```
Route::livewire('/posts/create', 'pages::post.create');
```

When you visit the specified URL, the component will be rendered as a complete page using your application's layout.

## Layouts

Components rendered via routes will use your application's layout file. By default, Livewire looks for a layout called `layouts::app` located at `resources/views/layouts/app.blade.php`.

You may create this file if it doesn't already exist by running the following command:

```
php artisan livewire:layout
```

This command will generate a file called `resources/views/layouts/app.blade.php`.

Ensure you have created a Blade file at this location and included a `{{ $slot }}` placeholder:

```
<!-- resources/views/layouts/app.blade.php -->
 
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
 
        <title>{{ $title ?? config('app.name') }}</title>
 
        @vite(['resources/css/app.css', 'resources/js/app.js'])
 
        @livewireStyles
    </head>
    <body>
        {{ $slot }}
 
        @livewireScripts
    </body>
</html>
```

You may customize the default layout by updating the `component_layout` configuration option in your `config/livewire.php` file:

```
'component_layout' => 'layouts::dashboard',
```

### Component-specific layouts

To use a different layout for a specific component, you may place the `#[Layout]` attribute above your component class:

```
<?php
 
use Livewire\Attributes\Layout;
use Livewire\Component;
 
new #[Layout('layouts::dashboard')] class extends Component {
    // ...
};
```

Alternatively, you may use the `->layout()` method within your component's `render()` method:

```
<?php
 
use Livewire\Component;
 
new class extends Component {
    // ...
 
    public function render()
    {
        return $this->view()
            ->layout('layouts::dashboard');
    }
};
```

## Setting the page title

Assigning unique page titles to each page in your application is helpful for both users and search engines.

To set a custom page title for a page component, first, make sure your layout file includes a dynamic title:

```
<head>
    <title>{{ $title ?? config('app.name') }}</title>
</head>
```

Next, above your Livewire component's class, add the `#[Title]` attribute and pass it your page title:

```
<?php
 
use Livewire\Attributes\Title;
use Livewire\Component;
 
new #[Title('Create post')] class extends Component {
    // ...
};
```

This will set the page title for the component. In this example, the page title will be "Create Post" when the component is rendered.

If you need to pass a dynamic title, such as a title that uses a component property, you can use the `->title()` fluent method in the component's `render()` method:

```
public function render()
{
    return $this->view()
         ->title('Create Post');
}
```

## Setting additional layout file slots

If your layout file has any named slots in addition to `$slot`, you can set their content in your Blade view by defining `<x-slot>` outside your root element. For example, if you want to be able to set the page language for each component individually, you can add a dynamic `$lang` slot into the opening HTML tag in your layout file:

```
<!-- resources/views/layouts/app.blade.php -->
 
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $lang ?? app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
 
        <title>{{ $title ?? config('app.name') }}</title>
 
        @vite(['resources/css/app.css', 'resources/js/app.js'])
 
        @livewireStyles
    </head>
    <body>
        {{ $slot }}
 
        @livewireScripts
    </body>
</html>
```

Then, in your component view, define an `<x-slot>` element outside the root element:

```
<x-slot:lang>fr</x-slot> // This component is in French
 
<div>
    // French content goes here...
</div>
```

## Accessing route parameters

When working with page components, you may need to access route parameters within your Livewire component.

To demonstrate, first, define a route with a parameter in your `routes/web.php` file:

```
Route::livewire('/posts/{id}', 'pages::show-post');
```

Here, we've defined a route with an `id` parameter which represents a post's ID.

Next, update your Livewire component to accept the route parameter in the `mount()` method:

```
<?php
 
use App\Models\Post;
use Livewire\Component;
 
new class extends Component {
    public Post $post;
 
    public function mount($id)
    {
        $this->post = Post::findOrFail($id);
    }
};
```

In this example, because the parameter name `$id` matches the route parameter `{id}`, if the `/posts/1` URL is visited, Livewire will pass the value of "1" as `$id`.

## Using route model binding

Laravel's route model binding allows you to automatically resolve Eloquent models from route parameters.

After defining a route with a model parameter in your `routes/web.php` file:

```
Route::livewire('/posts/{post}', 'pages::show-post');
```

You can now accept the route model parameter through the `mount()` method of your component:

```
<?php
 
use App\Models\Post;
use Livewire\Component;
 
new class extends Component {
    public Post $post;
 
    public function mount(Post $post)
    {
        $this->post = $post;
    }
};
```

Livewire knows to use "route model binding" because the `Post` type-hint is prepended to the `$post` parameter in `mount()`.

Like before, you can reduce boilerplate by omitting the `mount()` method:

```
<?php
 
use Livewire\Component;
use App\Models\Post;
 
new class extends Component {
    public Post $post;
};
```

The `$post` property will automatically be assigned to the model bound via the route's `{post}` parameter.

## See also

- **[Components](https://livewire.laravel.com/docs/4.x/components)** — Learn about creating and organizing components
- **[Navigate](https://livewire.laravel.com/docs/4.x/navigate)** — Build SPA-like navigation between pages
- **[Redirecting](https://livewire.laravel.com/docs/4.x/redirecting)** — Redirect users after form submissions or actions
- **[Layout Attribute](https://livewire.laravel.com/docs/4.x/attribute-layout)** — Specify layouts for full-page components
- **[Title Attribute](https://livewire.laravel.com/docs/4.x/attribute-title)** — Set page titles dynamically

 Ecosystem sponsor

 [Neon is a complete backend built around Lakebase Postgres, for developers, startups, and agent platforms, by Databricks. neon.com &nearr;](https://neon.com)

 [Return to Components](https://livewire.laravel.com/docs/4.x/components) [Continue to Properties](https://livewire.laravel.com/docs/4.x/properties)
