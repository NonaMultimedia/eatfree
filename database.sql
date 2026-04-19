-- =====================================================
-- EATFREE DATABASE SCHEMA
-- Complete production-ready database structure
-- =====================================================

-- Drop existing tables if they exist (for clean reinstall)
DROP TABLE IF EXISTS wallet_transactions;
DROP TABLE IF EXISTS meal_claims;
DROP TABLE IF EXISTS donations;
DROP TABLE IF EXISTS vendor_withdrawals;
DROP TABLE IF EXISTS vendor_subscriptions;
DROP TABLE IF EXISTS vendor_queue;
DROP TABLE IF EXISTS ecosystem_settings;
DROP TABLE IF EXISTS global_wallet;
DROP TABLE IF EXISTS vouchers;
DROP TABLE IF EXISTS beneficiaries;
DROP TABLE IF EXISTS vendors;
DROP TABLE IF EXISTS administrators;

-- =====================================================
-- CORE TABLES
-- =====================================================

-- Administrators table
CREATE TABLE administrators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin') DEFAULT 'admin',
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Vendors table
CREATE TABLE vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    registration_number VARCHAR(50) NULL,
    tax_number VARCHAR(50) NULL,
    bank_name VARCHAR(100) NULL,
    bank_account_number VARCHAR(50) NULL,
    bank_branch_code VARCHAR(20) NULL,
    bank_account_holder VARCHAR(100) NULL,
    address TEXT NOT NULL,
    city VARCHAR(50) NOT NULL,
    province VARCHAR(50) NOT NULL,
    postal_code VARCHAR(10) NULL,
    logo_path VARCHAR(255) NULL,
    documents_path VARCHAR(255) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    status ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
    meals_remaining INT DEFAULT 0,
    total_meals_served INT DEFAULT 0,
    wallet_balance DECIMAL(10,2) DEFAULT 0.00,
    subscription_status ENUM('none', 'active', 'expired', 'cancelled') DEFAULT 'none',
    subscription_expires_at DATE NULL,
    agrees_to_r20_meals TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_city_province (city, province),
    INDEX idx_subscription_status (subscription_status)
);

-- Beneficiaries table
CREATE TABLE beneficiaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    id_number VARCHAR(13) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    address TEXT NOT NULL,
    city VARCHAR(50) NOT NULL,
    province VARCHAR(50) NOT NULL,
    total_meals_claimed INT DEFAULT 0,
    last_claim_date DATE NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_id_number (id_number),
    INDEX idx_city_province (city, province)
);

-- Vouchers table
CREATE TABLE vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_code VARCHAR(20) NOT NULL UNIQUE,
    beneficiary_id INT NOT NULL,
    vendor_id INT NOT NULL,
    amount DECIMAL(10,2) DEFAULT 20.00,
    subsidy_amount DECIMAL(10,2) DEFAULT 5.00,
    status ENUM('active', 'used', 'expired', 'cancelled') DEFAULT 'active',
    used_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_voucher_code (voucher_code),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
);

-- Global wallet (system funding pool)
CREATE TABLE global_wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    balance DECIMAL(12,2) DEFAULT 0.00,
    total_received DECIMAL(12,2) DEFAULT 0.00,
    total_distributed DECIMAL(12,2) DEFAULT 0.00,
    meals_funded INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Wallet transactions
CREATE TABLE wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM('donation', 'meal_subsidy', 'vendor_payment', 'withdrawal', 'refund', 'subscription') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reference_id INT NULL,
    reference_type VARCHAR(50) NULL,
    description TEXT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_created_at (created_at)
);

-- Donations table
CREATE TABLE donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_name VARCHAR(100) NULL,
    donor_email VARCHAR(100) NULL,
    donor_phone VARCHAR(20) NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('payfast', 'bank_transfer', 'cash', 'other') DEFAULT 'payfast',
    payment_reference VARCHAR(100) NULL,
    payfast_m_payment_id VARCHAR(100) NULL,
    is_anonymous TINYINT(1) DEFAULT 0,
    message TEXT NULL,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_payfast_id (payfast_m_payment_id),
    INDEX idx_created_at (created_at)
);

