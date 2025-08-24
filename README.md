# Inventory Database and PHP Setup

This document provides step-by-step instructions to install MariaDB, create an `inventory_db` database with a table, set up a database user, and install PHP (with curl support).

---

## Installation and Setup Instructions

### 1. Install MariaDB

On **Debian/Ubuntu**:
```bash
sudo apt update
sudo apt install mariadb-server mariadb-client -y
```
### On **CentOS/RHEL**:
```bash
sudo yum install mariadb-server mariadb -y
```
## Enable and Start MariaDB Service
```bash
sudo systemctl enable mariadb
sudo systemctl start mariadb
```
## Create Database and User
```bash
sudo mariadb
```
```sql
-- Create the database
CREATE DATABASE inventory_db;

-- Create a user with a strong password
CREATE USER 'inventory_user'@'localhost' IDENTIFIED BY 'strongpassword';

-- Grant privileges to the new user
GRANT ALL PRIVILEGES ON inventory_db.* TO 'inventory_user'@'localhost';
FLUSH PRIVILEGES;

-- Switch to the new database
USE inventory_db;

-- Create the inventory table
CREATE TABLE inventory (
    barcode       VARCHAR(50)  NOT NULL PRIMARY KEY,
    product_name  VARCHAR(255) NOT NULL,
    quantity      INT(11)      NOT NULL DEFAULT 0,
    serial_number VARCHAR(100) NULL,
    category      VARCHAR(50)  NOT NULL DEFAULT 'Other'
);

-- Verify the table structure
DESCRIBE inventory;
```
You should see the table structure as defined above.
```sql
+---------------+--------------+------+-----+---------+-------+
| Field         | Type         | Null | Key | Default | Extra |
+---------------+--------------+------+-----+---------+-------+
| barcode       | varchar(50)  | NO   | PRI | NULL    |       |
| product_name  | varchar(255) | NO   |     | NULL    |       |
| quantity      | int(11)      | NO   |     | 0       |       |
| serial_number | varchar(100) | YES  |     | NULL    |       |
| category      | varchar(50)  | NO   |     | Other   |       |
+---------------+--------------+------+-----+---------+-------+
```
Now exit the MariaDB shell:
```sql
EXIT;
```
### 2. Install PHP with curl support
On **Debian/Ubuntu**:
```bash
sudo apt update
```
Install desired PHP with required extensions. Replace * with desired version:
```bash
sudo apt install php php-mysqli php-curl -y
```
### On **CentOS/RHEL**:
```bash
sudo yum install epel-release -y
sudo yum install php php-mysqlnd php-curl -y
```
### Verify PHP Installation
```bash
php -v
```
You should see the installed PHP version.
### 3. Configure PHP Files
- Update `index.php`, `scan.php`, and `auth.php` with your database credentials and PIN.
- Ensure the web server user has permission to read these files.
- Place the PHP files in your web server's root directory (e.g., `/var/www/html` for Apache).
- Restart your web server to apply changes:
```bash
sudo systemctl restart apache2  # For Apache on Debian/Ubuntu
sudo systemctl restart httpd    # For Apache on CentOS/RHEL
``` 
### 4. Access the Application
Open your web browser and navigate to `http://your_server_ip/index.php` to access the inventory application.
Make sure to replace `your_server_ip` with the actual IP address or domain name of your server.