<?php

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

/**
 * Last Id Data Container.
 */
class LastInsertId
{
    /** @var int */
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
