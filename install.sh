#!/bin/bash

# Inventory Management System - Automated Installation Script
# This script automates the installation of MariaDB, PHP, Apache, and sets up the inventory system

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to detect OS
detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$NAME
        VER=$VERSION_ID
    elif type lsb_release >/dev/null 2>&1; then
        OS=$(lsb_release -si)
        VER=$(lsb_release -sr)
    else
        OS=$(uname -s)
        VER=$(uname -r)
    fi
    
    case $OS in
        *"Ubuntu"*|*"Debian"*|*"Pop"*|*"Mint"*|*"Elementary"*)
            DISTRO="debian"
            ;;
        *"CentOS"*|*"Red Hat"*|*"RHEL"*|*"Fedora"*|*"Rocky"*|*"AlmaLinux"*)
            DISTRO="rhel"
            ;;
        *)
            print_error "Unsupported operating system: $OS"
            print_status "Supported: Ubuntu, Debian, Pop!_OS, Linux Mint, CentOS, RHEL, Fedora, Rocky Linux, AlmaLinux"
            exit 1
            ;;
    esac
}

# Function to check if running as root
check_root() {
    if [[ $EUID -eq 0 ]]; then
        print_error "This script should not be run as root for security reasons."
        print_status "Please run as a regular user. The script will prompt for sudo when needed."
        exit 1
    fi
}

# Function to check if sudo is available
check_sudo() {
    if ! command -v sudo &> /dev/null; then
        print_error "sudo is required but not installed. Please install sudo first."
        exit 1
    fi
}

# Function to install packages on Debian/Ubuntu
install_debian() {
    print_status "Installing packages for Debian/Ubuntu..."
    
    sudo apt update
    
    # Install MariaDB
    print_status "Installing MariaDB..."
    sudo apt install mariadb-server mariadb-client -y
    
    # Install Apache
    print_status "Installing Apache..."
    sudo apt install apache2 -y
    
    # Install PHP and extensions
    print_status "Installing PHP and extensions..."
    sudo apt install php php-mysqli php-curl php-json php-mbstring -y
    
    # Enable and start services
    sudo systemctl enable mariadb apache2
    sudo systemctl start mariadb apache2
    
    # Configure firewall if UFW is active
    if command -v ufw &> /dev/null && sudo ufw status | grep -q "Status: active"; then
        print_status "Configuring UFW firewall..."
        sudo ufw allow 'Apache Full'
    fi
}

# Function to install packages on RHEL/CentOS/Fedora
install_rhel() {
    print_status "Installing packages for RHEL/CentOS/Fedora..."
    
    # Determine package manager
    if command -v dnf &> /dev/null; then
        PKG_MGR="dnf"
    else
        PKG_MGR="yum"
        # Install EPEL for older systems
        sudo yum install epel-release -y
    fi
    
    # Install MariaDB
    print_status "Installing MariaDB..."
    sudo $PKG_MGR install mariadb-server mariadb -y
    
    # Install Apache
    print_status "Installing Apache..."
    sudo $PKG_MGR install httpd -y
    
    # Install PHP and extensions
    print_status "Installing PHP and extensions..."
    sudo $PKG_MGR install php php-mysqlnd php-curl php-json php-mbstring -y
    
    # Enable and start services
    sudo systemctl enable mariadb httpd
    sudo systemctl start mariadb httpd
    
    # Configure firewall if firewalld is active
    if command -v firewall-cmd &> /dev/null && sudo firewall-cmd --state &> /dev/null; then
        print_status "Configuring firewalld..."
        sudo firewall-cmd --permanent --add-service=http
        sudo firewall-cmd --permanent --add-service=https
        sudo firewall-cmd --reload
    fi
}

# Function to secure MariaDB installation
secure_mariadb() {
    print_status "Starting MariaDB security setup..."
    print_warning "You will be prompted to set a root password and secure the installation."
    
    # Check if MariaDB is running
    if ! sudo systemctl is-active --quiet mariadb; then
        print_error "MariaDB is not running. Please check the installation."
        exit 1
    fi
    
    # Run mysql_secure_installation
    print_status "Running mysql_secure_installation..."
    print_warning "Please follow the prompts to secure your MariaDB installation."
    echo "Recommended answers: Y, Y, Y, Y, Y"
    sudo mysql_secure_installation
}

