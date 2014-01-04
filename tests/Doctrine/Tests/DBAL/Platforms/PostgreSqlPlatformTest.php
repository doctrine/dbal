<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\PostgreSqlPlatform;

class PostgreSqlPlatformTest extends AbstractPostgreSqlPlatformTestCase
{
    public function createPlatform()
    {
        return new PostgreSqlPlatform;
    }
    
    public function getGenerateDatabaseSql(){
        $sql = 'CREATE DATABASE foobar';
    }
    
    public function getGenerateRoleSql(){
        $sql = 'CREATE ROLE role_test LOGIN
  ENCRYPTED PASSWORD 'md53175bce1d3201d16594cebf9d7eb3f9d'
  SUPERUSER INHERIT CREATEDB CREATEROLE REPLICATION;
ALTER ROLE role_test IN DATABASE foobar
  SET search_path = "$user", public, access, rrhh;';
        return $sql;
    }
    
    public function getGenerateTablesSql()
    {
        $sql = 'CREATE TABLE state (id SERIAL NOT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id));'.
               'CREATE TABLE access.user (id SERIAL NOT NULL, user VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id));'.
               'CREATE TABLE rrhh.personal (id SERIAL NOT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id));';
               
        return $sql;
    }
    
    

    public function testForGetListTableWithSchemaIndexesSQL()
    {
        $table = 'state';
        $database = 'foobar';
        
        $this->assertEquals("SELECT quote_ident(relname) as relname, pg_index.indisunique, pg_index.indisprimary,
                       pg_index.indkey, pg_index.indrelid
                 FROM pg_class, pg_index
                 WHERE oid IN (
                    SELECT indexrelid
                    FROM pg_index si, pg_class sc, pg_namespace sn
                    WHERE sc.relname = 'state' AND sn.nspname = ANY(string_to_array((select replace(replace(setting,'\"\$user\"',user),' ','') from pg_catalog.pg_settings where name = 'search_path'),','))  AND sc.oid=si.indrelid AND sc.relnamespace = sn.oid
                 ) AND pg_index.indexrelid = oid", $this->_patform->getListTableForeignKeysSQL($table, $database));
    }
    
    public function testForGetTableWithSchemaWhereClause(){
        
        $table = 'rrhh.personal';
        $classAlias = 'sc';
        $namespaceAlias = 'sn';
        
        $this->assertEquals("sc.relname = 'personal' AND sn.nspname = 'rrhh'", $this->_patform->getTableWhereClause($table, $classAlias, $namespaceAlias));
        
    }
    
    public function testForGetTableWithPublicSchemaWhereClause(){
        
        $table = 'state';
        $classAlias = 'sc';
        $namespaceAlias = 'sn';
        
        $this->assertEquals("sc.relname = 'state' AND sn.nspname = ANY(string_to_array((select replace(replace(setting,'\"\$user\"',user),' ','') from pg_catalog.pg_settings where name = 'search_path'),','))", $this->_patform->getTableWhereClause($table, $classAlias, $namespaceAlias));
        
    }

    
}
