# Student ID Card System

Student flow:
- Open `index.php`
- Fill full name, level (100-400), matric number, and upload photo (JPG/PNG/WEBP, max 2MB)
- Record is stored in database

Admin flow:
- Open `admin/login.php`
- Default account (auto-created if none): `admin` / `admin123`
- Search by name or matric number
- Filter by level
- Edit student details including post and image
- Generate ID cards one-by-one, selected students, by level, or all students
- Download output directly (single PNG or ZIP for multiple)

Notes:
- Generated ID cards are not saved to database.
- Template used for generation: `assets/Id_card_design.png`.
