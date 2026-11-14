<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

/**
 * Default built-in types provided by Doctrine DBAL.
 */
final class Types
{
    public const ASCII_STRING           = 'ascii_string';
    public const BIGINT                 = 'bigint';
    public const BINARY                 = 'binary';
    public const BLOB                   = 'blob';
    public const BOOLEAN                = 'boolean';
    public const DATE_IMMUTABLE         = 'date_immutable';
    public const DATEINTERVAL           = 'dateinterval';
    public const DATETIME_IMMUTABLE     = 'datetime_immutable';
    public const DATETIME_UTC_IMMUTABLE = 'datetime_utc_immutable';
    public const DATETIMETZ_IMMUTABLE   = 'datetimetz_immutable';
    public const DECIMAL                = 'decimal';
    public const NUMBER                 = 'number';
    public const FLOAT                  = 'float';
    public const ENUM                   = 'enum';
    public const GUID                   = 'guid';
    public const INTEGER                = 'integer';
    public const JSON                   = 'json';
    public const JSON_OBJECT            = 'json_object';
    public const JSONB                  = 'jsonb';
    public const JSONB_OBJECT           = 'jsonb_object';
    public const SMALLFLOAT             = 'smallfloat';
    public const SMALLINT               = 'smallint';
    public const STRING                 = 'string';
    public const TEXT                   = 'text';
    public const TIME_IMMUTABLE         = 'time_immutable';

    /** @deprecated Use {@see Types::DATE_IMMUTABLE} instead. */
    public const DATE_MUTABLE = 'date';

    /** @deprecated Use {@see Types::DATETIME_IMMUTABLE} instead. */
    public const DATETIME_MUTABLE = 'datetime';

    /** @deprecated Use {@see Types::DATETIME_UTC_IMMUTABLE} instead. */
    public const DATETIME_UTC_MUTABLE = 'datetime_utc';

    /** @deprecated Use {@see Types::DATETIMETZ_IMMUTABLE} instead. */
    public const DATETIMETZ_MUTABLE = 'datetimetz';

    /** @deprecated Use {@see Types::JSON} instead. */
    public const SIMPLE_ARRAY = 'simple_array';

    /** @deprecated Use {@see Types::TIME_IMMUTABLE} instead. */
    public const TIME_MUTABLE = 'time';

    /** @codeCoverageIgnore */
    private function __construct()
    {
    }
}
