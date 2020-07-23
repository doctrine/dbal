Architecture
============

The DBAL consists of two layers: drivers and a wrapper. Each layer
is mainly defined in terms of 3 components: ``Connection``,
``Statement`` and ``Result``.
A ``Doctrine\DBAL\Connection`` wraps a ``Doctrine\DBAL\Driver\Connection``,
a ``Doctrine\DBAL\Statement`` wraps a ``Doctrine\DBAL\Driver\Statement``
and a ``Doctrine\DBAL\Result`` wraps a ``Doctrine\DBAL\Driver\Result``.

``Doctrine\DBAL\Driver\Connection``, ``Doctrine\DBAL\Driver\Statement``
and ``Doctrine\DBAL\Driver\Result`` are just interfaces.
These interfaces are implemented by concrete drivers.

What do the wrapper components add to the underlying driver
implementations? The enhancements include SQL logging, events and
control over the transaction isolation level in a portable manner,
among others.

Apart from the three main components, a DBAL driver should also provide
an implementation of the ``Doctrine\DBAL\Driver`` interface that
has two primary purposes:

1. Translate the DBAL connection parameters to the ones specific
   to the driver's connection class.
2. Act as a factory of other driver-specific components like
   platform, schema manager and exception converter.

The DBAL is separated into several different packages that
separate responsibilities of the different RDBMS layers.

Drivers
-------

The drivers abstract a PHP specific database API by enforcing three
interfaces:

-  ``\Doctrine\DBAL\Driver\Connection``
-  ``\Doctrine\DBAL\Driver\Statement``
-  ``\Doctrine\DBAL\Driver\Result``

Platforms
---------

The platforms abstract the generation of queries and which database
features a platform supports. The
``\Doctrine\DBAL\Platforms\AbstractPlatform`` defines the common
denominator of what a database platform has to publish to the
userland, to be fully supportable by Doctrine. This includes the
SchemaTool, Transaction Isolation and many other features. The
Database platform for MySQL for example can be used by multiple
MySQL extensions: pdo_mysql and mysqli.

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

