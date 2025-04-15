<?php
/*
 * MicroDbal PHPUnit Test Suite
 * - Is designed to run with any database supported by PHP's PDO.
 * The SQL used in this test suite is intended to be as portable as possible.
 * However, this syntax may not be supported by all databases.
 *
 * - Is designed to be run by run-tests.php so you can pass <dsn> <username> <password> as arguments.
 *
 * - Is expected to pass successfully on the following databases:
 * - Firebird
 * - MySQL / MariaDB
 * - PostgreSQL
 * - SQL Server
 * - SQLite
 *
 * A test table is created in the database and populated with test data.
 * The test table is dropped after the tests are completed.
 * The test table is named "temp_table".
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use aLe\MicroDbal;

class microDbalTest extends TestCase
{
    private MicroDbal $db;

    protected function setUp(): void
    {
        $dsn = getenv('DB_DSN') ?: 'sqlite::memory:';
        $username = getenv('DB_USERNAME') ?: null;
        $password = getenv('DB_PASSWORD') ?: null;

        $this->db = new MicroDbal($dsn, $username, $password);

        if ($this->db->driverName === 'mysql') {
            $this->db->run("SET SESSION sql_mode = 'ANSI_QUOTES'");
        }

        try {
            $this->db->run('DROP TABLE "temp_table"');
        } catch (PDOException $e) {
            // Ignore the error if the table does not exist
        }

        // create test table
        switch ($this->db->driverName) {
            case 'mysql':
                $this->db->run('CREATE TABLE `temp_table` (`id` INTEGER PRIMARY KEY AUTO_INCREMENT, `name` VARCHAR(50), `age` INT)');
                break;
            case 'sqlite':
                $this->db->run('CREATE TABLE "temp_table" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "name" VARCHAR(50), "age" INTEGER)');
                break;
            case 'sqlsrv':
                $this->db->run('CREATE TABLE "temp_table" ("id" INTEGER PRIMARY KEY IDENTITY(1,1), "name" VARCHAR(50), "age" INTEGER)');
                break;
            default: // standard syntax
                $this->db->run('CREATE TABLE "temp_table" ("id" INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY, "name" VARCHAR(50), "age" INTEGER)');
        }


        $this->db->run('INSERT INTO "temp_table" ("name", "age") VALUES (?, ?)', ['Alice', 30]);
        $this->db->run('INSERT INTO "temp_table" ("name", "age") VALUES (?, ?)', ['Bob', 40]);
    }

    public function testGetLastInsertedId(): void
    {
        try {
            // get the last inserted ID
            $lastId = $this->db->getLastInsertedId();
            $this->assertEquals(2, (int) $lastId, 'Expected last inserted ID to be 2.');
        } catch (PDOException $e) {
            // Handle the exception if the driver does not support lastInsertId
            $this->assertEquals('IM001', $e->getCode(), 'Expected SQLSTATE[IM001] for unsupported driver.');
        }

    }

    public function testGetAll(): void
    {
        // Query returns results
        $result = $this->db->getAll('SELECT * FROM "temp_table"', [], $columnsMeta);
        $this->assertCount(2, $result);

        // columnsMeta should contain metadata about the columns
        $this->assertCount(3, $columnsMeta);

        // Query does not return results
        $result = $this->db->getAll('SELECT * FROM "temp_table" WHERE "age" > ?', [100]);
        $this->assertEquals([], $result);

        // Query is bogus
        $this->expectException(PDOException::class);
        $this->db->getAll('SELECT * FROM NON_EXISTING_TABLE');
    }

    public function testFetch(): void
    {
        $i = 0;
        $stmt = $this->db->run('SELECT * FROM "temp_table"');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->assertIsArray($row, 'Expected each row to be an associative array.');
            $i++;
        }
        $this->assertEquals(2, $i, 'Expected to fetch 2 rows from the temp_table.');
    }

    public function testGetRow(): void
    {
        // Query returns a result
        $result = $this->db->getRow('SELECT * FROM "temp_table" WHERE "name" = ?', ['Bob']);
        $this->assertEquals(['id' => 2, 'name' => 'Bob', 'age' => 40], $result);

        // Query does not return a result
        $result = $this->db->getRow('SELECT * FROM "temp_table" WHERE "name" = ?', ['Charlie']);
        $this->assertSame([], $result);

        // Query is bogus
        $this->expectException(PDOException::class);
        $this->db->getRow('SELECT * FROM NON_EXISTING_TABLE');
    }

    public function testGetCol(): void
    {
        // Query returns results
        $result = $this->db->getCol('SELECT "name" FROM "temp_table"');
        $this->assertEquals(['Alice', 'Bob'], $result, 'Expected to fetch the first column with names.');

        // Query does not return results
        $result = $this->db->getCol('SELECT "name" FROM "temp_table" WHERE "age" > ?', [100]);
        $this->assertSame([], $result, 'Expected an empty array when no rows match the query.');

        // Query is bogus
        $this->expectException(PDOException::class);
        $this->db->getCol('SELECT NON_EXISTING_COLUMN FROM "temp_table"');
    }

    public function testGetOne(): void
    {
        // Query returns a result
        $result = $this->db->getOne('SELECT "age" FROM "temp_table" WHERE "name" = ?', ['Alice']);
        $this->assertEquals(30, $result);

        // Query does not return a result
        $result = $this->db->getOne('SELECT "age" FROM "temp_table" WHERE "name" = ?', ['Charlie']);
        $this->assertFalse($result);

        // Query is bogus
        $this->expectException(PDOException::class);
        $this->db->getOne('SELECT "age" FROM NON_EXISTING_TABLE');
    }

    public function testGetOneObject(): void
    {
        // Query returns a result
        $result = $this->db->getOneObject('SELECT * FROM "temp_table" WHERE "name" = ?', ['Alice'], Person::class);
        $this->assertInstanceOf(Person::class, $result);
        $this->assertEquals('Alice', $result->name);

        // Query does not return a result
        $result = $this->db->getOneObject('SELECT * FROM "temp_table" WHERE "name" = ?', ['Charlie'], Person::class);
        $this->assertFalse($result);

        // Query is bogus
        $this->expectException(PDOException::class);
        $this->db->getOneObject('SELECT * FROM NON_EXISTING_TABLE', [], Person::class);
    }

    public function testGetAllObjects(): void
    {
        // Query returns results
        $result = $this->db->getAllObjects('SELECT * FROM "temp_table"', [], Person::class);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Person::class, $result[0]);

        // Query does not return results
        $result = $this->db->getAllObjects('SELECT * FROM "temp_table" WHERE "age" > ?', [100], Person::class);
        $this->assertEquals([], $result);

        // Query is bogus
        $this->expectException(PDOException::class);
        $this->db->getAllObjects('SELECT * FROM NON_EXISTING_TABLE', [], Person::class);
    }

    public function testGetRowCount(): void
    {
        // Insert 1 row and check row count
        $this->db->run('INSERT INTO "temp_table" ("name", "age") VALUES (?, ?)', ['Charlie', 25], $rowCount);
        $this->assertEquals(1, $rowCount, 'Expected row count to be 1 after a single insert.');
    }

    public function testSqlIn(): void
    {
        // Case 1: gen IN from an array of integers
        $params = [1, 2];
        $sqlFragment = $this->db->sqlIn($params);
        $result = $this->db->getAll('SELECT * FROM "temp_table" WHERE "id" IN ' . $sqlFragment, $params);
        $this->assertCount(2, $result, 'Expected 2 rows to match the integer IN clause.');
        $this->assertEquals([['id' => 1, 'name' => 'Alice', 'age' => 30], ['id' => 2, 'name' => 'Bob', 'age' => 40]], $result);

        // Case 2: gen IN from an empty array, note that the id column is of type INTEGER
        $params = [];
        $sqlFragment = $this->db->sqlIn($params);
        $result = $this->db->getAll('SELECT * FROM "temp_table" WHERE "id" IN ' . $sqlFragment, $params);
        $this->assertEquals([], $result, 'Expected no rows to match the empty integer IN clause.');

        // Case 3: gen IN from an empty array, note that the name column is of type VARCHAR
        $params = [];
        $sqlFragment = $this->db->sqlIn($params);
        $result = $this->db->getAll('SELECT * FROM "temp_table" WHERE "name" IN ' . $sqlFragment, $params);
        $this->assertEquals([], $result, 'Expected no rows to match the empty string IN clause.');

        // Case 4: gen IN from an array of a single integer 
        $params = [1];
        $sqlFragment = $this->db->sqlIn($params);
        $result = $this->db->getAll('SELECT * FROM "temp_table" WHERE "id" IN ' . $sqlFragment, $params);
        $this->assertCount(1, $result, 'Expected 1 row to match the single integer IN clause.');
        $this->assertEquals([['id' => 1, 'name' => 'Alice', 'age' => 30]], $result);

        // Case 5: gen IN from an array of strings
        $params = ['Alice', 'Bob'];
        $sqlFragment = $this->db->sqlIn($params);
        $result = $this->db->getAll('SELECT * FROM "temp_table" WHERE "name" IN ' . $sqlFragment, $params);
        $this->assertCount(2, $result, 'Expected 2 rows to match the string IN clause.');
        $this->assertEquals([['id' => 1, 'name' => 'Alice', 'age' => 30], ['id' => 2, 'name' => 'Bob', 'age' => 40]], $result);

    }

    public function testsqlLike(): void
    {
        $untrustedValue = 'A';
        $escapedValue = $this->db->sqlLike($untrustedValue);
        $result = $this->db->getAll('SELECT * FROM "temp_table" WHERE "name" LIKE ?', [$escapedValue . '%']);
        $this->assertCount(1, $result, 'Expected 1 row to match the LIKE clause with escaped `A` in input.');
        $this->assertEquals([['id' => 1, 'name' => 'Alice', 'age' => 30]], $result);

        $untrustedValue = '%';
        $escapedValue = $this->db->sqlLike($untrustedValue);
        $result = $this->db->getAll('SELECT * FROM "temp_table" WHERE "name" LIKE ?', [$escapedValue . '%']);
        $this->assertCount(0, $result, 'Expected no row to match the LIKE clause with escaped `%` in input.');
    }

    public function testPostgresqlReturning(): void
    {
        // Skip the test if the driver is not PostgreSQL
        $driver = $this->db->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($this->db->driverName !== 'pgsql') {
            $this->markTestSkipped('This test is only applicable for PostgreSQL.');
        }

        // Execute the UPDATE query with RETURNING clause
        $updatedAge = $this->db->getOne('UPDATE "temp_table" SET "age" = "age" + 5 WHERE "name" = ? RETURNING "age"', ['Alice']);

        // Assert the updated age
        $this->assertEquals(35, $updatedAge, 'Expected Alice\'s age to be updated to 35.');
    }

    public function testCleanup(): void
    {
        try {
            $this->db->run('DROP TABLE "temp_table"');
        } catch (PDOException $e) {
            // Ignore the error if the table does not exist
        }
        $this->assertTrue(true);
    }

}


// Define a class to map database rows
class Person
{
    public int $id;
    public string $name;
    public int $age;
}