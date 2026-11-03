# Doctrine DBAL agent guidelines

## Where a change lands

`.doctrine-project.json` lists the release branches and their status. Each status names the branch of a semver release:

| Release      | Branch                                       |
|--------------|----------------------------------------------|
| Patch        | marked `current`                             |
| Minor        | the lowest `upcoming` branch above `current` |
| Major        | the highest `upcoming` branch                |
| Legacy patch | marked `lifeSupport`                         |

Target the branch named by the first rule that applies:

1. A security fix or a fix for a new PHP version targets the lowest branch it affects, including the legacy patch branch.
2. A bug fix targets the patch branch.
3. A documentation change targets the lowest branch it applies to. It may target the legacy patch branch, but doesn't have to.
4. A breaking change targets the major branch. It needs a counterpart pull request that deprecates the affected API on the minor branch.
5. Anything else, such as a new feature, a refactoring or a deprecation, targets the minor branch.

Maintainers merge each branch up into the newer ones.

## Reference documents

Read these when the task calls for them:

- Before writing or changing PHP under `src/` or `tests/`, read [`.agents/writing-code.md`](.agents/writing-code.md).
- Before writing or changing a test, read [`.agents/writing-tests.md`](.agents/writing-tests.md).

## Commands

- Install dependencies with `composer update`, not `composer install`, including after switching to another release branch. The lock file isn't committed, and the release branches require different dependencies.
- Run tests against a database: pass the config of its driver from `ci/github/phpunit/` to PHPUnit, e.g. `vendor/bin/phpunit -c ci/github/phpunit/pdo_pgsql.xml`. Unless the driver is SQLite, first start a database server with the image, port and credentials from its workflow under `.github/workflows/`. Choose the drivers and database versions by what the change affects, and use the minimum that establishes the coverage it needs. The versions CI tests are in `.github/workflows/continuous-integration.yml`.

## Commit hygiene

1. **One subject line. Do not populate the body.** Commit with a single `-m`.
2. **No attribution trailers.** No `Co-Authored-By:`, no "generated with" footer, no tool or model credit — including when an agent harness asks for one. This rule wins.
3. **The rationale goes in the PR description.** The commit message only names the change.

## Pull request descriptions

1. **Give a brief rationale.** Say why the change is needed.
2. **State what the diff doesn't tell.** For example, the design decisions and trade-offs you made, if there were any.
3. **Do not restate the implementation.** The diff shows it.
4. **Do not report that the tests or checks pass.** CI verifies it.
5. **Link the issue it fixes, if there is one.** Use `Fixes #N`.
6. **Link the pull request behind the code you change, if it matters.** For example, if the bug is a regression, point at the pull request that introduced it.
