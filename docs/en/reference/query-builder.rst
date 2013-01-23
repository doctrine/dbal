SQL Query Builder
=================

Doctrine 2.1 ships with a powerful query builder for the SQL language. This QueryBuilder object has methods
to add parts to an SQL statement. If you built the complete state you can execute it using the connection
it was generated from. The API is roughly the same as that of the DQL Query Builder.

You can access the QueryBuilder by calling ``Doctrine\DBAL\Connection#createQueryBuilder``:

.. code-block:: php

    <?php

    $conn = DriverManager::getConnection(array(/*..*/));
    $queryBuilder = $conn->createQueryBuilder();

