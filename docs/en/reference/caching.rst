Caching
=======

A ``Doctrine\DBAL\Connection`` can automatically cache result sets. The
feature is optional though, and by default, no result set is cached.

To use the result cache, there are three mandatory steps:

1. Configure a global result cache, or provide one at query time.
2. Provide a cache profile for the result set you want to cache when
   making a query.

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
