# SiteGround Deployment Checklist

## Pre-Deployment
- [ ] Create MySQL database and user in cPanel
- [ ] Note down database credentials
- [ ] Prepare domain/subdomain for installation

## Deployment Steps

### 1. File Upload
- [ ] Extract `photography-website-deployment.zip` to `public_html/`
- [ ] Verify all files are uploaded correctly
- [ ] Check folder structure matches expected layout

### 2. Configuration
- [ ] Copy `config.sample.php` to `config.php`
- [ ] Edit `config.php` with your database details:
  - [ ] DB_HOST (usually 'localhost')
  - [ ] DB_NAME (your database name)
  - [ ] DB_USER (your database username)
  - [ ] DB_PASS (your database password)
- [ ] Update SITE_URL to your domain
- [ ] Change HASH_SALT to a random string
- [ ] Set ADMIN_EMAIL to your email address

### 3. Database Setup
- [ ] Access phpMyAdmin in cPanel
- [ ] Select your database
- [ ] Import `schema.sql` file
- [ ] Verify all tables were created successfully

### 4. Permissions
- [ ] Set `/assets/uploads/` folder to 755 permissions
- [ ] Ensure PHP can write to upload directories
- [ ] Test image upload functionality

### 5. Security
- [ ] Login to `/admin/` with default credentials:
  - Email: `admin@example.com`
  - Password: `password`
- [ ] **IMMEDIATELY** change admin password
- [ ] Test admin functionality

### 6. Content Setup
- [ ] Upload hero background image in Settings
- [ ] Add your bio text and about photo
- [ ] Upload gallery images
- [ ] Configure services
- [ ] Set up contact information
- [ ] Add social media links

### 7. Stripe Integration (Optional)
- [ ] Create Stripe account if selling prints
- [ ] Create Payment Links for your products
- [ ] Add payment links to products in admin
- [ ] Test purchase flow

### 8. Testing
- [ ] Test public website on desktop and mobile
- [ ] Test contact form submission
- [ ] Test admin login/logout
- [ ] Test image uploads
- [ ] Test product management
- [ ] Verify email notifications work

### 9. Launch
- [ ] Update DNS if needed
- [ ] Test final website
- [ ] Share with friends/clients for feedback

## Post-Launch
- [ ] Monitor contact form submissions
- [ ] Regular backups of database and files
- [ ] Keep admin credentials secure
- [ ] Monitor website performance

## Support
If you encounter issues:
1. Check PHP error logs in cPanel
2. Verify database connection
3. Test individual admin functions
4. Check file permissions

Default login credentials:
- Email: `admin@example.com`
- Password: `password`
**⚠️ Change immediately after first login!**
