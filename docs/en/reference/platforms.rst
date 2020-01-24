Platforms
=========

Platforms abstract query generation and the subtle differences of
the supported database vendors. In most cases you don't need to
interact with the ``Doctrine\DBAL\Platforms`` package a lot, but
there might be certain cases when you are programming database
independent where you want to access the platform to generate
queries for you.

The platform can be accessed from any ``Doctrine\DBAL\Connection``
instance by calling the ``getDatabasePlatform()`` method.

::

    <?php
    $platform = $conn->getDatabasePlatform();

Each database driver has a platform associated with it by default.
Several drivers also share the same platform, for example PDO\_OCI
and OCI8 share the ``OraclePlatform``.

Doctrine provides abstraction for different versions of platforms
if necessary to represent their specific features and dialects.
For example has Microsoft added support for sequences in their 2012
version. Therefore Doctrine offers a separate platform class for this
extending the previous 2008 version. The 2008 version adds support
for additional data types which in turn don't exist in the previous
2005 version and so on.
A list of available platform classes that can be used for each vendor
can be found as follows:

MySQL
^^^^^

-  ``MySqlPlatform`` for version 5.0 and above.
-  ``MySQL57Platform`` for version 5.7 (5.7.9 GA) and above.
-  ``MySQL80Platform`` for version 8.0 (8.0 GA) and above.

MariaDB
^^^^^

-  ``MariaDb1027Platform`` for version 10.2 (10.2.7 GA) and above.

Oracle
^^^^^^

-  ``OraclePlatform`` for all versions.

Microsoft SQL Server
^^^^^^^^^^^^^^^^^^^^

-  ``SQLServerPlatform`` for version 2000 and above.
-  ``SQLServer2005Platform`` for version 2005 and above.
-  ``SQLServer2008Platform`` for version 2008 and above.
-  ``SQLServer2012Platform`` for version 2012 and above.

PostgreSQL
^^^^^^^^^^

-  ``PostgreSqlPlatform`` for all versions.
-  ``PostgreSQL91Platform`` for version 9.1 and above.
-  ``PostgreSQL92Platform`` for version 9.2 and above.
-  ``PostgreSQL94Platform`` for version 9.4 and above.

SAP Sybase SQL Anywhere
^^^^^^^^^^^^^^^^^^^^^^^

-  ``SQLAnywherePlatform`` for version 10 and above.
-  ``SQLAnywhere11Platform`` for version 11 and above.
-  ``SQLAnywhere12Platform`` for version 12 and above.
-  ``SQLAnywhere16Platform`` for version 16 and above.

SQLite
^^^^^^

-  ``SqlitePlatform`` for all versions.

Drizzle
^^^^^^

-  ``DrizzlePlatform`` for all versions.

It is highly encouraged to use the platform class that matches your
database vendor and version best. Otherwise it is not guaranteed
that the compatibility in terms of SQL dialect and feature support
between Doctrine DBAL and the database server will always be given.

If you want to overwrite parts of your platform you can do so when
creating a connection. There is a ``platform`` option you can pass
an instance of the platform you want the connection to use:

::

    <?php
    $myPlatform = new MyPlatform();
    $options = array(
        'driver' => 'pdo_sqlite',
        'path' => 'database.sqlite',
        'platform' => $myPlatform
    );
    $conn = DriverManager::getConnection($options);

This way you can optimize your schema or generated SQL code with
features that might not be portable for instance, however are
required for your special needs. This can include using triggers or
views to simulate features or adding behaviour to existing SQL
functions.

Platforms are also responsible to know which database type
translates to which PHP Type. This is a very tricky issue across
all the different database vendors, for example MySQL BIGINT and
Oracle NUMBER should be handled as integer. Doctrine 2 offers a
powerful way to abstract the database to php and back conversion,
which is described in the next section.

