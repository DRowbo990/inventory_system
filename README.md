# Inventory Management System

A simple web-based inventory management system built with PHP and MariaDB, featuring barcode scanning capabilities.

## Features

- Inventory tracking with quantities and categories
- PIN-based authentication
- Product name, serial number, and category management
- Real-time inventory updates

## Quick Start

**Option 1: Automatic Installation (Recommended)**
```bash
chmod +x install.sh
./install.sh
```

**Option 2: Manual Installation**
Follow the detailed instructions below.

---

## Manual Installation Instructions

### 1. Install and Configure MariaDB

#### Install MariaDB Server

**On Debian/Ubuntu:**
```bash
sudo apt update
sudo apt install mariadb-server mariadb-client -y
```

**On CentOS/RHEL/Fedora:**
```bash
sudo yum install mariadb-server mariadb -y
# OR for newer versions:
# sudo dnf install mariadb-server mariadb -y
```

#### Start and Enable MariaDB Service
```bash
sudo systemctl enable mariadb
sudo systemctl start mariadb
sudo systemctl status mariadb  # Verify it's running
```

#### Secure MariaDB Installation (Optional but Recommended)
```bash
sudo mysql_secure_installation
```

### 2. Create Database and User
#### Connect to MariaDB and Create Database
```bash
sudo mariadb
```

Run the following SQL commands in the MariaDB shell:

```sql
-- Create the inventory database
CREATE DATABASE inventory_db;

-- Create a dedicated user with secure password
-- ⚠️ IMPORTANT: Replace 'your_secure_password' with a strong password
CREATE USER 'inventory_user'@'localhost' IDENTIFIED BY 'your_secure_password';

-- Grant necessary privileges to the user
GRANT ALL PRIVILEGES ON inventory_db.* TO 'inventory_user'@'localhost';
FLUSH PRIVILEGES;

-- Switch to the inventory database
USE inventory_db;

-- Create the main inventory table
CREATE TABLE inventory (
    barcode       VARCHAR(50)  NOT NULL PRIMARY KEY,
    product_name  VARCHAR(255) NOT NULL,
    quantity      INT(11)      NOT NULL DEFAULT 0,
    serial_number VARCHAR(100) NULL,
    category      VARCHAR(50)  NOT NULL DEFAULT 'Other',
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Verify the table was created successfully
DESCRIBE inventory;
```

#### Expected Table Structure
```
+---------------+--------------+------+-----+---------------------+-------------------------------+
| Field         | Type         | Null | Key | Default             | Extra                         |
+---------------+--------------+------+-----+---------------------+-------------------------------+
| barcode       | varchar(50)  | NO   | PRI | NULL                |                               |
| product_name  | varchar(255) | NO   |     | NULL                |                               |
| quantity      | int(11)      | NO   |     | 0                   |                               |
| serial_number | varchar(100) | YES  |     | NULL                |                               |
| category      | varchar(50)  | NO   |     | Other               |                               |
| created_at    | timestamp    | NO   |     | current_timestamp() |                               |
| updated_at    | timestamp    | NO   |     | current_timestamp() | on update current_timestamp() |
+---------------+--------------+------+-----+---------------------+-------------------------------+
```

#### Exit MariaDB Shell
```sql
EXIT;
```

### 3. Install PHP and Required Extensions
**On Debian/Ubuntu:**
```bash
sudo apt update
sudo apt install php php-mysqli php-curl php-json php-mbstring -y
```

**On CentOS/RHEL/Fedora:**
```bash
# For CentOS/RHEL 7
sudo yum install epel-release -y
sudo yum install php php-mysqlnd php-curl php-json php-mbstring -y

# For CentOS/RHEL 8+ or Fedora
sudo dnf install php php-mysqlnd php-curl php-json php-mbstring -y
```

#### Verify PHP Installation
```bash
php -v
php -m | grep -E "(mysqli|curl|json)"
```

### 4. Install and Configure Web Server (Apache)

**On Debian/Ubuntu:**
```bash
sudo apt install apache2 -y
sudo systemctl enable apache2
sudo systemctl start apache2
```

