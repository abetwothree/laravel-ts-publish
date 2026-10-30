# Taskboard

Laravel 13 application with Inertia v3 and Vue 3 (TypeScript, `<script setup>`), styled with Tailwind v4.

## Stack notes

- `abetwothree/laravel-ts-publish` generates frontend TypeScript from the PHP side into `resources/js/types/data/` (aliased as `@data/*`). That directory is generated output: never edit it by hand, regenerate it with `php artisan ts:publish`.
- `@tolki/ts` is installed and its Vite plugin republishes automatically during `vite dev`.
- Pages live in `resources/js/pages/`, resolved by Inertia component name (`Tasks/Index` → `resources/js/pages/Tasks/Index.vue`).

## Commands

- `php artisan test` — PHP tests
- `npm run types` — type-check the frontend with vue-tsc
- `npm run build` — production build
