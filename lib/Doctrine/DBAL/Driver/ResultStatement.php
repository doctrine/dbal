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

namespace Doctrine\DBAL\Driver;

use PDO;

/**
 * Interface for the reading part of a prepare statement only.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
interface ResultStatement extends \Traversable
{
    /**
     * Closes the cursor, enabling the statement to be executed again.
     *
     * @return boolean              Returns TRUE on success or FALSE on failure.
     */
    function closeCursor();


    /**
     * columnCount
     * Returns the number of columns in the result set
     *
     * @return integer              Returns the number of columns in the result set represented
     *                              by the PDOStatement object. If there is no result set,
     *                              this method should return 0.
     */
    function columnCount();

    /**
     * setFetchMode
     * Set the fetch mode to use while iterating this statement.
     *
     * @param integer $fetchStyle
     */
    public function setFetchMode($fetchStyle);

    /**
     * fetch
     *
     * @see Query::HYDRATE_* constants
     * @param integer $fetchStyle           Controls how the next row will be returned to the caller.
     *                                      This value must be one of the Query::HYDRATE_* constants,
     *                                      defaulting to Query::HYDRATE_BOTH
     *
     * @param integer $cursorOrientation    For a PDOStatement object representing a scrollable cursor,
     *                                      this value determines which row will be returned to the caller.
     *                                      This value must be one of the Query::HYDRATE_ORI_* constants, defaulting to
     *                                      Query::HYDRATE_ORI_NEXT. To request a scrollable cursor for your
     *                                      PDOStatement object,
     *                                      you must set the PDO::ATTR_CURSOR attribute to Doctrine::CURSOR_SCROLL when you
     *                                      prepare the SQL statement with Doctrine_Adapter_Interface->prepare().
     *
     * @param integer $cursorOffset         For a PDOStatement object representing a scrollable cursor for which the
     *                                      $cursorOrientation parameter is set to Query::HYDRATE_ORI_ABS, this value specifies
     *                                      the absolute number of the row in the result set that shall be fetched.
     *
     *                                      For a PDOStatement object representing a scrollable cursor for
     *                                      which the $cursorOrientation parameter is set to Query::HYDRATE_ORI_REL, this value
     *                                      specifies the row to fetch relative to the cursor position before
     *                                      PDOStatement->fetch() was called.
     *
     * @return mixed
     */
    function fetch($fetchStyle = PDO::FETCH_BOTH);

    /**
     * Returns an array containing all of the result set rows
     *
     * @param integer $fetchStyle           Controls how the next row will be returned to the caller.
     *                                      This value must be one of the Query::HYDRATE_* constants,
     *                                      defaulting to Query::HYDRATE_BOTH
     *
     * @param integer $columnIndex          Returns the indicated 0-indexed column when the value of $fetchStyle is
     *                                      Query::HYDRATE_COLUMN. Defaults to 0.
     *
     * @return array
     */
    function fetchAll($fetchStyle = PDO::FETCH_BOTH);

    /**
     * fetchColumn
     * Returns a single column from the next row of a
     * result set or FALSE if there are no more rows.
     *
     * @param integer $columnIndex          0-indexed number of the column you wish to retrieve from the row. If no
     *                                      value is supplied, PDOStatement->fetchColumn()
     *                                      fetches the first column.
     *
     * @return string                       returns a single column in the next row of a result set.
     */
    function fetchColumn($columnIndex = 0);
}

