Transactions
============

A ``Doctrine\DBAL\Connection`` provides a PDO-like API for
transaction management, with the methods
``Connection#beginTransaction()``, ``Connection#commit()`` and
``Connection#rollback()``.

Transaction demarcation with the Doctrine DBAL looks as follows:

::

    <?php
    $conn->beginTransaction();
    try{
        // do stuff
        $conn->commit();
    } catch(Exception $e) {
        $conn->rollback();
        throw $e;
    }

Alternatively, the control abstraction
``Connection#transactional($func)`` can be used to make the code
more concise and to make sure you never forget to rollback the
transaction in the case of an exception. The following code snippet
is functionally equivalent to the previous one:

::

    <?php
    $conn->transactional(function($conn) {
        // do stuff
    });

The ``Doctrine\DBAL\Connection`` also has methods to control the
transaction isolation level as supported by the underlying
database. ``Connection#setTransactionIsolation($level)`` and
``Connection#getTransactionIsolation()`` can be used for that purpose.
The possible isolation levels are represented by the following
constants:

::

    <?php
    Connection::TRANSACTION_READ_UNCOMMITTED
    Connection::TRANSACTION_READ_COMMITTED
    Connection::TRANSACTION_REPEATABLE_READ
    Connection::TRANSACTION_SERIALIZABLE

The default transaction isolation level of a
``Doctrine\DBAL\Connection`` is chosen by the underlying platform
but it is always at least READ\_COMMITTED.

Transaction Nesting
-------------------

A ``Doctrine\DBAL\Connection`` also adds support for nesting
transactions, or rather propagating transaction control up the call
stack. For that purpose, the ``Connection`` class keeps an internal
counter that represents the nesting level and is
increased/decreased as ``beginTransaction()``, ``commit()`` and
 ``rollback()`` are invoked. ``beginTransaction()`` increases the
nesting level whilst
 ``commit()`` and ``rollback()`` decrease the nesting level. The nesting level starts at 0. Whenever the nesting level transitions from 0 to 1, ``beginTransaction()`` is invoked on the underlying driver connection and whenever the nesting level transitions from 1 to 0, ``commit()`` or ``rollback()`` is invoked on the underlying driver, depending on whether the transition was caused by ``Connection#commit()`` or ``Connection#rollback()``.

What this means is that transaction control is basically passed to
code higher up in the call stack and the inner transaction block is
ignored, with one important exception that is described further
below. Do not confuse this with "real" nested transactions or
savepoints. These are not supported by Doctrine. There is always
only a single, real database transaction.

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
        } catch (Exception $e) {
            $conn->rollback(); // 2 => 1, transaction marked for rollback only
            throw $e;
        }
    
        ...
    
        $conn->commit(); // 1 => 0, "real" transaction committed
    } catch (Exception $e) {
        $conn->rollback(); // 1 => 0, "real" transaction rollback
        throw $e;
    }

However,
**a rollback in a nested transaction block will always mark the current transaction so that the only possible outcome of the transaction is to be rolled back**.
That means in the above example, the rollback in the inner
transaction block marks the whole transaction for rollback only.
Even if the nested transaction block would not rethrow the
exception, the transaction is marked for rollback only and the
commit of the outer transaction would trigger an exception, leading
to the final rollback. This also means that you can not
successfully commit some changes in an outer transaction if an
inner transaction block fails and issues a rollback, even if this
would be the desired behavior (i.e. because the nested operation is
"optional" for the purpose of the outer transaction block). To
achieve that, you need to restructure your application logic so as
to avoid nesting transaction blocks. If this is not possible
because the nested transaction blocks are in a third-party API
you're out of luck.

All that is guaruanteed to the inner transaction is that it still
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

    Directly invoking ``PDO#beginTransaction()``,
    ``PDO#commit()`` or ``PDO#rollback()`` or the corresponding methods
    on the particular ``Doctrine\DBAL\Driver\Connection`` instance in
    use bypasses the transparent transaction nesting that is provided
    by ``Doctrine\DBAL\Connection`` and can therefore corrupt the
    nesting level, causing errors with broken transaction boundaries
    that may be hard to debug.



