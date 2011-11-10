Schema-Representation
=====================

Doctrine has a very powerful abstraction of database schemas. It
offers an object-oriented representation of a database schema with
support for all the details of Tables, Sequences, Indexes and
Foreign Keys. These Schema instances generate a representation that
is equal for all the supported platforms. Internally this
functionality is used by the ORM Schema Tool to offer you create,
drop and update database schema methods from your Doctrine ORM
Metadata model. Up to very specific functionality of your database
system this allows you to generate SQL code that makes your Domain
model work.

You will be pleased to hear, that Schema representation is
completly decoupled from the Doctrine ORM though, that is you can
also use it in any other project to implement database migrations
or for SQL schema generation for any metadata model that your
application has. You can easily generate a Schema, as a simple
example shows:

.. code-block:: php

    <?php
    $schema = new \Doctrine\DBAL\Schema\Schema();
    $myTable = $schema->createTable("my_table");
    $myTable->addColumn("id", "integer", array("unsigned" => true));
    $myTable->addColumn("username", "string", array("length" => 32));
    $myTable->setPrimaryKey(array("id"));
    $myTable->addUniqueIndex(array("username"));
    $schema->createSequence("my_table_seq");
    
    $myForeign = $schema->createTable("my_foreign");
    $myForeign->addColumn("id", "integer");
    $myForeign->addColumn("user_id", "integer");
    $myForeign->addForeignKeyConstraint($myTable, array("user_id"), array("id"), array("onUpdate" => "CASCADE"));
    
    $queries = $schema->toSql($myPlatform); // get queries to create this schema.
    $dropSchema = $schema->toDropSql($myPlatform); // get queries to safely delete this schema.

Now if you want to compare this schema with another schema, you can
use the ``Comparator`` class to get instances of ``SchemaDiff``,
``TableDiff`` and ``ColumnDiff``, as well as information about other
foreign key, sequence and index changes.

.. code-block:: php

    <?php
    $comparator = new \Doctrine\DBAL\Schema\Comparator();
    $schemaDiff = $comparator->compare($fromSchema, $toSchema);
    
    $queries = $schemaDiff->toSql($myPlatform); // queries to get from one to another schema.
    $saveQueries = $schemaDiff->toSaveSql($myPlatform);

The Save Diff mode is a specific mode that prevents the deletion of
tables and sequences that might occour when making a diff of your
schema. This is often necessary when your target schema is not
complete but only describes a subset of your application.

All methods that generate SQL queries for you make much effort to
get the order of generation correct, so that no problems will ever
occour with missing links of foreign keys.


