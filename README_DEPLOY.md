# Deploy checklist for HostGator

Follow these steps to deploy the project to HostGator (shared hosting):

1. Prepare config
   - Copy `includes/config.example.php` → `includes/config.php` and fill DB credentials (cPanel often prefixes DB/user names).

2. Upload files
   - Upload the entire project to the document root for your domain (usually `public_html`). Keep the `public/` folder and the root files together.

3. Permissions
   - Set directories to 755 and files to 644. Use File Manager or FTP. If you have SSH use the script in `scripts/fix_permissions.sh`.

4. SSL / HTTPS
   - Enable AutoSSL in cPanel (SSL/TLS) for your domain. After SSL is active, the `.htaccess` will redirect to HTTPS.

5. Database
   - Create the MySQL database in cPanel (MySQL Databases). Note the exact name (prefix). Create a DB user and grant ALL PRIVILEGES.
   - Import `schema.sql` with phpMyAdmin.

6. Test
   - Visit your domain and login. If you see database errors, check `public/test_db_error.log` (temporary) or run the provided `public/test_db.php`.

7. Cleanup
   - Remove `public/test_db.php`, `phpinfo.php`, `test_db_root.php` and any debug files after confirming the site works.
