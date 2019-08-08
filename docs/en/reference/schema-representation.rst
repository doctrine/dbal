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
completely decoupled from the Doctrine ORM though, that is you can
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
    $myTable->setComment('Some comment');
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
tables and sequences that might occur when making a diff of your
schema. This is often necessary when your target schema is not
complete but only describes a subset of your application.

All methods that generate SQL queries for you make much effort to
get the order of generation correct, so that no problems will ever
occur with missing links of foreign keys.

Schema Assets
-------------

A schema asset is considered any abstract atomic unit in a database such as schemas,
tables, indexes, but also sequences, columns and even identifiers.
The following chapter gives an overview of all available Doctrine 2
schema assets with short explanations on their context and usage.
All schema assets reside in the ``Doctrine\DBAL\Schema`` namespace.

.. note::

    This chapter is far from being completely documented.

Column
~~~~~~

Represents a table column in the database schema.
A column consists of a name, a type, portable options, commonly supported options and
vendors specific options.

Portable options
^^^^^^^^^^^^^^^^

The following options are considered to be fully portable across all database platforms:

-  **notnull** (boolean): Whether the column is nullable or not. Defaults to ``true``.
-  **default** (integer|string): The default value of the column if no value was specified.
   Defaults to ``null``.
-  **autoincrement** (boolean): Whether this column should use an autoincremented value if
   no value was specified. Only applies to Doctrine's ``smallint``, ``integer``
   and ``bigint`` types. Defaults to ``false``.
-  **length** (integer): The maximum length of the column. Only applies to Doctrine's
   ``string`` and ``binary`` types. Defaults to ``null`` and is evaluated to ``255``
   in the platform.
-  **fixed** (boolean): Whether a ``string`` or ``binary`` Doctrine type column has
   a fixed length. Defaults to ``false``.
-  **precision** (integer): The precision of a Doctrine ``decimal`` or ``float`` type
   column that determines the overall maximum number of digits to be stored (including scale).
   Defaults to ``10``.
-  **scale** (integer): The exact number of decimal digits to be stored in a Doctrine
   ``decimal`` or ``float`` type column. Defaults to ``0``.
-  **customSchemaOptions** (array): Additional options for the column that are
   supported by all vendors:

   -  **unique** (boolean): Whether to automatically add a unique constraint for the column.
      Defaults to ``false``.

Common options
^^^^^^^^^^^^^^

The following options are not completely portable but are supported by most of the
vendors:

-  **unsigned** (boolean): Whether a ``smallint``, ``integer`` or ``bigint`` Doctrine
   type column should allow unsigned values only. Supported by MySQL, SQL Anywhere
   and Drizzle. Defaults to ``false``.
-  **comment** (integer|string): The column comment. Supported by MySQL, PostgreSQL,
   Oracle, SQL Server, SQL Anywhere and Drizzle. Defaults to ``null``.

Vendor specific options
^^^^^^^^^^^^^^^^^^^^^^^

The following options are completely vendor specific and absolutely not portable:

-  **columnDefinition**: The custom column declaration SQL snippet to use instead
   of the generated SQL by Doctrine. Defaults to ``null``. This can useful to add
   vendor specific declaration information that is not evaluated by Doctrine
   (such as the ``ZEROFILL`` attribute on MySQL).
-  **customSchemaOptions** (array): Additional options for the column that are
   supported by some vendors but not portable:

   -  **charset** (string): The character set to use for the column. Currently only supported
      on MySQL and Drizzle.
   -  **collation** (string): The collation to use for the column. Supported by MySQL, PostgreSQL,
      Sqlite, SQL Server and Drizzle.
   -  **check** (string): The check constraint clause to add to the column.
      Defaults to ``null``.
