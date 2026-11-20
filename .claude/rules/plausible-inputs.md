---
paths:
  - "src/**"
  - "docs/known-gaps.md"
---

# Plausible inputs

The package reads arbitrary PHP, so some input will always defeat it. Inference is held to a **plausible** bar: the
code an application writes in its resources, models, requests and helpers.

- **Plausible** input publishes a type its payload satisfies, or the flat type `develop` publishes. Idiomatic Laravel
  counts, as do patterns in Laravel's docs and shapes found in real projects. Missing one is a bug to fix.
- **Contrived** input gets a line in `docs/known-gaps.md` instead of code: PHP magic no application reaches for, such
  as a namespace constant redefining a built-in one, a reference through a variable variable, a `__get()` and an
  `__isset()` that disagree, a property hook that unsets another property, or an unpack that fills a conditional
  helper's default.
- Classify by evidence: search the fixture app, real applications and public code (GitHub code search) for the shape,
  and cite what you found when you decline it.
- Output that fails `tsc` and takes a one-line fix is fixed whatever its likelihood.
- The discriminant write check names the writes it knows, so a reviewer can always find one more. Where it meets a
  construct it does not recognise, decline the split rather than model one more writer; #174 tracks turning it into
  an allowlist of item shapes known to be read-only.
