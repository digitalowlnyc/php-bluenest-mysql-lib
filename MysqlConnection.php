<?php

/**
 * Creator: Bryan Mayor
 * Company: Blue Nest Digital, LLC
 * License: (Blue Nest Digital LLC, All rights reserved)
 * Copyright: Copyright 2017 Blue Nest Digital LLC
 */
class MysqlConnection
{
    public $host;
    public $user;
    public $pass;
    public $port;
    public $database;

    private $lazyConnect = true;

    public $connection = null;
    private $verbose;

    function __construct($host, $pass, $user, $database, $port, $verbose = false) {
        $this->host = $host;
        $this->pass = $pass;
        $this->user = $user;
        $this->database = $database;
        $this->port = $port;
        $this->verbose = $verbose;

        if(!$this->lazyConnect) {
            $this->connect();
        }
    }

    function connect() {
        if($this->verbose) {
            echo "Connecting to MySQL: " .  $this->database . ' on ' . $this->host . ":" . $this->port . PHP_EOL;
        }

        $this->connection = mysqli_connect(
            $this->host,
            $this->user,
            $this->pass,
            $this->database,
            $this->port
        );

        if($this->connection === false) {
            throw new \Exception("Could not connect to MySQL");
        }

        if($this->verbose) {
            echo "Connected to MySQL" . PHP_EOL;
        }
    }

    function getConnection() {
        if($this->lazyConnect && $this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    public function commit() {
        if(!$this->connection->commit()) {
            throw new \Exception("Could not commit to mysql: "  . $mysqli->error);
            echo "Done committing" . PHP_EOL;
        }
    }

    public static function fromEnv() {
        $host = EnvLoader::get("database.host");
        $pass = EnvLoader::get("database.pass");
        $user = EnvLoader::get("database.user");
        $database = EnvLoader::get("database.database_name");
        $port = EnvLoader::get("database.port");
        $verbose = EnvLoader::get("database.verbose");

        return new MysqlConnection($host, $pass, $user, $database, $port, $verbose);
    }
}