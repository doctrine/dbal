<?php

namespace Doctrine\DBAL\Driver\SQLSrv;

/**
 * Last Id Data Container.
 *
 * @since 2.3
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class LastInsertId
{
    /**
     * @var int
     */
    private $id;

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}
