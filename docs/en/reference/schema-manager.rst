Schema-Manager
==============

A Schema Manager instance helps you with the abstraction of the
generation of SQL assets such as Tables, Sequences, Foreign Keys
and Indexes.

To instantiate a ``SchemaManager`` for your connection you can use
the ``createSchemaManager()`` method:

.. code-block:: php

    <?php
    $schemaManager = $conn->createSchemaManager();

Now with the ``SchemaManager`` instance in ``$schemaManager`` you can use the
available methods to learn about your database schema:

.. note::

    Parameters containing identifiers passed to the SchemaManager
    methods are *NOT* quoted automatically! Identifier quoting is
    really difficult to do manually in a consistent way across
    different databases. You have to manually quote the identifiers
    when you accept data from user or other sources not under your
    control.

listDatabases()
---------------

Retrieve an array of databases on the configured connection:

.. code-block:: php

    <?php
    $databases = $sm->listDatabases();

listSequences()
---------------

Retrieve an array of ``Doctrine\DBAL\Schema\Sequence`` instances
that exist for a database:

.. code-block:: php

    <?php
    $sequences = $sm->listSequences();

Or if you want to manually specify a database name:

.. code-block:: php

    <?php
    $sequences = $sm->listSequences('dbname');

Now you can loop over the array inspecting each sequence object:

.. code-block:: php

    <?php
    foreach ($sequences as $sequence) {
        echo $sequence->getName() . "\n";
    }

listTableColumns()
------------------

Retrieve an array of ``Doctrine\DBAL\Schema\Column`` instances that
exist for the given table:

.. code-block:: php

    <?php
    $columns = $sm->listTableColumns('user');

Now you can loop over the array inspecting each column object:

.. code-block:: php

    <?php
    foreach ($columns as $column) {
        echo $column->getName() . ': ' . $column->getType() . "\n";
    }

introspectTable()
-----------------

Retrieve a single ``Doctrine\DBAL\Schema\Table`` instance that
encapsulates the definition of the given table:

.. code-block:: php

    <?php
    $table = $sm->introspectTable('user');

Now you can call methods on the table to manipulate the in memory
schema for that table. For example we can add a new column:

.. code-block:: php

    <?php
    $table->addColumn('email_address', 'string');

listTableForeignKeys()
----------------------

Retrieve an array of ``Doctrine\DBAL\Schema\ForeignKeyConstraint``
instances that exist for the given table:

.. code-block:: php

    <?php
    $foreignKeys = $sm->listTableForeignKeys('user');

Now you can loop over the array inspecting each foreign key
object:

.. code-block:: php

    <?php
    foreach ($foreignKeys as $foreignKey) {
        echo $foreignKey->getName() ."\n";
    }

listTableIndexes()
------------------

Retrieve an array of ``Doctrine\DBAL\Schema\Index`` instances that
exist for the given table:

.. code-block:: php

    <?php
    $indexes = $sm->listTableIndexes('user');

Now you can loop over the array inspecting each index object:

.. code-block:: php

    <?php
    foreach ($indexes as $index) {
        echo $index->getName() . ': ' . ($index->isUnique() ? 'unique' : 'not unique') . "\n";
    }

listTables()
------------

Retrieve an array of ``Doctrine\DBAL\Schema\Table`` instances that
exist in the connections database:

.. code-block:: php

    <?php
    $tables = $sm->listTables();

Each ``Doctrine\DBAl\Schema\Table`` instance is populated with
information provided by all the above methods. So it encapsulates
an array of ``Doctrine\DBAL\Schema\Column`` instances that can be
retrieved with the ``getColumns()`` method:

.. code-block:: php

    <?php
    foreach ($tables as $table) {
        echo $table->getName() . " columns:\n\n";
        foreach ($table->getColumns() as $column) {
            echo ' - ' . $column->getName() . "\n";
        }
    }

listViews()
-----------

Retrieve an array of ``Doctrine\DBAL\Schema\View`` instances that
exist in the connections database:

.. code-block:: php

    <?php
    $views = $sm->listViews();

Now you can loop over the array inspecting each view object:

.. code-block:: php

    <?php
    foreach ($views as $view) {
        echo $view->getName() . ': ' . $view->getSql() . "\n";
    }

introspectSchema()
------------------

For a complete representation of the current database you can use
the ``introspectSchema()`` method which returns an instance of
``Doctrine\DBAL\Schema\Schema``, which you can use in conjunction
with the SchemaTool or Schema Comparator.

.. code-block:: php

    <?php
    $fromSchema = $sm->introspectSchema();

Now we can clone the ``$fromSchema`` to ``$toSchema`` and drop a
table:

.. code-block:: php

    <?php
    $toSchema = clone $fromSchema;
    $toSchema->dropTable('user');

Now we can compare the two schema instances in order to calculate
the differences between them and return the SQL required to make
the changes on the database:

.. code-block:: php

    <?php
    $sql = $fromSchema->getMigrateToSql($toSchema, $conn->getDatabasePlatform());

The ``$sql`` array should give you a SQL query to drop the user
table:

.. code-block:: php

    <?php
    print_r($sql);

    /*
    array(
      0 => 'DROP TABLE user'
    )
    */

Overriding the schema manager
-----------------------------

All schema manager classes can be overridden, for instance if your application needs to modify SQL statements emitted
by the schema manager or the comparator. If you want your own schema manager to be returned by
``Connection::createSchemaManager()`` you need to configure a factory for it.

.. code-block:: php

    <?php
    use Doctrine\DBAL\Configuration;
    use Doctrine\DBAL\DriverManager;
    use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
    use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
    use Doctrine\DBAL\Schema\MySQLSchemaManager;
    use Doctrine\DBAL\Schema\SchemaManagerFactory;

    class MyCustomMySQLSchemaManager extends MySQLSchemaManager
    {
        // .. your custom logic.
    }

    final class MySchemaManagerFactory implements SchemaManagerFactory
    {
        private readonly SchemaManagerFactory $defaultFactory;

        public function __construct()
        {
            $this->defaultFactory = new DefaultSchemaManagerFactory();
        }

        public function createSchemaManager(Connection $connection): AbstractSchemaManager
        {
            $platform = $connection->getDatabasePlatform();
            if ($platform instanceof AbstractMySQLPlatform) {
                return new MyCustomMySQLSchemaManager($connection, $platform);
            }

            return $this->defaultFactory->createSchemaManager($connection);
        }
    }

    $configuration = new Configuration();
    $configuration->setSchemaManagerFactory(new MySchemaManagerFactory());

    $connection = DriverManager::getConnection([/* your connection parameters */], $configuration);
