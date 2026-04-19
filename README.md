# EatFree - Community Digital Feeding System

A complete, production-ready platform connecting donors with local food vendors to provide meals to communities across South Africa.

## System Overview

- **Main Site**: https://eatfree.co.za - Public + Vendor Portal
- **Admin Portal**: https://admin.eatfree.co.za - Administrator Dashboard
- **Ecosystem Engine**: https://eco.eatfree.co.za - System Balance Management

## Core Features

### For Donors
- Make secure donations via PayFast
- Track donation impact
- Anonymous donation option

### For Vendors
- Register as a food vendor
- Subscribe for R99/month (includes 50 meals)
- Manage meal inventory
- Request withdrawals (minimum R200)
- View statements and analytics

### For Beneficiaries
- Claim meal vouchers using SA ID
- Find nearby vendors
- Receive R20 meals (R5 subsidized by EatFree)

### For Administrators
- Approve/reject vendors
- Manage withdrawals
- View donations and meal claims
- Ecosystem manager for capacity planning
- System settings configuration

## Installation

### 1. Database Setup

1. Create a MySQL database
2. Import the database schema:
   ```bash
   mysql -u username -p database_name < database.sql
   ```

### 2. Configuration

1. Open `config/config.php`
2. Update the database credentials:
   ```php
   define('DB_HOST', 'your_host');
   define('DB_NAME', 'your_database');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ```

3. Update PayFast settings (for production):
   ```php
   define('PAYFAST_SANDBOX', false);
   define('PAYFAST_MERCHANT_ID', 'your_merchant_id');
   define('PAYFAST_MERCHANT_KEY', 'your_merchant_key');
   define('PAYFAST_PASSPHRASE', 'your_passphrase');
   ```

### 3. File Permissions

Ensure the following directories are writable:
- `/uploads/logos/`
- `/uploads/documents/`
- `/logs/`

```bash
chmod 755 uploads/ uploads/logos/ uploads/documents/ logs/
```

### 4. Default Admin Account

- **Username**: admin
- **Password**: Admin@123

**IMPORTANT**: Change the default password immediately after first login!

## Subdomain Setup

Configure your DNS and web server for the following subdomains:

1. **eatfree.co.za** → Points to main site root
2. **admin.eatfree.co.za** → Points to main site root (accesses /admin/)
3. **eco.eatfree.co.za** → Points to main site root (accesses ecosystem.php)

### Apache Configuration

The included `.htaccess` file handles subdomain routing. Ensure `mod_rewrite` is enabled.

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name eatfree.co.za;
    root /var/www/eatfree;
    index index.html index.php;
    
    location / {
        try_files $uri $uri/ =404;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.5-fpm.sock;
    }
}

server {
    listen 80;
    server_name admin.eatfree.co.za;
    root /var/www/eatfree;
    index index.php;
    
    location / {
        try_files $uri $uri/ /admin/$1;
    }
}
```

## Business Rules

- **Meal Price**: R20 per meal
- **Subsidy**: R5 per meal (EatFree contribution)
- **Vendor Subscription**: R99/month + 15% tax = R113.85
- **Meals per Subscription**: 50 meals
- **Minimum Withdrawal**: R200
- **Target Threshold**: R1,000,000 for Full Free Mode

## API Endpoints

All API endpoints return JSON and are located in `/api/`:

### Authentication
- `POST /api/vendor-login.php` - Vendor login
- `POST /api/admin-login.php` - Admin login
- `GET /api/logout.php` - Logout

### Vendors
- `POST /api/vendor-register.php` - Register new vendor
- `POST /api/vendor-subscribe.php` - Initiate subscription payment
- `POST /api/request-withdrawal.php` - Request withdrawal

### Vouchers
- `POST /api/generate-voucher.php` - Generate meal voucher
- `POST /api/verify-voucher.php` - Verify and redeem voucher

### Donations
- `POST /api/process-donation.php` - Initiate donation payment
- `POST /api/payfast-itn.php` - PayFast payment notification handler

### Data
- `GET /api/get-dashboard-data.php` - Get dashboard statistics
- `GET /api/get-vendors.php` - Get approved vendors list

## PayFast Integration

The system uses PayFast for payment processing:

1. **Donations**: One-time payments
2. **Subscriptions**: Monthly recurring payments

### Sandbox Testing

Use these test credentials in sandbox mode:
- **Merchant ID**: 10000100
- **Merchant Key**: 46f0cd694581a

### ITN (Instant Transaction Notification)

The ITN handler at `/api/payfast-itn.php` processes payment confirmations from PayFast.

## Security Features

- Password hashing with bcrypt
- CSRF token protection
- Session security (httponly, secure, samesite)
- SQL injection protection (PDO prepared statements)
- XSS protection (output escaping)
- File upload validation
- Directory access protection

## File Structure

```
eatfree/
├── admin/                  # Admin portal
│   ├── dashboard.php
│   ├── ecosystem.php
│   ├── vendors.php
│   ├── withdrawals.php
│   ├── donations.php
│   ├── claims.php
│   └── settings.php
├── api/                    # API endpoints
│   ├── vendor-login.php
│   ├── admin-login.php
│   ├── vendor-register.php
│   ├── vendor-subscribe.php
│   ├── generate-voucher.php
│   ├── verify-voucher.php
│   ├── process-donation.php
│   ├── payfast-itn.php
│   ├── request-withdrawal.php
│   ├── get-dashboard-data.php
│   ├── get-vendors.php
│   └── logout.php
├── assets/
│   ├── css/
│   │   ├── istyle3.css
│   │   ├── Montserrat.css
│   │   ├── Cooper Black Regular.css
│   │   └── Kaushan Script.css
│   ├── img/
│   │   └── vendor-placeholder.png
│   └── js/
├── config/
│   └── config.php          # Configuration file
├── uploads/
│   ├── logos/              # Vendor logos
│   └── documents/          # Vendor documents
├── vendor/                 # Vendor portal
│   └── dashboard.php
├── logs/                   # Error logs
├── .htaccess              # Apache configuration
├── database.sql           # Database schema
├── index.html             # Main site
├── donate.html            # Donation page
├── login.html             # Vendor login
├── vendor-register.html   # Vendor registration
├── payment-success.php    # Payment success page
├── payment-cancel.php     # Payment cancel page
├── admin-login.php        # Admin login
├── modules.html           # Reusable components
└── README.md              # This file
```

## Troubleshooting

### Database Connection Issues
- Verify credentials in `config/config.php`
- Ensure MySQL is running
- Check database user permissions

### PayFast Payments Not Working
- Verify PayFast credentials
- Check if sandbox mode is enabled/disabled correctly
- Ensure ITN URL is accessible from internet
- Check logs in `/logs/payfast_itn.log`

### File Upload Issues
- Verify upload directories are writable
- Check PHP upload limits in `php.ini`
- Ensure file types are allowed

### Session Issues
- Check browser cookie settings
- Verify session path is writable
- Clear browser cookies and cache

## Support

For support, contact: support@eatfree.co.za

## License

Powered by WiseLink PTY (LTD) - 2026+

## Credits

- Bootstrap 5 - UI Framework
- Bootstrap Icons - Icon Library
- PayFast - Payment Processing
- Google Fonts - Typography
