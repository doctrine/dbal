<?php

namespace Doctrine\DBAL\Schema;

class SchemaException extends \Doctrine\DBAL\DBALException
{
    const TABLE_DOESNT_EXIST = 10;
    const TABLE_ALREADY_EXISTS = 20;
    const COLUMN_DOESNT_EXIST = 30;
    const COLUMN_ALREADY_EXISTS = 40;
    const INDEX_DOESNT_EXIST = 50;
    const INDEX_ALREADY_EXISTS = 60;
    const SEQUENCE_DOENST_EXIST = 70;
    const SEQUENCE_ALREADY_EXISTS = 80;
    const INDEX_INVALID_NAME = 90;
    const FOREIGNKEY_DOESNT_EXIST = 100;
    const CONSTRAINT_DOESNT_EXIST = 110;
    const NAMESPACE_ALREADY_EXISTS = 120;
}
