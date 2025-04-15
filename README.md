# microDbal

**microDbal** is a minimal PHP database abstraction layer that wraps PDO to give you clean, safe, and simple database access.  
No ORM. No query builder. No magic.  
Just SQL in, arrays out.
For advanced use cases not covered by the library, the underlying PDO instance remains directly accessible.

## 🚀 Why microDbal?

- ✅ Write raw SQL the way you want with named placeholders (:name) or positional placeholders (?)
- ✅ Run query with prepared statements and get results in only 1 step
- ✅ Get results as arrays or objects

## Tested with those Databases
- ✅ Firebird
- ✅ MySQL / MariaDB
- ✅ MS SQL Server
- ✅ PostgreSQL
- ✅ SQLite
- ✅ should support any database that is compatible with PHP's PDO.

## Installation
Install via Composer:
```bash
composer require a-le/microdbal
```

## Usage

**Note:** SQL must be adapted to your targeted database. The following examples use SQL syntax for **SQLite**.

### 1. Connect to the Database
```php
// Constructor signature: __construct(string $dsn, ?string $username = null, ?string $password = null)
$db = new MicroDbal('sqlite::memory:');
```

---

### 2. Run Queries
```php
// Method signature: run(string $sql, array $args = [], ?int &$affectedRows = null): PDOStatement
$db->run('CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR, age INTEGER)');
$db->run('INSERT INTO test (name, age) VALUES (?, ?)', ['Pauline', 25]); // question mark placeholder
$db->run('INSERT INTO test (name, age) VALUES (:name, :age)', ['name' => 'Ryan', 'age' => 15]); // named placeholder
```

---

### 3. Get All Rows
```php
// Method signature: getAll(string $sql, array $args = [], ?array &$columnsMeta = null): array
$rows = $db->getAll('SELECT * FROM test WHERE age > ?', [10]);
print_r($rows);
```
**Output:**
```php
Array
(
    [0] => Array
        (
            [id] => 1
            [name] => Pauline
            [age] => 25
        ),
    [1] => Array
        (
            [id] => 2
            [name] => Ryan
            [age] => 15
        )
)
```

---

### 4. Get a Single Row
```php
// Method signature: getRow(string $sql, array $args = [], ?array &$columnsMeta = null): array
$row = $db->getRow('SELECT * FROM test WHERE id = ?', [1]);
print_r($row);
```
**Output:**
```php
Array
(
    [id] => 1
    [name] => Pauline
    [age] => 25
)
```

---

### 5. Get a Single Column
```php
// Method signature: getCol(string $sql, array $args = []): array
$col = $db->getCol('SELECT id FROM test WHERE age >= ?', [10]);
print_r($col);
```
**Output:**
```php
Array
(
    [0] => 1,
    [1] => 2,
)
```

---

### 6. Get a Single Value
```php
// Method signature: getOne(string $sql, array $args = []): mixed
$cnt = $db->getOne('SELECT COUNT(*) FROM test WHERE age < ?', [18]);
print_r($cnt);
```
**Output:**
```php
1
```

---

### 7. Get Affected Rows
```php
$db->run('INSERT INTO test (name, age) VALUES (?, ?)', ['Marty', 65], $affectedRows);
print_r($affectedRows);
```
**Output:**
```php
1
```

---

### 8. Get Last Inserted ID
```php
print_r($db->getLastInsertedId());
```
**Output:**
```php
3
```

---

### 9. Get Column Metadata
```php
$db->getAll('SELECT * FROM test WHERE false', [], $columnsMeta);
print_r($columnsMeta);
```
**Output:**
```php
Array
(
    [0] => Array
        (
            [name] => id
            (...)
        )
    [1] => Array
        (
            [name] => name
            (...)
        )
    [2] => Array
        (
            [name] => age
            (...)
        )
)
```

---

### 10. Transactions
```php
$db->beginTransaction();
$db->run('INSERT INTO test (name, age) VALUES (?, ?)', ['John', 30]);
$db->commit();

$db->beginTransaction();
$db->run('INSERT INTO test (name, age) VALUES (?, ?)', ['Jane', 40]);
$db->rollBack();

if ($db->inTransaction()) {
    echo "Transaction is active.";
}
```

---

### 11. Fetch Objects
```php
class Person
{
    public int $id;
    public string $name;
    public int $age;
}

// Method signature: getOneObject(string $sql, array $args = [], string $className): ?object
$person = $db->getOneObject('SELECT * FROM test WHERE id = ?', [1], Person::class);

// Method signature: getAllObjects(string $sql, array $args = [], string $className): array
$peopleAbove18 = $db->getAllObjects('SELECT * FROM test where age > ?', [18], Person::class);
```

---

### 12. SQL `IN` Helper
```php
// Method signature: sqlIn(array $values): string
$untrusted = [1, 2, 3];
$sqlFragment = $db->sqlIn($$untrusted);
$result = $db->getAll('SELECT * FROM test WHERE id IN ' . $sqlFragment, $params);
print_r($result);
```

---

### 13. SQL `LIKE` Helper
```php
// Method signature: sqlLike(string $value, string $escapeChar = '\\'): string
$untrusted = 'A';
$likeClause = $db->sqlLike($untrusted).'%';
$result = $db->getAll('SELECT * FROM test WHERE name LIKE ' . $likeClause);
print_r($result);
```

---
## FAQ

### What happens if there is an error?
- This library enforces `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, which ensures that PDO will throw an exception (`PDOException`) whenever an error occurs.
- These exceptions can be handled like any other PHP error using `try...catch` blocks.

#### Common setups for dealing with PHP errors:
- **Development Environment:**
  - Display errors for debugging purposes.
  - Example configuration in `php.ini`:
    ```ini
    error_reporting = E_ALL
    display_errors = On
    ```

- **Production Environment:**
  - Log errors instead of displaying them to the user.
  - Example configuration in `php.ini`:
    ```ini
    error_reporting = E_ALL
    display_errors = Off
    log_errors = On
    error_log = /path/to/error.log
     ```

With these configurations:
- In development, errors will be displayed to help with debugging.
- In production, errors will be logged to a file to avoid exposing sensitive information to users.

If you need to recover from an error, you can use a `try...catch` block to handle the exception and continue the script's execution.

For more information about the rationale behind this approach, see: [PHP Delusions - Try Catch](https://phpdelusions.net/delusion/try-catch)

---
### Isn't it better to use a query builder than writing SQL by hand ?
- If your code targets multiple databases, the answer is yes ! Query builders helps abstract database-specific differences and make your code more portable.
- However, in other cases, consider the tradeoffs :
  - SQL written directly in your code is always more readable and expressive than generating it with PHP code, provided you are familiar with SQL.
  - You are limited to common SQL functionalities.


## Running Tests
To run tests using PHPUnit:
1. Install with composer:
   ```bash

   ```

2. Run tests on SQLite (default to memory DB if no DSN provided) :
   ```bash
   php run-tests.php
   ```

3. Run tests on any database connection :
   ```bash
   php run-tests.php <dsn> <username> <password>
   ```

   Example
   ```bash
   php run-tests.php "pgsql:dbname=postgres;host=localhost" "user" "password"
   ```

## Status
The API is currently under development and subject to changes. 
Backward compatibility is not guaranteed until a stable version is released.

## Contributing
Contributions are welcome! Feel free to open issues or submit pull requests.
This library is intended to be super light, as the name suggests.

## License
This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.