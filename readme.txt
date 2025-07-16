/class-attendance/
|-- api/
|   |-- auth.php             # Handles login/registration
|   |-- classes.php          # CRUD for classes
|   |-- students.php         # CRUD for students
|   |-- attendance.php       # Handles marking attendance, reports
|   |-- import_export.php    # Handles data import/export
|   `-- generate_qr.php      # Generates QR code image for a student
|
|-- assets/
|   |-- css/
|   |   `-- style.css        # Custom Material 3 styles
|   `-- js/
|       |-- app.js           # Main JavaScript logic
|       `-- qrcode.min.js    # 3rd-party QR code generator library
|       `-- html5-qrcode.min.js # 3rd-party QR code scanner library
|
|-- config/
|   `-- database.php         # Database connection settings
|
|-- includes/
|   |-- header.php
|   `-- footer.php
|
|-- vendor/                  # For PHP libraries (e.g., QR code generator)
|
|-- index.php                # Login/Registration page
|-- dashboard.php            # Main application page after login
`-- logout.php               # Ends the session