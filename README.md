# Macpro Accounting

A customized **FrontAccounting ERP** application for small and medium enterprises. Built on top of [FrontAccounting](http://frontaccounting.com), it provides double-entry accounting, multi-currency support, and a document-based interface for everyday business activity.

---

## Requirements

Before starting, make sure the following are installed on your server:

| Requirement | Version |
|---|---|
| Web server (Apache with mod_php or IIS) | Any modern version |
| PHP | >= 5.6 (7.x recommended) |
| MySQL or MariaDB | MySQL >= 4.1 with InnoDB enabled, or any MariaDB version |

### PHP Configuration

- Set `session.auto_start = 0` in `php.ini`
- Set `register_globals = Off` and `magic_quotes_gpc = Off` in `php.ini` (CGI mode only; Apache mod_php handles this via `.htaccess` automatically)
- Ensure **InnoDB** tables are enabled in MySQL

---

## How to Start the Application

### 1. Deploy the application files

Copy the `macproacc/` folder to your web server's document root. For example on Linux:

```bash
cp -r macproacc/ /var/www/html/macproacc
```

Or point your virtual host directly at the `macproacc/` directory.

### 2. Configure the database connection

Edit `macproacc/config_db.php` and fill in your MySQL credentials:

```php
$db_connections = array(
  0 => array(
    'name'       => 'My Company',
    'host'       => 'localhost',
    'port'       => '',
    'dbname'     => 'your_database_name',
    'collation'  => 'utf8_general_ci',
    'tbpref'     => '0_',
    'dbuser'     => 'your_db_user',
    'dbpassword' => 'your_db_password',
  ),
);
```

### 3. (First-time only) Run the installer

If the database has not been set up yet, navigate to the install URL in your browser:

```
http://localhost/macproacc/install/
```

Follow the on-screen wizard to create the database schema and an admin account.  
**After installation completes, delete or rename the `install/` directory for security.**

### 4. Open the application

Open your browser and go to:

```
http://localhost/macproacc/
```

### 5. Log in

Use the credentials you created during installation:

- **Username:** `admin`
- **Password:** *(the password you set during install)*

---

## Project Structure

| Folder / File | Description |
|---|---|
| `macproacc/` | Main application (customized FrontAccounting) |
| `origmacproacc/` | Original unmodified FrontAccounting source |
| `macproacc/config.php` | Application settings (timezone, debug, title, etc.) |
| `macproacc/config_db.php` | Database connection settings |
| `macproacc/company/` | Per-company data and cache |
| `macproacc/sql/` | SQL schema and seed data |

---

## Troubleshooting

- **Blank page or session errors:** Check that `session.auto_start = 0` in `php.ini`.
- **Database connection fails:** Verify credentials in `config_db.php` and that the MySQL service is running.
- **InnoDB errors:** Make sure InnoDB is enabled in your MySQL configuration (`innodb_file_per_table = ON`).
- **HTTPS required error:** If running locally over plain HTTP, open `macproacc/includes/session.inc` and set `SECURE_ONLY` to `false`.
- **Permission errors on uploads/logs:** Make sure the web server user has write access to `macproacc/company/` and `macproacc/tmp/`.