-- Meal claims
CREATE TABLE meal_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_id INT NOT NULL,
    beneficiary_id INT NOT NULL,
    vendor_id INT NOT NULL,
    claimed_at DATETIME NOT NULL,
    verified_by_vendor_id INT NOT NULL,
    subsidy_amount DECIMAL(10,2) DEFAULT 5.00,
    meal_price DECIMAL(10,2) DEFAULT 20.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
    FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_claimed_at (claimed_at)
);

-- Vendor subscriptions
CREATE TABLE vendor_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 99.00,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 14.85,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 113.85,
    meals_included INT DEFAULT 50,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payfast_m_payment_id VARCHAR(100) NULL,
    subscription_start DATE NULL,
    subscription_end DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_payment_status (payment_status)
);

-- Vendor withdrawals
CREATE TABLE vendor_withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending',
    bank_reference VARCHAR(100) NULL,
    processed_at DATETIME NULL,
    processed_by INT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES administrators(id) ON DELETE SET NULL,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_status (status)
);

-- Ecosystem settings
CREATE TABLE ecosystem_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meal_price DECIMAL(10,2) DEFAULT 20.00,
    subsidy_amount DECIMAL(10,2) DEFAULT 5.00,
    meals_per_vendor INT DEFAULT 50,
    min_withdrawal DECIMAL(10,2) DEFAULT 200.00,
    vendor_subscription_amount DECIMAL(10,2) DEFAULT 99.00,
    vendor_subscription_tax DECIMAL(10,2) DEFAULT 15.00,
    target_threshold DECIMAL(12,2) DEFAULT 1000000.00,
    current_mode ENUM('subsidized', 'full_free') DEFAULT 'subsidized',
    system_costs DECIMAL(10,2) DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Vendor queue (waiting list)
CREATE TABLE vendor_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    queue_position INT NOT NULL,
    status ENUM('waiting', 'approved', 'removed') DEFAULT 'waiting',
    estimated_approval_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_queue_position (queue_position)
);

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Default admin account (Username: admin, Password: Admin@123)
INSERT INTO administrators (username, email, password_hash, full_name, role) VALUES 
('admin', 'admin@eatfree.co.za', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'super_admin');

-- Initialize global wallet
INSERT INTO global_wallet (balance, total_received, total_distributed, meals_funded) VALUES 
(0.00, 0.00, 0.00, 0);

-- Initialize ecosystem settings
INSERT INTO ecosystem_settings (meal_price, subsidy_amount, meals_per_vendor, min_withdrawal, vendor_subscription_amount, vendor_subscription_tax, target_threshold, current_mode, system_costs) VALUES 
(20.00, 5.00, 50, 200.00, 99.00, 15.00, 1000000.00, 'subsidized', 0.00);

-- =====================================================
-- VIEWS FOR REPORTING
-- =====================================================

-- Vendor stats view
CREATE VIEW vendor_stats AS
SELECT 
    v.id,
    v.business_name,
    v.status,
    v.meals_remaining,
    v.total_meals_served,
    v.wallet_balance,
    v.subscription_status,
    COUNT(DISTINCT mc.id) as meals_this_month,
    COUNT(DISTINCT CASE WHEN mc.claimed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN mc.id END) as meals_this_week
FROM vendors v
LEFT JOIN meal_claims mc ON v.id = mc.vendor_id AND mc.claimed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY v.id;

-- Dashboard summary view
CREATE VIEW dashboard_summary AS
SELECT 
    (SELECT COUNT(*) FROM vendors WHERE status = 'approved') as total_vendors,
    (SELECT COUNT(*) FROM vendors WHERE status = 'pending') as pending_vendors,
    (SELECT COUNT(*) FROM beneficiaries) as total_beneficiaries,
    (SELECT COUNT(*) FROM meal_claims) as total_meals_served,
    (SELECT COUNT(*) FROM meal_claims WHERE claimed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as meals_this_month,
    (SELECT COALESCE(SUM(amount), 0) FROM donations WHERE status = 'completed') as total_donations,
    (SELECT COALESCE(SUM(amount), 0) FROM donations WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as donations_this_month,
    (SELECT balance FROM global_wallet LIMIT 1) as wallet_balance,
    (SELECT meals_funded FROM global_wallet LIMIT 1) as meals_funded;
