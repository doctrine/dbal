<?php

namespace Doctrine\Tests\DBAL\Mocks;

use Doctrine\DBAL\Platforms\Keywords\KeywordList;

class KeywordsMock extends KeywordList
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'mock';
    }

    /**
     * {@inheritdoc}
     */
    protected function getKeywords()
    {
        return array(
            'RESERVED'
        );
    }
}
