<?php

namespace Doctrine\DBAL\Schema\Visitor;

/**
 * Visitor that can visit schema namespaces.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
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
