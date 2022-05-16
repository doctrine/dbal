Transactions
============

A ``Doctrine\DBAL\Connection`` provides an API for
transaction management, with the methods
``beginTransaction()``, ``commit()`` and ``rollBack()``.

Transaction demarcation with the Doctrine DBAL looks as follows:

::

    <?php
    $conn->beginTransaction();
    try{
        // do stuff
        $conn->commit();
    } catch (\Exception $e) {
        $conn->rollBack();
        throw $e;
    }

Alternatively, the control abstraction
``Connection#transactional($func)`` can be used to make the code
more concise and to make sure you never forget to rollback the
transaction in the case of an exception. The following code snippet
is functionally equivalent to the previous one:

::

    <?php
    $conn->transactional(function(Connection $conn): void {
        // do stuff
    });

Note that the closure above doesn't have to be a void, anything it
returns will be returned by ``transactional()``:

::

    <?php
    $one = $conn->transactional(function(Connection $conn): int {
        // do stuff
        return $conn->fetchOne('SELECT 1');
    });


The ``Doctrine\DBAL\Connection`` class also has methods to control the
transaction isolation level as supported by the underlying
database. ``Connection#setTransactionIsolation($level)`` and
``Connection#getTransactionIsolation()`` can be used for that purpose.
The possible isolation levels are represented by the following
constants:

::

    <?php
    TransactionIsolationLevel::READ_UNCOMMITTED
    TransactionIsolationLevel::READ_COMMITTED
    TransactionIsolationLevel::REPEATABLE_READ
    TransactionIsolationLevel::SERIALIZABLE

The default transaction isolation level of a
``Doctrine\DBAL\Connection`` instance is chosen by the underlying
platform but it is always at least ``READ_COMMITTED``.

Transaction Nesting
-------------------

Calling ``beginTransaction()`` while already in a transaction will
not result in an actual transaction inside a transaction, even if your
RDBMS supports it. Instead, transaction nesting is emulated by resorting
to SQL savepoints. There is always only a single, real database
transaction.

Let's examine what happens with an example

::

    <?php
    // $conn instanceof Doctrine\DBAL\Connection
    $conn->beginTransaction(); // 0 => 1, "real" transaction started
    try {

        ...

        // nested transaction block, this might be in some other API/library code that is
        // unaware of the outer transaction.
        $conn->beginTransaction(); // 1 => 2, savepoint created
        try {
            ...

            $conn->commit(); // 2 => 1
        } catch (\Exception $e) {
            $conn->rollBack(); // 2 => 1, rollback to savepoint
            throw $e;
        }

        ...

        $conn->commit(); // 1 => 0, "real" transaction committed
    } catch (\Exception $e) {
        $conn->rollBack(); // 1 => 0, "real" transaction rollback
        throw $e;
    }

Everything is handled at the SQL level: the main transaction is not
marked for rollback only, but the inner emulated transaction is rolled
back to the savepoint.

.. warning::

    Directly invoking ``PDO::beginTransaction()``,
    ``PDO::commit()`` or ``PDO::rollBack()`` or the corresponding methods
    on the particular ``Doctrine\DBAL\Driver\Connection`` instance
    bypasses the transparent transaction nesting that is provided
    by ``Doctrine\DBAL\Connection`` and can therefore corrupt the
    nesting level, causing errors with broken transaction boundaries
    that may be hard to debug.

Auto-commit mode
----------------

A ``Doctrine\DBAL\Connection`` supports setting the auto-commit mode
to control whether queries should be automatically wrapped into a
transaction or directly be committed to the database.
By default a connection runs in auto-commit mode which means
that it is non-transactional unless you start a transaction explicitly
via ``beginTransaction()``. To have a connection automatically open up
a new transaction on ``connect()`` and after ``commit()`` or ``rollBack()``,
you can disable auto-commit mode with ``setAutoCommit(false)``.

::

    <?php
    // define connection parameters $params and initialize driver $driver

    $conn = new \Doctrine\DBAL\Connection($params, $driver);

    $conn->setAutoCommit(false); // disables auto-commit
    $conn->connect(); // connects and immediately starts a new transaction

    try {
        // do stuff
        $conn->commit(); // commits transaction and immediately starts a new one
    } catch (\Exception $e) {
        $conn->rollBack(); // rolls back transaction and immediately starts a new one
    }

    // still transactional

.. note::

    Changing auto-commit mode during an active transaction, implicitly
    commits active transactions for that particular connection.

::

    <?php
    // define connection parameters $params and initialize driver $driver

    $conn = new \Doctrine\DBAL\Connection($params, $driver);

    // we are in auto-commit mode
    $conn->beginTransaction();

    // disable auto-commit, commits currently active transaction
    $conn->setAutoCommit(false); // also causes a new transaction to be started

    // no-op as auto-commit is already disabled
    $conn->setAutoCommit(false);

    // enable auto-commit again, commits currently active transaction
    $conn->setAutoCommit(true); // does not start a new transaction automatically

Committing or rolling back an active transaction will of course only
open up a new transaction automatically if the particular action causes
the transaction context of a connection to terminate.
That means committing or rolling back nested transactions are not affected
by this behaviour.

::

    <?php
    // we are not in auto-commit mode, transaction is active

    try {
        // do stuff

        $conn->beginTransaction(); // start inner transaction, nesting level 2

        try {
            // do stuff
            $conn->commit(); // commits inner transaction, does not start a new one
        } catch (\Exception $e) {
            $conn->rollBack(); // rolls back inner transaction, does not start a new one
        }

        // do stuff

        $conn->commit(); // commits outer transaction, and immediately starts a new one
    } catch (\Exception $e) {
        $conn->rollBack(); // rolls back outer transaction, and immediately starts a new one
    }

To initialize a ``Doctrine\DBAL\Connection`` with auto-commit disabled,
you can also use the ``Doctrine\DBAL\Configuration`` container to modify the
default auto-commit mode via ``Doctrine\DBAL\Configuration::setAutoCommit(false)``
and pass it to a ``Doctrine\DBAL\Connection`` when instantiating.

Error handling
--------------

In order to handle errors related to deadlocks or lock wait timeouts,
you can use Doctrine built-in transaction exceptions.
All transaction exceptions where retrying makes sense have a marker interface: ``Doctrine\DBAL\Exception\RetryableException``.
A practical example is as follows:

::

    <?php

    try {
        // process stuff
    } catch (\Doctrine\DBAL\Exception\RetryableException $e) {
        // retry the processing
    }

If you need stricter control, you can catch the concrete exceptions directly:

- ``Doctrine\DBAL\Exception\DeadlockException``: this can happen when each member
  of a group of actions is waiting for some other member to release a shared lock.
- ``Doctrine\DBAL\Exception\LockWaitTimeoutException``: this exception happens when
  a transaction has to wait a considerable amount of time to obtain a lock, even if
  a deadlock is not involved.

