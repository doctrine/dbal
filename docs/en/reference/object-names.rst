Object Names
============

Doctrine DBAL provides an abstraction for representing names of database objects such as tables, columns, indexes, and
so on. Each object name consists of one or more identifiers.

.. _unquoted-and-quoted-identifiers:

Unquoted and Quoted Identifiers
-------------------------------

The ``Identifier`` class from the ``Doctrine\DBAL\Schema\Name`` namespace (not to be confused with the internal
``Doctrine\DBAL\Schema\Identifier`` class) represents an SQL identifier. An identifier may be *unquoted* or *quoted*.
Use ``Identifier::unquoted($name)`` or ``Identifier::quoted($name)`` to create an unquoted or quoted identifier,
respectively.

Whether an identifier is quoted affects how its value is normalized (upper-cased, lower-cased, or used as-is) depending
on the database platform.

.. note::

    The fact that an identifier is declared as unquoted does **not** mean that it can be used to inject arbitrary
    fragments into the resulting SQL.

    Although this behavior is not yet consistent across the codebase, the goal is for DBAL to **quote all identifiers**
    in SQL, regardless of whether they are declared as quoted or unquoted. Application developers remain responsible —
    both before and after this improvement — for ensuring that only valid object names are passed to DBAL.

Rendering of Quoted Identifiers in SQL
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The original case of quoted identifiers is preserved on all platforms.
For example, a quoted ``UserId`` is rendered as ``"UserId"``.

Rendering of Unquoted Identifiers in SQL
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. On **Oracle** [1]_ and **Db2** [2]_, the value is upper-cased.
   For example, an unquoted ``user_id`` is rendered as ``"USER_ID"``.
2. On **PostgreSQL** [3]_, the value is lower-cased.
   For example, an unquoted ``USER_ID`` is rendered as ``"user_id"``.
3. On **MySQL**, **SQLite**, and **SQL Server**, the value is used as-is.
   For example, an unquoted ``UserId`` is rendered as ``"UserId"``.

Choosing Between Unquoted and Quoted Identifiers
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Whether to use quoted or unquoted identifiers depends on the target database platforms and the desired SQL rendering.
See the :ref:`quoting-identifiers` article for platform-specific recommendations.

Unqualified and Optionally Qualified Names
------------------------------------------

The ``Name`` interface represents SQL object names. Currently, Doctrine DBAL supports *unqualified* and *optionally
qualified* names.

An **unqualified name** is represented by the ``UnqualifiedName`` class and consists of a single identifier.
Example: ``user_id``.

An **optionally qualified name** is represented by the ``OptionallyQualifiedName`` class and consists of an identifier and
an optional qualifier.
Examples: ``users``, ``public.users``.

Doctrine DBAL uses unqualified names for objects that belong to a table, and optionally qualified names for objects that belong to a database. The following table summarizes the mapping:

+----------------------------+----------------------+------------------------------------------------------------+
| Object Type                | Name Type            | Examples                                                   |
+============================+======================+============================================================+
| **Table**                  | Optionally qualified | ``products``, ``inventory.products``                       |
+----------------------------+----------------------+------------------------------------------------------------+
| **Column**                 | Unqualified          | ``id``                                                     |
+----------------------------+----------------------+------------------------------------------------------------+
| **Index**                  | Unqualified          | ``category_id_idx``                                        |
+----------------------------+----------------------+------------------------------------------------------------+
| **Primary key constraint** | Unqualified          | ``products_pk``                                            |
+----------------------------+----------------------+------------------------------------------------------------+
| **Unique constraint**      | Unqualified          | ``sku_uq``                                                 |
+----------------------------+----------------------+------------------------------------------------------------+
| **Foreign key constraint** | Unqualified          | ``category_fk``                                            |
+----------------------------+----------------------+------------------------------------------------------------+
| **View**                   | Optionally qualified | ``available_products``, ``fulfillment.available_products`` |
+----------------------------+----------------------+------------------------------------------------------------+
| **Sequence**               | Optionally qualified | ``product_id_seq``, ``inventory.product_id_seq``           |
+----------------------------+----------------------+------------------------------------------------------------+

Named and Optionally Named Objects
----------------------------------

All classes representing database objects implement either the ``NamedObject`` or ``OptionallyNamedObject`` interface.

* A ``NamedObject`` instance is guaranteed to have a name (a concrete implementation of the ``Name`` interface).
* An ``OptionallyNamedObject`` instance may or may not have a name (also a concrete ``Name``).

All database objects except constraints are named.
Constraints are optionally named.

.. [1] `Oracle: Database Object Naming Rules <https://docs.oracle.com/en/database/oracle/oracle-database/23/sqlrf/Database-Object-Names-and-Qualifiers.html#GUID-75337742-67FD-4EC0-985F-741C93D918DA>`_
.. [2] `Db2: Case sensitivity and the correct use of quotation marks <https://www.ibm.com/docs/en/db2/12.1.0?topic=sources-case-sensitivity-correct-use-quotation-marks>`_
.. [3] `PostgreSQL: Identifiers and Key Words <https://www.postgresql.org/docs/current/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS>`_
