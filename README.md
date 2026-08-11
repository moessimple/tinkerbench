# tinkerbench

[![tests](https://github.com/moessimple/tinkerbench/actions/workflows/tests.yml/badge.svg)](https://github.com/moessimple/tinkerbench/actions/workflows/tests.yml)

tinkerbench is a local PHP snippet runner built on Laravel, Inertia, and Vue. Type a snippet, run it, and see the output right away, without wiring it into an existing project first. Each run executes in its own isolated subprocess, so a snippet that redeclares a class or function never crashes a later run.

## Requirements

* PHP 8.5
* Node.js

[Laravel Herd](https://herd.laravel.com) is recommended (gives the app a `https://tinkerbench.test` URL and its own PHP version) but not required: without it, tinkerbench runs snippets through the PHP binary that is already running the app.

## Installation

Clone the repository and run the setup script:

```bash
git clone git@github.com:moessimple/tinkerbench.git
cd tinkerbench
composer setup
```

`composer setup` installs the PHP and Node dependencies, creates the `.env` file, generates the application key, runs the database migrations, and builds the frontend assets.

With Herd, link the project so it is reachable at `https://tinkerbench.test`:

```bash
herd link tinkerbench
herd secure tinkerbench
```

Without Herd, start the app with:

```bash
composer dev
```

By default, snippets run through the PHP binary under `~/Library/Application Support/Herd/bin`. Set `HERD_BIN` in `.env` to point at a different Herd installation.

## Usage

Open the app in the browser, type a snippet, and run it:

```php
echo 'Hello, world!';
```

The output appears below the editor.

## Testing

Run the full quality gate (formatting, static analysis, type coverage, and the test suite):

```bash
composer test
```

Run a focused set of tests:

```bash
php artisan test --compact --filter=HerdTest
```

## Architecture

Business code lives under `src/Application`, `src/Domain`, and `src/Support`, domain first, rather than under `app/`, which stays reserved for framework glue such as service providers. The full set of project conventions is recorded in `.ai/rules`.

## Credits

* [Maurice Hadamczyk](https://github.com/moessimple)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
