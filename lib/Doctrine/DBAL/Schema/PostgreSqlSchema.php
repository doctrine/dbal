<?php
/*
*  $Id$
*
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
    protected function setSearchPathArray($search_path){
        if ($search_path){
            $this->search_path_array = explode(",",$search_path);
        }else{
            $this->search_path_array = array();
        }
        return;
    }
    
    /**
     * set search path for this schema
     *
     * @param string $search_path The psql schema search path.
     */
    public function setSearchPath($search_path){
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
    protected function searchObject($tableName,$hayStack){
      $tableName = strtolower($tableName);		
      $tableData = $this->splitNameToParts($tableName);
      if ( $tableData['schema'] ){
         if (array_key_exists($tableName,$hayStack)){
            return $hayStack[$tableName];
         }
      }else{
         foreach($this->search_path_array as $dbSchema){
            $fullName = $dbSchema.".".$tableData['name'];
            if (array_key_exists($fullName,$hayStack)){
               return $hayStack[$fullName];
            }	
         }
      }
      return false;
   }	 	
   public function hasTable($tableName)
      {
         $table = $this->searchObject($tableName,$this->_tables);
         if ($table !== false){
            return 1;
         }
      return 0;
   }
   public function getTable($tableName)
   {
      $table = $this->searchObject($tableName,$this->_tables);		
      if ($table !== false){
         return $table;
      }
      throw SchemaException::tableDoesNotExist($tableName);
   }

	
   public function getTableBySchemaAndName($tableName){
      if (!isset($this->_tables[$tableName])) {
         return false;
      }
      return $this->tables[$table];
   }
  
   public function hasSequence($sequenceName)
   {
      $sequence = $this->searchObject($sequenceName,$this->_sequences);
         if ($sequence !== false){
            return 1;
         }
      return 0;
   }
	
   public function getSequence($sequenceName)
   {
      $sequence = $this->searchObject($sequenceName,$this->_sequences);		
      if ($sequence !== false){
         return $sequence;
      }
      throw SchemaException::tableDoesNotExist($sequenceName);
   }

   private function splitNameToParts($name){
      $r = array( 'schema' => false, 'name' => $name );
      $dotPos = stripos($name,'.');
      if ($dotPos){
         $r['schema'] = substr($name,0,$dotPos);
         $r['name'] = substr($name,$dotPos+1);
      }
      return $r;	
   }    
}
