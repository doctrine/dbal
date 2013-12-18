Configuration
=============

Getting a Connection
--------------------

You can get a DBAL Connection through the
``Doctrine\DBAL\DriverManager`` class.

.. code-block:: php

    <?php
    $config = new \Doctrine\DBAL\Configuration();
    //..
    $connectionParams = array(
        'dbname' => 'mydb',
        'user' => 'user',
        'password' => 'secret',
        'host' => 'localhost',
        'driver' => 'pdo_mysql',
    );
    $conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);

The ``DriverManager`` returns an instance of
``Doctrine\DBAL\Connection`` which is a wrapper around the
underlying driver connection (which is often a PDO instance).

The following sections describe the available connection parameters
in detail.

Driver
~~~~~~

The driver specifies the actual implementations of the DBAL
interfaces to use. It can be configured in one of three ways:


-  ``driver``: The built-in driver implementation to use. The
   following drivers are currently available:

   -  ``pdo_mysql``: A MySQL driver that uses the pdo\_mysql PDO
      extension.
   -  ``pdo_sqlite``: An SQLite driver that uses the pdo\_sqlite PDO
      extension.
   -  ``pdo_pgsql``: A PostgreSQL driver that uses the pdo\_pgsql PDO
      extension.
   -  ``pdo_oci``: An Oracle driver that uses the pdo\_oci PDO
      extension.
      **Note that this driver caused problems in our tests. Prefer the oci8 driver if possible.**
   -  ``pdo_sqlsrv``: A Microsoft SQL Server driver that uses pdo\_sqlsrv PDO
   -  ``oci8``: An Oracle driver that uses the oci8 PHP extension.

-  ``driverClass``: Specifies a custom driver implementation if no
   'driver' is specified. This allows the use of custom drivers that
   are not part of the Doctrine DBAL itself.
-  ``pdo``: Specifies an existing PDO instance to use.

Wrapper Class
~~~~~~~~~~~~~

By default a ``Doctrine\DBAL\Connection`` is wrapped around a
driver ``Connection``. The ``wrapperClass`` option allows to
specify a custom wrapper implementation to use, however, a custom
wrapper class must be a subclass of ``Doctrine\DBAL\Connection``.

Connection Details
~~~~~~~~~~~~~~~~~~

The connection details identify the database to connect to as well
as the credentials to use. The connection details can differ
depending on the used driver. The following sections describe the
options recognized by each built-in driver.

.. note::

    When using an existing PDO instance through the ``pdo``
    option, specifying connection details is obviously not necessary.


pdo\_sqlite
^^^^^^^^^^^


-  ``user`` (string): Username to use when connecting to the
   database.
-  ``password`` (string): Password to use when connecting to the
   database.
-  ``path`` (string): The filesystem path to the database file.
   Mutually exclusive with ``memory``. ``path`` takes precedence.
-  ``memory`` (boolean): True if the SQLite database should be
   in-memory (non-persistent). Mutually exclusive with ``path``.
   ``path`` takes precedence.

pdo\_mysql
^^^^^^^^^^


-  ``user`` (string): Username to use when connecting to the
   database.
-  ``password`` (string): Password to use when connecting to the
   database.
-  ``host`` (string): Hostname of the database to connect to.
-  ``port`` (integer): Port of the database to connect to.
-  ``dbname`` (string): Name of the database/schema to connect to.
-  ``unix_socket`` (string): Name of the socket used to connect to
   the database.
-  ``charset`` (string): The charset used when connecting to the
   database.

pdo\_pgsql
^^^^^^^^^^


-  ``user`` (string): Username to use when connecting to the
   database.
-  ``password`` (string): Password to use when connecting to the
   database.
-  ``host`` (string): Hostname of the database to connect to.
-  ``port`` (integer): Port of the database to connect to.
-  ``dbname`` (string): Name of the database/schema to connect to.

PostgreSQL behaves differently with regard to booleans when you use
``PDO::ATTR_EMULATE_PREPARES`` or not. To switch from using ``'true'``
and ``'false'`` as strings you can change to integers by using:
``$conn->getDatabasePlatform()->setUseBooleanTrueFalseStrings($flag)``.

pdo\_oci / oci8
^^^^^^^^^^^^^^^


-  ``user`` (string): Username to use when connecting to the
   database.
-  ``password`` (string): Password to use when connecting to the
   database.
-  ``host`` (string): Hostname of the database to connect to.
-  ``port`` (integer): Port of the database to connect to.
-  ``dbname`` (string): Name of the database/schema to connect to.
-  ``pooled`` (boolean): Whether to enable database resident
   connection pooling.
-  ``charset`` (string): The charset used when connecting to the
   database.

pdo\_sqlsrv
^^^^^^^^^^


-  ``user`` (string): Username to use when connecting to the
   database.
-  ``password`` (string): Password to use when connecting to the
   database.
-  ``host`` (string): Hostname of the database to connect to.
-  ``port`` (integer): Port of the database to connect to.
-  ``dbname`` (string): Name of the database/schema to connect to.

Custom Platform
~~~~~~~~~~~~~~~

Each built-in driver uses a default implementation of
``Doctrine\DBAL\Platforms\AbstractPlatform``. If you wish to use a
customized or custom implementation, you can pass a precreated
instance in the ``platform`` option.

Custom Driver Options
~~~~~~~~~~~~~~~~~~~~~

The ``driverOptions`` option allows to pass arbitrary options
through to the driver. This is equivalent to the fourth argument of
the `PDO constructor <http://php.net/manual/en/pdo.construct.php>`_.
