# tinkerbench

[![tests](https://github.com/moessimple/tinkerbench/actions/workflows/tests.yml/badge.svg)](https://github.com/moessimple/tinkerbench/actions/workflows/tests.yml)

tinkerbench is a local PHP snippet runner built on Laravel, Inertia, and Vue. Enter a snippet of PHP code, run it, and see the output. Each run executes in an isolated subprocess, so redeclaring a class or function between runs never crashes the app.

## Requirements

* PHP 8.5
* Node.js
* [Laravel Herd](https://herd.laravel.com)

## Installation

Clone the repository and run the setup script:

```bash
git clone git@github.com:moessimple/tinkerbench.git
cd tinkerbench
composer setup
```

`composer setup` installs the PHP and Node dependencies, creates the `.env` file, generates the application key, runs the database migrations, and builds the frontend assets.

Link the project to Herd so it is reachable at `https://tinkerbench.test`:

```bash
herd link tinkerbench
herd secure tinkerbench
```

By default, snippets run through the PHP binary under `~/Library/Application Support/Herd/bin`. Set `HERD_BIN` in `.env` if your Herd installation lives elsewhere.

## Usage

Open `https://tinkerbench.test`, type a PHP snippet into the editor, and run it. The output appears below.

## Testing

Run the full quality gate (formatting, static analysis, type coverage, and the test suite):

```bash
composer test
```

Run a focused set of tests:

```bash
php artisan test --compact --filter=SomeTest
```

## Architecture

Business code lives under `src/Application`, `src/Domain`, and `src/Support`, domain first, rather than under `app/`, which stays reserved for framework glue such as service providers. The full set of project conventions is recorded in `.ai/rules`.

## Credits

* [Maurice Hadamczyk](https://github.com/moessimple)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
