# Cris Mitchell Photography Website

A complete one-page photography portfolio with a lightweight CMS backend built with PHP and MySQL.

## Features

### Public Website
- **Hero Section**: Full-screen background image with title, tagline, and CTAs
- **Gallery**: Grid layout with modal view for full-size images
- **Featured Prints**: Product showcase with Stripe payment integration
- **About Section**: Bio text with portrait photo
- **Services**: Service cards with descriptions and icons
- **Contact Form**: Ajax form submission with email notifications
- **Responsive Design**: Mobile-first design with Tailwind CSS
- **Interactive Elements**: Alpine.js for smooth interactions

### Admin CMS
- **Secure Login**: Password-protected admin area
- **Image Management**: Upload, categorize, and organize gallery images
- **Product Management**: Create and manage prints with Stripe integration
- **Service Management**: Add and edit service offerings
- **Settings Management**: Update site content, hero images, and contact info
- **Contact Management**: View and manage form submissions
- **Dashboard**: Overview with statistics and quick actions

## Tech Stack
- **PHP 8.1+** - Server-side logic
- **MySQL** - Database storage
- **Tailwind CSS** - Styling framework (via CDN)
- **Alpine.js** - JavaScript interactivity
- **Responsive Design** - Mobile-first approach

## Installation Instructions

### 1. Prepare Your Environment
- Ensure you have PHP 8.1+ and MySQL available on SiteGround
- Create a new MySQL database and user in cPanel

### 2. Upload Files
- Extract all files to your `public_html/` directory
- Set proper permissions: `755` for directories, `644` for files

### 3. Configure Database
1. Copy `config.sample.php` to `config.php`
2. Edit `config.php` with your database credentials and settings:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```
3. Import `schema.sql` into your MySQL database via phpMyAdmin

### 4. Set Up File Uploads
- Ensure `/assets/uploads/` directory has write permissions (755)
- The script will create subdirectories automatically

### 5. Configure Email
- Update email settings in `config.php` for contact form notifications
- Test contact form functionality

### 6. Default Admin Login
- **Email**: `admin@example.com`
- **Password**: `password`
- **⚠️ IMPORTANT**: Change this immediately after first login!

## File Structure

```
/
├── index.php              # Main public website
├── config.sample.php      # Configuration template
├── schema.sql            # Database setup
├── .htaccess            # Apache configuration
├── README.md            # This file
├── admin/               # Admin CMS
│   ├── index.php        # Dashboard
│   ├── login.php        # Admin login
│   ├── logout.php       # Admin logout
│   ├── images.php       # Image management
│   ├── products.php     # Product management
│   ├── services.php     # Service management
│   ├── settings.php     # Site settings
│   └── contacts.php     # Contact management
├── includes/            # Shared utilities
│   └── db.php          # Database and utility functions
├── server/             # API endpoints
│   └── contact.php     # Contact form processing
└── assets/             # Static assets
    └── uploads/        # Uploaded images
        ├── full/       # Full-size images
        └── thumbs/     # Thumbnails
```

## Usage Instructions

### Managing Content

1. **Login to Admin**: Visit `/admin/` and login with your credentials
2. **Upload Images**: Go to Images section, upload photos for your gallery
3. **Create Products**: Add prints with pricing and Stripe payment links
4. **Update About**: Edit your bio and upload a portrait in Settings
5. **Manage Services**: Add or edit your photography services
6. **Site Settings**: Update title, tagline, contact info, and social links

### Setting Up Stripe Payments

1. Create a Stripe account at stripe.com
2. Create Payment Links in your Stripe Dashboard
3. Copy the payment link URLs into your products in the admin panel
4. Test the purchase flow

### Contact Form

- Form submissions are stored in the database
- Email notifications are sent to the admin email address
- View and manage submissions in the admin Contacts section

## Security Features

- **CSRF Protection**: All forms use CSRF tokens
- **SQL Injection Protection**: Prepared statements throughout
- **XSS Protection**: Input sanitization and output escaping
- **Session Security**: Secure session handling
- **File Upload Security**: Type and size validation
- **Password Hashing**: Bcrypt password hashing

## Recent Updates

- Fixed gallery exclusion logic to prevent about/hero images appearing in gallery
- Fixed admin image delete functionality with proper Alpine.js modal
- Enhanced security features and CSRF protection
- Improved responsive design and mobile experience

## Customization

### Styling
- Main styles use Tailwind CSS via CDN
- Custom animations and fonts defined in `<style>` blocks
- Colors and spacing can be customized through Tailwind classes

### Adding New Sections
- Edit `index.php` to add new content sections
- Add corresponding admin management if needed
- Update navigation links

### Database Changes
- Add new tables by creating migration scripts
- Update `includes/db.php` for new utility functions

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check `config.php` credentials
   - Ensure database exists and user has proper permissions

2. **Image Upload Issues**
   - Check file permissions on `/assets/uploads/`
   - Verify PHP upload settings in `.htaccess`

3. **Contact Form Not Working**
   - Check PHP mail() function is enabled
   - Verify email configuration in `config.php`

4. **Admin Login Issues**
   - Ensure sessions are working (check server session settings)
   - Verify user exists in database with correct password hash

### Support
For technical issues, check:
- PHP error logs
- MySQL error logs
- Browser console for JavaScript errors

## License
This project is provided as-is for your photography website. Feel free to modify as needed.
