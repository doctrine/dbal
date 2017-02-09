<?php

namespace Doctrine\DBAL\Driver;

/**
 * Last insert ID container.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 */
final class LastInsertId
{
    /**
     * @var string
     */
    private $value = '0';

    /**
     * @return string
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function set($value)
    {
        // The last insert ID is reset to "0" in certain situations by some implementations,
        // therefore we keep the previously set insert ID locally.
        if ('0' !== $value) {
            $this->value = $value;
        }
    }
}
