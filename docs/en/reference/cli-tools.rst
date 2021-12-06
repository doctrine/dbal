CLI Tools
=========

Doctrine DBAL bundles commands that can be integrated into a Symfony console application.

When you use DBAL inside a full-stack Symfony application, DoctrineBundle already integrates those into your
application's console.

There is also a standalone console runner available. To use it, make sure that Symfony console is installed::

    composer require symfony/console

With a small PHP script, you can bootstrap the console tools:

.. code-block:: php

    #!/usr/bin/env php
    <?php

    use Doctrine\DBAL\DriverManager;
    use Doctrine\DBAL\Tools\Console\ConnectionProvider\SingleConnectionProvider;
    use Doctrine\DBAL\Tools\Console\ConsoleRunner;

    // The path to Composer's autoloader
    // Adjust it according to your project's structure
    require __DIR__ . '/vendor/autoload.php';

    $connection = DriverManager::getConnection([
        // Configure your DBAL connection here.
    ]);

    ConsoleRunner::run(
        new SingleConnectionProvider($connection)
    );

If your application uses more than one connection, write your own implementation of ``ConnectionProvider`` and use it
instead of the ``SingleConnectionProvider`` class.
