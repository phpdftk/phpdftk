# Contributing

Thank you for your interest in contributing to phpdftk!

## Development Setup

```bash
git clone https://github.com/apprlabs/phpdftk.git
cd phpdftk
composer install
```

**Requirements:** PHP 8.4+ with extensions: `zlib`, `openssl`, `simplexml`.

## Running Tests

```bash
# All tests
vendor/bin/phpunit

# Single test suite
vendor/bin/phpunit --testsuite core
vendor/bin/phpunit --testsuite writer

# Single test file
vendor/bin/phpunit packages/pdf/core/tests/Document/SimpleTextTest.php

# Single test method
vendor/bin/phpunit --filter testGeneratesSimpleTextPdf
```

## Static Analysis

```bash
scripts/analyse
```

PHPStan runs at level 6. All code must pass before merging.

## Pull Request Guidelines

1. **Fork and branch** from `main`.
2. **Write tests** for new features and bug fixes.
3. **Run the test suite** and static analysis before submitting.
4. **Keep PRs focused** — one feature or fix per PR.
5. **Follow existing patterns** — match the code style, naming conventions, and architecture of the surrounding code.

## Project Structure

This is a monorepo with 14 packages under `packages/`. Each package has its own `composer.json`, `src/`, and `tests/` directories. See the [README](README.md) for the full package overview.

## Reporting Bugs

Open an issue at [github.com/apprlabs/phpdftk/issues](https://github.com/apprlabs/phpdftk/issues) with:

- PHP version and OS
- Minimal reproduction steps
- Expected vs. actual behavior
