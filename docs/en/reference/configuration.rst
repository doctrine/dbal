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

Or, using the simpler URL form:

.. code-block:: php

    <?php
    $config = new \Doctrine\DBAL\Configuration();
    //..
    $connectionParams = array(
        'url' => 'mysql://user:secret@localhost/mydb',
    );
    $conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);

The ``DriverManager`` returns an instance of
``Doctrine\DBAL\Connection`` which is a wrapper around the
underlying driver connection (which is often a PDO instance).

The following sections describe the available connection parameters
in detail.

Connecting using a URL
~~~~~~~~~~~~~~~~~~~~~~

The easiest way to specify commonly used connection parameters is
using a database URL. The scheme is used to specify a driver, the
user and password in the URL encode user and password for the
connection, followed by the host and port parts (the "authority").
The path after the authority part represents the name of the
database, sans the leading slash. Any query parameters are used as
additional connection parameters.

The scheme names representing the drivers are either the regular
driver names (see below) with any underscores in their name replaced
with a hyphen (to make them legal in URL scheme names), or one of the
following simplified driver names that serve as aliases:

-  ``db2``: alias for ``ibm_db2``
-  ``mssql``: alias for ``pdo_sqlsrv``
-  ``mysql``/``mysql2``: alias for ``pdo_mysql``
-  ``pgsql``/``postgres``/``postgresql``: alias for ``pdo_pgsql``
-  ``sqlite``/``sqlite3``: alias for ``pdo_sqlite``

For example, to connect to a "foo" MySQL DB using the ``pdo_mysql``
driver on localhost port 4486 with the charset set to UTF-8, you
would use the following URL::

    mysql://localhost:4486/foo?charset=UTF-8

This is identical to the following connection string using the
full driver name::

    pdo-mysql://localhost:4486/foo?charset=UTF-8

If you wanted to use the ``drizzle_pdo__mysql`` driver instead::

    drizzle-pdo-mysql://localhost:4486/foo?charset=UTF-8

In the two last example above, mind the dashes instead of the
underscores in the URL schemes.

For connecting to an SQLite database, the authority portion of the
URL is obviously irrelevant and thus can be omitted. The path part
of the URL is, like for all other drivers, stripped of its leading
slash, resulting in a relative file name for the database::

    sqlite:///somedb.sqlite

This would access ``somedb.sqlite`` in the current working directory
and is identical to the following::

    sqlite://ignored:ignored@ignored:1234/somedb.sqlite

To specify an absolute file path, e.g. ``/usr/local/var/db.sqlite``,
simply use that as the database name, which results in two leading
slashes for the path part of the URL, and four slashes in total after
the URL scheme name and its following colon::

    sqlite:////usr/local/var/db.sqlite

Which is, again, identical to supplying ignored user/pass/authority::

    sqlite://notused:inthis@case//usr/local/var/db.sqlite

To connect to an in-memory SQLite instance, use ``:memory::`` as the
database name::

    sqlite:///:memory:

.. note::

    Any information extracted from the URL overwrites existing values
    for the parameter in question, but the rest of the information
    is merged together. You could, for example, have a URL without
    the ``charset`` setting in the query string, and then add a
    ``charset`` connection parameter next to ``url``, to provide a
    default value in case the URL doesn't contain a charset value.


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
-  ``instancename`` (string): Optional parameter, complete whether to
   add the INSTANCE_NAME parameter in the connection. It is generally used
   to connect to an Oracle RAC server to select the name of a particular instance.   


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

Automatic platform version detection
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Doctrine ships with different database platform implementations for some vendors
to support version specific features, dialect and behaviour.
As of Doctrine DBAL 2.5 the appropriate platform implementation for the underlying
database server version can be detected at runtime automatically for nearly all drivers.
Before 2.5 you had to configure Doctrine to use a certain platform implementation
explicitly with the ``platform`` connection parameter (see section below).
Otherwise Doctrine always used a default platform implementation. For example if
your application was backed by a SQL Server 2012 database, Doctrine would still use
the SQL Server 2008 platform implementation as it is the default, unless you told
Doctrine explicitly to use the SQL Server 2012 implementation.

The following drivers support automatic database platform detection out of the box
without any extra configuration required:

-  ``pdo_mysql``
-  ``mysqli``
-  ``pdo_pgsql``
-  ``pdo_sqlsrv``
-  ``sqlsrv``

Some drivers cannot provide the version of the underlying database server without
having to query for it explicitly. For performance reasons (to save one extra query
on every connect), Doctrine does not enable automatic database platform version
detection for the following drivers:

-  ``sqlanywhere``

If you still want to tell Doctrine which database server version you are using in
order to choose the appropriate platform implementation, you can pass the
``serverVersion`` option with a vendor specific version string that matches the
database server version you are using.
You can also pass this option if you want to disable automatic database platform
detection for a driver that natively supports it and choose the platform version
implementation explicitly.

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
