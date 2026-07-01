<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

/**
 * Default built-in types provided by Doctrine DBAL.
 */
final class Types
{
    public const string ASCII_STRING         = 'ascii_string';
    public const string BIGINT               = 'bigint';
    public const string BINARY               = 'binary';
    public const string BLOB                 = 'blob';
    public const string BOOLEAN              = 'boolean';
    public const string DATE_MUTABLE         = 'date';
    public const string DATE_IMMUTABLE       = 'date_immutable';
    public const string DATEINTERVAL         = 'dateinterval';
    public const string DATETIME_MUTABLE     = 'datetime';
    public const string DATETIME_IMMUTABLE   = 'datetime_immutable';
    public const string DATETIMETZ_MUTABLE   = 'datetimetz';
    public const string DATETIMETZ_IMMUTABLE = 'datetimetz_immutable';
    public const string DECIMAL              = 'decimal';
    public const string NUMBER               = 'number';
    public const string FLOAT                = 'float';
    public const string ENUM                 = 'enum';
    public const string GUID                 = 'guid';
    public const string INTEGER              = 'integer';
    public const string JSON                 = 'json';
    public const string JSON_OBJECT          = 'json_object';
    public const string JSONB                = 'jsonb';
    public const string JSONB_OBJECT         = 'jsonb_object';
    public const string SMALLFLOAT           = 'smallfloat';
    public const string SMALLINT             = 'smallint';
    public const string STRING               = 'string';
    public const string TEXT                 = 'text';
    public const string TIME_MUTABLE         = 'time';
    public const string TIME_IMMUTABLE       = 'time_immutable';

    /** @codeCoverageIgnore */
    private function __construct()
    {
    }
}
