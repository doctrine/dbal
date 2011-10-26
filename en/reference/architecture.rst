Architecture
============

As already said, the DBAL is a thin layer on top of PDO. PDO itself
is mainly defined in terms of 2 classes: ``PDO`` and
``PDOStatement``. The equivalent classes in the DBAL are
``Doctrine\DBAL\Connection`` and ``Doctrine\DBAL\Statement``. A
``Doctrine\DBAL\Connection`` wraps a
``Doctrine\DBAL\Driver\Connection`` and a
``Doctrine\DBAL\Statement`` wraps a
``Doctrine\DBAL\Driver\Statement``.

``Doctrine\DBAL\Driver\Connection`` and
``Doctrine\DBAL\Driver\Statement`` are just interfaces. These
interfaces are implemented by concrete drivers. For all PDO based
drivers, ``PDO`` and ``PDOStatement`` are the implementations of
these interfaces. Thus, for PDO-based drivers, a
``Doctrine\DBAL\Connection`` wraps a ``PDO`` instance and a
``Doctrine\DBAL\Statement`` wraps a ``PDOStatement`` instance. Even
more, a ``Doctrine\DBAL\Connection`` *is a*
``Doctrine\DBAL\Driver\Connection`` and a
``Doctrine\DBAL\Statement`` *is a*
``Doctrine\DBAL\Driver\Statement``.

What does a ``Doctrine\DBAL\Connection`` or a
``Doctrine\DBAL\Statement`` add to the underlying driver
implementations? The enhancements include SQL logging, events and
control over the transaction isolation level in a portable manner,
among others.

A DBAL driver is defined to the outside in terms of 3 interfaces:
``Doctrine\DBAL\Driver``, ``Doctrine\DBAL\Driver\Connection`` and
``Doctrine\DBAL\Driver\Statement``. The latter two resemble (a
subset of) the corresponding PDO API.

A concrete driver implementation must provide implementation
classes for these 3 interfaces.

The DBAL is separated into several different packages that
perfectly separate responsibilities of the different RDBMS layers.

Drivers
-------

The drivers abstract a PHP specific database API by enforcing two
interfaces:


-  ``\Doctrine\DBAL\Driver\Driver``
-  ``\Doctrine\DBAL\Driver\Statement``

The above two interfaces require exactly the same methods as PDO.

Platforms
---------

The platforms abstract the generation of queries and which database
features a platform supports. The
``\Doctrine\DBAL\Platforms\AbstractPlatform`` defines the common
denominator of what a database platform has to publish to the
userland, to be fully supportable by Doctrine. This includes the
SchemaTool, Transaction Isolation and many other features. The
Database platform for MySQL for example can be used by all 3 MySQL
extensions, PDO, Mysqli and ext/mysql.

Logging
-------

The logging holds the interface and some implementations for
debugging of Doctrine SQL query execution during a request.

Schema
------

The schema offers an API for each database platform to execute DDL
statements against your platform or retrieve metadata about it. It
also holds the Schema Abstraction Layer which is used by the
different Schema Management facilities of Doctrine DBAL and ORM.

Types
-----

The types offer an abstraction layer for the converting and
generation of types between Databases and PHP. Doctrine comes
bundled with some common types but offers the ability for
developers to define custom types or extend existing ones easily.


