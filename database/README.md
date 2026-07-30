# MariaDB setup

The app reads MariaDB connection settings from environment variables.
Start from `mariadb.env.example` and set production values outside the web root.

Required values:

```sh
export LOGIC_DB_HOST=127.0.0.1
export LOGIC_DB_PORT=3306
export LOGIC_DB_NAME=logic_app
export LOGIC_DB_USER=logic_app
export LOGIC_DB_PASSWORD=change-this-password
export LOGIC_DB_CHARSET=utf8mb4
```

Socket connections are also supported:

```sh
export LOGIC_DB_SOCKET=/path/to/mariadb.sock
```

Create the database and user in MariaDB:

```sql
CREATE DATABASE logic_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'logic_app'@'localhost' IDENTIFIED BY 'change-this-password';
GRANT ALL PRIVILEGES ON logic_app.* TO 'logic_app'@'localhost';
FLUSH PRIVILEGES;
```

Seed the MariaDB database from the JSON seed files:

```sh
php database/seed-mariadb.php
```

Verify schema creation, seed counts, and example/exercise insert-update-delete:

```sh
php database/verify-mariadb.php
```

The seed JSON files were regenerated from `database/logic.sqlite`, so the current SQLite examples and exercises are the MariaDB seed source.

Content management:

- Browser UI: `/admin.php`
- JSON API: `/api/admin-content.php?resource=examples`
- JSON API: `/api/admin-content.php?resource=exercises`
- Reseed endpoint: `POST /api/admin-content.php?resource=seed`

Admin access uses the `users` table. A temporary administrator is created automatically:
username `admin`, password `admin`. Change this password before production use.
