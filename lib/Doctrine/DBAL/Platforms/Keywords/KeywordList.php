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
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */


namespace Doctrine\DBAL\Platforms\Keywords;

/**
 * Abstract interface for a SQL reserved keyword dictionary.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       2.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 */
abstract class KeywordList
{
    private $keywords = null;

    /**
     * Check if the given word is a keyword of this dialect/vendor platform.
     *
     * @param  string $word
     * @return bool
     */
    public function isKeyword($word)
    {
        if ($this->keywords === null) {
            $this->initializeKeywords();
        }

        return isset($this->keywords[strtoupper($word)]);
    }

    protected function initializeKeywords()
    {
        $this->keywords = array_flip(array_map('strtoupper', $this->getKeywords()));
    }

    abstract protected function getKeywords();

    /**
     * Name of this keyword list.
     *
     * @return string
     */
    abstract public function getName();
}
