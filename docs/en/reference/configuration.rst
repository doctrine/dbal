Configuration
=============

Getting a Connection
--------------------

You can get a DBAL Connection through the
``Doctrine\DBAL\DriverManager`` class.

.. code-block:: php

    <?php
    use Doctrine\DBAL\DriverManager;

    //..
    $connectionParams = [
        'dbname' => 'mydb',
        'user' => 'user',
        'password' => 'secret',
        'host' => 'localhost',
        'driver' => 'pdo_mysql',
    ];
    $conn = DriverManager::getConnection($connectionParams);

Alternatively, if you store your connection settings as a connection URL (DSN),
you can parse the URL to extract connection parameters for ``DriverManager``:

.. code-block:: php

    <?php
    use Doctrine\DBAL\DriverManager;
    use Doctrine\DBAL\Tools\DsnParser;

    //..
    $dsnParser = new DsnParser();
    $connectionParams = $dsnParser
        ->parse('mysqli://user:secret@localhost/mydb');

    $conn = DriverManager::getConnection($connectionParams);

The ``DriverManager`` returns an instance of
``Doctrine\DBAL\Connection`` which is a wrapper around the
underlying driver connection (which is often a PDO instance).

The following sections describe the available connection parameters
in detail.

Connecting using a URL
~~~~~~~~~~~~~~~~~~~~~~

The easiest way to specify commonly used connection parameters is
using a database URL or DSN. The scheme is used to specify a driver, the
user and password in the URL encode user and password for the
connection, followed by the host and port parts (the "authority").
The path after the authority part represents the name of the
database, sans the leading slash. Any query parameters are used as
additional connection parameters.

The scheme names representing the drivers are the driver names
with any underscores in their name replaced with a hyphen
(to make them legal in URL scheme names).

For example, to connect to a "foo" MySQL database using the ``pdo_mysql``
driver on localhost port 4486 with the "charset" option set to ``utf8mb4``,
you would use the following URL::

    pdo-mysql://localhost:4486/foo?charset=utf8mb4

In the example above, mind the dashes instead of the
underscores in the URL scheme.

For connecting to an SQLite database, the authority portion of the
URL is obviously irrelevant and thus can be omitted. The path part
of the URL is, like for all other drivers, stripped of its leading
slash, resulting in a relative file name for the database::

    pdo-sqlite:///somedb.sqlite

This would access ``somedb.sqlite`` in the current working directory
and is identical to the following::

    pdo-sqlite://ignored:ignored@ignored:1234/somedb.sqlite

To specify an absolute file path, e.g. ``/usr/local/var/db.sqlite``,
simply use that as the database name, which results in two leading
slashes for the path part of the URL, and four slashes in total after
the URL scheme name and its following colon::

    pdo-sqlite:////usr/local/var/db.sqlite

Which is, again, identical to supplying ignored user/pass/authority::

    pdo-sqlite://notused:inthis@case//usr/local/var/db.sqlite

To connect to an in-memory SQLite instance, use ``:memory:`` as the
database name::

    pdo-sqlite:///:memory:

Using the DSN parser
^^^^^^^^^^^^^^^^^^^^

By default, the URL scheme of the parsed DSN has to match one of DBAL's driver
names. However, it might be that you have to deal with connection strings where
you don't have control over the used scheme, e.g. in a PaaS environment. In
order to make the parser understand which driver to use e.g. for ``mysql://``
DSNs, you can configure the parser with a mapping table:

.. code-block:: php

    <?php
    use Doctrine\DBAL\Tools\DsnParser;

    //..
    $dsnParser = new DsnParser(['mysql' => 'mysqli', 'postgres' => 'pdo_pgsql']);
    $connectionParams = $dsnParser
        ->parse('mysql://user:secret@localhost/mydb');

The DSN parser returns the connection params back to you so you can add or
modify individual parameters before passing the params to the
``DriverManager``. For example, you can add a database name if its missing in
the DSN or hardcode one if the DSN is not allowed to configure the database
name.

.. code-block:: php

    <?php
    use Doctrine\DBAL\DriverManager;
    use Doctrine\DBAL\Tools\DsnParser;

    //..
    $connectionParams = $dsnParser->parse($myDsn);
    $connectionParams['dbname'] ??= 'default_db';

    $conn = DriverManager::getConnection($connectionParams);

You can also use the mapping table to map a DSN's scheme to a custom driver
class:

.. code-block:: php

    <?php
    use Doctrine\DBAL\Tools\DsnParser;
    use App\DBAL\CustomDriver; // implements Doctrine\DBAL\Driver

    //..
    $dsnParser = new DsnParser(['custom' => CustomDriver::class]);
    $connectionParams = $dsnParser
        ->parse('custom://user:secret@localhost/mydb');

Driver
~~~~~~

