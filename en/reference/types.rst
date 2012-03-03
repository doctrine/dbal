Types
=====

Besides abstraction of SQL one needs a translation between database
and PHP data-types to implement database independent applications.
Doctrine 2 has a type translation system baked in that supports the
conversion from and to PHP values from any database platform,
as well as platform independent SQL generation for any Doctrine
Type.

Using the ORM you generally don't need to know about the Type
system. This is unless you want to make use of database vendor
specific database types not included in Doctrine 2. The following
PHP Types are abstracted across all the supported database
vendors:


-  Integer
-  SmallInt
-  BigInt
-  String (string with maximum length, for example 255)
-  Text (strings without maximum length)
-  Decimal (restricted floats, *NOTE* Only works with a setlocale()
   configuration that uses decimal points!)
-  Boolean
-  DateTime
-  Date (DateTime instance where only Y-m-d get persisted)
-  Time (DateTime instance where only H:i:s get persisted)
-  Array (serialized into a text field for all vendors by default)
-  Object (serialized into a text field for all vendors by default)
-  Float (*NOTE* Only works with a setlocale() configuration that
   uses decimal points!)

Types are flyweights. This means there is only ever one instance of
a type and it is not allowed to contain any state. Creation of type
instances is abstracted through a static get method
``Doctrine\DBAL\Types\Type::getType()``.

.. note::

    See the `Known Vendor Issue <./../known-vendor-issues>`_ section
    for details about the different handling of microseconds and
    timezones across all the different vendors.

.. warning::

    All Date types assume that you are exclusively using the default timezone
    set by `date_default_timezone_set() <http://docs.php.net/manual/en/function.date-default-timezone-set.php>`_
    or by the php.ini configuration ``date.timezone``.

    If you need specific timezone handling you have to handle this
    in your domain, converting all the values back and forth from UTC.

Detection of Database Types
---------------------------

When calling table inspection methods on your connections
``SchemaManager`` instance the retrieved database column types are
translated into Doctrine mapping types. Translation is necessary to
allow database abstraction and metadata comparisons for example for
Migrations or the ORM SchemaTool.

Each database platform has a default mapping of database types to
Doctrine types. You can inspect this mapping for platform of your
choice looking at the
``AbstractPlatform::initializeDoctrineTypeMappings()``
implementation.

If you want to change how Doctrine maps a database type to a
``Doctrine\DBAL\Types\Type`` instance you can use the
``AbstractPlatform::registerDoctrineTypeMapping($dbType, $doctrineType)``
method to add new database types or overwrite existing ones.

.. note::

    You can only map a database type to exactly one Doctrine type.
    Database vendors that allow to define custom types like PostgreSql
    can help to overcome this issue.


Custom Mapping Types
--------------------

Just redefining how database types are mapped to all the existing
Doctrine types is not at all that useful. You can define your own
Doctrine Mapping Types by extending ``Doctrine\DBAL\Types\Type``.
You are required to implement 4 different methods to get this
working.

See this example of how to implement a Money object in PostgreSQL.
For this we create the type in PostgreSQL as:

.. code-block:: sql

    CREATE DOMAIN MyMoney AS DECIMAL(18,3);

Now we implement our ``Doctrine\DBAL\Types\Type`` instance:

::

    <?php
    namespace My\Project\Types;
    
    use Doctrine\DBAL\Types\Type;
    use Doctrine\DBAL\Platforms\AbstractPlatform;
    
    /**
     * My custom datatype.
     */
    class MoneyType extends Type
    {
        const MONEY = 'money'; // modify to match your type name
    
        public function getSqlDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
        {
            return 'MyMoney';
        }
    
        public function convertToPHPValue($value, AbstractPlatform $platform)
        {
            return new Money($value);
        }
    
        public function convertToDatabaseValue($value, AbstractPlatform $platform)
        {
            return $value->toDecimal();
        }
    
        public function getName()
        {
            return self::MONEY;
        }
    }

The job of Doctrine-DBAL is to transform your type into SQL declaration. You can modify the SQL declaration Doctrine will produce. At first, you must to enable this feature by overriding the canRequireSQLConversion method: 

::

    <?php
    public function canRequireSQLConversion()
    {
        return true;
    }

Then you override the methods convertToPhpValueSQL and convertToDatabaseValueSQL : 

::

    <?php
    public function convertToPHPValueSQL($sqlExpr, $platform)
    {
        return 'MyMoneyFunction(\''.$sqlExpr.'\') ';
    }
    
    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform)
    {
        return 'MyFunction('.$sqlExpr.')';
    }


Now we have to register this type with the Doctrine Type system and
hook it into the database platform:

::

    <?php
    Type::addType('money', 'My\Project\Types\MoneyType');
    $conn->getDatabasePlatform()->registerDoctrineTypeMapping('MyMoney', 'money');

This would allow to use a money type in the ORM for example and
have Doctrine automatically convert it back and forth to the
database.


