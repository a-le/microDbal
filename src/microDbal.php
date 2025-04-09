<?php

declare(strict_types=1);

namespace aLe;

use PDO;
use PDOException;
use PDOStatement;


class MicroDbal
{
    /**
     * @var PDO The PDO instance used for database operations.
     */
    public $pdo;

    /**
     * @var int The number of rows affected by the last SQL statement.
     */
    private int $stmtRowCount = 0;

    /**
     * Creates a new instance of the MicroDbal class.
     * @param string $dsn 
     * @param string|null $username 
     * @param string|null $password 
     * @param array $options 
     * @return void 
     */
    public function __construct(string $dsn, string|null $username = null, string|null $password = null, array $options = [])
    {
        $default_options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Error reporting, let PDO throw an exception
        ];
        $options = array_replace($default_options, $options);
        $this->pdo = new PDO($dsn, $username, $password, $options);
    }

    /**
     * Prepares and executes a SQL statement with the given arguments. 
     * Returns the statement object.
     * @param string $sql 
     * @param array $args 
     * @return PDOStatement|false 
     * @throws PDOException 
     */
    public function run(string $sql, array $args = []): PDOStatement|false
    {
        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) {
            return false;
        }
        $stmt->execute($args);
        $this->stmtRowCount = $stmt->rowCount();
        return $stmt;
    }

    /**
     * Fetch next row from the result set as an associative array.
     * @param string $sql 
     * @param array $args 
     * @return array|false 
     * @throws PDOException 
     */
    public function get(string $sql, array $args = []): array|false
    {
        $stmt = $this->run($sql, $args);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Returns all rows of the result set as an associative array.
     * @param string $sql 
     * @param array $args 
     * @return array 
     * @throws PDOException 
     */
    public function getAll(string $sql, array $args = []): array
    {
        return $this->run($sql, $args)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns the next row of the result set as an associative array.
     * This method is useful when you want to retrieve a single row.
     * It prepares and executes the SQL statement, fetches the first row, and closes the cursor.
     * If no rows are found, an empty array is returned.
     * @param string $sql 
     * @param array $args 
     * @return array 
     * @throws PDOException 
     */
    public function getRow(string $sql, array $args = []): array
    {
        $stmt = $this->run($sql, $args);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $r === false ? [] : $r;
    }

    /**
     * Returns the first column of the result set as an array.
     * This method is useful when you want to retrieve a single column.
     * It prepares and executes the SQL statement, fetches all values from the first column, and closes the cursor.
     * If no rows are found, an empty array is returned.
     * @param string $sql 
     * @param array $args 
     * @return array 
     * @throws PDOException 
     */
    public function getFirstColumn(string $sql, array $args = []): array
    {
        $stmt = $this->run($sql, $args);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Returns the first value of the next row of the result set.
     * This method is useful when you want to retrieve a single value.
     * It prepares and executes the SQL statement, fetches the value, and closes the cursor.
     * If no rows are found, false is returned.
     * @param string $sql 
     * @param array $args 
     * @return mixed 
     * @throws PDOException 
     */
    public function getOne(string $sql, array $args = []): mixed
    {
        $stmt = $this->run($sql, $args);
        $r = $stmt->fetch(PDO::FETCH_COLUMN);
        $stmt->closeCursor();
        return $r;
    }

    /**
     * Fetch the next row of the result set as an object of the specified class.
     * If no rows are found, false is returned.
     * @param string $sql 
     * @param string $className 
     * @param array $args 
     * @param array $constructorArgs 
     * @return mixed|false 
     * @throws PDOException 
     */
    public function getOneObject(string $sql, string $className, array $args = [], array $constructorArgs = []): mixed
    {
        $stmt = $this->run($sql, $args);
        $stmt->setFetchMode(PDO::FETCH_CLASS, $className, $constructorArgs);
        return $stmt->fetch();
    }

    /**
     * Fetch all rows of the result set as objects of the specified class.
     * If no rows are found, an empty array is returned.
     * @param string $sql 
     * @param string $className 
     * @param array $args 
     * @param array $constructorArgs 
     * @return array 
     * @throws PDOException 
     */
    public function getAllObjects(string $sql, string $className, array $args = [], array $constructorArgs = []): array
    {
        $stmt = $this->run($sql, $args);
        return $stmt->fetchAll(PDO::FETCH_CLASS, $className, $constructorArgs);
    }

    /**
     * Helper
     * Returns the ID of the last inserted row or sequence value.
     * This method is useful when you want to retrieve the ID of the last inserted row after an INSERT statement.
     * Will throw a PDOException if not supported by the driver.
     * @see https://www.php.net/manual/en/pdo.lastinsertid.php for more information
     * @param string|null $name 
     * @return mixed|false 
     * @throws PDOException 
     */
    public function getLastInsertedId(string|null $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    /**
     * Returns the number of rows affected by the last executed statement using methods of this library.
     * This method is useful when you want to know how many rows were affected by an UPDATE, DELETE, or INSERT statement.
     * $this->stmtRowCount is set with pdostatement.rowcount in the run method
     * @return int 
     */
    public function getRowCount(): int
    {
        return $this->stmtRowCount;
    }

    /** 
     * beginTransaction Helper
     * @return bool — TRUE on success or FALSE on failure.
     * @throws PDOException — If there is already a transaction started or the driver does not support transactions
     * @link https://php.net/manual/en/pdo.begintransaction.php
     */
    public function beginTrans(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /** 
     * commit Helper
     * @return bool — TRUE on success or FALSE on failure.
     * @throws PDOException — if there is no active transaction.
     * @link https://php.net/manual/en/pdo.commit.php
     */
    public function commitTrans(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * rollBack Helper
     * @return bool — TRUE on success or FALSE on failure.
     * @throws PDOException — if there is no active transaction.
     * @link https://php.net/manual/en/pdo.rollback.php
     */
    public function rollBackTrans(): bool
    {
        return $this->pdo->rollBack();
    }

    /** 
     * inTransaction Helper
     * @return bool — TRUE if a transaction is currently active, FALSE otherwise.
     * @link https://php.net/manual/en/pdo.intransaction.php
    */
    public function inTrans(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Generates a SQL IN clause for integer values.
     * This method is useful when you want to create a SQL IN clause with a list of integer values.
     * @param array $arr 
     * @return string 
     */
    public function sqlInInt(array $arr): string
    {
        if (!count($arr)) return '(SELECT 1 WHERE FALSE)';
        return '(' . implode(',', array_map('intval', $arr)) . ')';
    }

    /**
     * Generates a SQL IN clause for string values.
     * This method is useful when you want to create a SQL IN clause with a list of string values.
     * @param array $arr 
     * @return string 
     */
    public function sqlInStr(array $arr): string
    {
        if (!count($arr)) return '(SELECT \'\' WHERE FALSE)';
        return '(' . implode(',', array_map(array(&$this->pdo, 'quote'), $arr)) . ')';
    }

    /**
     * Generates a SQL LIKE clause with escaped characters.
     * This method is useful when you want to create a SQL LIKE clause composed of unsafe string.
     * @param $str
     * @param string $escape
     * @return string
     */
    public function sqlLikeEscape(string $str, string $escape = '\\'): string
    {
        $quoted = trim($this->pdo->quote($str), "'");
        $chars = array('%' => $escape . '%', '_' => $escape . '_');
        return  strtr($quoted, $chars);
    }
}
