# HealthPlus — Pharmacy Management & Billing System

A complete pharmacy management and point-of-sale billing system built with **plain PHP 8+ and MySQL** (PDO). No frameworks, no build step, no external CDNs — copy the folder to any PHP server and run.

## Features

- **POS billing** with live product search, cart, discount (flat/percent), tax, multiple payment methods, and printable invoices.
- **Batch / lot tracking** with expiry dates and automatic **FEFO** (First-Expiry-First-Out) stock deduction on every sale.
- **Full stock audit trail** (`stock_movements`) for every in / out / return / adjustment.
- **Inventory**: medicines, categories, suppliers, per-batch stock, reorder levels, barcode, rack location, Rx flag.
- **Purchases** (stock-in) with multi-item entry and payment status.
- **Sales history** with returns/refunds that restock the correct batches.
- **Customers** with spend history; **prescriptions** with image/PDF upload.
- **Expiry & low-stock alerts** dashboard.
- **Reports**: revenue, profit, items sold, payment-method breakdown, daily chart, top medicines, sales by category, purchases, live inventory valuation, plus CSV export and print.
- **Role-based access**: `admin`, `pharmacist`, `cashier`.
- Security: PDO prepared statements everywhere, CSRF tokens on all forms, hashed passwords (`password_hash`/bcrypt), session regeneration on login, output escaping.

## Requirements

- PHP 8.0 or newer (uses typed helpers and `str_starts_with`).
- MySQL 5.7+ / MariaDB 10.3+.
- Apache, Nginx, or the built-in PHP server.

## Setup

1. **Create the database and import the schema** (this also loads demo data):

   ```bash
   mysql -u root -p < database.sql
   ```

   This creates a database named `pharmacy_db`.

2. **Configure the DB connection.** Edit `config/database.php` and set your host, DB name, username, and password:

   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_NAME', 'pharmacy_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');   // set your MySQL password here
   ```

3. **Create the admin login.** Open `install.php` in your browser once:

   ```
   http://localhost/pharmacy/install.php
   ```

   > **Why this step exists (the one unusual thing):** MySQL cannot generate a bcrypt hash, so the admin password is *not* hard-coded in `database.sql`. Instead `install.php` calls PHP's `password_hash()` to create (or reset) the admin account securely. This is the correct, secure way to seed the first password.

   Default credentials created: **username `admin` / password `admin123`**.

4. **Delete `install.php`** after you've run it:

   ```bash
   rm install.php
   ```

5. **Log in** at `http://localhost/pharmacy/` and change the admin password under **Users**, and your shop details under **Settings**.

### Quick run with PHP's built-in server

```bash
cd pharmacy
php -S localhost:8000
# then open http://localhost:8000
```

## Default roles

| Role        | Can do                                                                 |
|-------------|-----------------------------------------------------------------------|
| admin       | Everything, incl. Users & Settings                                    |
| pharmacist  | Sales, inventory, purchases, suppliers, reports, returns              |
| cashier     | POS sales, view medicines/customers, record prescriptions            |

## Notes

- The `uploads/` folder stores prescription scans. It ships with an `.htaccess` that disables script execution there; make sure the folder is writable by the web server (`chmod 755 uploads`).
- Demo data includes 8 medicines with opening-stock batches (one deliberately near expiry so the alerts page has something to show) and a "Walk-in Customer".
- Invoice numbers follow the `INV-000001` format; the prefix is configurable in **Settings**.
- Currency symbol and default tax rate are set in **Settings** and applied across billing and reports.

## Project layout

```
pharmacy/
├─ config/database.php      PDO connection (edit this)
├─ includes/                functions.php, header.php, footer.php
├─ assets/css, assets/js    custom stylesheet + tiny progressive-enhancement JS
├─ uploads/                 prescription files (writable)
├─ database.sql             schema + seed data
├─ install.php              one-time admin creator (delete after use)
├─ index.php / logout.php   auth
├─ dashboard.php            KPIs & alerts
├─ pos.php / invoice.php    billing + printable receipt
├─ sales.php                sales history + returns
├─ medicines.php / medicine_edit.php / batches.php
├─ categories.php / suppliers.php / customers.php
├─ purchases.php / purchase_add.php / purchase_view.php
├─ prescriptions.php
├─ expiry.php               expiry & low-stock alerts
├─ reports.php              analytics + CSV export
├─ users.php                staff accounts (admin)
└─ settings.php             shop configuration (admin)
```
