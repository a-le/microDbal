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
        ];
        $forced_options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // let PDO throw exception on error
        ];
        $options = array_replace($default_options, $options, $forced_options);

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            // throw a new ErrorException that contains only the message and do not reveal credentials 
            throw new PDOException('Connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        $this->driverName = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Prepares and executes a SQL statement with the given arguments. 
     * This method is useful for executing SQL statements that do not return a result set, such as INSERT, UPDATE, or DELETE.
     * Or if you want to access the statement object directly.
     * Returns the statement object.
     * @param string $sql 
     * @param array $args 
     * @param int|null $rowCount Optional reference to store the number of affected rows.
     * @return PDOStatement
     * @throws PDOException 
     */
    public function run(string $sql, array $args = [], ?int &$rowCount = null): PDOStatement|false
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);

        if (func_num_args() > 2) {
            $rowCount = $stmt->rowCount();
        }

        return $stmt;
    }


    /**
     * Returns all rows of the result set as an associative array.
     * @param string $sql 
     * @param array $args 
     * @param array|null $columnsMeta Optional reference to store column metadata.
     * @return array 
     * @throws PDOException 
     */
    public function getAll(string $sql, array $args = [], ?array &$columnsMeta = null): array
    {
        $stmt = $this->run($sql, $args);

        if (func_num_args() > 2) {
            $columnsMeta = [];
            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $columnsMeta[$i] = $stmt->getColumnMeta($i);
            }
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns the next row of the result set as an associative array.
     * This method is useful when you know your query returns at most 1 row.
     * If no rows are found, an empty array is returned.
     * @param string $sql 
     * @param array $args 
     * @return array 
     * @throws PDOException 
     */
    public function getRow(string $sql, array $args = [], ?array &$columnsMeta = null): array
    {
        $stmt = $this->run($sql, $args);

        if (isset($columnsMeta)) {
            $columnsMeta = [];
            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $columnsMeta[$i] = $stmt->getColumnMeta($i);
            }
        }

        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $r === false ? [] : $r;
    }

    /**
     * Returns the first column of the result set as an array.
     * This method is useful when your query returns rows for only 1 column.
     * If no rows are found, an empty array is returned.
     * @param string $sql 
     * @param array $args 
     * @return array 
     * @throws PDOException 
     */
    public function getCol(string $sql, array $args = []): array
    {
        $stmt = $this->run($sql, $args);
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * Returns the first value of the next row of the result set.
     * This method is useful when your query returns a single value.
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
     * This method is useful when you want to retrieve a single object from the first row.
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
        $r = $stmt->fetch();
        $stmt->closeCursor();
        return $r;
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
    // public function getLastRowCount(): int
    // {
    //     return $this->lastRowCount;
    // }

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
    public function sqlLike(string $str, string $escape = '\\'): string
    {
        // Escape special characters for SQL LIKE
        $escaped = strtr($str, [
            '%' => $escape . '%',
            '_' => $escape . '_',
        ]);

        return $escaped;
    }

}
