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

/**
 * MySQL 5.7.8 reserved keywords list.
 *
 * @author İsmail BASKIN <ismailbaskin1@gmail.com>
 * @link   www.doctrine-project.org
 * @since  2.6
 */
class MySQL578Keywords extends MySQL57Keywords
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'MySQL578';
    }

    /**
     * {@inheritdoc}
     *
     * @link https://dev.mysql.com/doc/refman/5.7/en/keywords.html
     */
    protected function getKeywords()
    {
        $parentKeywords = array_diff(parent::getKeywords(), array(
            'NONBLOCKING',
        ));

        return array_merge($parentKeywords, array(
            'GENERATED',
            'OPTIMIZER_COSTS',
            'STORED',
            'VIRTUAL'
        ));
    }
}
