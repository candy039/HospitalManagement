# Carewell Hospital Management System

A working academic HMS using **PHP 8.1+**, **MySQL 8 / MariaDB 10.4+**, HTML, CSS and vanilla JavaScript. PHP processes regular forms directly with PDO and redirects after successful writes. No API, JavaScript framework, package manager or cloud database is required. Fonts are bundled locally with their open font licenses.

## Run on Windows with XAMPP

1. Install XAMPP with PHP 8.1 or later (PHP 8.2 recommended).
2. Copy this repository's contents into `C:\xampp\htdocs\hospital-management`. `index.php` should be directly inside that folder.
3. Open the XAMPP Control Panel. Start **Apache** and **MySQL**; both should show green. If either fails, inspect its XAMPP logs and resolve port conflicts (Apache normally uses 80/443; MySQL uses 3306).
4. Open phpMyAdmin from XAMPP's MySQL **Admin** button. Choose **Import**, select `database/hospital_management.sql`, and click **Import/Go**. The SQL creates `hospital_management`, six linked tables, seven users and sample clinical data. Import once into a fresh database. It deliberately does not drop existing tables or overwrite data.
5. Open `http://localhost/hospital-management/` in your Windows browser. If Apache uses a different port, include that port in the address.
6. Sign in with any sample account below. You will reach that role's protected dashboard.

The default PDO connection in `config/database.php` matches XAMPP: `localhost`, port `3306`, database `hospital_management`, user `root`, empty password. If your MySQL settings differ, adjust that file locally or configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` in the PHP process environment. A `.env` loader is not used. Do not commit real credentials. Dates default to `Asia/Dhaka`; `APP_TIMEZONE` can override this.

### Sample accounts

All sample accounts use password **`Carewell123!`**. These are academic demo credentials, with bcrypt hashes stored in MySQL.

| Role | Email |
| --- | --- |
| Admin | `admin@carewell.test` |
| Doctor — General Medicine | `sarah@carewell.test` |
| Doctor — Cardiology | `james@carewell.test` |
| Receptionist | `reception@carewell.test` |
| Patient — Olivia | `olivia@carewell.test` |
| Patient — Noah | `noah@carewell.test` |
| Patient — Ava | `ava@carewell.test` |

Patient IDs are shown as `PT-0001`, etc. Reception creates patient accounts and hands the patient their initial password. There is no public self-registration.

## Demonstration walkthrough

1. **Admin:** inspect live statistics, add doctors/receptionists, then use **Edit / status** to edit or deactivate staff. Deactivated accounts cannot log in, and existing sessions lose access on their next request. Staff profiles are editable only by admins.
2. **Receptionist:** register a patient, search by name or numeric/formatted patient ID, edit details, then book an appointment with an active doctor. View availability in the doctor directory or booking selector. Change an appointment through **Edit**, or cancel an open appointment.
3. **Doctor:** view assigned patients, open their medical history and records, create a diagnosis/treatment/prescription and edit your own records. Complete open appointments from the schedule.
4. **Patient:** view your profile, assigned care team, appointments, history and prescriptions. Update basic contact details from **My profile**.

Doctor assignment is established by a **non-cancelled appointment**, including completed visits, so historical care remains accessible. Assigned doctors may read the patient's care history but can only edit records they authored. Patients are always limited to their own records, even if request IDs are changed.

Reception may delete patients without medical records (their appointments and linked login are removed transactionally). Patients with clinical records cannot be deleted. Staff are deactivated rather than deleted. Appointment slot checks prevent overlapping active bookings at the same exact date/time for either the patient or doctor. Availability is descriptive text; reception chooses a time within those hours. This simple project does not model appointment durations, holidays or recurring shifts. There is no separate audit log; recent appointments provide the dashboard activity view.

## Project layout

- `config/database.php`: reusable PDO connection with XAMPP defaults.
- `includes/auth.php`: sessions, CSRF, escaping, database helpers and patient access scope.
- `includes/actions.php`: role-checked, validated, transactional POST handlers.
- `includes/app.php`: shared server-rendered page templates and forms.
- `includes/header.php`, `sidebar.php`, `footer.php`: shared navigation/layout.
- `admin/`, `doctor/`, `receptionist/`, `patient/`: protected role/page entry points.
- `assets/`: responsive CSS, small vanilla JS helpers and offline fonts.
- `database/hospital_management.sql`: phpMyAdmin-importable schema and demo seed.
- `tests/smoke.php`: CLI HTTP/database integration tests.

Security includes prepared SQL statements, `password_hash()`/`password_verify()`, output escaping, server validation, POST/redirect/GET, session ID regeneration, HttpOnly/SameSite cookies, CSRF tokens on state-changing forms, role checks and record ownership checks. This is a local academic demo; it has not undergone a production clinical-data security review.

## Validation performed in the cloud

- SQL imported successfully into MariaDB 11.8; PHP 8.4 PDO connectivity verified.
- All PHP source files passed syntax checks.
- The integration suite passed **172 checks** covering all seven seed logins, all role pages, registration/update/delete, staff creation/edit/deactivation, appointments/update/cancellation/completion, conflicting slots, records/prescriptions, database-backed statistics, cross-role/record access denial, CSRF and logout.
- Chromium checked login, desktop dashboard, mobile overflow and the mobile sidebar; further form checks are recorded in `tests/VALIDATION.md`.
- **Windows XAMPP startup and phpMyAdmin's browser import were not executed here.** This Linux environment used the same SQL through the MariaDB client. Complete steps 3–6 above on Windows before your demonstration.

## Repeat the integration suite

Use a **disposable database**. The test intentionally creates fixtures and does not clean up your application database. The runner refuses names that do not end in `_test`. No extra PHP libraries are needed.

1. Make a copy of the SQL file outside the project. In that copy, replace both database-name occurrences `hospital_management` with `hospital_management_test` (the `CREATE DATABASE` and `USE` lines). Import that copy into a fresh test database using phpMyAdmin. For a second run, drop only the disposable test database and reimport the copy.
2. Open a terminal in this project and start a separate PHP server with its test database configured. On Windows Command Prompt:

   ```bat
   set DB_NAME=hospital_management_test
   C:\xampp\php\php.exe -S 127.0.0.1:8081 -t .
   ```

3. In a second terminal in this project:

   ```bat
   set DB_NAME=hospital_management_test
   set TEST_BASE_URL=http://127.0.0.1:8081
   C:\xampp\php\php.exe tests\smoke.php
   ```

   Set matching `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD` in both terminals if needed. On Linux/macOS, use the equivalent `DB_NAME=hospital_management_test php ...` commands. The runner and server must use the same disposable database. Stop the test server afterwards.

## Troubleshooting

- **Database unavailable:** start MySQL, import the SQL, then check host/port/password in the PDO configuration. MySQL's control-panel Admin opens phpMyAdmin; it does not start MySQL by itself.
- **Could not find driver:** enable `pdo_mysql` in the active PHP `php.ini`, then restart Apache.
- **Table already exists during import:** the seed import is for a fresh database. Back up any real work first; use a new database name or explicitly remove only your disposable demo database before reimporting.
- **Access denied:** confirm the user role and account status. Doctors need a non-cancelled appointment assignment before viewing a patient.
- **Email already registered:** use a unique email or edit the existing account.
- **Invalid form token:** reload the page after login/logout, then resubmit the form.
