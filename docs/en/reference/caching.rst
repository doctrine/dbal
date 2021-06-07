Caching
=======

A ``Doctrine\DBAL\Statement`` can automatically cache result sets. The
feature is optional though, and by default, no result set is cached.

To use the result cache, there are three mandatory steps:

1. Configure a global result cache, or provide one at query time.
2. Provide a cache profile for the result set you want to cache when
   making a query.
3. Read the entire result set from the database.

Configuring the result cache
----------------------------

Any instance of ``Psr\Cache\CacheItemPoolInterface`` can be used as a result
cache and can be set on the configuration object (optionally it can also
be passed at query time):

::

    <?php
    $cache = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
    $config = $conn->getConfiguration();
    $config->setResultCache($cache);

Note that this documentation uses Symfony Cache in all examples. Any other cache implementation
that follows the PSR-6 standard can be used instead.

Providing a cache profile
-------------------------

To get the result set of a query cached, it is necessary to pass a
``Doctrine\DBAL\Cache\QueryCacheProfile`` instance to the
``executeQuery()`` or ``executeCacheQuery()`` methods. The difference
between these two methods is that the former has the cache profile as an
optional argument, whereas it is required when calling the latter:

::

    <?php
    $stmt = $conn->executeQuery($query, $params, $types, new QueryCacheProfile(0, "some key"));
    $stmt = $conn->executeCacheQuery($query, $params, $types, new QueryCacheProfile(0, "some key"));

As stated before, it is also possible to pass in a
``Psr\Cache\CacheItemPoolInterface`` instance into the constructor of
``Doctrine\DBAL\Cache\QueryCacheProfile`` in which case it overrides the
default cache instance:

::

    <?php
    $cache = new \Symfony\Component\Cache\Adapter\FilesystemAdapter();
    new QueryCacheProfile(0, "some key", $cache);

Reading the entire result set
-----------------------------

Caching half a result set would cause bugs if a subsequent caller needed
more rows from that same result sets. To be able to cache the entire
result set, it must be fetched entirely from the database, and not all
APIs do that. The easiest way to ensure that is to use one of the
``fetchAll*()`` methods:

::

    <?php
    $stmt = $conn->executeCacheQuery($query, $params, $types, new QueryCacheProfile(0, "some key"));
    $data = $stmt->fetchAllAssociative();

.. warning::

    When using the cache layer not all fetch modes are supported. See
    the code of the ``Doctrine\DBAL\Cache\CachingResult`` for
    details.
