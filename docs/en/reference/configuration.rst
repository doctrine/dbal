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
   -  ``drizzle_pdo_mysql``: A Drizzle driver that uses pdo\_mysql PDO
      extension.
   -  ``mysqli``: A MySQL driver that uses the mysqli extension.
   -  ``pdo_sqlite``: An SQLite driver that uses the pdo\_sqlite PDO
      extension.
   -  ``pdo_pgsql``: A PostgreSQL driver that uses the pdo\_pgsql PDO
      extension.
   -  ``pdo_oci``: An Oracle driver that uses the pdo\_oci PDO
      extension.
      **Note that this driver caused problems in our tests. Prefer the oci8 driver if possible.**
   -  ``pdo_sqlsrv``: A Microsoft SQL Server driver that uses pdo\_sqlsrv PDO
      **Note that this driver caused problems in our tests. Prefer the sqlsrv driver if possible.**
   -  ``sqlsrv``: A Microsoft SQL Server driver that uses the sqlsrv PHP extension.
   -  ``oci8``: An Oracle driver that uses the oci8 PHP extension.
   -  ``sqlanywhere``: A SAP Sybase SQL Anywhere driver that uses the sqlanywhere PHP extension.

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

drizzle\_pdo\_mysql
^^^^^^^^^^^^^^^^^^^

**Requires** drizzle plugin ``mysql_protocol`` or ``mysql_unix_socket_protocol`` to be enabled.
On Ubuntu this can be done by editing ``/etc/drizzle/conf.d/mysql-protocol.cnf``
or ``/etc/drizzle/conf.d/mysql-unix-socket-protocol.cnf`` and restart drizzled daemon.

-  ``user`` (string): Username to use when connecting to the
   database. Only needed if authentication is configured for drizzled.
-  ``password`` (string): Password to use when connecting to the
   database. Only needed if authentication is configured for drizzled.
-  ``host`` (string): Hostname of the database to connect to.
-  ``port`` (integer): Port of the database to connect to.
-  ``dbname`` (string): Name of the database/schema to connect to.
-  ``unix_socket`` (string): Name of the socket used to connect to
   the database.

mysqli
^^^^^^


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
-  ``driverOptions`` Any supported flags for mysqli found on `http://www.php.net/manual/en/mysqli.real-connect.php`

pdo\_pgsql
^^^^^^^^^^


-  ``user`` (string): Username to use when connecting to the
   database.
-  ``password`` (string): Password to use when connecting to the
   database.
-  ``host`` (string): Hostname of the database to connect to.
-  ``port`` (integer): Port of the database to connect to.
-  ``dbname`` (string): Name of the database/schema to connect to.
-  ``charset`` (string): The charset used when connecting to the
   database.
-  ``sslmode`` (string): Determines whether or with what priority
   a SSL TCP/IP connection will be negotiated with the server.
   See the list of available modes:
   `http://www.postgresql.org/docs/9.1/static/libpq-connect.html#LIBPQ-CONNECT-SSLMODE`

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
-  ``servicename`` (string): Optional name by which clients can
   connect to the database instance. Will be used as Oracle's
   ``SID`` connection parameter if given and defaults to Doctrine's
   ``dbname`` connection parameter value.
-  ``service`` (boolean): Whether to use Oracle's ``SERVICE_NAME``
   connection parameter in favour of ``SID`` when connecting. The
   value for this will be read from Doctrine's ``servicename`` if
   given, ``dbname`` otherwise.
-  ``pooled`` (boolean): Whether to enable database resident
   connection pooling.
-  ``charset`` (string): The charset used when connecting to the
   database.

pdo\_sqlsrv / sqlsrv
^^^^^^^^^^^^^^^^^^^^


-  ``user`` (string): Username to use when connecting to the
   database.
-  ``password`` (string): Password to use when connecting to the
   database.
-  ``host`` (string): Hostname of the database to connect to.
-  ``port`` (integer): Port of the database to connect to.
-  ``dbname`` (string): Name of the database/schema to connect to.

sqlanywhere
^^^^^^^^^^^


-  ``user`` (string): Username to use when connecting to the
   database.
-  ``password`` (string): Password to use when connecting to the
   database.
-  ``server`` (string): Name of a running database server to connect to.
-  ``host`` (string): Hostname of the database to connect to.
-  ``port`` (integer): Port of the database to connect to.
-  ``dbname`` (string): Name of the database/schema to connect to.
-  ``persistent`` (boolean): Whether to establish a persistent connection.

Depending on the used underlying platform version, you can specify
any other connection parameter that is supported by the particular
platform version via the ``driverOptions`` option.
You can find a list of supported connection parameters for each
platform version here:

- `SQL Anywhere 10.0.1 <http://dcx.sybase.com/index.html#1001/en/dbdaen10/da-conmean.html>`_
- `SQL Anywhere 11.0.0 <http://dcx.sybase.com/index.html#1100/en/dbadmin_en11/conmean.html>`_
- `SQL Anywhere 11.0.1 <http://dcx.sybase.com/index.html#1101/en/dbadmin_en11/conmean.html>`_
- `SQL Anywhere 12.0.0 <http://dcx.sybase.com/index.html#1200/en/dbadmin/da-conparm.html>`_
- `SQL Anywhere 12.0.1 <http://dcx.sybase.com/index.html#1201/en/dbadmin/da-conparm.html>`_
- `SAP Sybase SQL Anywhere 16.0 <http://dcx.sybase.com/index.html#sa160/en/dbadmin/da-conparm.html>`_

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
