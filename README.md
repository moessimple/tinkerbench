
# tinkerbench

![App](images/app.png)

[![tests](https://github.com/moessimple/tinkerbench/actions/workflows/tests.yml/badge.svg)](https://github.com/moessimple/tinkerbench/actions/workflows/tests.yml)

tinkerbench is a browser based REPL for any project linked in [Laravel Herd](https://herd.laravel.com).
Write a PHP snippet, run it against that project's own runtime, and see the output immediately, no separate setup per project.
A take on Laravel's [`tinker`](https://github.com/laravel/tinker), inspired by [Tinkerwell](https://tinkerwell.app).

## Features

* Any Herd linked project as a target, switchable without leaving the page.
* Multiple named snippets per project; create, rename, and delete them as needed.
* Command palette (`⌘P`) to jump between snippets and projects, similar to an editor's quick open.
* Monaco based editor with PHP syntax highlighting, autosave, and a run shortcut (`⌘Enter`).
* PHP autocompletion, hover documentation, and signature help for the target project's own code, powered by the intelephense language server (also widely used as a VS Code extension).
* Output rendering adapts to the value: `dump()`/`dd()` calls use Symfony's interactive VarDumper, JSON is syntax highlighted, and HTML output renders in a sandboxed frame.
* A single chronological feed of everything a run touched, each entry as its own card in execution order. Every project gets dumps, return values, exceptions, and standard output. A Laravel project also gets database queries, log entries, and N+1 warnings. Filter by kind with live counts, and click a card to jump the editor to the line that produced it.
* Query cards pretty-print and syntax-highlight their SQL, flag queries that ran more than once or slower than 100ms, and can be sorted slowest first. Every card has a button to copy its contents.
* Light and dark theme, switchable from the sidebar, defaulting to your system preference.

**tinkerbench runs locally on your own machine through Herd. It's a personal dev tool.**

## Requirements

* [Laravel Herd](https://herd.laravel.com) with PHP 8.5 available. Herd bundles PHP, Composer, and Node, so there is nothing else to install.
* Target projects need PHP 8.2 or newer (`herd isolate` per project).
* The Laravel feed (query, log, and N+1 cards) needs a Laravel 12 or newer target. Any other PHP project still produces dumps, return values, exceptions, and standard output.

## Installation

Clone the repository and run the setup script:

```bash
git clone git@github.com:moessimple/tinkerbench.git
cd tinkerbench
composer setup
```

`composer setup` installs the PHP and JS dependencies, creates `.env` with an app key, runs the migrations, builds the frontend, links the project to Herd at [`https://tinkerbench.test`](https://tinkerbench.test), and sets that site to PHP 8.5.

## Usage

Open [`https://tinkerbench.test`](https://tinkerbench.test). It opens the `scratch` snippet in the `tinkerbench` project by default.

* Write PHP in the editor and run it with the play button or `⌘Enter`.
* Press `⌘P` to search snippets and projects. Start the query with `#` to search snippets only, with `/` to switch to another Herd project, or type a name that doesn't exist yet to create a snippet.
* Clear the output or maximize the editor from the sidebar icons.

## Staying Up to Date

Pull the latest changes and rerun the setup script to bring dependencies and migrations back in sync:

```bash
git pull
composer setup
```

## Testing

Run the full quality gate (formatting, static analysis, type coverage, and the PHP and JS test suites):

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
