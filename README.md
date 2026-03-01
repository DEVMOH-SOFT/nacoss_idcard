Student ID Card Generation System

This project provides a simple PHP/MySQL system for managing student ID cards.

Setup:

1. Copy the `student-id-system` folder into your XAMPP `htdocs` directory.
2. Create a MySQL database named `student_id_system`.
3. Run the provided SQL script (`db.sql`) to create the `students` table and database.
   You can import it via phpMyAdmin or the MySQL command line:
   ```sql
   source /path/to/student-id-system/db.sql;
   ```
4. Visit `http://localhost/student-id-system` in your browser.

**Notes:**

- Place a TrueType font file (e.g. `arial.ttf` or `OpenSans-Regular.ttf`) in `assets/` for ID generation.
- Ensure the `uploads/students` folder is writable by the web server.
