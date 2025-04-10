# MicroDbal Library

MicroDbal is a lightweight database abstraction library built on top of PHP's PDO.  
The API focuses on the most common database operations, ensuring security by exclusively using prepared statements. 
For advanced use cases not covered by the library, the underlying PDO instance remains directly accessible.
This library follows the best practices outlined at [PHP Delusions](https://phpdelusions.net/pdo).

## Features
- Simple API for common database operations
- Lightweight and easy to integrate
- Queries can uses named placeholders (:name) or positional placeholders (?)
- Helper methods for SQL `IN` clauses and `LIKE` clauses
- Let use the underlying PDO instance directly if ever needed

## Tested with those Databases
- [ ] DuckDB
- [x] Firebird
- [x] MySQL / MariaDB
- [x] MS SQL Server
- [x] PostgreSQL
- [x] SQLite
- [x] supports all databases that are compatible with PHP's PDO.

## Installation
Install via Composer:
```bash
composer require aLe/microdbal
```

## Usage
Here’s a list of all methods provided by the `MicroDbal` class, along with examples :

- `__construct(string $dsn, ?string $username = null, ?string $password = null, array $options = [])`  
  **Example:**
  ```php
  $db = new MicroDbal('sqlite::memory:');
  ```

- `run(string $sql, array $args = []): PDOStatement|false`  
  **Example:**
  ```php
  $db->run('INSERT INTO users (name, age) VALUES (:name, :age)', ['name' => 'Alice', 'age' => 30]); // named placeholders
  $db->run('INSERT INTO users (name, age) VALUES (?, ?)', ['Alice', 30]); // positional placeholders
  ```

- `get(string $sql, array $args = []): array|false`  
  **Example:**
  ```php
  $user = $db->get('SELECT * FROM users WHERE id = :id', ['id' => 1]);
  ```

- `getAll(string $sql, array $args = []): array`  
  **Example:**
  ```php
  $users = $db->getAll('SELECT * FROM users WHERE age > :age', ['age' => 18]);
  ```

- `getRow(string $sql, array $args = []): array`  
  **Example:**
  ```php
  $user = $db->getRow('SELECT * FROM users WHERE id = :id', ['id' => 1]);
  ```

- `getFirstColumn(string $sql, array $args = []): array`  
  **Example:**
  ```php
  $names = $db->getFirstColumn('SELECT id FROM users WHERE age > :age', ['age' => 18]);
  ```

- `getOne(string $sql, array $args = []): mixed`  
  **Example:**
  ```php
  $age = $db->getOne(sql: 'SELECT age FROM users WHERE id = :id', ['id' => 1]);
  ```

- `getOneObject(string $sql, array $args = [], string $className, array $constructorArgs = []): mixed`  
  **Example:**
  ```php
  $user = $db->getOneObject(sql: 'SELECT * FROM users WHERE id = :id', User::class, ['id' => 1]);
  ```

- `getAllObjects(string $sql, array $args = [], string $className, array $constructorArgs = []): array`  
  **Example:**
  ```php
  $users = $db->getAllObjects(sql: 'SELECT * FROM users WHERE age > :age', className: User::class, ['age' => 18]);
  ```

- `getLastInsertedId(?string $name = null): string|false`  
- `getRowCount(): int`  
- `beginTrans(): bool`  
- `commitTrans(): bool`  
- `rollBackTrans(): bool`  
- `inTrans(): bool`  

- `sqlIn(array $arr): string`  
  **Example:**
  ```php
  $ids = [1, 2, 3];
  $sqlFragment = $db->sqlIn($ids); // $sqlFragment will be '(?,?,?)';
  $users = $db->getAll("SELECT * FROM users WHERE id IN $sqlFragment", $ids);
  ```

- `sqlLikeEscape(string $str, string $escape = '\\'): string`  
  **Example:**
  ```php
  $search = $db->sqlLikeEscape('A', '\\').'%';
  $users = $db->getAll("SELECT * FROM users WHERE name LIKE ? ESCAPE '\\'", [$search]);
  ```


## Running Tests
To run tests using PHPUnit:
1. Install dependencies:
   ```bash
   composer install
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