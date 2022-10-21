<?php

namespace Doctrine\DBAL;

/**
 * Container for all DBAL events.
 *
 * This class cannot be instantiated.
 */
final class Events
{
    /**
     * Private constructor. This class cannot be instantiated.
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    public const postConnect = 'postConnect';

    /** @deprecated */
    public const onSchemaCreateTable = 'onSchemaCreateTable';

    /** @deprecated */
    public const onSchemaCreateTableColumn = 'onSchemaCreateTableColumn';

    /** @deprecated */
    public const onSchemaDropTable = 'onSchemaDropTable';

    /** @deprecated */
    public const onSchemaAlterTable = 'onSchemaAlterTable';

    /** @deprecated */
    public const onSchemaAlterTableAddColumn = 'onSchemaAlterTableAddColumn';

    /** @deprecated */
    public const onSchemaAlterTableRemoveColumn = 'onSchemaAlterTableRemoveColumn';

    /** @deprecated */
    public const onSchemaAlterTableChangeColumn = 'onSchemaAlterTableChangeColumn';

    /** @deprecated */
    public const onSchemaAlterTableRenameColumn = 'onSchemaAlterTableRenameColumn';

    public const onSchemaColumnDefinition = 'onSchemaColumnDefinition';
    public const onSchemaIndexDefinition  = 'onSchemaIndexDefinition';
    public const onTransactionBegin       = 'onTransactionBegin';
    public const onTransactionCommit      = 'onTransactionCommit';
    public const onTransactionRollBack    = 'onTransactionRollBack';
}
