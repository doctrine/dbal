CLI Tools
=========

Doctrine DBAL bundles the `dbal:run-sql` command that can be integrated into a Symfony console application.

The command may be added to the application as follows:

.. code-block:: php

    use Doctrine\DBAL\Connection;
    use Doctrine\DBAL\Tools\Console\ConnectionProvider\SingleConnectionProvider;
    use Symfony\Component\Console\Application;

    /** @var Connection $connection */
    $connection = /* ... */;

    /** @var Application $cli */
    $cli = /* ... */;

    $connectionProvider = new SingleConnectionProvider($connection);

    $cli->add(new RunSqlCommand($connectionProvider));

If your application uses more than one connection, write your own implementation of ``ConnectionProvider`` and use it
instead of the ``SingleConnectionProvider`` class.
