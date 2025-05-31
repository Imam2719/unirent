
=============================
 UniRent Project - XAMPP Setup Guide
=============================

This guide will help you set up and run the UniRent project locally using XAMPP.

------------------------------------------
1. REQUIREMENTS
------------------------------------------
- XAMPP installed (https://www.apachefriends.org/index.html)
- PHP 8.x recommended
- MySQL (included in XAMPP)
- Web browser

------------------------------------------
2. SETUP STEPS
------------------------------------------

1. Extract Project Files
-------------------------
- Extract the contents of the `unirent.zip` archive.
- Copy the extracted folder (e.g., `unirent`) to your XAMPP `htdocs` directory:
  > C:\xampp\htdocs\unirent

2. Start Apache and MySQL
--------------------------
- Open XAMPP Control Panel.
- Start both **Apache** and **MySQL**.

3. Import the Database
------------------------
- Open http://localhost/phpmyadmin in your browser.
- Create a new database named: **unirent**
- Click the newly created `unirent` database, then go to **Import**.
- Choose the SQL file from the project zip (e.g., `unirent.sql`) and import it.

4. Update Configuration (if needed)
------------------------------------
- Open `includes/config.php` inside the project.
- Confirm the database credentials (usually no need to change if using XAMPP default):

  ```php
  define('DB_HOST', 'localhost');
  define('DB_USER', 'root');
  define('DB_PASS', '');
  define('DB_NAME', 'unirent');
  ```

5. Access the Project in Browser
---------------------------------
- Visit: http://localhost/unirent
- You should see the UniRent homepage.



------------------------------------------
3. COMMON TROUBLESHOOTING
------------------------------------------

- "Database connection failed":
  - Ensure MySQL is running.
  - Confirm `config.php` settings match your phpMyAdmin credentials.

- "Page Not Found":
  - Double check the folder name in `htdocs` and the URL in the browser.

- "SQL Errors":
  - Ensure the `unirent.sql` file is imported correctly.

------------------------------------------
4. NOTES
------------------------------------------

- Admin panel is under `/admin` directory: http://localhost/unirent/admin/dashboard.php
- Student dashboard: http://localhost/unirent/dashboard.php

6. Admin & Student Login
----------------
- Default admin credentials (you can update via phpMyAdmin):
  - Email: DMRHsir@admin.com
  - Password: DMRHsir@admin.com 

student: email: abcd@student.com
password: abcd@student.com

That's it! You are ready to use the UniRent platform.

