# AGENTS.md

## Guidelines

You must remember to follow all instructions in this AGENTS.md file, and you must not deviate from them unless they are incorrect and you have a better widely accepted standard from top programmers or largely used projects. You must ensure that the code is clean, well-documented, and follows best practices for TypeScript development.

## What is the abetwothree/laravel-ts-publish project?

The `abetwothree/laravel-ts-publish` project is a Laravel package aiming to convert several Laravel & PHP entities into their respective TypeScript type or interface definition to able to have strong typing on the frontend based on the data & structure of the backend.

## Commands and their descriptions

- Install dependencies: `composer install` & `npm install`
- Run tests with coverage: `composer test-cov` in parallel mode to run faster.
  - Run tests without coverage: `composer test` in parallel mode to run faster.
- Run linter: `composer lint`

`composer lint` and `composer analyse` pass `--memory-limit=-1` to PHPStan, so a local `memory_limit` ini does not apply to them. If PHPStan still dies during result-cache revalidation, delete `build/phpstan` and re-run.

Do not try to run the `ts:publish` command from the workbench directory because it won't have a database connection, so the output will not have DB backed values.

## Implementing Types

Implementing features related to figuring out types and publishing them to TypeScript should follow this workflow:

- Write tests testing thoroughly the new feature with the expected PHP input and expected TypeScript output to ensure everything works as intended.
- Write the fixture in the test `workbench/*` app
- Write the code for the feature in `src/*`
- Run `composer test` and wait for it to finish
- Check TypeScript output `workbench/resources/js/types/data/` to make sure the functional or types output is correct
- If not correct, make the necessary changes in the `src/*` code or the test fixture and repeat the process.
- Even if tests pass, if the outputted types do not match what we expected them to be, then the code is incorrect.
- If the tests pass but the output is incorrect, after updating the source code, update the test to properly test the expected TypeScript output.

Two CI gates check the generated output programmatically — one for properties that regressed to
`unknown`, one for type tokens emitted without their import. See [Type inference gates](./docs/testing/type-inference-gates.md);
note both read the **committed** type trees, so commit the regenerated output before running them.

## Use AI tools

When working on this project, make sure to use the available MCP servers and skills as reference and helpers to ensure the code is of the highest quality possible. Use the TypeScript Expert skill to help with TypeScript-specific questions and best practices and ensure they meet the project's standards.

Use the skills in `.github/skills` to help follow the best practices and standards.

## To do lists

When it makes sense, create a to-do list when working on a feature or bugfix. Use checkboxes so that it's easy to see what is done and what is left to do.

## Feature descriptions

When you need an explanation for how a feature works, you can read the README.md file. That has all the current documentation for the project.

## Strict mode

All php files must have this line at the top of the file to ensure strict types:

```php
declare(strict_types=1);
```

## PHPStan rules

* All PHPStan code must be at level 10
* Complicated types for arrays, unions, intersections or others must be defined at the top of the file as `@phpstan-type ExampleType = array<string, class-string>` or Similar, and then used in the code as `ExampleType` instead of writing the full complicated type inline. This makes the code more readable and maintainable.
* Make sure to import types from dependent classes, traits or interfaces with `@phpstan-import-type` and use them in the code instead of writing the full type inline. This also makes the code more readable and maintainable.

## How to write comments

This applies to all files outside of the `workbench` directory.

### Comment length

Outright, choose no comments if the code is self explanatory. If a comment is needed, it should be short and to the point.

**At most 3 lines, each at most 120 characters.** Prefer one line, or none.

Say _why_, then stop. Do not restate what the code does, narrate how a decision was reached, or record how something was verified — that belongs in the commit message or the PR. Only a genuinely non-obvious failure mode or an invisible-from-the-code workaround earns more, and even then keep it tight.

This applies to every file type maintained in this repo.

### Comment Format

All functions and methods must have a super simple docblock comment that describes what the function does from an extremely high level overview. E.g. "Calculates the area of a rectangle."

