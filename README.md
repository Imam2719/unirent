
## ⚙️ Setup Instructions (Localhost via XAMPP)

1. **Install XAMPP**
   - Download and install [XAMPP](https://www.apachefriends.org/index.html).

2. **Place the Project**
   - Move the `unirent` folder to the `htdocs` directory inside your XAMPP installation.

3. **Start Apache and MySQL**
   - Open XAMPP Control Panel.
   - Start both Apache and MySQL services.

4. **Import Database**
   - Open `http://localhost/phpmyadmin`.
   - Create a database named `unirent`.
   - Import the `unirent.sql` file into this database.

5. **Access the App**
   - Visit [http://localhost/unirent](http://localhost/unirent) in your browser.

## 🧪 Sample Users

Use `create-test-users.php` to populate the database with test users for easy login and testing.

## 📌 Notes

- Use the `debug-*.php` scripts for database and rental flow inspection.
- The system includes rudimentary error handling and connection checking tools like `check-database.php`.


