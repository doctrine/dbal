Sharding
========

.. note::

    The sharding extension is currently in transition from a separate Project
    into DBAL. Class names may differ.

Starting with 2.3 Doctrine DBAL contains some functionality to simplify the
development of horizontally sharded applications. In this first release it
contains a ``ShardManager`` interface. This interface allows to programatically
select a shard to send queries to. At the moment there are no functionalities
yet to dynamically pick a shard based on ID, query or database row yet. That
means the sharding extension is primarily suited for:

- multi-tenant applications or
- applications with completely separated datasets (example: weather data).

Both kind of application will work with both DBAL and ORM.

.. note::

    Horizontal sharding is an evasive architecture that will affect your application code and using this
    extension to Doctrine will not make it work "magically".

You have to understand and integrate the following drawbacks:

- Pre-generation of IDs that are unique across all shards required.
- No transaction support across shards.
- No foreign key support across shards (meaning no "real" relations).
- Very complex (or impossible) to query aggregates across shards.
- Denormalization: Composite keys required where normalized non-sharded db schemas don't need them.
- Schema Operations have to be done on all shards.

The primary questions in a sharding architecture are:

* Where is my data located?
* Where should I save this new data to find it later?

To answer these questions you generally have to craft a function that will tell
you for a given ID, on which shard the data for this ID is located. To simplify
this approach you will generally just pick a table which is the root of a set of
related data and decide for the IDs of this table. All the related data that
belong to this table are saved on the same shard.

Take for example a multi-user blog application with the following tables:

- Blog [id, name]
- Post [id, blog_id, subject, body, author_id]
- Comment [id, post_id, comment, author_id]
- User [id, username]

A sensible sharding architecture will split the application by blog. That means
all the data for a particular blog will be on a single shard and scaling is
done by putting the amount of blogs on many different database servers.

Now users can post and comment on different blogs that reside on different
shards. This makes the database schema above slightly tricky, because both
`author_id` columns cannot have foreign keys to `User (id)`. Instead the User
table is located in an entirely different "dimension" of the application in
terms of the sharding architecture.

To simplify working with this kind of multi-dimensional database schema, you
can replace the author_ids with something more "meaningful", for example the
e-mail address of the users if that is always known. The "user" table can then
be separated from the database schema above and put on a second horizontally
scaled sharding architecture.

As you can see, even with just the four tables above, sharding actually becomes
quite complex to think about.

The rest of this section discusses Doctrine sharding functionality in technical
detail.

ID Generation
-------------

To solve the issue of unique ID-generation across all shards are several
approaches you should evaluate:

Use GUID/UUIDs
~~~~~~~~~~~~~~

The most simple ID-generation mechanism for sharding are
universally unique identifiers. These are 16-byte
(128-bit) numbers that are guaranteed to be unique across different servers.
You can `read up on UUIDs on Wikipedia <http://en.wikipedia.org/wiki/Universally_unique_identifier>`_.

The drawback of UUIDs is the segmentation they cause on indexes. Because UUIDs
are not sequentially generated, they can have negative impact on index access
performance. Additionally they are much bigger
than numerical primary keys (which are normally 4-bytes in length).

At the moment Doctrine DBAL drivers MySQL and SQL Server support the generation
of UUID/GUIDs. You can use the following bit of code to generate them across
platforms:

.. code-block:: php

    <?php
    use Doctrine\DBAL\DriverManager;
    use Ramsey\Uuid\Uuid;

    $conn = DriverManager::getConnection(/**..**/);
    $guid = Uuid::uuid1();

    $conn->insert('my_table', [
        'id'  => $guid->toString(),
        'foo' => 'bar',
    ]);

In your application you should hide this details in Id-Generation services:

.. code-block:: php

    <?php
    namespace MyApplication;

    use Ramsey\Uuid\Uuid;

    class IdGenerationService
    {
        public function generateCustomerId() : Uuid
        {
            return Uuid::uuid1();
        }
    }

