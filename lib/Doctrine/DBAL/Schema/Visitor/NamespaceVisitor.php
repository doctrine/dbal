<?php

namespace Doctrine\DBAL\Schema\Visitor;

/**
 * Visitor that can visit schema namespaces.
 *
 * @link   www.doctrine-project.org
 */
interface NamespaceVisitor
{
    /**
     * Accepts a schema namespace name.
     *
     * @param string $namespaceName The schema namespace name to accept.
     */
    public function acceptNamespace($namespaceName);
}
