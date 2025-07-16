<?php
require_once 'includes/header.php';
?>

<!-- View 1: List of all classes (default view) -->
<div id="class-list-view">
    <section id="class-management">
        <div class="section-header">
            <h2>My Classes</h2>
            <button id="add-class-btn" class="button icon-button">
                <span class="material-symbols-outlined">add</span> Add Class
            </button>
        </div>
        <p class="view-instructions">Select a class to view its details, take attendance, or manage students.</p>
        <div id="class-list-container" class="list-container">
            <!-- Class cards will be dynamically loaded here by JavaScript -->
        </div>
    </section>
</div>


<!-- View 2: Detailed view for a single class (hidden by default) -->
<div id="class-detail-view" style="display: none;">
    <nav class="breadcrumb">
        <a href="#" id="back-to-classes-link">← Back to All Classes</a>
    </nav>
    <h2 id="detail-class-name"></h2>
    
    <!-- Tab Navigation -->
    <div class="tab-nav">
        <button class="tab-button active" data-tab="attendance">Attendance</button>
        <button class="tab-button" data-tab="students">Enrolled Students</button>
        <button class="tab-button" data-tab="reports">Reports</button>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Attendance Tab -->
        <div id="tab-attendance" class="tab-pane active">
            <section id="attendance-taker">
                <h3>Take Attendance</h3>
                <p>Start the scanner to mark students present for this class.</p>
                <div class="attendance-controls">
                    <button id="start-scan-btn" class="button primary icon-button">
                        <span class="material-symbols-outlined">qr_code_scanner</span> Start Scanning
                    </button>
                </div>
                <div id="qr-reader" style="display: none; max-width: 500px; margin: 20px auto;"></div>
            </section>
        </div>

        <!-- Enrolled Students Tab -->
        <div id="tab-students" class="tab-pane">
            <section id="student-management">
                <div class="section-header">
                    <h3>Enrolled Students</h3>
                    <div class="button-group">
                        <!-- ** THIS IS THE FIX ** -->
                        <!-- The ID is now 'add-student-to-class-btn' and the text is 'Add Student' -->
                        <button id="add-student-to-class-btn" class="button icon-button">
                            <span class="material-symbols-outlined">person_add</span> Add Student
                        </button>
                        <button id="import-students-btn" class="button">Import & Enroll CSV</button>
                    </div>
                </div>
                <div id="enrolled-student-list-container" class="list-container">
                    <!-- Enrolled student cards will be loaded here -->
                </div>
            </section>
        </div>

        <!-- Reports Tab -->
        <div id="tab-reports" class="tab-pane">
            <section id="reporting-section">
                <h3>Generate Attendance Report</h3>
                <p>Select a date range to download a CSV report for this class.</p>
                <form id="report-export-form">
                    <div class="form-group-inline">
                         <div class="form-group">
                            <label for="report-start-date">Start Date</label>
                            <input type="date" id="report-start-date" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label for="report-end-date">End Date</label>
                            <input type="date" id="report-end-date" name="end_date" required>
                        </div>
                    </div>
                    <button type="submit" class="button primary">Download Report</button>
                </form>
            </section>
        </div>
    </div>
</div>

<!-- This input is used by the 'Import & Enroll' button -->
<input type="file" id="csv-file-input" accept=".csv" style="display: none;">

<?php
require_once 'includes/footer.php';
?>