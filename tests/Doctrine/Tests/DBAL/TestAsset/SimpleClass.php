<?php

namespace Doctrine\Tests\DBAL\TestAsset;

class SimpleClass
{
    /** @var mixed */
    private $data;

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData($data) : void
    {
        $this->data = $data;
    }
}
