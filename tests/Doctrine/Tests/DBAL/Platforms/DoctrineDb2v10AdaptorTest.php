<?php


class DoctrineDb2v10AdaptorTest extends PHPUnit_Framework_TestCase {
    // The only difference this query has compared to the original is that
    // I cast the column named "DEFAULT" to varchar(254). I don't feel this will
    // affect the ability of the DISTINCT clause from doing it's job properly, because
    // I don't feel we would ever have any rows which only differ in the value of that column.
    // I had to do this to get it to run on db2 v10.
    private $oldSql = "
        SELECT DISTINCT
          c.tabschema,
          c.tabname,
          c.colname,
          c.colno,
          c.typename,
          CAST(c.default AS VARCHAR(254)) AS DEFAULT,
          c.nulls,
          c.length,
          c.scale,
          c.identity,
          tc.type                         AS tabconsttype,
          k.colseq,
          CASE
          WHEN c.generated = 'D' THEN 1
          ELSE 0
          END                             AS autoincrement
        FROM syscat.columns c
          LEFT JOIN (syscat.keycoluse k JOIN syscat.tabconst tc
              ON (k.tabschema = tc.tabschema
                  AND k.tabname = tc.tabname
                  AND tc.type = 'P'))
            ON (c.tabschema = k.tabschema
                AND c.tabname = k.tabname
                AND c.colname = k.colname)
        WHERE UPPER(c.tabname) = UPPER(?)
        ORDER BY c.colno
";

    // This query wraps the original query into a subquery, but omits the column named "DEFAULT".
    // This way the distinct clause can operate on the remaining columns without error, and then we join the
    // results back to the syscat.columns table in order to attach the values of the column we omitted.
    private $newSql = "
        SELECT
          cols.default,
          subq.*
        FROM (
               SELECT DISTINCT
                 c.tabschema,
                 c.tabname,
                 c.colname,
                 c.colno,
                 c.typename,
                 c.nulls,
                 c.length,
                 c.scale,
                 c.identity,
                 tc.type AS tabconsttype,
                 k.colseq,
                 CASE
                 WHEN c.generated = 'D' THEN 1
                 ELSE 0
                 END     AS autoincrement
               FROM syscat.columns c
                 LEFT JOIN (syscat.keycoluse k JOIN syscat.tabconst tc
                     ON (k.tabschema = tc.tabschema
                         AND k.tabname = tc.tabname
                         AND tc.type = 'P'))
                   ON (c.tabschema = k.tabschema
                       AND c.tabname = k.tabname
                       AND c.colname = k.colname)
               WHERE UPPER(c.tabname) = UPPER(?)
               ORDER BY c.colno
             ) subq
          JOIN syscat.columns cols
            ON subq.tabschema = cols.tabschema
               AND subq.tabname = cols.tabname
               AND subq.colno = cols.colno
        ORDER BY subq.colno
";

    private $conn;

    function setUp()
    {
        $config = require '../../config/autoload/global.php';
        extract($config['doctrine']['connection']['orm_default']['params']);
        $this->conn = db2_connect("DRIVER={IBM DB2 ODBC DRIVER};HOSTNAME=$host;PORT=$port;DATABASE=$dbname;PROTOCOL=TCPIP;UID=$user;PWD=$password;", $user, $password);
    }

    function testQueriesProduceIdenticalRowsForAllTables()
    {
        foreach ($this->getAllTables() as $tbl) {
            $this->checkRowsMatchForTable($tbl);
        }
    }

    private function checkRowsMatchForTable($tbl)
    {
        // run each query and fetch the rows they produce
        $oldRows = $this->fetchall($this->oldSql, [$tbl], $this->conn);
        $newRows = $this->fetchall($this->newSql, [$tbl], $this->conn);

        // compare the num of rows
        $this->assertEquals(count($oldRows), count($newRows));

        // sort them so that I can compare the correct rows
        $cmp = function($a, $b) {
            $fmt = "%s,%s,%s";
            return strcmp(
                sprintf($fmt, $a['TABSCHEMA'], $a['TABNAME'], $a['COLNO']),
                sprintf($fmt, $b['TABSCHEMA'], $b['TABNAME'], $b['COLNO'])
            );
        };
        usort($oldRows, $cmp);
        usort($newRows, $cmp);

        // compare the keys and values of row n in the old to row n in the new.
        // this doesn't consider the order of the columns in the row, which shouldn't matter.
        foreach ($oldRows as $k => $oldRow) {
            $diff = array_diff_assoc($oldRow, $newRows[$k]);
            if ($diff) {
                var_dump($k, $diff);
                print_r($oldRow);
                print_r($newRows[$k]);
                print_r($oldRows);
                print_r($newRows);
            }
            $this->assertEmpty($diff);
        }
    }

    private function getAllTables()
    {
        $sql = "
        select distinct TABNAME
        from syscat.columns
        ";

        return array_map(function($row) {
            return $row['TABNAME'];
        }, $this->fetchAll($sql, [], $this->conn));
    }

    private function fetchAll($sql, $args, $conn)
    {
        $ret = [];
        $stmt = db2_prepare($conn, $sql);
        db2_execute($stmt, $args);
        while ($row = db2_fetch_assoc($stmt)) {
            $ret[] = $row;
        }
        return $ret;
    }
}
 