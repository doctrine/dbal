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

Schema representation is completely decoupled from the Doctrine ORM.
You can also use it in any other project to implement database migrations
or for SQL schema generation for any metadata model that your
application has. You can generate a Schema, as the example below
shows:

.. code-block:: php

    <?php

    use Doctrine\DBAL\Schema\Column;
    use Doctrine\DBAL\Schema\ForeignKeyConstraint;
    use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
    use Doctrine\DBAL\Schema\Index;
    use Doctrine\DBAL\Schema\Index\IndexType;
    use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
    use Doctrine\DBAL\Schema\Schema;
    use Doctrine\DBAL\Schema\Table;

    $user = Table::editor()
        ->setUnquotedName('user')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName('integer')
                ->setUnsigned(true)
                ->create()
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('username')
                ->setTypeName('string')
                ->setLength(32)
                ->create()
        )
        ->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create()
        )
        ->addIndex(
            Index::editor()
                ->setUnquotedName('idx_username')
                ->setUnquotedColumnNames('username')
                ->setType(IndexType::UNIQUE)
                ->create()
        )
        ->setComment('User table')
        ->create();

    $post = Table::editor()
        ->setUnquotedName('post')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName('integer')
                ->create()
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('user_id')
                ->setTypeName('integer')
                ->create()
        )
        ->addForeignKeyConstraint(
            ForeignKeyConstraint::editor()
                ->setUnquotedName('fk_user_id')
                ->setUnquotedReferencingColumnNames('user_id')
                ->setUnquotedReferencedTableName('user')
                ->setUnquotedReferencedColumnNames('id')
                ->setOnUpdateAction(ReferentialAction::CASCADE)
                ->create()
        )
        ->create();

    $schema = new Schema([$user, $post]);
    $schema->createSequence('my_table_seq');

    $createSQL = $schema->toSql($myPlatform);     // get queries to create this schema
    $dropSQL   = $schema->toDropSql($myPlatform); // get queries to drop this schema

Now if you want to compare this schema with another schema, you can
use the ``Comparator`` class to get instances of ``SchemaDiff``,
``TableDiff`` and ``ColumnDiff``, as well as information about other
foreign key, sequence and index changes.

.. code-block:: php

    <?php
    $schemaManager = $connection->createSchemaManager();
    $comparator = $schemaManager->createComparator();
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
The following chapter gives an overview of all available Doctrine DBAL
schema assets with short explanations on their context and usage.
All schema assets reside in the ``Doctrine\DBAL\Schema`` namespace.

.. note::

    This chapter is far from being completely documented.

Table
~~~~~~

Represents a table in the schema.

Vendor specific options
^^^^^^^^^^^^^^^^^^^^^^^

The following options, that can be set using ``default_table_options``, are completely vendor specific
and absolutely not portable.

-  **charset** (string): The character set to use for the table. Currently only supported
  on MySQL.

-  **engine** (string): The DB engine used for the table. Currently only supported on MySQL.

-  **unlogged** (boolean): Set a PostgreSQL table type as
  `unlogged <https://www.postgresql.org/docs/current/sql-createtable.html>`_

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
-  **precision** (integer): The precision of a Doctrine ``decimal``, ``number`` or ``float``
   type column that determines the overall maximum number of digits to be stored (including scale).
   Defaults to ``10``.
-  **scale** (integer): The exact number of decimal digits to be stored in a Doctrine
   ``decimal``, ``number`` or ``float`` type column. Defaults to ``0``.
-  **customSchemaOptions** (array): Additional options for the column that are
   supported by all vendors:

Common options
^^^^^^^^^^^^^^

The following options are not completely portable but are supported by most of the
vendors:

-  **unsigned** (boolean): Whether a ``smallint``, ``integer`` or ``bigint`` Doctrine
   type column should allow unsigned values only. Supported only by MySQL.
   Defaults to ``false``.
-  **comment** (integer|string): The column comment. Supported by MySQL, PostgreSQL,
   Oracle and SQL Server. Defaults to ``null``.

Vendor specific options
^^^^^^^^^^^^^^^^^^^^^^^

The following options are completely vendor specific and absolutely not portable:

-  **columnDefinition** (string): The custom column declaration SQL snippet to use instead
   of the generated SQL by Doctrine. Defaults to ``null``. This can useful to add
   vendor specific declaration information that is not evaluated by Doctrine
   (such as the ``ZEROFILL`` attribute on MySQL).
-  **customSchemaOptions** (array): Additional options for the column that are
   supported by some vendors but not portable:

   -  **charset** (string): The character set to use for the column. Currently only supported
      on MySQL.
   -  **collation** (string): The collation to use for the column. Supported by MySQL, PostgreSQL,
      Sqlite and SQL Server.
