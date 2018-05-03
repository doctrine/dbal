<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Platforms\Keywords;

use function array_merge;

/**
 * MySQL 8.0 reserved keywords list.
 *
 * @link   www.doctrine-project.org
 */
class MySQL80Keywords extends MySQL57Keywords
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'MySQL80';
    }

    /**
     * {@inheritdoc}
     *
     * @link https://dev.mysql.com/doc/refman/8.0/en/keywords.html
     */
    protected function getKeywords()
    {
        $keywords = parent::getKeywords();

        $keywords = array_merge($keywords, [
            'ADMIN',
            'CUBE',
            'CUME_DIST',
            'DENSE_RANK',
            'EMPTY',
            'EXCEPT',
            'FIRST_VALUE',
            'FUNCTION',
            'GROUPING',
            'GROUPS',
            'JSON_TABLE',
            'LAG',
            'LAST_VALUE',
            'LEAD',
            'NTH_VALUE',
            'NTILE',
            'OF',
            'OVER',
            'PERCENT_RANK',
            'PERSIST',
            'PERSIST_ONLY',
            'RANK',
            'RECURSIVE',
            'ROW',
            'ROWS',
            'ROW_NUMBER',
            'SYSTEM',
            'WINDOW',
        ]);

        return $keywords;
    }
}