**On CentOS/RHEL/Fedora:**
```bash
# For CentOS/RHEL 7
sudo yum install httpd -y

# For CentOS/RHEL 8+ or Fedora
sudo dnf install httpd -y

sudo systemctl enable httpd
sudo systemctl start httpd
```

#### Configure Firewall (if enabled)
```bash
# For UFW (Ubuntu/Debian)
sudo ufw allow 'Apache Full'

# For firewalld (CentOS/RHEL/Fedora)
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

### 5. Deploy Application Files

#### Copy Files to Web Directory
```bash
# Create backup of default index (optional)
sudo mv /var/www/html/index.html /var/www/html/index.html.backup 2>/dev/null || true

# Copy application files
sudo cp *.php *.html /var/www/html/
sudo chown www-data:www-data /var/www/html/*.php /var/www/html/*.html  # Debian/Ubuntu
# sudo chown apache:apache /var/www/html/*.php /var/www/html/*.html    # CentOS/RHEL
sudo chmod 644 /var/www/html/*.php /var/www/html/*.html
```

### 6. Configure Application

#### Update Database Configuration
Edit the PHP files to configure your database connection:

1. **Edit database credentials in PHP files:**
   ```bash
   sudo nano /var/www/html/auth.php
   ```
   Update the following variables:
   ```php
   $db_host = 'localhost';
   $db_user = 'inventory_user';
   $db_pass = 'your_secure_password';  // Use the password you set earlier
   $db_name = 'inventory_db';
   ```

2. **Set your PIN in auth.php:**
   ```php
   $correct_pin = '1234';  // Change this to your desired PIN
   ```

#### Restart Web Server
```bash
sudo systemctl restart apache2   # Debian/Ubuntu
# sudo systemctl restart httpd   # CentOS/RHEL
```

### 7. Test the Installation

#### Verify Services are Running
```bash
sudo systemctl status mariadb
sudo systemctl status apache2    # or httpd on CentOS/RHEL
```

#### Test Database Connection
```bash
mysql -u inventory_user -p inventory_db -e "SELECT 'Database connection successful' as status;"
```

#### Access the Application
1. Open your web browser
2. Navigate to: `http://your_server_ip/index.php`
3. Replace `your_server_ip` with:
   - `localhost` if running locally
   - Your server's IP address if running on a remote server
   - Your domain name if you have one configured

## Security Considerations

⚠️ **Important Security Notes:**

1. **Change Default PIN:** Update the PIN in `auth.php` from the default value
2. **Use Strong Database Password:** Don't use weak passwords for the database user
3. **Enable HTTPS:** Configure SSL/TLS certificates for production use
4. **Regular Updates:** Keep your system and packages updated
5. **Firewall Configuration:** Only open necessary ports
6. **Backup Strategy:** Implement regular database backups

## Troubleshooting

### Common Issues

1. **Permission Denied Errors:**
   ```bash
   sudo chown -R www-data:www-data /var/www/html/
   sudo chmod -R 644 /var/www/html/*.php
   ```

2. **Database Connection Failed:**
   - Verify MariaDB is running: `sudo systemctl status mariadb`
   - Check credentials in PHP files
   - Test connection manually: `mysql -u inventory_user -p`

3. **PHP Not Working:**
   - Verify PHP is installed: `php -v`
   - Check Apache PHP module: `sudo a2enmod php7.4` (adjust version)
   - Restart Apache: `sudo systemctl restart apache2`

4. **Barcode Scanner Not Working:**
   - Ensure HTTPS is enabled (cameras require secure connection)
   - Check browser permissions for camera access
   - Verify the device has a camera

### Log Files
- Apache logs: `/var/log/apache2/error.log` (Debian/Ubuntu) or `/var/log/httpd/error_log` (CentOS/RHEL)
- MariaDB logs: `/var/log/mysql/error.log`

## File Structure

```
inventory/
├── index.php          # Main inventory dashboard
├── scan.php           # Barcode scanning handler
├── scan.html          # Barcode scanning interface
├── auth.php           # Authentication and database configuration
├── install.sh         # Automated installation script
├── README.md          # This documentation
└── LICENSE            # License information
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is licensed under the terms specified in the LICENSE file.