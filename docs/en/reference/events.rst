Events
======

Both ``Doctrine\DBAL\DriverManager`` and
``Doctrine\DBAL\Connection`` accept an instance of
``Doctrine\Common\EventManager``. The EventManager has a couple of
events inside the DBAL layer that are triggered for the user to
listen to.

PostConnect Event
-----------------

``Doctrine\DBAL\Events::postConnect`` is triggered right after the
connection to the database is established. It allows to specify any
relevant connection specific options and gives access to the
``Doctrine\DBAL\Connection`` instance that is responsible for the
connection management via an instance of
``Doctrine\DBAL\Event\ConnectionEventArgs`` event arguments
instance.

Doctrine ships with one implementation for the "PostConnect" event:

-  ``Doctrine\DBAL\Event\Listeners\OracleSessionInit`` allows to
   specify any number of Oracle Session related environment variables
   that are set right after the connection is established.

You can register events by subscribing them to the ``EventManager``
instance passed to the Connection factory:

.. code-block:: php

    <?php
    $evm = new EventManager();
    $evm->addEventSubscriber(new OracleSessionInit([
        'NLS_TIME_FORMAT' => 'HH24:MI:SS',
    ]));

    $conn = DriverManager::getConnection($connectionParams, null, $evm);

Schema Events
-------------

There are multiple events in Doctrine DBAL that are triggered on schema changes
of the database. It is possible to add your own event listener to be able to run
your own code before changes to the database are committed. An instance of
``Doctrine\Common\EventManager`` can also be added to :doc:`platforms`.

A event listener class can contain one or more methods to schema events. These
methods must be named like the events itself.

.. code-block:: php

    <?php
    $evm = new EventManager();
    $eventName = Events::onSomething;
    $evm->addEventListener($eventName, new MyEventListener());

.. code-block:: php

    <?php
    $evm = new EventManager();
    $eventNames = [Events::onSomething, Events::onSomethingElse];
    $evm->addEventListener($eventNames, new MyEventListener());

The following events are available.

OnSchemaColumnDefinition Event
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onSchemaColumnDefinition`` is triggered on a schema update and is
executed for every existing column definition of the database before changes are applied.
An instance of ``Doctrine\DBAL\Event\SchemaColumnDefinitionEventArgs`` is injected as argument
for event listeners.

.. code-block:: php

    <?php
    class MyEventListener
    {
        public function onSchemaColumnDefinition(SchemaColumnDefinitionEventArgs $event)
        {
            // Your EventListener code
        }
    }

    $evm = new EventManager();
    $evm->addEventListener(Events::onSchemaColumnDefinition, new MyEventListener());

    $conn = DriverManager::getConnection($connectionParams, null, $evm);

It allows you to access the table column definitions of the current database, table name, Platform and
``Doctrine\DBAL\Connection`` instance. Columns, that are about to be added, are not listed.

OnSchemaIndexDefinition Event
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onSchemaIndexDefinition`` is triggered on a schema update and is
executed for every existing index definition of the database before changes are applied.
An instance of ``Doctrine\DBAL\Event\SchemaIndexDefinitionEventArgs`` is injected as argument
for event listeners.

.. code-block:: php

    <?php
    class MyEventListener
    {
        public function onSchemaIndexDefinition(SchemaIndexDefinitionEventArgs $event)
        {
            // Your EventListener code
        }
    }

    $evm = new EventManager();
    $evm->addEventListener(Events::onSchemaIndexDefinition, new MyEventListener());

    $conn = DriverManager::getConnection($connectionParams, null, $evm);

It allows you to access the table index definitions of the current database, table name, Platform and
``Doctrine\DBAL\Connection`` instance. Indexes, that are about to be added, are not listed.

OnTransactionBegin Event
^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onTransactionBegin`` is triggered when ``Doctrine\DBAL\Connection::beginTransaction()``
is called. An instance of ``Doctrine\DBAL\Event\TransactionBeginEventArgs`` is injected as argument for event listeners.

.. code-block:: php

    <?php
    class MyEventListener
    {
        public function onTransactionBegin(TransactionBeginEventArgs $event)
        {
            // Your EventListener code
        }
    }

    $evm = new EventManager();
    $evm->addEventListener(Events::onTransactionBegin, new MyEventListener());

    $conn = DriverManager::getConnection($connectionParams, null, $evm);

It allows you to access the ``Doctrine\DBAL\Connection`` instance.
Please note that this event can be called multiple times, since transactions can be nested.

OnTransactionCommit Event
^^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onTransactionCommit`` is triggered when ``Doctrine\DBAL\Connection::commit()`` is called.
An instance of ``Doctrine\DBAL\Event\TransactionCommitEventArgs`` is injected as argument for event listeners.

.. code-block:: php

    <?php
    class MyEventListener
    {
        public function onTransactionCommit(TransactionCommitEventArgs $event)
        {
            // Your EventListener code
        }
    }

    $evm = new EventManager();
    $evm->addEventListener(Events::onTransactionCommit, new MyEventListener());

    $conn = DriverManager::getConnection($connectionParams, null, $evm);

It allows you to access the ``Doctrine\DBAL\Connection`` instance.
Please note that this event can be called multiple times, since transactions can be nested.
If you want to know if a transaction is actually committed, you should rely on
``TransactionCommitEventArgs::getConnection()->getTransactionNestingLevel() === 0`` or
``TransactionCommitEventArgs::getConnection()->isTransactionActive()``

OnTransactionRollBack Event
^^^^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onTransactionRollBack`` is triggered when ``Doctrine\DBAL\Connection::rollBack()`` is called.
An instance of ``Doctrine\DBAL\Event\TransactionRollBackEventArgs`` is injected as argument for event listeners.

.. code-block:: php

    <?php
    class MyEventListener
    {
        public function onTransactionRollBack(TransactionRollBackEventArgs $event)
        {
            // Your EventListener code
        }
    }

    $evm = new EventManager();
    $evm->addEventListener(Events::onTransactionRollBack, new MyEventListener());

    $conn = DriverManager::getConnection($connectionParams, null, $evm);

It allows you to access the ``Doctrine\DBAL\Connection`` instance.
Please note that this event can be called multiple times, since transactions can be nested.
If you want to know if a transaction is actually rolled back, you should rely on
``TransactionCommitRollBackArgs::getConnection()->getTransactionNestingLevel() === 0`` or
``TransactionCommitRollBackArgs::getConnection()->isTransactionActive()``
