# Fixture app

`fixture-app/` is a small Laravel 13 + Inertia + Vue app that installs this package from the repository root, set up
as the installation docs say. It answers what the workbench cannot: what `ts:publish` writes for a real app with a
database, and whether `vue-tsc` and a Vite build accept it.

It is `export-ignore`d, so a Composer install of the package never ships it. Only the plain app is tracked: its
`vendor/`, `node_modules/`, `.env`, SQLite database and published `resources/js/types/data/` are not.

## Set it up

```bash
cd fixture-app
composer install
npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
```

Composer links the package through a path repository at `..`, so the app always runs the checked-out branch.

## Check a change

```bash
php artisan ts:publish --fresh
npm run types   # vue-tsc --noEmit
npm run build   # the Vite plugin publishes with --only-functional first
```

`npm run types` reports no errors on a clean checkout. An error naming a missing `@tolki/types` export means the app's
`node_modules` is older than the `@tolki/types` the generated files import.

## Rules

- Keep it plain. Probe classes for an investigation are added in a copy of the app, not committed here.
- Its config is the package's published `config/ts-publish.php` with two changes: `models.additional_directories`
  lists `DatabaseNotification`, so `User`'s `notifications` relation resolves, and `broadcast_events.enabled` is
  `false`, which the Boost skill evals rely on. Republish it when the package config gains keys, and keep both.
- Pint, PHPStan, Pest and the root `tsconfig.json` never read it.
