Schema manager
==============

A schema manager instance helps you with the abstraction of the
generation of SQL objects such as tables, sequences, foreign key
constraints and indexes.

To instantiate a ``SchemaManager`` for your connection you can use
the ``createSchemaManager()`` method:

.. code-block:: php

    <?php
    $schemaManager = $conn->createSchemaManager();

Now with the ``SchemaManager`` instance in ``$schemaManager`` you can use the
available methods to learn about your database schema.

Introspecting database names
----------------------------

Retrieve a list of the names of available databases:

.. code-block:: php

    <?php
    $databaseNames = $schemaManager->introspectDatabaseNames();

Introspecting sequences
-----------------------

Retrieve a list of ``Doctrine\DBAL\Schema\Sequence`` instances
that exist in the current database:

.. code-block:: php

    <?php
    $sequences = $schemaManager->introspectSequences();

Now you can loop over the list inspecting each sequence object:

.. code-block:: php

    <?php
    foreach ($sequences as $sequence) {
        echo $sequence->getObjectName()->toString() . PHP_EOL;
    }

Introspecting table columns
---------------------------

Retrieve a list of ``Doctrine\DBAL\Schema\Column`` instances that
exist for the given table:

.. code-block:: php

    <?php
    $columns = $schemaManager->introspectTableColumnsByUnquotedName('user');

Or if the table name should be represented as a quoted identifier:

.. code-block:: php

    <?php
    $columns = $schemaManager->introspectTableColumnsByQuotedName('user');

Now you can loop over the list inspecting each column object:

.. code-block:: php

    <?php
    foreach ($columns as $column) {
        echo $column->getObjectName()->toString() . ': ' . $column->getType() . PHP_EOL;
    }

Introspecting a table
---------------------

Retrieve a single ``Doctrine\DBAL\Schema\Table`` instance that
encapsulates the definition of the given table:

.. code-block:: php

    <?php
    $table = $schemaManager->introspectTableByUnquotedName('user');

Or if the table name should be represented as a quoted identifier:

.. code-block:: php

    <?php
    $columns = $schemaManager->introspectTableByQuotedName('user');

Now you can call methods on the table to manipulate the in memory
schema for that table. For example we can add a new column:

.. code-block:: php

    <?php
    $table->addColumn('email_address', 'string');

Introspecting foreign key constraints of a table
------------------------------------------------

Retrieve a list of ``Doctrine\DBAL\Schema\ForeignKeyConstraint``
instances that exist for the given table:

.. code-block:: php

    <?php
    $foreignKeyConstraints = $schemaManager->introspectTableForeignKeyConstraintsByUnquotedName('user');

Or if the table name should be represented as a quoted identifier:

.. code-block:: php

    <?php
    $foreignKeyConstraints = $schemaManager->introspectTableForeignKeyConstraintsByQuotedName('user');

Now you can loop over the list inspecting each foreign key constraint object:

.. code-block:: php

    <?php
    foreach ($foreignKeyConstraints as $foreignKeyConstraint) {
        echo $foreignKeyConstraint->getObjectName()->toString() . PHP_EOL;
    }

Introspecting table indexes
---------------------------

Retrieve a list of ``Doctrine\DBAL\Schema\Index`` instances that
exist for the given table:

.. code-block:: php

    <?php
    $indexes = $schemaManager->introspectTableIndexesByUnquotedName('user');

Or if the table name should be represented as a quoted identifier:

.. code-block:: php

    <?php
    $indexes = $schemaManager->introspectTableIndexesByQuotedName('user');

Now you can loop over the list inspecting each index object:

.. code-block:: php

    <?php
    foreach ($indexes as $index) {
        echo $index->getObjectName()->toString() . ': ' . match($index->getType()) {
            IndexType::REGULAR  => 'regular',
            IndexType::UNIQUE   => 'unique',
            IndexType::FULLTEXT => 'fulltext',
            IndexType::SPATIAL  => 'spatial',
        } . PHP_EOL;
    }

Introspecting all tables in the database
----------------------------------------

Retrieve a list of ``Doctrine\DBAL\Schema\Table`` instances that
exist in the current database:

.. code-block:: php

    <?php
    $tables = $schemaManager->introspectTables();

Each ``Doctrine\DBAl\Schema\Table`` instance is populated with
information provided by all the above methods. So it encapsulates
a list of ``Doctrine\DBAL\Schema\Column`` instances that can be
retrieved with the ``getColumns()`` method:

.. code-block:: php

    <?php
    foreach ($tables as $table) {
        echo $table->getObjectName()->toString() . " columns:" . PHP_EOL;
        foreach ($table->getColumns() as $column) {
            echo ' - ' . $column->getObjectName()->toString() . PHP_EOL;
        }
    }

Introspecting all views in the database
---------------------------------------

Retrieve a list of ``Doctrine\DBAL\Schema\View`` instances that
exist in the current database:

.. code-block:: php

    <?php
    $views = $schemaManager->introspectViews();

Now you can loop over the list inspecting each view object:

.. code-block:: php

    <?php
    foreach ($views as $view) {
        echo $view->getObjectName()->toString() . ': ' . $view->getSql() . PHP_EOL;
    }

Introspecting the database schema
---------------------------------

For a complete representation of the schema of current database you can use
the ``introspectSchema()`` method which returns an instance of
``Doctrine\DBAL\Schema\Schema``, which you can use in conjunction
with a schema comparator.

.. code-block:: php

    <?php
    $fromSchema = $schemaManager->introspectSchema();

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
    $statements = $schemaManager->createComparator()
        ->compareSchemas($fromSchema, $toSchema)
        ->toSql($conn->getDatabasePlatform());

The ``$statements`` list should give you the SQL statements to drop the user
table:

.. code-block:: php

    <?php
    print_r($sql);

    /*
    array(
      0 => 'DROP TABLE user'
    )
    */

Creating a schema comparator
----------------------------

To create a comparator that can be used to compare two schemas use the
``createComparator()`` method which returns an instance of
``Doctrine\DBAL\Schema\Comparator``.

.. code-block:: php

    <?php
    $comparator = $schemaManager->createComparator();
    $schemaDiff = $comparator->compareSchemas($fromSchema, $toSchema);

To change the configuration of the comparator, you can pass a
``Doctrine\DBAL\Schema\ComparatorConfig`` object to the method:

.. code-block:: php

    <?php
    $config = (new ComparatorConfig())->withDetectRenamedColumns(false);
    $comparator = $schemaManager->createComparator($config);
    $schemaDiff = $comparator->compareSchemas($fromSchema, $toSchema);

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
