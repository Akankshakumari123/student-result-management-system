# Student Result Management System (PHP + MySQL)

A student result management application with a refreshed dashboard integrated into the existing PHP/MySQL SRMS project. The original admin pages remain available under the legacy admin tools link.

## Technology stack

- Frontend: HTML, CSS, JavaScript
- Backend: PHP (PDO)
- Database: MySQL / MariaDB
- Local server: XAMPP, WAMP, MAMP, or another PHP + MySQL environment

## Features

- Admin sign-in and session-protected modern dashboard
- Student add, edit, search and delete
- Result entry based on subjects assigned to a class in MySQL
- Automatic totals, percentages, grades and pass/fail status
- Printable student marksheet
- Dashboard statistics
- JSON export of student and result records
- Original SRMS pages retained for class, subject, notice and other administration tasks
- Parameterized database queries in the new dashboard API
- First-run admin account setup with password hashing

## Run locally using XAMPP

1. Install and start **Apache** and **MySQL** in XAMPP.
2. Copy this project folder into `C:\xampp\htdocs\Student-Result-Management-System` (Windows example).
3. Open `http://localhost/phpmyadmin`.
4. Create a database named `srms` (or use the SQL file's database name).
5. Import `database/srms-github.sql` into `srms`.
6. Check `includes/config.php` or set `SRMS_DB_HOST`, `SRMS_DB_USER`, `SRMS_DB_PASS`, and `SRMS_DB_NAME` environment variables if needed.
7. Open `http://localhost/Student-Result-Management-System/setup-admin.php` and create the first admin account. This page redirects to login after an admin exists.
8. Sign in at `http://localhost/Student-Result-Management-System/admin-login.php`.
9. The login opens the modern dashboard. The **Legacy admin tools** link preserves access to the original SRMS pages.

> The app needs PHP and MySQL. Opening the PHP files directly with `file://` or deploying to GitHub Pages alone will not run the backend. Use a PHP-capable host for a live full-stack deployment.

## GitHub upload

1. Create a GitHub repository named `student-result-management-system`.
2. Upload the project files and folders from this directory to the repository root.
3. Do not commit real student information, production database backups, or server credentials.
4. GitHub Pages cannot execute PHP or connect to MySQL. Use GitHub to host the source code and deploy the app to a PHP-capable host if you want it online.

## Database file

`database/srms-github.sql` contains the existing SRMS schema and sample records. The seeded admin password record has been removed; create an administrator through `setup-admin.php` after importing the SQL file.

## Grading rules in the modern dashboard

- Each class uses its active subject combinations from `tblsubjectcombination`.
- Each subject is out of 100.
- A student passes only when they score at least 33 in every listed subject.
- Grades: A+ (90%+), A (80%+), B+ (70%+), B (60%+), C (50%+), D (otherwise if passed), F (failed a subject).

These are demonstration rules; change them to match your institution's official grading policy.

## Security notes

- New API operations require an active admin session and CSRF token.
- The login handler upgrades legacy MD5 admin hashes to PHP's password hash format after a successful legacy login.
- Create a unique admin password during setup, keep database credentials out of public repositories, and use HTTPS on any public deployment.
- This project is an educational starter and should receive a security review before real institutional use.
