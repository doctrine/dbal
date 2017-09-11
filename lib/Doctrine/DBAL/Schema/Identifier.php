<?php

namespace Doctrine\DBAL\Schema;

/**
 * An abstraction class for an asset identifier.
 *
 * Wraps identifier names like column names in indexes / foreign keys
 * in an abstract class for proper quotation capabilities.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.4
 */
class Identifier extends AbstractAsset
{
    /**
     * Constructor.
     *
     * @param string $identifier Identifier name to wrap.
     * @param bool   $quote      Whether to force quoting the given identifier.
     */
    public function __construct($identifier, $quote = false)
    {
        $this->_setName($identifier);

        if ($quote && ! $this->_quoted) {
            $this->_setName('"' . $this->getName() . '"');
        }
    }
}
