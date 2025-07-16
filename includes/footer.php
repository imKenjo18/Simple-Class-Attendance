<?php
/**
 * This is the footer include file.
 * It closes the main HTML tags opened in header.php and
 * includes all the necessary JavaScript files for the application.
 */
?>

 </main>

    <!-- Student Attendance History Modal -->
    <div id="student-attendance-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <h3 id="student-attendance-title">Attendance History</h3>
            <div id="student-attendance-list" class="attendance-history-list">
                <!-- History will be populated by JS -->
            </div>
            <div class="modal-actions">
                <button type="button" class="button" id="close-attendance-modal">Close</button>
            </div>
        </div>
    </div>
    <!-- ================================================== -->
    <!--                       MODALS                       -->
    <!-- These are hidden by default and shown by JavaScript -->
    <!-- ================================================== -->

    <!-- Add/Edit Class Modal -->
    <div id="class-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <form id="class-form" novalidate>
                <h3 id="class-modal-title">Add New Class</h3>
                <input type="hidden" id="class-id" name="class_id">

                <div class="form-group">
                    <label for="class-name">Class Name (e.g., "Web Development 101")</label>
                    <input type="text" id="class-name" name="class_name" required>
                </div>
                <div class="form-group">
                    <label for="unit-code">Unit/Code (e.g., "CS-203")</label>
                    <input type="text" id="unit-code" name="unit_code">
                </div>
                    <div class="form-group">
                        <label for="day-of-week">Class Schedule</label>
                        <select id="day-of-week" name="day_of_week" required>
                            <option value="MWF">MWF (Monday, Wednesday, Friday)</option>
                            <option value="TTH">TTH (Tuesday, Thursday)</option>
                            <option value="S">S (Saturday)</option>
                        </select>
                    </div>
                <div class="form-group-inline">
                    <div class="form-group">
                        <label for="start-time">Start Time</label>
                        <input type="time" id="start-time" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label for="end-time">End Time</label>
                        <input type="time" id="end-time" name="end_time" required>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" id="cancel-class-modal" class="button">Cancel</button>
                    <button type="submit" class="button primary">Save Class</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add/Edit Student Modal -->
    <div id="student-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <form id="student-form" novalidate>
                <h3 id="student-modal-title">Add New Student</h3>
                <input type="hidden" id="student-id" name="student_id">

                <div class="form-group">
                    <label for="student-id-num">Unique ID Number</label>
                    <input type="text" id="student-id-num" name="student_id_num" required>
                </div>
                <div class="form-group-inline">
                    <div class="form-group">
                        <label for="first-name">First Name</label>
                        <input type="text" id="first-name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="last-name">Last Name</label>
                        <input type="text" id="last-name" name="last_name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number (Optional)</label>
                    <input type="tel" id="phone" name="phone">
                </div>

                <div class="modal-actions">
                    <button type="button" id="delete-student-in-modal-btn" class="button destructive">Delete Student</button>
                    <button type="button" id="cancel-student-modal" class="button">Cancel</button>
                    <button type="submit" class="button primary">Save Student</button>
                </div>
            </form>
        </div>
    </div>


    <!-- JavaScript libraries and your custom app script -->
    <!-- Place at the end of the body for faster page load -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>