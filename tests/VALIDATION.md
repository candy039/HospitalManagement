# Validation evidence

Executed on 17 November 2026 using PHP 8.4.24, MariaDB 11.8.6 and Chromium in the Linux cloud workspace.

- Imported `database/hospital_management.sql` successfully into the application database and a separate disposable test database.
- All PHP files passed `php -l`.
- `tests/smoke.php` completed **172 passing checks** against the disposable database. It exercised all seven sample logins, each role page, patient registration/search/update/delete, staff creation/edit/deactivation and immediate session revocation, appointment booking/update/conflict/cancellation/completion, medical record creation and prescription update, profile updates, real database counters, ownership/role rejection, invalid CSRF and logout.
- A separate Playwright/Chromium run submitted the visible receptionist patient-registration and booking forms, submitted a doctor's diagnosis/treatment/prescription form, signed in as that new patient to read the saved prescription, and updated their own address through the visible profile form. All operations displayed success and persisted across subsequent page loads.
- Chromium verified role redirection for all four roles, no horizontal page overflow at 1440px desktop and 390px mobile widths, the mobile sidebar toggle, and no JavaScript exceptions during the form walkthrough.
- Runtime installation was rerun successfully; startup/readiness was tested against the running services. Database data is retained outside the repository. Windows XAMPP and the phpMyAdmin browser import still require local verification using the README.

No Windows execution, production deployment, email delivery, duration-based scheduling or audit-trail testing is claimed.
