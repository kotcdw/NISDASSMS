# nisdasms.academy — domain setup

## 1. On this computer (now)
- XAMPP site added in `C:\xampp\apache\conf\extra\httpd-vhosts.conf` (backup: `httpd-vhosts.conf.bak-before-nisdas`).
- Right-click `bin\setup-domain.cmd` -> **Run as administrator**. It adds `nisdasms.academy` to the Windows hosts file, checks Apache and restarts it.
- Open http://nisdasms.academy/ (practice copy: http://nisdasms.academy/demo/).
- http://localhost/NISDASSMS/ keeps working too.

## 2. Going live on the internet
1. **Buy the domain** `nisdasms.academy` from any registrar that sells `.academy` (it was unregistered on 2026-10-31).
2. **Hosting**: PHP 8.0+ (pdo_mysql, fileinfo, mbstring, zip, gd, curl), MySQL/MariaDB, Apache with mod_rewrite (`.htaccess` allowed), free SSL (Let's Encrypt), and cron.
3. **DNS** at the registrar: `A` record `@` -> the host's IP, `CNAME` `www` -> `nisdasms.academy` (or use the host's nameservers).
4. **Upload** the whole NISDASSMS folder to the site's web root (so `index.php` is at the top level). Do NOT upload `storage\installed.lock` if you want a fresh install; otherwise export the database from phpMyAdmin here and import it there.
5. **Create `config\config.local.php`** on the server:
   ```php
   <?php return [
       'force_https' => true,
       'db' => ['host' => 'localhost', 'name' => 'HOST_DB_NAME', 'user' => 'HOST_DB_USER', 'password' => 'HOST_DB_PASSWORD'],
   ];
   ```
6. Turn on **SSL** in the hosting panel. The **Public web address** (Fees -> Settings) is already set to `https://nisdasms.academy` by database update 015; it is used in SMS links to parents (report cards, bills, logins). Check it once the site is live.
7. **Cron** (every 15 minutes): `php /path/to/bin/cron.php`.
8. Run **Go-live checklist** in the app and take a **Backup**.
9. On THIS computer, delete the two `nisdasms.academy` lines from `C:\Windows\System32\drivers\etc\hosts` (as administrator) — otherwise this PC keeps opening the local copy instead of the live site.
