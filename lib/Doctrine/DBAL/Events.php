<?php

namespace Doctrine\DBAL;

/**
 * Container for all DBAL events.
 *
 * This class cannot be instantiated.
 *
 * @author Roman Borschel <roman@code-factory.org>
 * @since 2.0
 */
final class Events
{
    /**
     * Private constructor. This class cannot be instantiated.
     */
    private function __construct()
    {
    }

    const postConnect = 'postConnect';

    const onSchemaCreateTable             = 'onSchemaCreateTable';
    const onSchemaCreateTableColumn       = 'onSchemaCreateTableColumn';
    const onSchemaDropTable               = 'onSchemaDropTable';
    const onSchemaAlterTable              = 'onSchemaAlterTable';
    const onSchemaAlterTableAddColumn     = 'onSchemaAlterTableAddColumn';
    const onSchemaAlterTableRemoveColumn  = 'onSchemaAlterTableRemoveColumn';
    const onSchemaAlterTableChangeColumn  = 'onSchemaAlterTableChangeColumn';
    const onSchemaAlterTableRenameColumn  = 'onSchemaAlterTableRenameColumn';
    const onSchemaColumnDefinition        = 'onSchemaColumnDefinition';
    const onSchemaIndexDefinition         = 'onSchemaIndexDefinition';
}
