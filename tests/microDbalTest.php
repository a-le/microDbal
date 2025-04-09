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
        $this->db->run('CREATE TEMPORARY TABLE test (id INTEGER, name TEXT, age INTEGER)');
        $this->db->run('INSERT INTO test (id, name, age) VALUES (?, ?, ?)', [1, 'Alice', 30]);
        $this->db->run('INSERT INTO test (id, name, age) VALUES (?, ?, ?)', [2, 'Bob', 40]);
    }

    public function testGet(): void
    {
        // Query returns a result
        $result = $this->db->get('SELECT * FROM test WHERE name = ?', ['Alice']);
        $this->assertEquals(['id' => 1, 'name' => 'Alice', 'age' => 30], $result);

        // Query does not return a result
        $result = $this->db->get('SELECT * FROM test WHERE name = ?', ['Charlie']);
        $this->assertFalse($result);

        // Query is bogus
        $this->expectException(PDOException::class);
        $this->db->get('SELECT * FROM non_existing_table');
    }

    public function testGetAll(): void
    {
        // Query returns results
        $result = $this->db->getAll('SELECT * FROM test');
        $this->assertCount(2, $result);

        // Query does not return results
        $result = $this->db->getAll('SELECT * FROM test WHERE age > ?', [100]);
        $this->assertEquals([], $result);

        // Query is bogus
        $this->expectException(PDOException::class);
        $this->db->getAll('SELECT * FROM non_existing_table');
    }

    public function testGetRow(): void
    {
        // Query returns a result
        $result = $this->db->getRow('SELECT * FROM test WHERE name = ?', ['Bob']);
        $this->assertEquals(['id' => 2, 'name' => 'Bob', 'age' => 40], $result);

        // Query does not return a result
        $result = $this->db->getRow('SELECT * FROM test WHERE name = ?', ['Charlie']);
        $this->assertSame([], $result);

        // Query is bogus
        $this->expectException(PDOException::class);
        $this->db->getRow('SELECT * FROM non_existing_table');
    }

    public function testGetFirstColumn(): void
    {
        // Query returns results
        $result = $this->db->getFirstColumn('SELECT name FROM test');
        $this->assertEquals(['Alice', 'Bob'], $result, 'Expected to fetch the first column with names.');

        // Query does not return results
        $result = $this->db->getFirstColumn('SELECT name FROM test WHERE age > ?', [100]);
        $this->assertSame([], $result, 'Expected an empty array when no rows match the query.');

        // Query is bogus
        $this->expectException(PDOException::class);
        $this->db->getFirstColumn('SELECT non_existing_column FROM test');
    }

    public function testGetOne(): void
    {
        // Query returns a result
        $result = $this->db->getOne('SELECT age FROM test WHERE name = ?', ['Alice']);
        $this->assertEquals(30, $result);

        // Query does not return a result
        $result = $this->db->getOne('SELECT age FROM test WHERE name = ?', ['Charlie']);
        $this->assertFalse($result);

        // Query is bogus
        $this->expectException(PDOException::class);
        $this->db->getOne('SELECT age FROM non_existing_table');
    }

    public function testGetOneObject(): void
    {
        // Query returns a result
        $result = $this->db->getOneObject('SELECT * FROM test WHERE name = ?', Person::class, ['Alice']);
        $this->assertInstanceOf(Person::class, $result);
        $this->assertEquals('Alice', $result->name);

        // Query does not return a result
        $result = $this->db->getOneObject('SELECT * FROM test WHERE name = ?', Person::class, ['Charlie']);
        $this->assertFalse($result);

        // Query is bogus
        $this->expectException(PDOException::class);
        $this->db->getOneObject('SELECT * FROM non_existing_table', Person::class);
    }

    public function testGetAllObjects(): void
    {
        // Query returns results
        $result = $this->db->getAllObjects('SELECT * FROM test', Person::class);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Person::class, $result[0]);

        // Query does not return results
        $result = $this->db->getAllObjects('SELECT * FROM test WHERE age > ?', Person::class, [100]);
        $this->assertEquals([], $result);

        // Query is bogus
        $this->expectException(PDOException::class);
        $this->db->getAllObjects('SELECT * FROM non_existing_table', Person::class);
    }

    public function testTransMethods(): void
    {
        // Test inTrans before starting a transaction
        $this->assertFalse($this->db->inTrans(), 'Expected not to be in a transaction initially.');

        // Begin transaction
        $this->db->beginTrans();
        $this->assertTrue($this->db->inTrans(), 'Expected to be in a transaction after beginTrans.');

        // Insert a new row
        $this->db->run('INSERT INTO test (id, name, age) VALUES (?, ?, ?)', [3, 'Charlie', 25]);

        // Roll back the transaction
        $this->db->rollBackTrans();
        $this->assertFalse($this->db->inTrans(), 'Expected not to be in a transaction after rollBackTrans.');

        // Verify the row was not committed
        $result = $this->db->get('SELECT * FROM test WHERE id = ?', [3]);
        $this->assertFalse($result, 'Expected no row to be found after rollback.');

        // Begin another transaction
        $this->db->beginTrans();
        $this->assertTrue($this->db->inTrans(), 'Expected to be in a transaction after beginTrans.');

        // Insert a new row
        $this->db->run('INSERT INTO test (id, name, age) VALUES (?, ?, ?)', [4, 'Diana', 28]);

        // Commit the transaction
        $this->db->commitTrans();
        $this->assertFalse($this->db->inTrans(), 'Expected not to be in a transaction after commitTrans.');

        // Verify the row was committed
        $result = $this->db->get('SELECT * FROM test WHERE id = ?', [4]);
        $this->assertEquals(['id' => 4, 'name' => 'Diana', 'age' => 28], $result, 'Expected the row to be found after commit.');
    }

    public function testGetRowCount(): void
    {
        // Insert 1 row and check row count
        $this->db->run('INSERT INTO test (id, name, age) VALUES (?, ?, ?)', [3, 'Charlie', 25]);
        $rowCount = $this->db->getRowCount();
        $this->assertEquals(1, $rowCount, 'Expected row count to be 1 after a single insert.');
    }

    public function testSqlInInt(): void
    {
        // Case 1: Non-empty array of integers
        $sqlFragment = $this->db->sqlInInt([1, 2]);
        $result = $this->db->getAll("SELECT * FROM test WHERE id IN $sqlFragment");
        $this->assertCount(2, $result, 'Expected 2 rows to match the integer IN clause.');
        $this->assertEquals([['id' => 1, 'name' => 'Alice', 'age' => 30], ['id' => 2, 'name' => 'Bob', 'age' => 40]], $result);

        // Case 2: Empty array
        $sqlFragment = $this->db->sqlInInt([]);
        $result = $this->db->getAll("SELECT * FROM test WHERE id IN $sqlFragment");
        $this->assertEquals([], $result, 'Expected no rows to match the empty integer IN clause.');

        // Case 3: Single integer
        $sqlFragment = $this->db->sqlInInt([1]);
        $result = $this->db->getAll("SELECT * FROM test WHERE id IN $sqlFragment");
        $this->assertCount(1, $result, 'Expected 1 row to match the single integer IN clause.');
        $this->assertEquals([['id' => 1, 'name' => 'Alice', 'age' => 30]], $result);
    }

    public function testSqlInStr(): void
    {
        // Case 1: Non-empty array of strings
        $sqlFragment = $this->db->sqlInStr(['Alice', 'Bob']);
        $result = $this->db->getAll("SELECT * FROM test WHERE name IN $sqlFragment");
        $this->assertCount(2, $result, 'Expected 2 rows to match the string IN clause.');
        $this->assertEquals([['id' => 1, 'name' => 'Alice', 'age' => 30], ['id' => 2, 'name' => 'Bob', 'age' => 40]], $result);

        // Case 2: Empty array
        $sqlFragment = $this->db->sqlInStr([]);
        $result = $this->db->getAll("SELECT * FROM test WHERE name IN $sqlFragment");
        $this->assertEquals([], $result, 'Expected no rows to match the empty string IN clause.');

        // Case 3: Single string
        $sqlFragment = $this->db->sqlInStr(['Alice']);
        $result = $this->db->getAll("SELECT * FROM test WHERE name IN $sqlFragment");
        $this->assertCount(1, $result, 'Expected 1 row to match the single string IN clause.');
        $this->assertEquals([['id' => 1, 'name' => 'Alice', 'age' => 30]], $result);

        // Case 4: Special characters
        $this->db->run('INSERT INTO test (id, name, age) VALUES (?, ?, ?)', [3, "O'Reilly", 35]);
        $sqlFragment = $this->db->sqlInStr(["O'Reilly"]);
        $result = $this->db->getAll("SELECT * FROM test WHERE name IN $sqlFragment");
        $this->assertCount(1, $result, 'Expected 1 row to match the string IN clause with special characters.');
        $this->assertEquals([['id' => 3, 'name' => "O'Reilly", 'age' => 35]], $result);
    }


    public function testsqlLikeEscape(): void
    {
        $untrustedValue = 'A';
        $escapedValue = $this->db->sqlLikeEscape($untrustedValue);
        $result = $this->db->getAll("SELECT * FROM test WHERE name LIKE ?", [$escapedValue.'%']);
        $this->assertCount(1, $result, 'Expected 1 row to match the LIKE clause with escaped `A` in input.');
        $this->assertEquals([['id' => 1, 'name' => 'Alice', 'age' => 30]], $result);

        $untrustedValue = '%';
        $escapedValue = $this->db->sqlLikeEscape($untrustedValue);
        $result = $this->db->getAll("SELECT * FROM test WHERE name LIKE ?", [$escapedValue.'%']);
        $this->assertCount(0, $result, 'Expected no row to match the LIKE clause with escaped `%` in input.');
    }

    public function testPostgresqlReturning(): void
    {
        // Skip the test if the driver is not PostgreSQL
        $driver = $this->db->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'pgsql') {
            $this->markTestSkipped('This test is only applicable for PostgreSQL.');
        }

        // Execute the UPDATE query with RETURNING clause
        $updatedAge = $this->db->getOne('UPDATE test SET age = age + 5 WHERE name = ? RETURNING age', ['Alice']);

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


