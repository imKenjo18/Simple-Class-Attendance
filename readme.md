# Class Attendance System with QR Code Scanner

A modern, simple, and efficient web-based class attendance system designed for teachers. This application automates the attendance process using QR codes, manages class rosters, and provides essential reporting tools.

> This project is built with a pure PHP backend and vanilla JavaScript frontend, making it lightweight, easy to understand, and simple to deploy on any standard web server.

---

## Screenshots



| Dashboard (Class List) | Class Detail View |
| :---: | :---: |
| ![Dashboard showing a list of classes](placeholder-dashboard.png) | ![Detailed view of a single class showing the enrolled students tab](placeholder-dass-detail.png) |

| Live QR Scanner | Student Attendance History |
| :---: | :---: |
| ![The QR code scanner camera view active and ready to scan](placeholder-qr-scanner.png) | ![The modal showing a student's complete attendance record for a class](placeholder-history.png) |

---

## Core Features

*   **Teacher Authentication:** Secure registration and login system for teachers.
*   **Class Management (CRUD):** Easily create, view, update, and delete classes.
*   **Flexible Scheduling:** Set class schedules for `MWF`, `TTH`, or `S` (Saturday).
*   **Student Management:**
    *   Add new students directly to a class.
    *   Edit student information (Name, ID, Phone).
    *   Delete students permanently from the system.
*   **Continuous QR Code Scanning:** A toggleable, high-speed QR scanner allows for marking multiple students present without interruption.
*   **Real-time Feedback:** The system provides instant toast notifications for successful scans, errors (e.g., wrong day, wrong time), and duplicate entries.
*   **Attendance History:** View a complete attendance record for any student within a specific class.
*   **Data Import/Export:**
    *   **Import Students:** Bulk-add and enroll students into a class from a CSV file.
    *   **Export Reports:** Generate and download detailed attendance reports in CSV format for any date range.

---

## Tech Stack

*   **Backend:** PHP 8+
*   **Database:** MySQL
*   **Frontend:** HTML5, CSS3, Vanilla JavaScript (ES6+)
*   **PHP Libraries:**
    *   `endroid/qr-code`: For generating student QR codes on the server.
*   **JavaScript Libraries:**
    *   `html5-qrcode`: For the client-side QR code scanning functionality.

---

## Setup and Installation

Follow these steps to get the project running on your local machine.

### Prerequisites

*   A local web server environment (e.g., WAMP, XAMPP, MAMP, or a simple PHP/MySQL setup).
*   [Composer](https://getcomposer.org/) for managing PHP dependencies.
*   A web browser and a code editor.

### 1. Clone the Repository

```bash
git clone https://github.com/your-username/class-attendance.git
cd class-attendance
```

### 2. Database Setup

1.  **Create a Database:** Using a tool like phpMyAdmin, create a new database. It's recommended to name it `class_attendance`.
2.  **Import the Schema:** Import the `class_attendance.sql` file (which I provided earlier) into your newly created database. This will set up all the necessary tables and relationships.

### 3. Configure Database Connection

1.  Open the file `config/database.php`.
2.  Update the database credentials (`DB_SERVER`, `DB_USERNAME`, `DB_PASSWORD`, `DB_NAME`) to match your local server environment.

```php
// config/database.php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // Your WAMP/XAMPP password might be empty
define('DB_NAME', 'class_attendance');
```

### 4. Install PHP Dependencies

The project uses Composer to manage the PHP QR code library. Run the following command in the project's root directory:

```bash
composer install
```
This will create a `vendor` folder containing the necessary libraries.

### 5. Set Your Timezone (Crucial!)

To ensure the attendance time checks work correctly, you must set your local timezone.

1.  Open the file `api/attendance.php`.
2.  Find the line `date_default_timezone_set('Asia/Manila');` near the top.
3.  Change `'Asia/Manila'` to your correct timezone string from the [official PHP list](https://www.php.net/manual/en/timezones.php).

### 6. Run the Application

Start your local server (Apache and MySQL in WAMP/XAMPP) and navigate to the project directory in your browser (e.g., `http://localhost/class-attendance/`).

---

## Project Workflow

1.  **Register/Login:** The first page is the login screen. Create a new teacher account or log in with existing credentials.
2.  **Add a Class:** From the dashboard, click "Add Class", fill in the details (e.g., "Computer Science 101", "MWF", start/end times), and save.
3.  **View Class Details:** Click on any class card to navigate to the detailed view for that class.
4.  **Manage Students:**
    *   Go to the "Enrolled Students" tab.
    *   Click "Add Student" to create a new student who will be automatically enrolled in this class.
    *   Click the "Edit" icon on a student's card to update their details or delete them.
5.  **Take Attendance:**
    *   Go to the "Attendance" tab.
    *   Click "Start Scanning". Your browser will ask for camera permission.
    *   Present student QR codes to the camera. The scanner will run continuously until you click "Stop Scanning".
6.  **Generate Reports:**
    *   Go to the "Reports" tab.
    *   Select a start and end date.
    *   Click "Download Report" to get a CSV file of the attendance records for that period.

---

## Database Schema

The database consists of five core tables:

*   `teachers`: Stores login credentials for teachers.
*   `classes`: Stores class information, schedules, and links to a teacher.
*   `students`: A global table of all students in the system.
*   `class_enrollment`: A pivot table that links students to the classes they are enrolled in.
*   `attendance_records`: Stores every single attendance event (`Present`, `Absent`, etc.) for a student in a specific class on a specific date and time.

All tables use foreign key constraints with `ON DELETE CASCADE` to ensure data integrity. For example, deleting a class will automatically remove all its enrollments and attendance records.

---

## Future Improvements

*   **Manual Attendance:** Add functionality to manually mark students as 'Absent', 'Late', or 'Excused'.
*   **Student View:** A separate login for students to view their own attendance records.
*   **Data Visualization:** Add charts and graphs to the Reports tab to visualize attendance trends.
*   **Password Reset:** Implement a "Forgot Password" feature for teachers.

---

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
System Structure

