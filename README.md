# Tourism Management System PHP

Legacy PHP/MySQL tourism booking application with a customer site and admin panel.

## Local Setup

1. Copy `.env.example` to `.env` and update database credentials.
2. Create a MySQL database named `tms`.
3. Import `SQL FIle/tms.sql`.
4. Put the `tms` folder under your web server document root.
5. Open `http://localhost/tms`.

## Windows XAMPP Runner

For Windows, install XAMPP and then double-click:

```text
run-windows.bat
```

The script checks XAMPP, Apache, MySQL, PHP extensions, project location, `.env`, database import, payment table, booking pricing columns, and the local site response. If the project is outside `C:\xampp\htdocs`, it can copy it there for you.

PowerShell options:

```powershell
.\run-windows.ps1
.\run-windows.ps1 -XamppPath D:\xampp
.\run-windows.ps1 -DbPort 3308
.\run-windows.ps1 -CopyToHtdocs -NoBrowser
```

If XAMPP MySQL is configured to port `3308`, keep this in `.env`:

```env
DB_HOST=127.0.0.1
DB_PORT=3308
DB_USER=root
DB_PASS=
DB_NAME=tms
```

## Admin Login

- URL: `http://localhost/tms/admin`
- Username: `admin`
- Default password from the SQL dump: `Test@123`

The first successful login upgrades the legacy MD5 password hash to bcrypt.

## PhonePe Payment Gateway

The payment flow is ready for PhonePe Standard Checkout v2. Keep real credentials only in `.env`.

Required values:

```env
APP_BASE_URL=http://localhost/tms
PHONEPE_ENABLED=true
PHONEPE_ENV=sandbox
PHONEPE_CLIENT_ID=your-client-id
PHONEPE_CLIENT_SECRET=your-client-secret
PHONEPE_CLIENT_VERSION=1
PHONEPE_OAUTH_GRANT_TYPE=client_credentials
```

The app creates `tblpayments` automatically when payment is used. You can also run `SQL FIle/payment_updates.sql` manually.

## Estimated Booking Pricing

Bookings store dummy estimated pricing for testing: travel days, adults, children, rooms, and the final estimated amount. The app adds these columns automatically when booking pages are opened. For an existing database, you can also run `SQL FIle/booking_pricing_updates.sql` manually if the columns are missing.

## Verification

Run PHP syntax checks:

```bash
find tms -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Security Notes

- New passwords are stored with bcrypt.
- Existing MD5 hashes are still accepted once and upgraded on successful login.
- Admin state-changing actions now use POST plus CSRF tokens.
- Package image uploads are validated and renamed before saving.
- Payment credentials are loaded from `.env`, which is ignored by git.
