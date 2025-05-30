# microDbal

**microDbal** is a minimal PHP database abstraction layer.  
It wraps PDO to give you clean, safe, and simple database access.  

## 🚀 Why microDbal?

- ✅ Micro by design – no ORM, no query builder, no dependencies, no annotations
- ✅ Just SQL in, data out – fetch results as arrays or objects
- ✅ Great for prototyping or learning SQL from a PHP-first perspective
- ✅ Write raw SQL your way – use named (:name) or positional (?) placeholders
- ✅ Run prepared statements and fetch results in a single step
- ✅ **Inspired by** the PDO article series from [PHP Delusions](https://phpdelusions.net/)

💬 If you like microDbal, leave a ⭐ on GitHub — it really helps!

## Tested with those Databases
Firebird, MySQL / MariaDB, MS SQL Server, PostgreSQL, SQLite.  
Should support any database that is compatible with PHP's PDO.

## Installation
Install via Composer:
```bash
composer require a-le/microdbal
```
Or alternatively:  
Simply drop MicroDbal.php into your project folder and include it manually.



## Usage

**Note:** SQL must be adapted to your targeted database. The following examples use SQL syntax for **SQLite**.

### 1. Autoload with composer
```php
require __DIR__ . '/vendor/autoload.php'; // Include the Composer autoloader
use aLe\MicroDbal;
```

#### Or alternatively:
```php
require 'microdbal.php';
use aLe\MicroDbal;
```

### 2. Connect to the Database
```php
// Constructor signature: __construct(string $dsn, ?string $username = null, ?string $password = null)
$db = new MicroDbal('sqlite::memory:');
```

---

### 3. Run Queries
```php
// Method signature: run(string $sql, array $args = [], ?int &$affectedRows = null): PDOStatement
$db->run('CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR, age INTEGER)');
$db->run('INSERT INTO test (name, age) VALUES (?, ?)', ['Pauline', 25]); // question mark placeholder
$db->run('INSERT INTO test (name, age) VALUES (:name, :age)', ['name' => 'Ryan', 'age' => 15]); // named placeholder
```

---

### 4. Get All Rows
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

### 5. Get a Single Row
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

### 6. Get a Single Column
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

### 7. Get a Single Value
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

### 8. Get Affected Rows
```php
$db->run('INSERT INTO test (name, age) VALUES (?, ?)', ['Marty', 65], $affectedRows);
print_r($affectedRows);
```
**Output:**
```php
1
```

---

### 9. Get Last Inserted ID
```php
print_r($db->getLastInsertedId());
```
**Output:**
```php
3
```

---

### 10. Get Column Metadata
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

### 11. Transactions
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

### 12. Fetch Objects
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
### 13. SQL `IN` Helper
```php
// Method signature: sqlIn(array $values): string
$arr = [1, 2, 3];
$sqlFragment = $db->sqlIn($arr);
$result = $db->getAll('SELECT * FROM test WHERE id IN ' . $sqlFragment, $arr);
```

---
### 14. SQL `LIKE` Helper
```php
// Method signature: sqlLike(string $value, string $escapeChar = '\\'): string
$s = 'P';
$arg = $db->sqlLike($s). '%';
$result = $db->getAll('SELECT * FROM test WHERE name LIKE ?', [$arg]);
```
---

### 15. Access the PDO Instance
The underlying PDO instance is exposed as a public property: `$db->pdo`.  
You can use it directly for uncovered operations or to integrate with other libraries that expect a PDO object.

For example, using [delight-im/PHP-Auth](https://github.com/delight-im/PHP-Auth), which requires a PDO connection:

```php
$auth = new \Delight\Auth\Auth($db->pdo);
```

---
### 16. Access the PDO Statement Object

The `run` method returns a standard PDOStatement object, which you can use as needed — for example, to manually fetch rows one by one:

```php
$stmt = $db->run('SELECT * FROM test');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Do something with $row
}
```
This gives you full control over result fetching, especially useful for large datasets or custom processing.

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

## Running Tests
To run tests using PHPUnit:
1. Clone the repository with composer :
   ```bash
   composer install a-le/microdbal
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
First stable version just released (april, 2025).

## 🤝 Contributing

Contributions are very welcome!  
Feel free to open an issue for bug reports, feature requests, or questions.

> 🛠️ This library aims to remain **micro**.  
> If you'd like to submit a pull request, please open an issue or discussion first to make sure it aligns with the project's goals.


## License
This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.