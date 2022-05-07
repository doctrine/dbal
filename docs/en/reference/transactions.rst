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
result in two very different behaviors depending on whether transaction
nesting with savepoints is enabled or not. In both cases though, there
won't be an actual transaction inside a transaction, even if your RDBMS
supports it. There is always only a single, real database transaction.

By default, transaction nesting at the SQL level with savepoints is
disabled. The value for that setting can be set on a per-connection
basis, with
``Doctrine\DBAL\Connection#setNestTransactionsWithSavepoints()``.

Nesting transactions without savepoints is deprecated, but is the
default behavior for backward compatibility reasons.

Dummy mode
~~~~~~~~~~
.. warning::

    This behavior is deprecated, avoid it with
    ``Doctrine\DBAL\Connection#setNestTransactionsWithSavepoints(true)``.

When transaction nesting with savepoints is disabled, what happens is
not so much transaction nesting as propagating transaction control up
the call stack. For that purpose, the ``Connection`` class keeps an
internal counter that represents the nesting level and is
increased/decreased as ``beginTransaction()``, ``commit()`` and
``rollBack()`` are invoked. ``beginTransaction()`` increases the nesting
level whilst ``commit()`` and ``rollBack()`` decrease the nesting level.
The nesting level starts at 0.
Whenever the nesting level transitions from 0 to 1,
``beginTransaction()`` is invoked on the underlying driver connection
and whenever the nesting level transitions from 1 to 0, ``commit()`` or
``rollBack()`` is invoked on the underlying driver, depending on whether
the transition was caused by ``Connection#commit()`` or
``Connection#rollBack()``.

What this means is that transaction control is basically passed to
code higher up in the call stack and the inner transaction block does
not actually result in an SQL transaction. It is not ignored either
though.

To visualize what this means in practice, consider the following
example:

::

    <?php
    // $conn instanceof Doctrine\DBAL\Connection
    $conn->beginTransaction(); // 0 => 1, "real" transaction started
    try {

        ...

        // nested transaction block, this might be in some other API/library code that is
        // unaware of the outer transaction.
        $conn->beginTransaction(); // 1 => 2
        try {
            ...

            $conn->commit(); // 2 => 1
        } catch (\Exception $e) {
            $conn->rollBack(); // 2 => 1, transaction marked for rollback only
            throw $e;
        }

        ...

        $conn->commit(); // 1 => 0, "real" transaction committed
    } catch (\Exception $e) {
        $conn->rollBack(); // 1 => 0, "real" transaction rollback
        throw $e;
    }

However, **a rollback in a nested transaction block will always mark the
current transaction so that the only possible outcome of the transaction
is to be rolled back**.
That means in the above example, the rollback in the inner
transaction block marks the whole transaction for rollback only.
Even if the nested transaction block would not rethrow the
exception, the transaction is marked for rollback only and the
commit of the outer transaction would trigger an exception, leading
to the final rollback. This also means that you cannot
successfully commit some changes in an outer transaction if an
inner transaction block fails and issues a rollback, even if this
would be the desired behavior (i.e. because the nested operation is
"optional" for the purpose of the outer transaction block). To
achieve that, you need to resort to transaction nesting with savepoint.

All that is guaranteed to the inner transaction is that it still
happens atomically, all or nothing, the transaction just gets a
wider scope and the control is handed to the outer scope.

.. note::

    The transaction nesting described here is a debated
    feature that has its critics. Form your own opinion. We recommend
    avoiding nesting transaction blocks when possible, and most of the
    time, it is possible. Transaction control should mostly be left to
    a service layer and not be handled in data access objects or
    similar.

.. warning::

    Directly invoking ``PDO::beginTransaction()``,
    ``PDO::commit()`` or ``PDO::rollBack()`` or the corresponding methods
    on the particular ``Doctrine\DBAL\Driver\Connection`` instance
    bypasses the transparent transaction nesting that is provided
    by ``Doctrine\DBAL\Connection`` and can therefore corrupt the
    nesting level, causing errors with broken transaction boundaries
    that may be hard to debug.

Emulated Transaction Nesting with Savepoints
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Let's now examine what happens when transaction nesting with savepoints
is enabled, with the same example as above

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

This time, everything is handled at the SQL level: the main transaction
is not marked for rollback only, but the inner emulated transaction is
rolled back to the savepoint.

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

