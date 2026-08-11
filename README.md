# tinkerbench

[![tests](https://github.com/moessimple/tinkerbench/actions/workflows/tests.yml/badge.svg)](https://github.com/moessimple/tinkerbench/actions/workflows/tests.yml)

tinkerbench is a local PHP snippet runner built on Laravel, Inertia, and Vue. Type a snippet, run it, and see the output right away, without wiring it into an existing project first. Each run executes in its own isolated subprocess, so a snippet that redeclares a class or function never crashes a later run.

## Scope

tinkerbench is built for a single developer running it on their own machine. It has no authentication and executes arbitrary PHP with no resource limits beyond the process itself. It is not meant to run as a shared or externally reachable service.

## Requirements

* [Laravel Herd](https://herd.laravel.com), which provides PHP and Node.js

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

## Credits

* [Maurice Hadamczyk](https://github.com/moessimple)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
