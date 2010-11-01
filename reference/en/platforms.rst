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


