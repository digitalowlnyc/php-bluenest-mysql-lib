<?php

require "DatabaseManager.php";

/**
 * User: Bryan Mayor
 * Company: Blue Nest Digital, LLC
 * Date: 8/21/16
 * Time: 3:42 AM
 * License: (All rights reserved)
 */
class DatabaseViewer
{
    /**
     * @var DatabaseManager
     */
    public $dbManager = null;
    private $currentTable = null;

    function __construct($dbManager) {
        $this->dbManager = $dbManager;
    }

    static function createFromDatabaseManager($host, $username, $password) {
        return new DatabaseViewer((DatabaseManagerBuilder::create()->host($host)->username($username)->password($password)->connect()));
    }

    function close() {
        $this->dbManager->close();
    }

    function getTable($table, $limit = 100) {
        $data = $this->dbManager->doQuery('SELECT * from ' . $table . ' LIMIT ' . $limit, false);
        $this->currentTable = $table;
        return $data;
    }
}