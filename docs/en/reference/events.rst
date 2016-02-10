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
    $eventName = 'onSchemaCreateTable';
    $evm->addEventListener($eventName, new MyEventListener());

.. code-block:: php

    <?php
    $evm = new EventManager();
    $eventNames = array('onSchemaCreateTable', 'onSchemaCreateTableColumn');
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

``Doctrine\DBAL\Events::onSchemaCreateTableColumn`` is triggered before a column
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

It allows you to access the ``Doctrine\DBAL\Schema\Table`` instance and its columns, the used Platform and
provides a way to add additional SQL statements.

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

``Doctrine\DBAL\Events::onSchemaAlterTableAddColumn`` is triggered

OnSchemaAlterTableRemoveColumn Event
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onSchemaAlterTableRemoveColumn`` is triggered

OnSchemaAlterTableChangeColumn Event
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onSchemaAlterTableChangeColumn`` is triggered

OnSchemaAlterTableRenameColumn Event
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onSchemaAlterTableRenameColumn`` is triggered

OnSchemaColumnDefinition Event
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onSchemaColumnDefinition`` is triggered

OnSchemaIndexDefinition Event
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``Doctrine\DBAL\Events::onSchemaIndexDefinition`` is triggered
