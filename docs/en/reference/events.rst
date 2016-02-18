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
   specify any number of Oracle Session related enviroment variables
   that are set right after the connection is established.

You can register events by subscribing them to the ``EventManager``
instance passed to the Connection factory:

.. code-block:: php

    <?php
    $evm = new EventManager();
    $evm->addEventSubscriber(new OracleSessionInit(array(
        'NLS_TIME_FORMAT' => 'HH24:MI:SS',
    )));
    
    $conn = DriverManager::getConnection($connectionParams, null, $evm);


Schema Events
-------------

There are multiple events in Doctrine DBAL that are triggered on schema changes
of the database. It is possible to add your own event listener to be able to run
your own code before changes to the database are commited. An instance of
``Doctrine\Common\EventManager`` can also be added to :doc:`platforms`.

A event listener class can contain one or more methods to schema events. These
methods must be named like the events itself.

.. code-block:: php

    <?php
    $evm = new EventManager();
    $eventName = Events::onSchemaCreateTable;
    $evm->addEventListener($eventName, new MyEventListener());

.. code-block:: php

    <?php
    $evm = new EventManager();
    $eventNames = array(Events::onSchemaCreateTable, Events::onSchemaCreateTableColumn);
    $evm->addEventListener($eventNames, new MyEventListener());

The following events are available.

OnSchemaCreateTable Event
^^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onSchemaCreateTable`` is triggered before every
create statement that is executed by one of the Platform instances and injects
an instance of ``Doctrine\DBAL\Event\SchemaCreateTableEventArgs`` as event argument
for event listeners.

.. code-block:: php

    <?php
    class MyEventListener
    {
        public function onSchemaCreateTable(SchemaCreateTableEventArgs $event)
        {
            // Your EventListener code
        }
    }

    $evm = new EventManager();
    $evm->addEventListener(Events::onSchemaCreateTable, new MyEventListener());

    $conn = DriverManager::getConnection($connectionParams, null, $evm);

It allows you to access the ``Doctrine\DBAL\Schema\Table`` instance and its columns, the used Platform and
provides a way to add additional SQL statements.


OnSchemaCreateTableColumn Event
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onSchemaCreateTableColumn`` is triggered on every new column before a
create statement that is executed by one of the Platform instances and injects
an instance of ``Doctrine\DBAL\Event\SchemaCreateTableColumnEventArgs`` as event argument
for event listeners.

.. code-block:: php

    <?php
    class MyEventListener
    {
        public function onSchemaCreateTableColumn(SchemaCreateTableColumnEventArgs $event)
        {
            // Your EventListener code
        }
    }

    $evm = new EventManager();
    $evm->addEventListener(Events::onSchemaCreateTableColumn, new MyEventListener());

    $conn = DriverManager::getConnection($connectionParams, null, $evm);

It allows you to access the ``Doctrine\DBAL\Schema\Table`` instance, the affected ``Doctrine\DBAL\Schema\Column``,
the used Platform and provides a way to add additional SQL statements.

OnSchemaDropTable Event
^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onSchemaDropTable`` is triggered before a drop table
statement that is executed by one of the Platform instances and injects
an instance of ``Doctrine\DBAL\Event\SchemaDropTableEventArgs`` as event argument
for event listeners.

.. code-block:: php

    <?php
    class MyEventListener
    {
        public function onSchemaDropTable(SchemaDropTableEventArgs $event)
        {
            // Your EventListener code
        }
    }

    $evm = new EventManager();
    $evm->addEventListener(Events::onSchemaDropTable, new MyEventListener());

    $conn = DriverManager::getConnection($connectionParams, null, $evm);

It allows you to access the ``Doctrine\DBAL\Schema\Table`` instance, the used Platform and
provides a way to set an additional SQL statement.

OnSchemaAlterTable Event
^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onSchemaAlterTable`` is triggered before every
alter statement that is executed by one of the Platform instances and injects
an instance of ``Doctrine\DBAL\Event\SchemaAlterTableEventArgs`` as event argument
for event listeners.

.. code-block:: php

    <?php
    class MyEventListener
    {
        public function onSchemaAlterTable(SchemaAlterTableEventArgs $event)
        {
            // Your EventListener code
        }
    }

    $evm = new EventManager();
    $evm->addEventListener(Events::onSchemaAlterTable, new MyEventListener());

    $conn = DriverManager::getConnection($connectionParams, null, $evm);

