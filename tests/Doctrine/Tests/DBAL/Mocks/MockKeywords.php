<?php

namespace Doctrine\Tests\DBAL\Mocks;

use Doctrine\DBAL\Platforms\Keywords\KeywordList;

class MockKeywords extends KeywordList
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'Mock';
    }

    /**
     * {@inheritdoc}
     */
    protected function getKeywords()
    {
        return array(
            'ALTER',
            'CREATE',
            'DELETE',
            'DROP',
            'INSERT',
            'SELECT',
            'UPDATE',
        );
    }
}