The driver specifies the actual implementations of the DBAL
interfaces to use. It can be configured in one of two ways:

-  ``driver``: The built-in driver implementation to use. The
   following drivers are currently available:

   -  ``pdo_mysql``: A MySQL driver that uses the pdo_mysql PDO
      extension.
   -  ``mysqli``: A MySQL driver that uses the mysqli extension.
   -  ``pdo_sqlite``: An SQLite driver that uses the pdo_sqlite PDO
      extension.
   -  ``sqlite3``: An SQLite driver that uses the sqlite3 extension.
   -  ``pdo_pgsql``: A PostgreSQL driver that uses the pdo_pgsql PDO
      extension.
   -  ``pgsql``: A PostgreSQL driver that uses the pgsql extension.
   -  ``pdo_oci``: An Oracle driver that uses the pdo_oci PDO
      extension.
      **Note that this driver caused problems in our tests. Prefer the oci8 driver if possible.**
   -  ``pdo_sqlsrv``: A Microsoft SQL Server driver that uses pdo_sqlsrv PDO
   -  ``sqlsrv``: A Microsoft SQL Server driver that uses the sqlsrv PHP extension.
   -  ``oci8``: An Oracle driver that uses the oci8 PHP extension.
   -  ``ibm_db2``: An IBM DB2 driver that uses the ibm_db2 PHP extension.

-  ``driverClass``: Specifies a custom driver implementation if no
   'driver' is specified. This allows the use of custom drivers that
   are not part of the Doctrine DBAL itself.

Wrapper Class
~~~~~~~~~~~~~

By default a ``Doctrine\DBAL\Connection`` is wrapped around a
driver ``Connection``. The ``wrapperClass`` option allows
specifying a custom wrapper implementation to use, however, a custom
wrapper class must be a subclass of ``Doctrine\DBAL\Connection``.

Connection Details
~~~~~~~~~~~~~~~~~~

The connection details identify the database to connect to as well
as the credentials to use. The connection details can differ
depending on the used driver. The following sections describe the
options recognized by each built-in driver.

pdo_sqlite
^^^^^^^^^^

-  ``user`` (string): Username to use when connecting to the
   database.
-  ``password`` (string): Password to use when connecting to the
   database.
-  ``path`` (string): The filesystem path to the database file.
   Mutually exclusive with ``memory``. ``path`` takes precedence.
-  ``memory`` (boolean): True if the SQLite database should be
   in-memory (non-persistent). Mutually exclusive with ``path``.
   ``path`` takes precedence.

sqlite3
^^^^^^^

-  ``path`` (string): The filesystem path to the database file.
   Mutually exclusive with ``memory``.
-  ``memory`` (boolean): True if the SQLite database should be
   in-memory (non-persistent). Mutually exclusive with ``path``.

pdo_mysql
^^^^^^^^^

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
-  ``ssl_key`` (string): The path name to the key file to use for SSL encryption.
-  ``ssl_cert`` (string): The path name to the certificate file to use for SSL encryption.
-  ``ssl_ca`` (string): The path name to the certificate authority file to use for SSL encryption.
-  ``ssl_capath`` (string): The pathname to a directory that contains trusted SSL CA certificates in PEM format.
-  ``ssl_cipher`` (string): A list of allowable ciphers to use for SSL encryption.
-  ``driverOptions`` Any supported flags for mysqli found on `http://www.php.net/manual/en/mysqli.real-connect.php`

pdo_pgsql / pgsql
^^^^^^^^^^^^^^^^^

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
   `https://www.postgresql.org/docs/9.4/static/libpq-connect.html#LIBPQ-CONNECT-SSLMODE`
-  ``sslrootcert`` (string): specifies the name of a file containing
   SSL certificate authority (CA) certificate(s). If the file exists,
   the server's certificate will be verified to be signed by one of these
   authorities.
   See https://www.postgresql.org/docs/9.4/static/libpq-connect.html#LIBPQ-CONNECT-SSLROOTCERT
-  ``sslcert`` (string): specifies the filename of the client SSL certificate.
   See `https://www.postgresql.org/docs/9.4/static/libpq-connect.html#LIBPQ-CONNECT-SSLCERT`
-  ``sslkey`` (string): specifies the location for the secret key used for the
   client certificate.
   See `https://www.postgresql.org/docs/9.4/static/libpq-connect.html#LIBPQ-CONNECT-SSLKEY`
-  ``sslcrl`` (string): specifies the filename of the SSL certificate
   revocation list (CRL).
   See `https://www.postgresql.org/docs/9.4/static/libpq-connect.html#LIBPQ-CONNECT-SSLCRL`
-  ``gssencmode`` (string): Optional GSS-encrypted channel/GSSEncMode configuration.
   See `https://www.postgresql.org/docs/current/libpq-connect.html#LIBPQ-CONNECT-GSSENCMODE`
