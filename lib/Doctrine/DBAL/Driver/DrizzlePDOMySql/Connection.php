<?php

namespace Doctrine\DBAL\Driver\DrizzlePDOMySql;

/**
 * @author Kim Hemsø Rasmussen <kimhemsoe@gmail.com>
 */
class Connection extends \Doctrine\DBAL\Driver\PDOConnection
{
    /**
     * {@inheritdoc}
     */
    public function quote($value, $type = \PDO::PARAM_STR)
    {
        if (\PDO::PARAM_BOOL === $type) {
            if ($value) {
                return 'true';
            } else {
                return 'false';
            }
        }

        return parent::quote($value, $type);
    }
}
