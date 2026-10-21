# AIP Management System

Traditional PHP web application using HTML, CSS, and vanilla JavaScript.

## Current state

- Login flow uses a PHP session and demo credentials for the interface preview.
- Dashboard and supporting pages use clearly marked static arrays in each page.
- `config/database.php` contains a PDO connection placeholder for an existing MySQL database.
- No database, tables, migrations, seeders, or sample records are created.

## Demo access

- Username: `admin`
- Password: `admin123`

## Connecting existing data

Replace the placeholder connection values in `config/database.php`, then replace the static arrays in the page files with prepared PDO queries against the existing schema.
