# Contributing

Contributions are welcome and appreciated! Here's everything you need to get started.

## Prerequisites

- PHP 8.4+
- Composer

## Getting Local Environment Started

1. Fork the repo and clone your fork locally.
2. Run `composer install` to install dependencies.
3. Run `npm install` to install JavaScript dependencies.
4. Run `touch workbench/database/database.sqlite` to create the SQLite database file for testing.
5. Run `vendor/bin/testbench migrate` to set up the test database.
6. Run `composer test` to run the tests and ensure everything is set up correctly.

## Workflow

1. Create a new branch from `main` for your change.
2. Make your changes and add or update tests as needed.
3. Ensure all checks pass before submitting your pull request (see below).
4. Submit a pull request to the `main` branch.

## Available Composer Scripts

| Command                | Description                                           |
|------------------------|-------------------------------------------------------|
| `composer test`        | Run the test suite (Pest)                             |
| `composer format`      | Fix code style (Pint)                                 |
| `composer analyse`     | Run static analysis (PHPStan level 10)                |
| `composer lint`        | Run both Pint and PHPStan in sequence                 |

## Before Submitting a Pull Request

Please make sure all of the following pass:

```bash
composer lint
composer test
```

`composer lint` will automatically fix code style issues via Pint and then run PHPStan static analysis. If Pint makes any changes, make sure to commit them.

## Coding Guidelines

- Follow the existing code style and conventions — check sibling files for reference.
- Add tests for any new functionality or bug fixes.
- Do not introduce new dependencies without discussion first.
