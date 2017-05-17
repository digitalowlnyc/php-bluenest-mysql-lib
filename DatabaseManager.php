<?php

/**
 * User: Bryan Mayor
 * Company: Blue Nest Digital, LLC
 * License: (All rights reserved)
 * Description: A class for interacting with a MySQL database using the MySQLi or MySQL driver.
 * Provides a builder class for creating new connections.
 * Supports querying using raw queries or prepared statements.
 */

if(!defined('NL')) {
    define('NL', PHP_EOL);
}

class DatabaseManager {

    var $databaseAddress;
    var $username;
    var $password;
    var $database = null;
    var $useMysql = false; // if true, use old mysql package instead of mysqli
    var $verboseQuery = true;

    /** @var Mysqli */
    var $connection;

    function mysqlError($connection) {
        if($this->useMysql) {
            return mysql_error($connection);
        } else {
            return mysqli_error($connection);
        }
    }

    function __construct($databaseAddress, $username, $password, $database = null, $createConnection = false)
    {
        $this->databaseAddress = $databaseAddress;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;

        if($createConnection){
            $this->connect();
        }
    }

    /**
     * Attempt to parse results into php types (int, float, bool, string, date)
     * @param $row
     */
    function parseRow(&$row) {
        foreach($row as $col=>$val) {
            if(is_int($val)) {
                $val = intval($val);
            } else if(is_numeric($val)) {
                $val = floatval($val);
            } else if(is_bool($val)) {
                $val = boolval($val);
            } else if(is_string($val)) {
                if(preg_match("#\d\d\d\d-\d\d-\d\d#", $val)) {
                    date("d-m-Y", strtotime($val));
                } else {
                    // default
                }
            }
            $row[$col] = $val;
        }
    }

    function setDatabase($databaseName)
    {
        if(!$this->connection->select_db($databaseName)) {
            die("Could not set database to $databaseName: " . $this->mysqlError($this->connection));
        }
        $this->database = $databaseName;
    }

    function connect()
    {
        if($this->useMysql) {
            $connection = mysql_connect($this->databaseAddress, $this->username, $this->password, $this->database);
        } else {
            $connection = mysqli_connect($this->databaseAddress, $this->username, $this->password, $this->database);
        }

        if(!$connection) {
            echo "errno: " . mysqli_connect_errno() . PHP_EOL;
            echo "error: " . mysqli_connect_error() . PHP_EOL;
            throw new Exception('Unable to connect to MySQL database');
        }

        $this->connection = $connection;
    }

    function close() {
        $this->connection->close();
    }

    function disconnect()
    {
        $this->close();
    }

    // Execute a query. Preferable to use doPreparedStatement for
    // security purposes.
    function doQuery($sql, $parseRow = false)
    {
        if($this->verboseQuery) {
            echo 'Querying: ' . $sql . ' in database ' . $this->database . NL;
        }

        if(!$result = $this->connection->query($sql)) {
            die('Error running query: ' . $this->mysqlError($this->connection));
        };

        $fetchMode = $this->useMysql ? MYSQL_ASSOC : MYSQLI_ASSOC;

        if($this->verboseQuery) {
            echo 'Selected ' . $result->num_rows . ' rows' . NL;
        }

        $resultArray = array();
        while ($row = $result->fetch_array($fetchMode)) {
            if($parseRow) {
                $this->parseRow($row);
            }
            $resultArray[] = $row;
        }

        return($resultArray);
    }

    // Execute prepared statement and return result as array of keyed-arrays
    function doPreparedStatement($preparedStatement, $bindParameters) {
        $fetchMode = $this->useMysql ? MYSQL_ASSOC : MYSQLI_ASSOC;

        if ($stmt = $this->connection->prepare($preparedStatement)) {

            /* bind parameters for markers */
            foreach($bindParameters as $parm=>$val) {
                $stmt->bind_param($parm, $val);
            }

            /* execute query */
            $stmt->execute();
            $result = $stmt->get_result();

            $resultArray = array();
            while ($row = $result->fetch_array($fetchMode)) {
                $resultArray[] = $row;
            }

            /* close statement */
            $stmt->close();

            return $resultArray;
        } else {
            die('Error creating prepared statement: ' . $this->mysqlError($this->connection));
        }
    }

    function getTableLayout() {
        $tableLayout = array();
        $tables = $this->getTables();
        foreach($tables as $table) {
            $tableLayout[$table] = $this->getColumns($table);
        }
        return $tableLayout;
    }

    function getColumns($table) {
        $columnsQueryRes = $this->doQuery("SHOW COLUMNS from `" . $table . "` from `" . $this->database . "`", false);
        $columns = array();
        foreach($columnsQueryRes as $row) {
            $columns[] = $row['Field'];
        }
        return $columns;
    }

    function getDatabases() {
        $tableQueryRes = $this->doQuery("SHOW DATABASES", false);
        return $tableQueryRes;
    }

    function getTables() {
        $tableQueryRes = $this->doQuery("SHOW TABLES from `" . $this->database . "`", false);
        var_dump($tableQueryRes);
        $tables = array();
        foreach($tableQueryRes as $row) {
            foreach($row as $key=>$val) {
                $tables[] = $val;
            }
        }
        return $tables;
    }

    function setCharset($charSet) {
        if(!$this->connection->set_charset($charSet)) {
            die('Error setting charset: ' . $this->mysqlError($this->connection));
        }
    }
}

class DatabaseManagerBuilder {
    private $host = null;
    private $database = null;
    private $username = null;
    private $password = null;
    private $verbose = false;
    private $requiredFileFields = array("username", "host", "password"); // arrays not allowed as class constants

    static function create() {
        return new DatabaseManagerBuilder();
    }

    function fromFile($filename) {
        if(!file_exists($filename)) {
            throw new Exception("The expected db configuration file '" . $filename . "' does not exist. Please create a JSON format file with this name
                with keys for 'host', 'username', and 'password");
        }

        $contents = file_get_contents($filename);
        $config = json_decode($contents, true);
        foreach($this->requiredFileFields as $requiredField) {
            if(!isset($config[$requiredField])) {
                throw new Exception("File " . $filename . "is missing json key/value " . $requiredField);
            }
        }
        $this->username($config["username"])->host($config["host"])->password($config["password"]);
        if(isset($config["database"])) {
            $this->database($config["database"]);
        }

        return $this;
    }

    function connect() {
        if($this->verbose) {
            $obscuredPassword = str_repeat('*', strlen($this->password));
            echo "DatabaseManagerBuilder: Connecting to host '" . $this->host . "' with username '" . $this->username . "' and password '" . $obscuredPassword . "'" . PHP_EOL;
        }
        $databaseManager = new DatabaseManager($this->host, $this->username, $this->password, $this->database, true);
        return $databaseManager;
    }

    function username($val) {
        $this->username = $val;
        return $this;
    }

    function password($val) {
        $this->password = $val;
        return $this;
    }

    function host($val) {
        $this->host = $val;
        return $this;
    }

    function database($val) {
        $this->database = $val;
        return $this;
    }

    function verbose($val) {
        $this->verbose = $val;
        return $this;
    }
}