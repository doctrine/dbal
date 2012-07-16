# Doctrine Shards

Doctrine Extension to support horizontal sharding in the Doctrine ORM.

## Idea

Implement sharding inside Doctrine at a level that is as unobtrusive to the developer as possible.

Problems to tackle:

1. Where to send INSERT statements?
2. How to generate primary keys?
3. How to pick shards for update, delete statements?
4. How to pick shards for select operations?
5. How to merge select queries that span multiple shards?
6. How to handle/prevent multi-shard queries that cannot be merged (GROUP BY)?
7. How to handle non-sharded data? (static metadata tables for example)
8. How to handle multiple connections?
9. Implementation on the DBAL or ORM level?

## Roadmap

Version 1: DBAL 2.3 (Multi-Tenant Apps)

    1. ID Generation support (in DBAL + ORM done)
    2. Multi-Tenant Support: Either pick a global metadata database or exactly one shard.
    3. Fan-out queries over all shards (or a subset) by result appending

Version 2: ORM related (complex):

    4. ID resolving (Pick shard for a new ID)
    5. Query resolving (Pick shards a query should send to)
    6. Shard resolving (Pick shards an ID could be on)
    7. Transactions
    8. Read Only objects

## Technical Requirements for Database Schemas

Sharded tables require the sharding-distribution key as one of their columns. This will affect your code compared to a normalized db-schema. If you have a Blog <-> BlogPost <-> PostComments entity setup sharded by `blog_id` then even the PostComment table needs this column, even if an "unsharded", normalized DB-Schema does not need this information.

## Implementation Details

Assumptions:

* For querying you either want to query ALL or just exactly one shard.
* IDs for ALL sharded tables have to be unique across all shards.
* Non-sharded data is replicated between all shards. They redundantly keep the information available. This is necessary so join queries on shards to reference data work.
* If you retrieve an object A from a shard, then all references and collections of this object reside on the same shard.
* The database schema on all shards is the same (or compatible)

### SQL Azure Federations

SQL Azure is a special case, points 1, 2, 3, 4, 7 and 8 are partly handled on the database level. This makes it a perfect test-implementation for just the subset of features in points 5-6. However there need to be a way to configure SchemaTool to generate the correct Schema on SQL Azure.

* SELECT Operations: The most simple assumption is to always query all shards unless the user specifies otherwise explicitly.
* Queries can be merged in PHP code, this obviously does not work for DISTINCT, GROUP BY and ORDER BY queries.

### Generic Sharding

More features are necessary to implement sharding on the PHP level, independent from database support:

1. Configuration of multiple connections, one connection = one shard.
2. Primary Key Generation mechanisms (UUID, central table, sequence emulation)

## Primary Use-Cases

1. Multi-Tenant Applications

These are easier to support as you have some value to determine the shard id for the whole request very early on.
Here also queries can always be limited to a single shard.

2. Scale-Out by some attribute (Round-Robin?)

This strategy requires access to multiple shards in a single request based on the data accessed.
