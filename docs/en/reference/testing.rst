Testing Guidelines
===================

To ensure high quality, all components of the Doctrine DBAL library are extensively covered with tests.

Having the code covered with tests and running all tests against each individual code change helps prevent
breakages of the library logic when its code changes.

Additionally, when code changes are accompanied by new tests, the tests:

1. Help understand what problem the given code change is trying to solve.
2. Make sure that the problem being solved needs to be solved in the DBAL.
3. Document the proper usage of the DBAL APIs.

Requirements
------------

1. Each pull request that adds new or changes the existing logic must have tests.

   .. note::

       Modifications to the keyword lists under the ``Doctrine\DBAL\Platforms\Keywords`` namespace
       don't have to be covered with tests.

2. The test that covers certain logic must fail without this logic implemented.

Types of Tests
--------------

Doctrine DBAL primarily uses unit and integration tests.

Unit Tests
~~~~~~~~~~

Unit tests are meant to cover the logic of a given unit (e.g. a class or a method) including the logic
of its interaction with other units. In this case, the other units could be mocked.

Unit tests are most welcomed for testing the logic that the DBAL itself defines (e.g. logging, caching, data types).

In this case, the DBAL is the source of truth about what this logic is and the test plays the role of its description.

Integration Tests
~~~~~~~~~~~~~~~~~

Integration (a.k.a. functional) tests are required when the behavior under test is dictated by the logic
defined outside of the DBAL. It could be:

- The underlying database platform.
- The underlying database driver.
- SQL syntax and the standard as such.

It is important to have integration tests for the cases above. Unlike unit tests, they make the external components
the source of truth and help make sure that the logic implemented in the DBAL is correct even if the external components
change (e.g. a new version of a database platform is supported).

When are Integration Tests not Required?
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Some cases cannot be reproduced with the existing integration testing suite. It could be the scenarios that involve
multiple concurrent database connections, transactions, locking, performance-related issues, etc.

In such cases, it is still important that a pull request fixing the issues is accompanied by a free-form reproducer
that demonstrates the issue being fixed.

Recommendations on Writing Tests
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Tests in Doctrine DBAL are located under the ``tests`` directory and implemented on top of PHPUnit. Use its
`documentation <https://phpunit.de/documentation.html>`_ to get started.

Writing Integration Tests
^^^^^^^^^^^^^^^^^^^^^^^^^

Integration tests are located under the ``tests/Doctrine/Tests/DBAL/Functional`` directory. Unlike unit tests,
they require a real database connection to test their logic against.

It is recommended to use ``Doctrine\DBAL\Tests\FunctionalTestCase`` as the base class for integration tests.
Based on the configuration, it will automatically create and connect to the test database.

Data Fixtures in Integration Tests
++++++++++++++++++++++++++++++++++

To test selecting and fetching data from the database, the test may create the necessary schema and populate it
with the test data. To create database tables, instead of checking if the table exists and reusing it,
it is recommended to use ``FunctionalTestCase::dropAndCreateTable()``. This way, the table will be dropped and created every time
providing better isolation between the test runs.

Testing Different Database Platforms
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Although most of the issues are originally discovered on a specific database platform,
the integration tests for all issues should be implemented by default at the database abstraction level
and run against all the platforms that support the API being tested.

This allows us to ensure that the same scenario that was found failing on one platform also works on others. Or otherwise,
the same issue could be reproduced on the platforms where it wasn't originally tested.

If the newly added test fails on other platforms, and fixing it is out of the scope, the test can be explicitly marked
as incomplete which will identify the issue.

Examples of such tests could be found under the ``Doctrine\DBAL\Tests\Functional\Platform`` namespace.

Using Unit and Integration Tests Together
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

For example, the ``AbstractPlatform::modifyLimitQuery()`` method has both unit and integration tests.

1. Unit test cases for each platform (``Doctrine\DBAL\Tests\Platforms\*PlatformTest``) have a test that calls
   ``$platform->modifyLimitQuery()`` and asserts that the resulting SQL looks as expected.
   These tests cannot guarantee that the generated SQL is valid syntactically and semantically but they guarantee
   that the code works as designed. They provide fast feedback because they don't require a database connection
   and can test all platforms in a single test suite run.
2. There is an integration test ``Doctrine\DBAL\Tests\Functional\ModifyLimitQueryTest`` which calls
   ``$platform->modifyLimitQuery()`` and executes the generated queries on a real database to which the test suite
   is connected. This test guarantees that the generated queries are valid but it's much slower and works
   only with one database at a time.

As you can see, both approaches have their strengths and weaknesses and can complement each other.

.. warning::

    Do not mix the unit and the integration approaches in one test. Each of the approaches has its area of application
    and purpose. Mixing them makes it harder to identify the reason and the impact of a failing mixed-type test.