It also needs to include parameter types for its parameters and its return type as shown below.

## Functions and methods style

All functions and methods must have a docblock comment that describes what the function does, its parameters and its return type. The docblock must be in the following format below. 

@param and @return types are only needed if the function accepts arrays or collections. For scalar types, the type can be inferred from the function signature. For arrays and collections, it's important to specify the shape of the elements in the array or collection to provide better documentation and help with static analysis tools like PHPStan.

```php
/**
 * Description of what the function does.
 * 
 * (Optional) What uses this function to accomplish what, and how it fits into the bigger picture of the project.
 * 
 * @param array{height: number, width: number} $param1 Description of the first parameter.
 * @return Collection<int, array{height: number, width: number}> Description of the return type.
 */
function exampleFunction(array $param1, string $param2): Collection {
    // function body
}
```

## Imports

Always use imports for classes. Never inline them like `\ReflectionEnum` without importing it at the top of the file. This makes it clear where the class is coming from and makes the code more readable. Use the class by doing `use ReflectionEnum;` at the top of the file.

A Laravel class absent from a supported Laravel version must instead be referenced by string FQCN behind `class_exists()`, recorded in [docs/laravel-version-guards.md](./docs/laravel-version-guards.md).

## Methods & properties organization in classes

When writing methods & properties in a class they should be ordered in the following way unless organized in a specific way for another reason. The order is as follows:

1. Constants
   1. Public
   2. Protected
   3. Private
2. Static properties
   1. Public
   2. Protected
   3. Private
3. Instance properties
   1. Public
   2. Protected
   3. Private
4. Constructor
5. Public methods
   1. Static methods
   2. Instance methods
6. Protected methods
   1. Static methods
   2. Instance methods
7. Private methods
   1. Static methods
   2. Instance methods

## Code review

Any code inside the `workbench` directory is meant for testing.

TypeScript & JSON files inside `workbench/resources/js/types` are published files generated by testing the package. They are meant to be used for ensuring that the generated TypeScript files are correct.

## Testing

### Test TypeScript files

DO NOT EDIT THE FILES IN `workbench/resources/js/types/`!! They are auto generated when the tests run. They are the end result of tests and are used to verify results.

If the TypeScript files need to be different then they should be changed by changing the PHP source files, changing the test cases, or changing the transformation logic. Then when the tests run, they will generate the correct TypeScript files.

- `workbench/vendor` is a testbench-generated symlink and is intentionally untracked. Never `git add` it.

## Git

- Never merge into the `main` branch. That is handled by CI or PRs by the users.

## Documentation

### README.md vs code comments

Documenting features go in README.md to document features for users. The code itself should have comments on all methdos as explained above or in the code itself.

### Known gaps

Work you decide _not_ to do belongs in a written record, not in a commit message or a source comment. Read
[docs/known-gaps.md](./docs/known-gaps.md) before starting anything in `src/Ast/` — several entries exist
specifically to stop the next person "fixing" something that is deliberate.

That file is deliberately narrow: it holds only what changes the package's output for a user, or what makes
a passing gate narrower than it looks. Its own header states the test. Everything else you defer — internal
refactor debt, test-suite quality notes, release chores — belongs in the follow-ups ledger of the plan that
deferred it, which is where that work is re-read from. Do not widen this file back out.

That ledger is local: `docs/superpowers/` is gitignored, so a plan and its deferred work exist only on the
machine that wrote them. Anything intended for a future plan is therefore also filed as a GitHub issue
labelled `next-plan`, with the reasoning and a pointer to the ledger entry, and the ledger entry carries the
issue number. The issue is the durable record; the ledger is the working copy.

### Change log

Do not update the CHANGELOG.md file. That is handled by CI when a new version is released. The CHANGELOG.md file is meant to be a record of changes for users, not for developers to update manually. It is automatically updated based on the commits and PRs that are merged into the main branch and updated when a new version is released.