A good starting point to read up on GUIDs (vs numerical ids) is this blog post
`Coding Horror: Primary Keys: IDs vs GUIDs <http://www.codinghorror.com/blog/2007/03/primary-keys-ids-versus-guids.html>`_.

Table Generator
~~~~~~~~~~~~~~~

In some scenarios there is no way around a numerical, automatically
incrementing id. The way Auto incrementing IDs are implemented in MySQL and SQL
Server however is completely unsuitable for sharding. Remember in a sharding
architecture you have to know where the row for a specific ID is located and
IDs have to be globally unique across all servers. Auto-Increment Primary Keys
are missing both properties.

To get around this issue you can use the so-called "table-generator" strategy.
In this case you define a single database that is responsible for the
generation of auto-incremented ids. You create a table on this database and
through the use of locking create new sequential ids.

There are three important drawbacks to this strategy:

-  Single point of failure
-  Bottleneck when application is write-heavy
-  A second independent database connection is needed to guarantee transaction
   safety.

If you can live with this drawbacks then you can use table-generation with the
following code in Doctrine:

.. code-block:: php

    <?php
    use Doctrine\DBAL\DriverManager;
    use Doctrine\DBAL\Id\TableGenerator;

    $conn = DriverManager::getConnection(/**..**); // connection 1

    // creating the TableGenerator automatically opens a second connection.
    $tableGenerator = new TableGenerator($conn, "sequences_tbl_name");

    $id1 = $tableGenerator->nextValue("sequence_name1");
    $id2 = $tableGenerator->nextValue("sequence_name2");

The table generator obviously needs a table to work. The schema of this table
is described in the ``TableGenerator`` class-docblock. Alternatively you
can use the ``Doctrine\DBAL\Id\TableGeneratorSchemaVisitor`` and apply it to your
``Doctrine\DBAL\Schema\Schema`` instance. It will automatically add the required
sequence table.

Natural Identifiers
~~~~~~~~~~~~~~~~~~~

Sometimes you are lucky and your application data-model comes with a natural
id. This is mostly the case for applications who get their IDs generated
somewhere else (exogeneous ID-generation) or that work with temporal data. In
that case you can just define the natural primary key and shard your
application based on this data.

Transactions
------------

Transactions in sharding can only work for data that is located on a single
shard. If you need transactions in your sharding architecture then you have to
make sure that the data updated during a transaction is located on a single
shard.

Foreign Keys
------------

Since you cannot create foreign keys between remote database servers, in a
sharding architecture you should put the data on a shard that belongs to each
other. But even if you can isolate most of the rows on a single shard there may
exist relations between tables that exist on different shards. In this case
your application should be aware of the potential inconsistencies and handle
them graciously.

Complex Queries
---------------

GROUP BY, DISTINCT and ORDER BY are clauses that cannot be easily used in a
sharding architecture. If you have to execute these queries against multiple
shards then you cannot just append the different results to each other.

You have to be aware of this problem and design your queries accordingly or
shard the data in a way that you never have to query multiple shards to
calculate a result.

ShardManager Interface
----------------------

The central API of the sharding extension is the ``ShardManager`` interface.
It contains two different groups of functions with regard to sharding.

First, it contains the Shard Selection API. You can pick a shard based on a
so-called "distribution-value" or reset the connection to the "global" shard,
a necessary database that often contains heavily cached, sharding independent
data such as meta tables or the "user/tenant" table.

.. code-block:: php

    <?php
    use Doctrine\DBAL\DriverManager;
    use Doctrine\Shards\DBAL\SQLAzure\SQLAzureShardManager;

    $conn = DriverManager::getConnection(array(
        'sharding' => array(
            'federationName' => 'my_database',
            'distributionKey' => 'customer_id',
        )
    ));
    $shardManager = new SQLAzureShardManager($conn);

    $currentCustomerId = 1234;
    $shardManager->selectShard($currentCustomerId);
    // all queries after this call hit the shard
    // where customer with id 1234 is on.

    $shardManager->selectGlobal();
    // the global database is selected.

To access the currently selected distribution value use the following API
method:

.. code-block:: php

    <?php
    $value = $shardManager->getCurrentDistributionValue();

The shard manager will prevent you switching shards when a transaction is open.
This is especially important when using sharding with the ORM. Because the ORM
uses a single transaction during the flush-operation this means that you can
only ever use one ``EntityManager`` with data from a single shard.

The second API is the "fan-out" query API. This allows you to execute queries against
ALL shards. The order of the results of this operation is undefined, that means
your query has to return the data in a way that works for the application, or
you have to sort the data in the application.

.. code-block:: php

    <?php
    $sql = "SELECT * FROM customers";
    $rows = $shardManager->queryAll($sql, $params);

Schema Operations: SchemaSynchronizer Interface
-----------------------------------------------

Schema Operations in a sharding architecture are tricky. You have to perform
them on all databases instances (shards) at the same time. Also Doctrine
has problems with this in particular as you cannot generate an SQL file with
changes on any development machine anymore and apply this on production. The
required changes depend on the amount of shards.

To allow the Doctrine Schema API operations on a sharding architecture we
performed a refactored from code inside ORM ``Doctrine\ORM\Tools\SchemaTool``
class and extracted the code for operations on Schema instances into a new
``Doctrine\Shards\DBAL\SchemaSynchronizer`` interface.

Every sharding implementation can implement this interface and allow schema
operations to take part on multiple shards.

SQL Azure Federations
---------------------

Doctrine Shards ships with a custom implementation for Microsoft SQL
Azure. The Azure platform provides a native sharding functionality. In SQL
Azure the sharding functionality is called Federations. This
functionality applies the following restrictions (in line with the ones listed
above):

- IDENTITY columns are not allowed on sharded tables (federated tables)
- Each table may only have exactly one clustered index and this index has to
  have the distribution key/sharding-id as one column.
- Every unique index (or primary key) has to contain the
  distribution-key/sharding-id.

Especially the requirements 2 and 3 prevent normalized database schemas. You
have to put the distribution key on every sharded table, which can affect your
application code quite a bit. This may lead to the creation of composite keys
where you normally wouldn't need them.

The benefit of SQL Azure Federations is that they implement all the
shard-picking logic on the server. You only have to make use of the ``USE
FEDERATION`` statement. You don't have to maintain a list of all the shards
inside your application and more importantly, resizing shards is done
transparently on the server.

Features of SQL Azure are:

- Central server to log into federations architecture. No need to know all
  connection details of all shards.
- Database level operation to split shards, taking away the tediousness of this
  operation for application developers.
- A global tablespace that can contain global data to all shards.
- One or many different federations (this library only supports working with
  one)
- Sharded or non-sharded tables inside federations
- Allows filtering SELECT queries on the database based on the selected
  sharding key value. This allows to implement sharded Multi-Tenant Apps very easily.

To setup an SQL Azure ShardManager use the following code:

.. code-block:: php

    <?php
    use Doctrine\DBAL\DriverManager;
    use Doctrine\Shards\DBAL\SQLAzure\SQLAzureShardManager;

    $conn = DriverManager::getConnection(array(
        'dbname'   => 'my_database',
        'host'     => 'tcp:dbname.windows.net',
        'user'     => 'user@dbname',
        'password' => 'XXX',
        'sharding' => array(
            'federationName'   => 'my_federation',
            'distributionKey'  => 'customer_id',
            'distributionType' => 'integer',
        )
    ));
    $shardManager = new SQLAzureShardManager($conn);

Currently you are limited to one federation in your application.

You can inspect all the currently known shards on SQL Azure using the
``ShardManager#getShards()`` function:

.. code-block:: php

    <?php
    foreach ($shardManager->getShards() as $shard) {
        echo $shard['id'] . " " . $shard['rangeLow'] . " - " . $shard['rangeHigh'];
    }

Schema Operations
~~~~~~~~~~~~~~~~~

Schema Operations on SQL Azure Federations are possible with the
``SQLAzureSchemaSynchronizer``. You can instantiate this from your code:

.. code-block:: php

    <?php
    use Doctrine\Shards\DBAL\SQLAzure\SQLAzureSchemaSynchronizer;

    $synchronizer = new SQLAzureSchemaSynchronizer($conn, $shardManager);

You can use the API such as ``createSchema($schema)`` then and it will be
distributed across all shards. The assumptions are:

- Using ``SchemaSynchronizer#createSchema()`` assumes the database is empty.
  The federation is created during this operation.
- Using ``SchemaSynchronizer#updateSchema()`` assumes the database and the
  federation exists. All shards of the federation are iterated and update is
  applied to all shards consecutively.

For a schema with tables in the global or federated sub-schema you have to use
the Schema API to mark tables:

.. code-block:: php

    <?php
    use Doctrine\DBAL\Schema\Schema;

    $schema = new Schema();

    // no options set, this table will be on the federation root
    $users = $schema->createTable('Users');
    //...

    // marked as sharded, but no distribution column given:
    // non-federated table inside the federation
    $products = $schema->createTable('Products');
    $products->addOption('azure.federated', true);
    //...

    // shared + distribution column:
    // federated table
    $customers = $schema->createTable('Customers');
    $customers->addColumn('CustomerID', 'integer');
    //...
    $customers->addOption('azure.federated', true);
    $customers->addOption('azure.federatedOnColumnName', 'CustomerID');

SQLAzure Filtering
~~~~~~~~~~~~~~~~~~

SQL Azure comes with a powerful filtering feature, that allows you to
automatically implement a multi-tenant application for a formerly single-tenant
application. The restriction to make this work is that your application does not work with
IDENTITY columns.

Normally when you select a shard using ``ShardManager#selectShard()`` any query
executed against this shard will return data from ALL the tenants located on
this shard. With the "FILTERING=ON" flag on the ``USE FEDERATION`` query
however SQL Azure can automatically filter all SELECT queries with the chosen
distribution value. Additionally you can automatically set the currently
selected distribution value in every INSERT statement using a function for this
value as the ``DEFAULT`` part of the column. If you are using GUIDs for every
row then UPDATE and DELETE statements using only GUIDs will work out perfectly
as well, as they are by definition for unique rows. This feature allows you to
build multi-tenant applications, even though they were not originally designed
that way.

To enable filtering you can use the
``SQLAzureShardManager#setFilteringEnabled()`` method. This method is not part
of the interface. You can also set a default value for filtering by passing it
as the "sharding.filteringEnabled" parameter to
``DriverManager#getConnection()``.

Generic SQL Sharding Support
----------------------------

Besides the custom SQL Azure support there is a generic implementation that
works with all database drivers. It requires to specify all database
connections and will switch between the different connections under the hood
when using the ``ShardManager`` API. This is also the biggest drawback of this
approach, since fan-out queries need to connect to all databases in a single
request.

See the configuration for a sample sharding connection:

.. code-block:: php

    <?php
    use Doctrine\DBAL\DriverManager;

    $conn = DriverManager::getConnection(array(
        'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
        'driver'       => 'pdo_sqlite',
        'global'       => array('memory' => true),
        'shards'       => array(
            array('id' => 1, 'memory' => true),
            array('id' => 2, 'memory' => true),
        ),
        'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
    ));

You have to configure the following options:

- 'wrapperClass' - Selecting the PoolingShardConnection as above.
- 'global' - An array of database parameters that is used for connecting to the
  global database.
- 'shards' - An array of shard database parameters. You have to specify an
  'id' parameter for each of the shard configurations.
- 'shardChoser' - Implementation of the
  ``Doctrine\Shards\DBAL\ShardChoser\ShardChoser`` interface.

The Shard Choser interface maps the distribution value to a shard-id. This
gives you the freedom to implement your own strategy for sharding the data
horizontally.