-  ``application_name`` (string): Name of the application that is
   connecting to database. Optional. It will be displayed at ``pg_stat_activity``.

PostgreSQL behaves differently with regard to booleans when you use
``PDO::ATTR_EMULATE_PREPARES`` or not. To switch from using ``'true'``
and ``'false'`` as strings you can change to integers by using:
``$conn->getDatabasePlatform()->setUseBooleanTrueFalseStrings($flag)``.

pdo_oci / oci8
^^^^^^^^^^^^^^

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
-  ``connectstring`` (string): Complete Easy Connect connection descriptor,
   see https://docs.oracle.com/database/121/NETAG/naming.htm. When using this option,
   you will still need to provide the ``user`` and ``password`` parameters, but the other
   parameters will no longer be used. Note that when using this parameter, the ``getHost``
   and ``getPort`` methods from ``Doctrine\DBAL\Connection`` will no longer function as expected.
-  ``persistent`` (boolean): Whether to establish a persistent connection.
-  ``driverOptions`` (array):
    -  ``exclusive`` (boolean): Once specified for an ``oci8`` connection, forces the driver to always establish
       a new connection instead of reusing an existing one from the connection pool.

pdo_sqlsrv / sqlsrv
^^^^^^^^^^^^^^^^^^^

-  ``user`` (string): Username to use when connecting to the
   database.
-  ``password`` (string): Password to use when connecting to the
   database.
-  ``host`` (string): Hostname of the database to connect to.
-  ``port`` (integer): Port of the database to connect to.
-  ``dbname`` (string): Name of the database/schema to connect to.
-  ``driverOptions`` (array): Any supported options found on `https://learn.microsoft.com/en-us/sql/connect/php/connection-options`

ibm_db2
^^^^^^^

-  ``dbname`` (string): Name of the database/schema to connect to or a complete connection string in
   the format "DATABASE=dbname;HOSTNAME=host;PORT=port;PROTOCOL=TCPIP;UID=user;PWD=password;".
-  ``user`` (string): Username to use when connecting to the database.
-  ``password`` (string): Password to use when connecting to the database.
-  ``host`` (string): Hostname of the database to connect to.
-  ``port`` (integer): Port of the database to connect to.
-  ``persistent`` (boolean): Whether to establish a persistent connection.
-  ``driverOptions`` (array): Any supported options found on `https://www.php.net/manual/en/function.db2-connect.php#refsect1-function.db2-connect-parameters`

Automatic platform version detection
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Doctrine ships with different database platform implementations for some vendors
to support version specific features, dialect and behaviour.

The drivers will automatically detect the platform version and instantiate
the corresponding platform class. However, this mechanism might cause the
connection to be established prematurely.

You can also pass the ``serverVersion`` option if you want to disable automatic
database platform detection and choose the platform version implementation explicitly.

Please specify the full server version as the database server would report it.
This is especially important for MySQL and MariaDB where the full version
string is taken into account when determining the platform.

MySQL
^^^^^

Connect to your MySQL server and run the ``SELECT VERSION()`` query::

    mysql> SELECT VERSION();
    +-----------+
    | VERSION() |
    +-----------+
    | 8.0.32    |
    +-----------+
    1 row in set (0.00 sec)

In the example above, ``8.0.32`` is the correct value for ``serverVersion``.

MariaDB
^^^^^^^

Connect to your MariaDB server and run the ``SELECT VERSION()`` query::

    MariaDB [(none)]> SELECT VERSION();
    +-----------------------------------------+
    | VERSION()                               |
    +-----------------------------------------+
    | 10.11.2-MariaDB-1:10.11.2+maria~ubu2204 |
    +-----------------------------------------+
    1 row in set (0.001 sec)

In the example above, ``10.11.2-MariaDB-1:10.11.2+maria~ubu2204`` is the
correct value for ``serverVersion``.

Postgres
^^^^^^^^

Connect to your Postgres server and run the ``SHOW server_version`` query::

    postgres=# SHOW server_version;
             server_version
    --------------------------------
     15.2 (Debian 15.2-1.pgdg110+1)
    (1 row)

In the example above, ``15.2 (Debian 15.2-1.pgdg110+1)`` is the correct value for
``server Version``.

Other Platforms
^^^^^^^^^^^^^^^

For other platforms, DBAL currently does not implement version-specific
platform detection, so specifying the ``serverVersion`` parameter has no effect.

However, you can still do so. You can use the string that the following
expression returns::

    $connection->getWrappedConnection()->getServerVersion();

Custom Driver Options
~~~~~~~~~~~~~~~~~~~~~

The ``driverOptions`` option allows to pass arbitrary options
through to the driver. This is equivalent to the fourth argument of
the `PDO constructor <http://php.net/manual/en/pdo.construct.php>`_.
