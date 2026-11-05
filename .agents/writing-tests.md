# Writing DBAL tests

Read [`docs/en/reference/testing.rst`](../docs/en/reference/testing.rst) first. The rules below build on it.

- Assert what the code is for, not what the line under test does. Don't assert that a getter returns its constant, or feed a defensive branch an input no real caller produces. Cover such a line through the behavior that consumes it.
- Name a test for the requirement or scenario, never for a code path or the verdict: `testAddDuplicateColumn`, not `testAddColumnThrowsException`.
- Cover the paths of one contract with a data provider. Its rows carry data and the expected result, and the test body has no loop or `if`. A returned value and a thrown exception are two contracts, so two tests. Build aggregates such as a table in the test body, not in the provider.
- Never cover logic specific to a database or driver with a unit test, such as one asserting the SQL a platform generates. Cover it with a functional test that asserts the portable outcome, not the SQL.
- Gate a test as narrowly as the behavior requires: no gate by default, a `supports…()` capability check when some platforms lack the feature, `instanceof` only for behavior that exists on one platform.
- Isolate a functional test by dropping the tables and other database objects it creates before creating them, not by giving them names no other test uses.
- Call `expectException()` right before the one statement that throws, after all setup.
- Create only the objects the test needs, and nothing irrelevant to it.
- Cover every line of logic a pull request adds or changes, with tests that run against each platform and driver the change affects.
- Type each data-provider column to the test parameter it fills, not to the values the current rows hold, so that a new row the test accepts doesn't require changing the provider's return type.
