<?php

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
        $this->assertCount(3, $columnsMeta);

        // Query does not return results
        $result = $this->db->getAll('SELECT * FROM "temp_table" WHERE "age" > ?', [100]);
        $this->assertEquals([], $result);

        // Query is bogus
        $this->expectException(PDOException::class);
        $this->db->getAll('SELECT * FROM NON_EXISTING_TABLE');
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

    public function testGetFirstColumn(): void
    {
        // Query returns results
        $result = $this->db->getFirstColumn('SELECT "name" FROM "temp_table"');
        $this->assertEquals(['Alice', 'Bob'], $result, 'Expected to fetch the first column with names.');

        // Query does not return results
        $result = $this->db->getFirstColumn('SELECT "name" FROM "temp_table" WHERE "age" > ?', [100]);
        $this->assertSame([], $result, 'Expected an empty array when no rows match the query.');

        // Query is bogus
        $this->expectException(PDOException::class);
        $this->db->getFirstColumn('SELECT NON_EXISTING_COLUMN FROM "temp_table"');
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

    public function testTransMethods(): void
    {
        // Test inTrans before starting a transaction
        $this->assertFalse($this->db->inTransaction(), 'Expected not to be in a transaction initially.');

        // Begin transaction
        $this->db->beginTransaction();
        $this->assertTrue($this->db->inTransaction(), 'Expected to be in a transaction after beginTransaction.');

        // Insert a new row
        $this->db->run('INSERT INTO "temp_table" ("name", "age") VALUES (?, ?)', ['Charlie', 25]);

        // Roll back the transaction
        $this->db->rollBack();
        $this->assertFalse($this->db->inTransaction(), 'Expected not to be in a transaction after rollBack.');

        // Verify the row was not committed
        $result = $this->db->getRow('SELECT * FROM "temp_table" WHERE "name" = ?', ["Charlie"]);
        $this->assertCount(0, $result, 'Expected no row to be found after rollback.');

        // Begin another transaction
        $this->db->beginTransaction();
        $this->assertTrue($this->db->inTransaction(), 'Expected to be in a transaction after beginTransaction.');

        // Insert a new row
        $this->db->run('INSERT INTO "temp_table" ("name", "age") VALUES (?, ?)', ['Diana', 28]);

        // Commit the transaction
        $this->db->commit();
        $this->assertFalse($this->db->inTransaction(), 'Expected not to be in a transaction after commit.');

        // Verify the row was committed
        $result = $this->db->getRow('SELECT * FROM "temp_table" WHERE "name" = ?', ["Diana"]);
        $this->assertArrayIsEqualToArrayIgnoringListOfKeys(['name' => 'Diana', 'age' => 28], $result, ['id'], 'Expected the row to be found after commit.');
    }

    public function testGetRowCount(): void
    {
        // Insert 1 row and check row count
        $this->db->run('INSERT INTO "temp_table" ("name", "age") VALUES (?, ?)', ['Charlie', 25], $rowCount);
        $this->assertEquals(1, $rowCount, 'Expected row count to be 1 after a single insert.');
    }

    public function testSqlIn(): void
    {
        // Case 1: Non-empty array of integers
        $params = [1, 2];
        $sqlFragment = $this->db->sqlIn($params);
        $result = $this->db->getAll('SELECT * FROM "temp_table" WHERE "id" IN ' . $sqlFragment, $params);
        $this->assertCount(2, $result, 'Expected 2 rows to match the integer IN clause.');
        $this->assertEquals([['id' => 1, 'name' => 'Alice', 'age' => 30], ['id' => 2, 'name' => 'Bob', 'age' => 40]], $result);

        // Case 2: Empty array
        $params = [];
        $sqlFragment = $this->db->sqlIn($params);
        $result = $this->db->getAll('SELECT * FROM "temp_table" WHERE "id" IN ' . $sqlFragment, $params);
        $this->assertEquals([], $result, 'Expected no rows to match the empty integer IN clause.');

        // Case 3: Empty array
        $params = [];
        $sqlFragment = $this->db->sqlIn($params);
        $result = $this->db->getAll('SELECT * FROM "temp_table" WHERE "name" IN ' . $sqlFragment, $params);
        $this->assertEquals([], $result, 'Expected no rows to match the empty string IN clause.');

        // Case 4: Single integer
        $params = [1];
        $sqlFragment = $this->db->sqlIn($params);
        $result = $this->db->getAll('SELECT * FROM "temp_table" WHERE "id" IN ' . $sqlFragment, $params);
        $this->assertCount(1, $result, 'Expected 1 row to match the single integer IN clause.');
        $this->assertEquals([['id' => 1, 'name' => 'Alice', 'age' => 30]], $result);

        // Case 5: Non-empty array of strings
        $params = ['Alice', 'Bob'];
        $sqlFragment = $this->db->sqlIn($params);
        $result = $this->db->getAll('SELECT * FROM "temp_table" WHERE "name" IN ' . $sqlFragment, $params);
        $this->assertCount(2, $result, 'Expected 2 rows to match the string IN clause.');
        $this->assertEquals([['id' => 1, 'name' => 'Alice', 'age' => 30], ['id' => 2, 'name' => 'Bob', 'age' => 40]], $result);

    }

    public function testsqlLikeEscape(): void
    {
        $untrustedValue = 'A';
        $escapedValue = $this->db->sqlLikeEscape($untrustedValue);
        $result = $this->db->getAll('SELECT * FROM "temp_table" WHERE "name" LIKE ?', [$escapedValue . '%']);
        $this->assertCount(1, $result, 'Expected 1 row to match the LIKE clause with escaped `A` in input.');
        $this->assertEquals([['id' => 1, 'name' => 'Alice', 'age' => 30]], $result);

        $untrustedValue = '%';
        $escapedValue = $this->db->sqlLikeEscape($untrustedValue);
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

}


// Define a class to map database rows
class Person
{
    public int $id;
    public string $name;
    public int $age;
}