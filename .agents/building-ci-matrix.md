# Building the CI build matrix

The matrix aims for high test coverage of the library with each supported dependency, while running only the jobs that test something no other job does.

## Premise

A test job exercises two layers, and each layer depends on a different part of the matrix:

1. The platform layer generates SQL and introspects schemas. Its behavior depends on the database server version, not on the driver that sends the SQL.
2. The driver layer connects, binds parameters, and fetches results. It also determines the PHP type of each fetched value (e.g. an integer column comes back as an `int` from one driver and as a `string` from another). Its behavior depends on the driver and the PHP version, not on the server version.

Running every platform version with every driver tests each of these things more than once. So platform versions and drivers are selected separately.

Code coverage only reveals a dedicated code path in the library. It doesn't reveal a server that changes behavior under unchanged library code. LTS releases are what most users run, so they get a job even without a dedicated code path.

## PHP versions

Test the lowest supported, the latest stable, and nightly. Test an intermediate version only if it has a dedicated code path, as determined by code coverage.

## Database platform versions

1. The lowest supported version.
2. The highest version. A pre-release version runs in addition to the latest GA version, not instead of it.
3. Every LTS release that the vendor still supports, for vendors that designate LTS releases.
4. Intermediate versions with a dedicated code path, as determined by code coverage. Prefer an LTS release over a non-LTS one, even if an older non-LTS release would trigger the same code path.

## Pattern

1. Each selected platform version runs once, on the latest stable PHP, with the PDO driver of the platform.
2. Each supported driver runs against the latest GA version of the platform, on the latest stable PHP and on the lowest supported PHP.
3. Each configuration variant (e.g. TLS, collation) runs once, with the driver the variant concerns. A server-side variant, such as a collation, runs with the PDO driver.
4. The SQLite drivers run on the lowest supported and the latest stable PHP.
5. The lowest Composer dependencies run on the lowest supported PHP with `pdo_sqlite`. All other jobs use the highest dependencies.
