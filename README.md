
# tinkerbench

![App](images/app.jpg)

[![tests](https://github.com/moessimple/tinkerbench/actions/workflows/tests.yml/badge.svg)](https://github.com/moessimple/tinkerbench/actions/workflows/tests.yml)

tinkerbench is a browser based REPL for any project linked in [Laravel Herd](https://herd.laravel.com).
Write a PHP snippet, run it against that project's own app context, and see the output immediately, no separate setup per project.
A take on Laravel's [`tinker`](https://github.com/laravel/tinker), inspired by [Tinkerwell](https://tinkerwell.app).

## Features

* Runs snippets against any Herd linked project, switch projects without leaving the page.
* Saves multiple named snippets per project. Create, rename, and delete them as needed.
* Command palette (`⌘P`) to jump between snippets and projects, similar to an editor's quick open.
* Monaco based editor with PHP syntax highlighting, autosave, and a run shortcut (`⌘Enter`).
* PHP autocompletion, hover documentation, and signature help for the target project's own code, powered by intelephense (the same language server VS Code uses).
* Raw or rendered output. Rendered mode shows `dump()`/`dd()` calls with Symfony's interactive VarDumper, syntax highlights JSON, and renders HTML output in a sandboxed frame.
* A debug tab next to the output, showing the queries, timing, and dumps a snippet run touched, and any exception it threw.

**tinkerbench runs locally on your own machine through Herd. It's a personal dev tool.**

## Roadmap

* A light theme alongside the current dark one, with a way to switch between them.

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

## Usage

Open [`https://tinkerbench.test`](https://tinkerbench.test). It opens the `scratch` snippet in the `tinkerbench` project by default.

* Write PHP in the editor and run it with the play button or `⌘Enter`.
* Press `⌘P` to search snippets, switch to another Herd project (`/` prefix), or create a new snippet by typing a name that doesn't exist yet.
* Toggle raw/rendered output, clear the output, or maximize the editor from the sidebar icons.

## Staying Up to Date

Pull the latest changes and rerun the setup script to bring dependencies and migrations back in sync:

```bash
git pull
composer setup
```

## Testing

Run the full quality gate (formatting, static analysis, type coverage, and the test suite):

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
