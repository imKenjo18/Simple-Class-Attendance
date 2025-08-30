<?php
/**
 * This is the footer include file.
 */
?>
    </main>

    <!-- ================================================== -->
    <!--                       MODALS                       -->
    <!-- ================================================== -->

    <div id="scanner-overlay" class="modal-overlay" style="display: none;">
        <!-- The #attendance-taker section will be moved here by JavaScript when scanning starts -->
    </div>

    <!-- Add/Edit Class Modal -->
    <div id="class-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <form id="class-form" novalidate>
                <h3 id="class-modal-title">Add New Class</h3>
                <input type="hidden" id="class-id" name="class_id">
                <div class="form-group">
                    <label for="class-name">Class Name</label>
                    <input type="text" id="class-name" name="class_name" required>
                </div>
                <div class="form-group">
                    <label for="unit-code">Unit/Code</label>
                    <input type="text" id="unit-code" name="unit_code">
                </div>

                <div class="form-group">
                    <label>Class Schedule (pick days and times)</label>
                    <div id="custom-schedule" class="schedule-grid">
                        <!-- One row per day -->
                        <div class="day-row">
                            <label><input type="checkbox" class="day-checkbox" data-day="Sunday"> Sunday</label>
                            <input type="time" class="day-start" data-day="Sunday" disabled>
                            <input type="time" class="day-end" data-day="Sunday" disabled>
                        </div>
                        <div class="day-row">
                            <label><input type="checkbox" class="day-checkbox" data-day="Monday"> Monday</label>
                            <input type="time" class="day-start" data-day="Monday" disabled>
                            <input type="time" class="day-end" data-day="Monday" disabled>
                        </div>
                        <div class="day-row">
                            <label><input type="checkbox" class="day-checkbox" data-day="Tuesday"> Tuesday</label>
                            <input type="time" class="day-start" data-day="Tuesday" disabled>
                            <input type="time" class="day-end" data-day="Tuesday" disabled>
                        </div>
                        <div class="day-row">
                            <label><input type="checkbox" class="day-checkbox" data-day="Wednesday"> Wednesday</label>
                            <input type="time" class="day-start" data-day="Wednesday" disabled>
                            <input type="time" class="day-end" data-day="Wednesday" disabled>
                        </div>
                        <div class="day-row">
                            <label><input type="checkbox" class="day-checkbox" data-day="Thursday"> Thursday</label>
                            <input type="time" class="day-start" data-day="Thursday" disabled>
                            <input type="time" class="day-end" data-day="Thursday" disabled>
                        </div>
                        <div class="day-row">
                            <label><input type="checkbox" class="day-checkbox" data-day="Friday"> Friday</label>
                            <input type="time" class="day-start" data-day="Friday" disabled>
                            <input type="time" class="day-end" data-day="Friday" disabled>
                        </div>
                        <div class="day-row">
                            <label><input type="checkbox" class="day-checkbox" data-day="Saturday"> Saturday</label>
                            <input type="time" class="day-start" data-day="Saturday" disabled>
                            <input type="time" class="day-end" data-day="Saturday" disabled>
                        </div>
                    </div>
                    <small>Pick one or more days, then set start and end times for each. Custom schedules are required to take attendance.</small>
                </div>


                <div class="modal-actions">
                    <button type="button" id="cancel-class-modal" class="button">Cancel</button>
                    <button type="submit" id="save-class-btn" class="button primary"><span class="spinner-inline" style="display:none"></span>Save Class</button>
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
                    <input type="text" id="student-id-num" name="student_id_num" required pattern="[ -~]+" title="Allowed: printable ASCII characters (Code 128)">
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
                    <button type="submit" id="save-student-btn" class="button primary"><span class="spinner-inline" style="display:none"></span>Save Student</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Student Attendance History Modal -->
    <div id="student-attendance-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <h3 id="student-attendance-title">Attendance History</h3>
            <div id="student-attendance-list" class="attendance-history-list">
                <!-- History will be populated by JS -->
            </div>
            <div class="modal-actions">
                <button type="button" id="download-history-btn" class="button primary">Download CSV</button>
                <button type="button" class="button" id="close-attendance-modal">Close</button>
            </div>
        </div>
    </div>

    <!-- JavaScript libraries and your custom app script -->
    <!-- Global Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display:none;" aria-hidden="true">
        <div class="spinner" role="status" aria-label="Loading"></div>
    </div>

    <script src="assets/js/html5-qrcode.min.js" type="text/javascript"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
