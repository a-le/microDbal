# MicroDbal Library

MicroDbal is a lightweight database abstraction library built on top of PHP's PDO.  
It simplifies common database operations with a straightforward API that focuses on the most commonly used features, ensuring security by exclusively using prepared statements.  
For advanced use cases not covered by the library, the underlying PDO instance remains directly accessible.
This library follows the best practices outlined at [PHP Delusions](https://phpdelusions.net/pdo).

## Features
- Simplified database operations (fetching rows, executing queries, transactions, etc.)
- Lightweight and easy to integrate
- Helper methods for SQL `IN` clauses and `LIKE` clauses
- Let use the underlying PDO instance directly if ever needed

## Tested with those Databases
- [x] SQLite
- [x] PostgreSQL
- [ ] MySQL/MariaDB
- [ ] Firebird
- [ ] ClickHouse
- [ ] DuckDB

Note that it should supports any databases that PHP PDO supports.

## Installation
Install via Composer:
```bash
composer require aLe/microdbal
```

## Usage
Here’s a list of all methods provided by the `MicroDbal` class:

- `__construct(string $dsn, ?string $username = null, ?string $password = null, array $options = [])`
- `run(string $sql, array $args = []): PDOStatement|false`
- `get(string $sql, array $args = []): array|false`
- `getAll(string $sql, array $args = []): array`
- `getRow(string $sql, array $args = []): array`
- `getFirstColumn(string $sql, array $args = []): array`
- `getOne(string $sql, array $args = []): mixed`
- `getOneObject(string $sql, string $className, array $args = [], array $constructorArgs = []): mixed`
- `getAllObjects(string $sql, string $className, array $args = [], array $constructorArgs = []): array`
- `getLastInsertedId(?string $name = null): string|false`
- `getRowCount(): int`
- `beginTrans(): bool`
- `commitTrans(): bool`
- `rollBackTrans(): bool`
- `inTrans(): bool`
- `sqlInInt(array $arr): string`
- `sqlInStr(array $arr): string`
- `sqlLikeEscape(string $str, string $escape = '\\'): string`


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