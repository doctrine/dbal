.. _spatial_types:

Spatial Types
=============

Doctrine DBAL provides two spatial mapping types, :ref:`geometry <geometry>` and
:ref:`geography <geography>`, for storing and retrieving geometric data such as points,
lines and polygons. Both are built-in types and can be retrieved through the usual
factory method:

.. code-block:: php

    <?php

    use Doctrine\DBAL\Types\Type;

    $type = Type::getType('geometry');

The difference between the two mirrors the distinction made by the underlying database:

- ``geometry`` operates on a planar (Euclidean) coordinate system. Distances and areas
  are computed on a flat plane.
- ``geography`` operates on a spherical coordinate system using longitude/latitude
  coordinates. Distances and areas are computed on the surface of the earth.

Both types convert values to and from the ``Doctrine\DBAL\Types\Geometry`` value object
rather than to raw strings or arrays. Spatial data is transported as GeoJSON on every
supported platform.

The ``Geometry`` and ``GeoJSON`` value objects
----------------------------------------------

``Doctrine\DBAL\Types\Geometry`` is an immutable value object that wraps a single
geometry together with its optional SRID (Spatial Reference System Identifier). You
create one from a GeoJSON string:

.. code-block:: php

    <?php

    use Doctrine\DBAL\Types\Geometry;

    $point = Geometry::fromGeoJSON('{"type":"Point","coordinates":[-122.4194,37.7749]}');

    $point->toGeoJSON(); // string, the GeoJSON representation
    (string) $point;     // same as toGeoJSON()
    $point->getSrid();   // int|null, read from the GeoJSON "crs" property
    $point->getGeoJSON(); // the underlying Doctrine\DBAL\Types\GeoJSON value object

``Geometry::fromGeoJSON()`` throws ``InvalidArgumentException`` when the input is not
valid GeoJSON.

``Doctrine\DBAL\Types\GeoJSON`` is the validator and wrapper behind ``Geometry``. It
parses and validates a GeoJSON string according to
`RFC 7946 <https://datatracker.ietf.org/doc/html/rfc7946>`_ and exposes
``GeoJSON::fromString()``, ``toString()`` and ``getSrid()``. The following geometry types
are supported:

- ``Point``, ``LineString``, ``Polygon``
- ``MultiPoint``, ``MultiLineString``, ``MultiPolygon``
- ``GeometryCollection``

``Feature`` and ``FeatureCollection`` are intentionally not supported, as they are not
suitable for storage in a database column.

The SRID is read from the ``crs.properties.name`` member of the GeoJSON payload and is
recognised in the EPSG (``EPSG:4326``) and URN (``urn:ogc:def:crs:EPSG::4326``) forms.
If no ``crs`` is present, ``getSrid()`` returns ``null``.

.. _spatial_types_geojson:

Why GeoJSON is the wire format
------------------------------

Spatial data is always transported as GeoJSON, not WKT, on every supported platform.
This is a constraint of the type-conversion design rather than a preference.

``Type::convertToDatabaseValueSQL()`` receives exactly one placeholder and wraps it in a
single SQL function call. A conversion function can therefore bind only one parameter.
A WKT-based construction such as ``ST_GeomFromText(?, ?)`` needs two bind parameters —
the WKT string *and* the SRID as separate arguments — and is not an option under this
design. GeoJSON carries the SRID inside the payload itself via the ``crs`` property, so
``ST_GeomFromGeoJSON(?)`` transports both the geometry and its SRID through a single
placeholder. The read path uses ``ST_AsGeoJSON()`` accordingly.

.. note::

    Support for additional input formats such as WKT should be added as a PHP-side
    conversion into the GeoJSON-backed ``Geometry`` value object (parse WKT, emit
    GeoJSON), keeping the single-placeholder wire format intact. It must not be added by
    switching the SQL wrapper to ``ST_GeomFromText``.

Declaring a spatial column
---------------------------

Spatial columns accept two additional options:

- **geometryType** (string): the geometry subtype, for example ``POINT``, ``LINESTRING``
  or ``POLYGON``. Defaults to ``GEOMETRY`` (any geometry type) if omitted.
- **srid** (integer): the EPSG identifier of the spatial reference system, for example
  ``4326`` for WGS 84.

