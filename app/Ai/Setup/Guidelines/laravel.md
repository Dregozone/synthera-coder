# Laravel guidelines

- Do things the Laravel way: Eloquent models and relationships, form requests for validation, named routes with `route()`, config files read through `config()` (never `env()` outside config).
- Prefer `php artisan make:*` commands to create classes, migrations and tests, so files land in the right place with the right structure.
- Look at sibling files before creating a new one and copy their structure, naming and style.
- Every change should be covered by a test. The project's tests run with `php artisan test --compact`.
- PHP style: curly braces on every control structure, typed parameters and return types, constructor property promotion.