It allows you to access the ``Doctrine\DBAL\Schema\TableDiff`` instance, the used Platform and
provides a way to add additional SQL statements.

OnSchemaAlterTableAddColumn Event
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onSchemaAlterTableAddColumn`` is triggered on every altered column before every
alter statement that is executed by one of the Platform instances and injects
an instance of ``Doctrine\DBAL\Event\SchemaAlterTableAddColumnEventArgs`` as event argument
for event listeners.

.. code-block:: php

    <?php
    class MyEventListener
    {
        public function onSchemaAlterTableAddColumn(SchemaAlterTableAddColumnEventArgs $event)
        {
            // Your EventListener code
        }
    }

    $evm = new EventManager();
    $evm->addEventListener(Events::onSchemaAlterTableAddColumn, new MyEventListener());

    $conn = DriverManager::getConnection($connectionParams, null, $evm);

It allows you to access the ``Doctrine\DBAL\Schema\TableDiff`` instance, the affected ``Doctrine\DBAL\Schema\Column``,
the used Platform and provides a way to add additional SQL statements.

OnSchemaAlterTableRemoveColumn Event
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onSchemaAlterTableRemoveColumn`` is triggered on every column that is going to be removed
before every alter-drop statement that is executed by one of the Platform instances and injects
an instance of ``Doctrine\DBAL\Event\SchemaAlterTableRemoveColumnEventArgs`` as event argument
for event listeners.

.. code-block:: php

    <?php
    class MyEventListener
    {
        public function onSchemaAlterTableRemoveColumn(SchemaAlterTableRemoveColumnEventArgs $event)
        {
            // Your EventListener code
        }
    }

    $evm = new EventManager();
    $evm->addEventListener(Events::onSchemaAlterTableRemoveColumn, new MyEventListener());

    $conn = DriverManager::getConnection($connectionParams, null, $evm);

It allows you to access the ``Doctrine\DBAL\Schema\TableDiff`` instance, the affected ``Doctrine\DBAL\Schema\Column``,
the used Platform and provides a way to add additional SQL statements.

OnSchemaAlterTableChangeColumn Event
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onSchemaAlterTableChangeColumn`` is triggered on every column that is going to be changed
before every alter statement that is executed by one of the Platform instances and injects
an instance of ``Doctrine\DBAL\Event\SchemaAlterTableRemoveColumnEventArgs`` as event argument
for event listeners.

.. code-block:: php

    <?php
    class MyEventListener
    {
        public function onSchemaAlterTableChangeColumn(SchemaAlterTableChangeColumnEventArgs $event)
        {
            // Your EventListener code
        }
    }

    $evm = new EventManager();
    $evm->addEventListener(Events::onSchemaAlterTableChangeColumn, new MyEventListener());

    $conn = DriverManager::getConnection($connectionParams, null, $evm);

It allows you to access the ``Doctrine\DBAL\Schema\TableDiff`` instance, a ``Doctrine\DBAL\Schema\ColumnDiff`` of
the affected column, the used Platform and provides a way to add additional SQL statements.

OnSchemaAlterTableRenameColumn Event
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onSchemaAlterTableRenameColumn`` is triggered on every column that is going to be renamed
before every alter statement that is executed by one of the Platform instances and injects
an instance of ``Doctrine\DBAL\Event\SchemaAlterTableRenameColumnEventArgs`` as event argument
for event listeners.

.. code-block:: php

    <?php
    class MyEventListener
    {
        public function onSchemaAlterTableRenameColumn(SchemaAlterTableRenameColumnEventArgs $event)
        {
            // Your EventListener code
        }
    }

    $evm = new EventManager();
    $evm->addEventListener(Events::onSchemaAlterTableRenameColumn, new MyEventListener());

    $conn = DriverManager::getConnection($connectionParams, null, $evm);

It allows you to access the ``Doctrine\DBAL\Schema\TableDiff`` instance, the old column name and
the new column in form of a ``Doctrine\DBAL\Schema\Column`` object, the used Platform and provides
a way to add additional SQL statements.

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
