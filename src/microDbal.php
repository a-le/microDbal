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
     * @var string The name of the database driver being used.
     */
    public readonly string $driverName;

    /**
     * @var int The number of rows affected by the last SQL statement.
     */
    private int $stmtRowCount = 0;

    /**
     * Creates a new instance of the MicroDbal class.
     * @link https://www.php.net/manual/en/pdo.construct.php
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
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Error reporting, let PDO throw an exception
        ];
        $options = array_replace($default_options, $options);
        $this->pdo = new PDO($dsn, $username, $password, $options);
        $this->driverName = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
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
     * @param array $args 
     * @param string $className 
     * @param array $constructorArgs 
     * @return mixed|false 
     * @throws PDOException 
     */
    public function getOneObject(string $sql, array $args, string $className, array $constructorArgs = []): mixed
    {
        $stmt = $this->run($sql, $args);
        $stmt->setFetchMode(PDO::FETCH_CLASS, $className, $constructorArgs);
        return $stmt->fetch();
    }

    /**
     * Fetch all rows of the result set as objects of the specified class.
     * If no rows are found, an empty array is returned.
     * @param string $sql 
     * @param array $args 
     * @param string $className 
     * @param array $constructorArgs 
     * @return array 
     * @throws PDOException 
     */
    public function getAllObjects(string $sql, array $args, string $className, array $constructorArgs = []): array
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
     * The PDOStatement::rowCount() is called in the run method, and the value is stored in the $stmtRowCount property.
     * @see https://www.php.net/manual/en/pdostatement.rowcount.php for more information
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
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /** 
     * commit Helper
     * @return bool — TRUE on success or FALSE on failure.
     * @throws PDOException — if there is no active transaction.
     * @link https://php.net/manual/en/pdo.commit.php
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * rollBack Helper
     * @return bool — TRUE on success or FALSE on failure.
     * @throws PDOException — if there is no active transaction.
     * @link https://php.net/manual/en/pdo.rollback.php
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    /** 
     * inTransaction Helper
     * @return bool — TRUE if a transaction is currently active, FALSE otherwise.
     * @link https://php.net/manual/en/pdo.intransaction.php
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Generates a SQL IN clause using placeholders.
     * @param array $arr
     * @return string
     */
    public function sqlIn(array $arr): string
    {
        if ($arr === []) {
            return '(null)';
        }
        $placeholders = implode(',', array_fill(0, count($arr), '?'));
        return "($placeholders)";
    }

    /**
     * Prepares a SQL LIKE clause value with escaped characters for use in a prepared statement.
     * This method escapes `%` and `_` characters in the input string and appends the necessary wildcards.
     * @param string $str The input string to escape.
     * @param string $escape The escape character (default is '\').
     * @return string The escaped string ready for a LIKE clause.
     */
    public function sqlLikeEscape(string $str, string $escape = '\\'): string
    {
        // Escape special characters for SQL LIKE
        $escaped = strtr($str, [
            '%' => $escape . '%',
            '_' => $escape . '_',
        ]);

        return $escaped;
    }

}