.. code-block:: php

    <?php

    use Doctrine\DBAL\Schema\Column;
    use Doctrine\DBAL\Schema\Table;
    use Doctrine\DBAL\Types\Types;

    $table = Table::editor()
        ->setUnquotedName('locations')
        ->setColumns(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setAutoincrement(true)
                ->create(),
            Column::editor()
                ->setUnquotedName('position')
                ->setTypeName(Types::GEOMETRY)
                ->setGeometryType('POINT')
                ->setSrid(4326)
                ->create(),
        )
        ->create();

On PostgreSQL this produces a ``position geometry(point,4326)`` column; on MySQL/MariaDB
it produces ``position POINT``.

Writing and reading values
--------------------------

When writing, pass a ``Geometry`` instance and bind it with the spatial type. The type
wraps the value in the platform's GeoJSON construction function:

.. code-block:: php

    <?php

    use Doctrine\DBAL\Types\Geometry;
    use Doctrine\DBAL\Types\Types;

    $connection->insert('locations', [
        'position' => Geometry::fromGeoJSON('{"type":"Point","coordinates":[-122.4194,37.7749]}'),
    ], [
        'position' => Types::GEOMETRY,
    ]);

Because the database stores spatial data in an internal binary format, a plain
``SELECT`` of the column does not return GeoJSON. Read the column back through the
platform's GeoJSON expression so that it can be converted to a ``Geometry`` instance:

.. code-block:: php

    <?php

    $platform = $connection->getDatabasePlatform();

    $rows = $connection->createQueryBuilder()
        ->select($platform->getGeometryAsGeoJSONSQL('position') . ' AS position')
        ->from('locations')
        ->executeQuery()
        ->fetchAllAssociative();

Platform support
----------------

    +----------------+-------------------------------+------------------------------------+
    | Platform       | geometry                      | geography                          |
    +================+===============================+====================================+
    | **PostgreSQL** | Yes (requires PostGIS)        | Yes (requires PostGIS)             |
    +----------------+-------------------------------+------------------------------------+
    | **MySQL**      | Yes (8.0+ for SRID in column) | No                                 |
    +----------------+-------------------------------+------------------------------------+
    | **MariaDB**    | Yes (10.2.4+)                 | No                                 |
    +----------------+-------------------------------+------------------------------------+
    | **SQLite**     | No                            | No                                 |
    +----------------+-------------------------------+------------------------------------+
    | **Oracle**     | No                            | No                                 |
    +----------------+-------------------------------+------------------------------------+
    | **DB2**        | No                            | No                                 |
    +----------------+-------------------------------+------------------------------------+
    | **SQL Server** | No                            | No                                 |
    +----------------+-------------------------------+------------------------------------+

- **PostgreSQL / PostGIS** supports both ``geometry`` and ``geography``. Values are read
  and written via ``ST_GeomFromGeoJSON(?)`` and ``ST_AsGeoJSON(?, 15, 2)``. A
  ``geography`` column casts the constructed value to ``::geography`` and defaults its
  SRID to ``4326`` when none is given.
- **MySQL 8.0+ / MariaDB 10.2.4+** supports ``geometry`` only, using the same
  ``ST_GeomFromGeoJSON`` / ``ST_AsGeoJSON`` functions. The column declaration emits the
  uppercased ``geometryType`` (for example ``POINT``, defaulting to ``GEOMETRY``).
  Declaring an SRID in the column definition requires MySQL 8.0+.
- On **SQLite, Oracle, DB2 and SQL Server** spatial types are not supported.

.. note::

    Using ``geography`` on MySQL or MariaDB, or using either spatial type on a platform
    that does not support it, throws
    ``Doctrine\DBAL\Platforms\Exception\NotSupported``.

Spatial indexes
---------------

A spatial index is declared by setting the index type to
``Doctrine\DBAL\Schema\Index\IndexType::SPATIAL``:

.. code-block:: php

    <?php

    use Doctrine\DBAL\Schema\Index;

    $index = Index::editor()
        ->setUnquotedName('position_idx')
        ->setType(Index\IndexType::SPATIAL)
        ->setUnquotedColumnNames('position')
        ->create();

PostgreSQL emits ``CREATE INDEX … USING GIST (…)``; MySQL and MariaDB emit
``CREATE SPATIAL INDEX …``. Schema introspection maps the access method back to
``IndexType::SPATIAL``, so spatial indexes round-trip through the ``SchemaManager``.
