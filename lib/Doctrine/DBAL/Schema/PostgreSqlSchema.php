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

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Visitor\CreateSchemaSqlCollector;
use Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector;
use Doctrine\DBAL\Schema\Visitor\Visitor;

/**
 * Schema class extended for postgresql.
 * Supports search_path
 * Object representation of a database schema
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @author  Thomas Warwaris <code@warwaris.at>
 */
class PostgreSqlSchema extends Schema
{	
    /**
     * (database schema) search_path from the DB
     *
     * @var array
     */
    private $search_path_array;

    /**
     * set search_path_array from string
     */
	protected function setSearchPathArray($search_path)
    {
        $this->search_path_array = array();
        if ($search_path) {
            $tmp = explode(",",$search_path);
            foreach ($tmp as $dbSchema)
            {
                array_push($this->search_path_array, trim($dbSchema));
            }
        }
        return;
    }
    
    /**
     * set search path for this schema
     *
     * @param string $search_path The psql schema search path.
     */
    public function setSearchPath($search_path)
    {
        $this->setSearchPathArray( $search_path);
        return;
    }
    
    /**
     * search a DB object in the list of objects (tables or sequences)
     *
     * @param string $tableName Name of the object in the form schema.name or name.
     * @param array $hayStack List of known objects
     * @return object $result Returns the object
     */     
    protected function searchObject($tableName,$hayStack)
    {
        $tableName = strtolower($tableName);		
        $tableData = $this->splitNameToParts($tableName);
        if ( $tableData['schema'] ) {
            if (array_key_exists($tableName,$hayStack)) {
                return $hayStack[$tableName];
            }
        } else {
            foreach($this->search_path_array as $dbSchema) {
                $fullName = $dbSchema.".".$tableData['name'];
                if (array_key_exists($fullName,$hayStack)) {
                    return $hayStack[$fullName];
                }	
            }
        }
        return false;
    }
   
    /**
     * check if a table exists by name
     *
     * @param string $tableName
     * @return bool
     */ 	 	
    public function hasTable($tableName)
    {
        $table = $this->searchObject($tableName,$this->_tables);
        if ($table !== false){
            return true;
        }
        return false;
    }
    
    /**
     * return a table by name
     *
     * @param string $tableName
     * @return Table
     */ 	 	
    public function getTable($tableName)
    {
        $table = $this->searchObject($tableName,$this->_tables);		
        if ($table !== false) {
            return $table;
        }
        throw SchemaException::tableDoesNotExist($tableName);
    }

    /**
     * check if a sequence exists by name
     * 
     * @param string $sequenceName
     * @return bool
     */ 	 	
    public function hasSequence($sequenceName)
    {
        $sequence = $this->searchObject($sequenceName,$this->_sequences);
        if ($sequence !== false) {
            return true;
        }
        return false;
    }
	
    /**
     * return a sequence by name
     * @param string $sequenceName
     * @return Sequence
     */ 
    public function getSequence($sequenceName)
    {
        $sequence = $this->searchObject($sequenceName,$this->_sequences);		
        if ($sequence !== false) {
            return $sequence;
        }
        throw SchemaException::tableDoesNotExist($sequenceName);
    }

    /**
     * split a DB object name into schema and name part on the first dot
     *
     * @param string $name
     * @return array $return array returning the schema (or false) in ['schema'] and the name in ['name']
     */ 
    protected function splitNameToParts($name)
    {
        $r = array( 'schema' => false, 'name' => $name );
        $dotPos = stripos($name,'.');
        if ($dotPos) {
            $r['schema'] = substr($name,0,$dotPos);
            $r['name'] = substr($name,$dotPos+1);
        }
        return $r;	
    }        
}
