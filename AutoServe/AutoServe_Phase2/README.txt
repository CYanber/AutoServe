AUTOSERVE — PHASE 2 STARTER PROJECT
Technology: PHP 8, MySQL 8, PDO, Bootstrap 5.3
No XAMPP required.

IMPORTANT:
This package implements Phase 2 only. It does NOT implement the Phase 3 stored
procedure, stored function, triggers, or automatic BookingAudit integration.

REQUIREMENTS
1. MySQL Server 8.x installed and running. MySQL Workbench is a database client;
   Workbench by itself is not the database server.
2. PHP 8.x installed with extensions pdo and pdo_mysql enabled.
3. A browser.

STEP 1 — CREATE THE DATABASE
1. Open MySQL Workbench and connect to your local MySQL Server.
2. Open database/schema.sql and execute the entire script.
3. Open database/sample_data.sql and execute it.
4. Check that autoserve_db contains these 7 tables:
   Owner, Vehicle, Mechanic, ServiceType, ServiceSlot, ServiceBooking, BookingAudit.

STEP 2 — CONFIGURE THE CONNECTION
Open config/database.php.
Set DB_USER and DB_PASS to your MySQL username and password.
The default is root with an empty password; change it if your MySQL setup differs.

STEP 3 — CREATE DEMO STAFF USERS
Open a terminal in the AutoServe_Phase2 folder and run:
php setup/seed.php
Demo manager:
  manager@autoserve.test
  Manager123!
Demo mechanic:
  mechanic@autoserve.test
  Mechanic123!
Delete setup/seed.php after creating these demo accounts, or keep it outside
the public web directory in a real deployment.

STEP 4 — RUN THE PHP WEBSITE (NO XAMPP)
Open Command Prompt or PowerShell in the AutoServe_Phase2 folder and run:
php -S localhost:8000
Then open:
http://localhost:8000

STEP 5 — TEST THE MAIN WORKFLOW
1. Register a new owner account.
2. Log in as manager and create one or more future service slots.
3. Log out and register/log in as a vehicle owner.
4. Add a vehicle under My Vehicles.
5. Browse Service Slots and book a service.
6. Log in as the assigned mechanic and update the booking:
   Pending → Confirmed → In Progress → Completed.
7. Log in as owner again and confirm the updated status appears in My Bookings.

PHASE 2 FEATURES
- Owner registration and login
- Mechanic / manager login and role-based navigation
- Owner vehicle CRUD
- Manager service-slot create, edit and close
- Browse open slots by date and mechanic
- Create and cancel owner bookings
- Search/filter owner bookings by plate and status
- Mechanic job list and status updates
- Relational JOIN queries
- PDO prepared statements and basic server-side validation

PHASE 2 LIMITATIONS / ITEMS RESERVED FOR PHASE 3
- sp_book_service
- fn_active_bookings
- trg_booking_audit_complete
- trg_booking_validate_status
- Automatic audit rows
- Advanced transaction-based booking protection
- Advanced reports

TROUBLESHOOTING
- "Database connection failed": MySQL Server may be stopped, credentials may be
  incorrect, or pdo_mysql may be disabled.
- "could not find driver": enable/install PHP's pdo_mysql extension.
- "php is not recognized": install PHP 8 and add its folder to Windows PATH.
- Workbench opens but website cannot connect: Workbench is only the client;
  confirm that MySQL Server 8.x is installed and running.
