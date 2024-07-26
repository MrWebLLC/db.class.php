<?php
class db
{
    /**
     * The singleton instance of the class.
     *
     * @var self|null
     */
    private static $instance = NULL;
    /**
     * The database connection.
     *
     * @var mysqli|null
     */
    private $connection = NULL;
    /**
     * The log connection.
     *
     * @var mysqli|null
     */
    private $logConn;
    /**
     * The ID of the last inserted row.
     *
     * @var int|null
     */
    private $insertId;
    /**
     * The number of affected rows.
     *
     * @var int|null
     */
    private $affectedRows;
    /**
     * The error message.
     *
     * @var string|null
     */
    private $error;
    /**
     * The error code.
     *
     * @var int|null
     */
    private $errorCode;
    /**
     * The error message.
     *
     * @var string|null
     */
    private $errorMessage;
    /**
     * The stack trace of the error.
     *
     * @var string|null
     */
    private $stackTrace;
    /**
     * The result of the last executed query.
     *
     * @var mysqli_result|null
     */
    private $result;
    /**
     * The SQL query to be executed.
     *
     * @var string|null
     */
    private $sql;

    /**
     * The parameters passed to the query to be used in prepared statement.
     *
     * @var array|null
     */
    private $params;
    /**
     * Returns a singleton instance of the class.
     *
     * @return self The singleton instance of the class.
     */
    public static function getInstance()
    {
        if (!self::$instance) { // If no instance then make one
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor for the class.
     *
     * Establishes a connection to a MySQL database using the global $dbConfig variable.
     * If the connection fails, sets the error information and returns.
     * If the charset is not passed in $dbConfig, Sets the character set of the connection to 'utf8mb4'.
     *
     * @return void
     */
    private function __construct()
    {
        global $dbConfig;
        try {
            $this->connection = new mysqli($dbConfig->host, $dbConfig->username, $dbConfig->password, $dbConfig->name);
        } catch (mysqli_sql_exception $e) {
            $this->setError($e);
            return;
        }
        $charset = $dbConfig->charset ?? 'utf8mb4';
        $this->connection->set_charset($charset);
    }

    /**
     * Private clone method to prevent duplication of connection.
     *
     * This method is declared as private and is empty, which means it does not perform any operations.
     * It is used to prevent the creation of multiple instances of the class by making the class non-cloneable.
     *
     * @return void
     */
    private function __clone()
    {
    }
    /**
     * Sets the error code, error message, and stack trace of the exception.
     *
     * @param mysqli_sql_exception $e The exception object.
     * @return void
     */
    private function setError(mysqli_sql_exception $e): void
    {
        $this->errorCode = $e->getCode();
        $this->errorMessage = $e->getMessage();
        $this->stackTrace = $e->getTrace();
    }
    /**
     * Validates an SQL query based on its type.
     *
     * @param string $sql The SQL query to validate.
     * @param string $type The type of SQL query to validate against.
     * @return string|int|bool Returns the validated SQL query if it matches the specified Query command type,
     *  based on beginning of string. otherwise returns 0 for no match, and false for error.
     * Ex. if called from $this->select, sql must begin with select.
     */
    private function validateSql(string $sql, string $type): string|int|bool
    {
        $sql = trim($sql);
        $match = preg_match('/^\s*(' . $type . ')\s/i', $sql);

        return ($match) ? $sql : $match;
    }
    /**
     * Sets and returns the ID of the last inserted row and sets the number of affected rows.
     *
     * @return int The ID of the last inserted row.
     */
    private function returnInsertType(): int
    {
        $this->affectedRows = $this->connection->affected_rows;
        return $this->insertId = $this->connection->insert_id;
    }

    /**
     * Executes an SQL query with optional parameters and returns the result based on the query type.
     *
     * @param string $sql The SQL query to execute.
     * @param mixed|null $params The parameters to bind to the query (optional).
     * @param string $type The type of SQL query (insert, replace, delete, update, or default).
     * @throws mysqli_sql_exception If there is an error executing the query.
     * @return mixed The result of the query execution, depending on the query type:
     * - For insert and replace queries, returns the ID of the last inserted row.
     * - For delete and update queries, returns the number of affected rows.
     * - For default queries, returns the result set.
     */
    private function exec($sql, $params, $type)
    {
        try {
            $this->sql = $sql;
            if (is_null($params)) {
                $this->result = $this->connection->query($this->sql);
            } else {
                $this->params = $params;
                $this->result = $this->connection->execute_query($this->sql, $params);
            }
        } catch (mysqli_sql_exception $e) {
            $this->setError($e);
            return false;
        }

        $returnValue = match ($type) {
            'insert', 'replace' => $this->returnInsertType(),
            'delete', 'update'  => $this->affectedRows = $this->connection->affected_rows,
            default => $this->result
        };

        return $returnValue;
    }


    /**
     * Retrieves the version number of the database.
     *
     * @return string The version number of the database.
     */
    function getVersionNumber()
    {
        $dbType = $this->getval("SELECT version();");
        return preg_replace('/[^0-9.].*/', '', $dbType);
    }

    /**
     * Retrieves the type of the database.
     *
     * This function executes a SQL query to retrieve the version of the database.
     * It then checks if the version string contains the word 'MariaDB', and if so,
     * sets the type of the database to 'MariaDB'. Otherwise, it sets the type to 'MySQL'.
     *
     * @return string The type of the database ('MySQL' or 'MariaDB').
     */
    function getDatabaseType()
    {
        $dbType = $this->getval("SELECT version();");
        $type = 'MySQL';
        if (strpos($dbType, 'MariaDB') !== false) $type = 'MariaDB';
        return $type;
    }
    /**
     * Selects a database by name.
     *
     * @param string $dbname The name of the database to select.
     * @throws Exception If the database selection fails.
     * @return void
     */
    function selectDb($dbname)
    {
        if (!$this->connection->select_db($dbname))
            throw new Exception('Database selection failed: Cannot Connect to ' . $dbname);
    }
    /**
     * Creates a new table in the database by executing an SQL query with optional parameters.
     *
     * @param string $sql The SQL query to execute.
     * @return bool Returns true if the record was successfully created, false otherwise.
     */
    function create($sql)
    {
        $sql = $this->validateSql($sql, __FUNCTION__);
        if (!$sql) {
            $this->error = "Invalid SQL provided for the create command";
            return false;
        }
        $this->sql = $sql;
        $this->result = $this->exec($this->sql, NULL, __FUNCTION__);

        if ($this->error) {
            return false;
        }
        return true;
    }
    /**
     * Drops a record in the database by executing an SQL query with optional parameters.
     *
     * @param string $sql The SQL query to execute.
     * @return bool Returns true if the record was successfully dropped, false otherwise.
     */
    function drop($sql)
    {
        $sql = $this->validateSql($sql, __FUNCTION__);
        if (!$sql) {
            $this->error = "Invalid SQL provided for the drop command";
            return false;
        }
        $this->sql = $sql;
        $this->result = $this->exec($this->sql, NULL, __FUNCTION__);

        if ($this->error) {
            return false;
        }
        return true;
    }
    /**
     * Closes the connection to the database.
     *
     * @return bool Returns true if the connection was successfully closed, false otherwise.
     */
    function close()
    {
        return $this->connection->close();
    }

    /**
     * Executes a SELECT SQL query with optional parameters and returns the result set.
     *
     * @param string $sql The SQL query to execute.
     * @param array|null $params The parameters to bind to the query (optional).
     * @return mixed The result set of the SQL query.
     */
    function select($sql, $params = NULL)
    {
        // returns result set
        return $this->exec($sql, $params, __FUNCTION__);
    }
    /**
     * Inserts data into the database using the provided SQL statement and parameters.
     *
     * @param string $sql The SQL statement to execute.
     * @param array|null $params The parameters to bind to the SQL statement.
     * @return mixed The result of the database query.
     */
    function insert($sql, $params = NULL)
    {
        return $this->exec($sql, $params, __FUNCTION__);
    }
    /**
     * Updates data in the database using the provided SQL statement and parameters.
     *
     * @param string $sql The SQL statement to execute.
     * @param array|null $params The parameters to bind to the SQL statement (optional).
     * @return mixed The result of the database query.
     */
    function update($sql, $params = NULL)
    {
        return $this->exec($sql, $params, __FUNCTION__);
    }
    /**
     * Deletes data from the database using the provided SQL statement and parameters.
     *
     * @param string $sql The SQL statement to execute.
     * @param array|null $params The parameters to bind to the SQL statement (optional).
     * @return mixed The result of the database query.
     */
    function delete($sql, $params = NULL)
    {
        return $this->exec($sql, $params, __FUNCTION__);
    }

    /**
     * Retrieves an array of data from the given result set.
     *
     * @param mixed $res The result set to retrieve data from. If empty, the default result set will be used.
     * @param string $type The type of array to retrieve. Defaults to 'BOTH'.
     * @return array|null The array of data from the result set, or null if no data is available.
     */
    function getarray($res = '', $type = 'BOTH')
    {
        if ($res == '') $res = $this->result;
        return $res->fetch_array(constant('MYSQLI_' . $type));
    }
    /**
     * Retrieves an associative array of data from the given result set.
     *
     * @param mixed $res The result set to retrieve data from. If empty, the default result set will be used.
     * @return array|null The associative array of data from the result set, or null if no data is available.
     */
    function getassoc($res = '')
    {
        if ($res == '') $res = $this->result;
        return $res->fetch_assoc();
    }
    /**
     * Retrieves an object from the given result set.
     *
     * @param mixed $res The result set to retrieve data from. If empty, the default result set will be used.
     * @throws Exception If there is an error fetching the object.
     * @return mixed The object fetched from the result set, or null if no data is available.
     */
    function getobj($res = '')
    {
        if ($res == '') $res = $this->result;
        try {
            return $res->fetch_object();
        } catch (Exception $e) {
            $this->error = $e;
        }
    }
    /**
     * Returns the number of rows in the given result set. If no result set is provided,
     * it uses the default result set. If the result set is empty, it returns 0.
     *
     * @param mixed $res The result set to get the number of rows from. Defaults to the default result set.
     * @return int The number of rows in the result set, or 0 if the result set is empty.
     */
    function nr($res = '')
    {
        if ($res == '') $res = $this->result;
        return ($res) ? $res->num_rows : 0;
    }
    /**
     * Sets the internal pointer of the result set to the specified offset.
     *
     * @param mixed $res The result set to seek in.
     * @param int $offset The offset to seek to.
     * @return void
     */
    function seek($res, $offset)
    {
        $res->data_seek($offset);
    }
    /**
     * Retrieves the number of fields in the given result set.
     *
     * @param mixed $res The result set to retrieve the number of fields from. Defaults to the default result set.
     * @return int The number of fields in the result set.
     */
    function numfields($res = '')
    {
        if ($res == '') $res = $this->result;
        return $res->field_count;
    }
    /**
     * Retrieves the type of the field at the specified index in the given result set.
     *
     * @param mixed $res The result set to retrieve the field type from.
     * @param int $i The index of the field in the result set.
     * @return object|null The field type object at the specified index, or null if the index is out of range.
     */
    function fieldtype($res, $i)
    {
        return $res->fetch_field_direct($i);
    }
    /**
     * Retrieves the name of a field from a result set at a specified index.
     *
     * @param mixed $res The result set to retrieve the field name from.
     * @param int $i The index of the field in the result set.
     * @return string|null The name of the field at the specified index, or null if the index is out of range.
     */
    function fieldname($res, $i)
    {
        $field = $res->fetch_field_direct($i);
        return $field->name;
    }
    /**
     * Frees the result set.
     *
     * @param mixed $res The result set to free. If empty, the default result set will be used.
     * @return mixed The result of freeing the result set.
     */
    function free($res = '')
    {
        if ($res == '') $res = $this->result;
        return $res->free_result();
    }

    /**
     * Retrieves a single value from a database query.
     *
     * @param string $sql The SQL query to execute.
     * @param array|null $params An optional array of parameters to bind to the query.
     * @return mixed The first element of the first row of the result set, or false if the query fails to execute.
     */
    function getval($sql, $params = NULL)
    {
        // return one value
        $this->result = $this->select($sql, $params);
        if (!$this->result) return false;

        $row = $this->result->fetch_array();
        return $row[0];
    }
    /**
     * Retrieves the number of rows in the result set obtained from executing the given SQL query with optional parameters.
     *
     * @param string $sql The SQL query to execute.
     * @param mixed $params An optional array of parameters to bind to the query.
     * @return int|false The number of rows in the result set, or false if the query fails to execute.
     */
    function num_rows($sql, $params = NULL)
    {
        $this->sql = $sql;
        $this->result = $this->select($this->sql, $params);
        if (!$this->result) return false;
        return $this->result->num_rows;
    }
    /**
     * Retrieves a single row from the database using the given SQL query and optional parameters.
     *
     * @param string $sql The SQL query to execute.
     * @param mixed $params An optional array of parameters to bind to the query.
     * @return array|false The fetched row as an associative array, or false if the query fails to execute.
     */
    function getrow($sql, $params = NULL)
    {
        $this->result = $this->select($sql, $params);
        if (!$this->result) return false;
        return $this->result->fetch_assoc();
    }
    /**
     * Retrieves a single object from the database using the given SQL query and optional parameters.
     *
     * @param string $sql The SQL query to execute.
     * @param mixed $params An optional array of parameters to bind to the query.
     * @return object|false The fetched object, or false if the query fails to execute.
     */
    function getobject($sql, $params = NULL)
    {
        // return one object
        $this->result = $this->select($sql, $params);

        if (!$this->result) return false;
        return $this->result->fetch_object();
    }
}