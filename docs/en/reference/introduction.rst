Introduction
============

The Doctrine database abstraction & access layer (DBAL) offers a
lightweight and thin runtime layer around a PDO-like API and a lot
of additional, horizontal features like database schema
introspection and manipulation through an OO API.

The fact that the Doctrine DBAL abstracts the concrete PDO API away
through the use of interfaces that closely resemble the existing
PDO API makes it possible to implement custom drivers that may use
existing native or self-made APIs. For example, the DBAL ships with
a driver for Oracle databases that uses the oci8 extension under
the hood.

The following database vendors are currently supported:

- MySQL
- Oracle
- Microsoft SQL Server
- PostgreSQL
- SAP Sybase SQL Anywhere
- SQLite
- Drizzle

The Doctrine 2 database layer can be used independently of the
object-relational mapper. In order to use the DBAL all you need is
the class loader provided by Composer, to be able to autoload the classes:

.. code-block:: php

    <?php
    
    require_once 'vendor/autoload.php';

Now you are able to load classes that are in the
``/path/to/doctrine`` directory like
``/path/to/doctrine/Doctrine/DBAL/DriverManager.php`` which we will
use later in this documentation to configure our first Doctrine
DBAL connection.