# Function to create database and user
setup_database() {
    print_status "Setting up inventory database..."
    
    # Prompt for database password
    echo
    print_status "Please enter a secure password for the inventory_user database account:"
    read -s -p "Password: " DB_PASSWORD
    echo
    read -s -p "Confirm password: " DB_PASSWORD_CONFIRM
    echo
    
    if [ "$DB_PASSWORD" != "$DB_PASSWORD_CONFIRM" ]; then
        print_error "Passwords do not match. Please run the script again."
        exit 1
    fi
    
    if [ ${#DB_PASSWORD} -lt 8 ]; then
        print_error "Password must be at least 8 characters long."
        exit 1
    fi
    
    # Create SQL commands file
    cat > /tmp/setup_db.sql << EOF
CREATE DATABASE IF NOT EXISTS inventory_db;
CREATE USER IF NOT EXISTS 'inventory_user'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON inventory_db.* TO 'inventory_user'@'localhost';
FLUSH PRIVILEGES;

USE inventory_db;

CREATE TABLE IF NOT EXISTS inventory (
    barcode       VARCHAR(50)  NOT NULL PRIMARY KEY,
    product_name  VARCHAR(255) NOT NULL,
    quantity      INT(11)      NOT NULL DEFAULT 0,
    serial_number VARCHAR(100) NULL,
    category      VARCHAR(50)  NOT NULL DEFAULT 'Other',
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
EOF
    
    # Execute SQL commands
    print_status "Creating database and user..."
    if sudo mysql < /tmp/setup_db.sql; then
        print_success "Database and user created successfully!"
    else
        print_error "Failed to create database. Please check MariaDB installation."
        rm -f /tmp/setup_db.sql
        exit 1
    fi
    
    # Clean up
    rm -f /tmp/setup_db.sql
    
    # Save database password for later use
    DB_PASS="$DB_PASSWORD"
}

# Function to deploy application files
deploy_files() {
    print_status "Deploying application files..."
    
    # Determine web directory based on distro
    if [ "$DISTRO" = "debian" ]; then
        WEB_DIR="/var/www/html"
        WEB_USER="www-data"
        WEB_GROUP="www-data"
    else
        WEB_DIR="/var/www/html"
        WEB_USER="apache"
        WEB_GROUP="apache"
    fi
    
    # Backup existing index.html if it exists
    if [ -f "$WEB_DIR/index.html" ]; then
        sudo mv "$WEB_DIR/index.html" "$WEB_DIR/index.html.backup"
    fi
    
    # Copy PHP and HTML files
    sudo cp *.php *.html "$WEB_DIR/" 2>/dev/null || {
        print_error "Failed to copy application files. Make sure you're running this script from the inventory directory."
        exit 1
    }
    
    # Set proper ownership and permissions
    sudo chown $WEB_USER:$WEB_GROUP "$WEB_DIR"/*.php "$WEB_DIR"/*.html
    sudo chmod 644 "$WEB_DIR"/*.php "$WEB_DIR"/*.html
    
    print_success "Application files deployed successfully!"
}

# Function to configure application
configure_app() {
    print_status "Configuring application..."
    
    # Determine web directory
    if [ "$DISTRO" = "debian" ]; then
        WEB_DIR="/var/www/html"
        SERVICE_NAME="apache2"
    else
        WEB_DIR="/var/www/html"
        SERVICE_NAME="httpd"
    fi
    
    # Prompt for PIN
    echo
    print_status "Please enter a 4-digit PIN for the application:"
    read -p "PIN: " APP_PIN
    
    if ! [[ "$APP_PIN" =~ ^[0-9]{4}$ ]]; then
        print_error "PIN must be exactly 4 digits."
        exit 1
    fi
    
    # Update auth.php with database credentials and PIN
    if [ -f "$WEB_DIR/auth.php" ]; then
        # Create a temporary file with the updated configuration
        sudo sed -i "s/\$db_pass = '.*';/\$db_pass = '$DB_PASS';/" "$WEB_DIR/auth.php"
        sudo sed -i "s/\$correct_pin = '.*';/\$correct_pin = '$APP_PIN';/" "$WEB_DIR/auth.php"
        print_success "Application configured with database credentials and PIN!"
    else
        print_error "auth.php not found. Please check if files were copied correctly."
        exit 1
    fi
    
    # Restart web server
    print_status "Restarting web server..."
    sudo systemctl restart $SERVICE_NAME
}

# Function to test installation
test_installation() {
    print_status "Testing installation..."
    
    # Test MariaDB
    if sudo systemctl is-active --quiet mariadb; then
        print_success "MariaDB is running"
    else
        print_error "MariaDB is not running"
        return 1
    fi
    
    # Test Apache/HTTP
    if [ "$DISTRO" = "debian" ]; then
        SERVICE_NAME="apache2"
    else
        SERVICE_NAME="httpd"
    fi
    
    if sudo systemctl is-active --quiet $SERVICE_NAME; then
        print_success "Web server is running"
    else
        print_error "Web server is not running"
        return 1
    fi
    
    # Test PHP
    if php -v > /dev/null 2>&1; then
        print_success "PHP is installed and working"
    else
        print_error "PHP is not working correctly"
        return 1
    fi
    
    # Test database connection
    if mysql -u inventory_user -p"$DB_PASS" inventory_db -e "SELECT 1;" > /dev/null 2>&1; then
        print_success "Database connection successful"
    else
        print_error "Database connection failed"
        return 1
    fi
    
    print_success "All tests passed!"
}

# Function to display final information
show_completion_info() {
    echo
    echo "=========================================="
    print_success "Installation completed successfully!"
    echo "=========================================="
    echo
    print_status "You can now access your inventory system at:"
    echo "  • Local access: http://localhost/index.php"
    if command -v ip &> /dev/null; then
        LOCAL_IP=$(ip route get 1 | awk '{print $7; exit}' 2>/dev/null)
        if [ -n "$LOCAL_IP" ]; then
            echo "  • Network access: http://$LOCAL_IP/index.php"
        fi
    fi
    echo
    print_status "Configuration Summary:"
    echo "  • Database: inventory_db"
    echo "  • Database User: inventory_user"
    echo "  • Web Directory: $WEB_DIR"
    echo "  • Your PIN: $APP_PIN"
    echo
    print_warning "Important Security Notes:"
    echo "  • Change the default PIN after first login"
    echo "  • Consider enabling HTTPS for production use"
    echo "  • Keep your system and packages updated"
    echo "  • Backup your database regularly"
    echo
    print_status "Troubleshooting:"
    echo "  • Check service status: sudo systemctl status mariadb $SERVICE_NAME"
    echo "  • View Apache logs: sudo tail -f /var/log/apache2/error.log"
    echo "  • View this help: cat README.md"
    echo
}

# Main installation function
main() {
    echo "=========================================="
    echo "Inventory Management System Installer"
    echo "=========================================="
    echo
    
    # Pre-installation checks
    check_root
    check_sudo
    detect_os
    
    print_status "Detected OS: $OS"
    print_status "Distribution type: $DISTRO"
    echo
    
    print_warning "This script will install and configure:"
    echo "  • MariaDB database server"
    echo "  • Apache web server"
    echo "  • PHP with required extensions"
    echo "  • Inventory management application"
    echo
    
    read -p "Do you want to continue? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_status "Installation cancelled."
        exit 0
    fi
    
    echo
    print_status "Starting installation..."
    
    # Install packages based on distribution
    if [ "$DISTRO" = "debian" ]; then
        install_debian
    else
        install_rhel
    fi
    
    # Set up MariaDB security
    secure_mariadb
    
    # Set up database and user
    setup_database
    
    # Deploy application files
    deploy_files
    
    # Configure application
    configure_app
    
    # Test installation
    test_installation
    
    # Show completion information
    show_completion_info
    
    print_success "Setup complete! Enjoy your new inventory management system!"
}

# Run main function
main "$@"
