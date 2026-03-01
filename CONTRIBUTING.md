# Contributing

## Getting Local Environment Started

1. Fork the repo and clone your fork locally.
2. Run `composer install` to install dependencies.
3. Run `touch workbench/database/database.sqlite` to create the SQLite database file for testing.
4. Run `vendor/bin/testbench migrate` to set up the test database.
5. Run `vendor/bin/pest` to run the tests and ensure everything is set up correctly.
