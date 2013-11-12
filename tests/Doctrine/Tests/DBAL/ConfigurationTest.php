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

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Configuration;
use Doctrine\Tests\DbalTestCase;

require_once __DIR__ . '/../TestInit.php';

/**
 * Unit tests for the configuration container.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 */
class ConfigurationTest extends DbalTestCase
{
    /**
     * The configuration container instance under test.
     *
     * @var \Doctrine\DBAL\Configuration
     */
    protected $config;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->config = new Configuration();
    }

    /**
     * Tests that the default auto-commit mode for connections can be retrieved from the configuration container.
     *
     * @group DBAL-81
     */
    public function testReturnsDefaultConnectionAutoCommitMode()
    {
        $this->assertTrue($this->config->getAutoCommit());
    }

    /**
     * Tests that the default auto-commit mode for connections can be set in the configuration container.
     *
     * @group DBAL-81
     */
    public function testSetsDefaultConnectionAutoCommitMode()
    {
        $this->config->setAutoCommit(false);

        $this->assertFalse($this->config->getAutoCommit());

        $this->config->setAutoCommit(0);

        $this->assertFalse($this->config->getAutoCommit());
    }
}
