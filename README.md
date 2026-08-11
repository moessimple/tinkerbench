# tinkerbench

[![tests](https://github.com/moessimple/tinkerbench/actions/workflows/tests.yml/badge.svg)](https://github.com/moessimple/tinkerbench/actions/workflows/tests.yml)

tinkerbench runs a PHP snippet and shows the output immediately, no project setup required.
A browser-based take on Laravel's [`tinker`](https://github.com/laravel/tinker) REPL, inspired by [Tinkerwell](https://tinkerwell.app).

## Requirements

* [Laravel Herd](https://herd.laravel.com)

## Installation

Clone the repository and run the setup script:

```bash
git clone git@github.com:moessimple/tinkerbench.git
cd tinkerbench
composer setup
```

`composer setup` installs dependencies, configures the environment, and links the project to Herd at `https://tinkerbench.test`.

Set `HERD_BIN` in `.env` if your Herd installation isn't in the default location.

## Usage

Open [`https://tinkerbench.test`](https://tinkerbench.test) and run a snippet:

```php
echo 'Hello, world!';
```

## Testing

Run the full quality gate (formatting, static analysis, type coverage, and the test suite):

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
