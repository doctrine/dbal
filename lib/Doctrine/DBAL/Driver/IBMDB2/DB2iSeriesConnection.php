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

namespace Doctrine\DBAL\Driver\IBMDB2;

/**
 * IBMi Db2 Connection.
 * More documentation about iSeries schema at https://www-01.ibm.com/support/knowledgecenter/ssw_ibm_i_72/db2/rbafzcatsqlcolumns.htm
 *
 * @author Cassiano Vailati <c.vailati@esconsulting.it>
 */
class DB2iSeriesConnection extends DB2Connection
{
    protected $driverOptions = array();

    /**
     * @param array  $params
     * @param string $username
     * @param string $password
     * @param array  $driverOptions
     *
     * @throws \Doctrine\DBAL\Driver\IBMDB2\DB2Exception
     */
    public function __construct(array $params, $username, $password, $driverOptions = array())
    {
        $this->driverOptions = $driverOptions;
        parent::__construct($params, $username, $password, $driverOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        $sql = 'SELECT IDENTITY_VAL_LOCAL() AS VAL FROM QSYS2'.$this->getSchemaSeparatorSymbol().'QSQPTABL';
        $stmt = $this->prepare($sql);
        $stmt->execute();

        $res = $stmt->fetch();

        return $res['VAL'];
    }

    /**
     * Returns the appropriate schema separation symbol for i5 systems.
     * Other systems can hardcode '.' but i5 may need '.' or  '/' depending on the naming mode.
     *
     * @return string
     */
    public function getSchemaSeparatorSymbol()
    {
        // if "i5 naming" is on, use '/' to separate schema and table. Otherwise use '.'
        if (array_key_exists('i5_naming', $this->driverOptions) && $this->driverOptions['i5_naming']) {

            // "i5 naming" mode requires a slash
            $schemaSepSymbol = '/';

        } else {
            // SQL naming requires a dot
            $schemaSepSymbol = '.';
        }

        return $schemaSepSymbol;
    }
}
