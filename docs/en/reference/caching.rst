Caching
=======

A ``Doctrine\DBAL\Statement`` can automatically cache result sets.

For this to work an instance of ``Doctrine\Common\Cache\Cache`` must be provided.
This can be set on the configuration object (optionally it can also be passed at query time):

::

    <?php
    $cache = new \Doctrine\Common\Cache\ArrayCache();
    $config = $conn->getConfiguration();
    $config->setResultCacheImpl($cache);

To get the result set of a query cached it is necessary to pass a
``Doctrine\DBAL\Cache\QueryCacheProfile`` instance to the ``executeQuery`` or ``executeCacheQuery``
instance. The difference between these two methods is that the former does not
require this instance, while the later has this instance as a required parameter:

::

    <?php
    $stmt = $conn->executeQuery($query, $params, $types, new QueryCacheProfile(0, "some key"));
    $stmt = $conn->executeCacheQuery($query, $params, $types, new QueryCacheProfile(0, "some key"));

It is also possible to pass in a the ``Doctrine\Common\Cache\Cache`` instance into the
constructor of ``Doctrine\DBAL\Cache\QueryCacheProfile`` in which case it overrides
the default cache instance:

::

    <?php
    $cache = new \Doctrine\Common\Cache\FilesystemCache(__DIR__);
    new QueryCacheProfile(0, "some key", $cache);

In order for the data to actually be cached its necessary to ensure that the entire
result set is read (easiest way to ensure this is to use ``fetchAll``) and the statement
object is closed:

::

    <?php
    $stmt = $conn->executeCacheQuery($query, $params, $types, new QueryCacheProfile(0, "some key"));
    $data = $stmt->fetchAll();
    $stmt->closeCursor(); // at this point the result is cached


.. warning::

    When using the cache layer not all fetch modes are supported. See the code of the `ResultCacheStatement <https://github.com/doctrine/dbal/blob/master/lib/Doctrine/DBAL/Cache/ResultCacheStatement.php#L156>`_ for details.
