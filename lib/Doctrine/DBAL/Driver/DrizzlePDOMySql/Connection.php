<?php

namespace Doctrine\DBAL\Driver\DrizzlePDOMySql;

use Doctrine\DBAL\ParameterType;

/**
 * @author Kim Hemsø Rasmussen <kimhemsoe@gmail.com>
 */
class Connection extends \Doctrine\DBAL\Driver\PDOConnection
{
    /**
     * {@inheritdoc}
     */
    public function quote($value, $type = ParameterType::STRING)
    {
        if ($type === ParameterType::BOOLEAN) {
            if ($value) {
                return 'true';
            }

            return 'false';
        }

        return parent::quote($value, $type);
    }
}
