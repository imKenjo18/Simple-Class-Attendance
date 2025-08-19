/**
 * Main JavaScript file for the Class Attendance Dashboard.
 * FINAL CLEANED VERSION with all features including Scanner Centering.
 */
document.addEventListener('DOMContentLoaded', () => {

    // --- STATE MANAGEMENT ---
    let currentClassId = null;
    let currentStudentForHistory = null; // Holds info for the history download
    let originalScannerParent = null; // To remember where the scanner section belongs

    // --- GLOBAL DOM ELEMENT SELECTORS ---
    const classListView = document.getElementById('class-list-view');
    const classDetailView = document.getElementById('class-detail-view');
    const classModal = document.getElementById('class-modal');
    const classForm = document.getElementById('class-form');
    const studentModal = document.getElementById('student-modal');
    const studentForm = document.getElementById('student-form');
    const attendanceModal = document.getElementById('student-attendance-modal');
    const classListContainer = document.getElementById('class-list-container');
    const enrolledStudentListContainer = document.getElementById('enrolled-student-list-container');
    const scannerOverlay = document.getElementById('scanner-overlay');
    const attendanceTakerSection = document.getElementById('attendance-taker');

    // --- INITIALIZATION ---
    function initializeDashboard() {
        showClassListView();
        loadClasses();
        setupEventListeners();
    }

    // --- VIEW MANAGEMENT ---
    function showClassListView() {
        classListView.style.display = 'block';
        classDetailView.style.display = 'none';
        currentClassId = null;
        document.querySelector('.tab-button[data-tab="attendance"]').click();
        stopScanner(); // Ensure scanner stops when leaving view
    }

    function showClassDetailView(classInfo) {
        currentClassId = classInfo.id;
        document.getElementById('detail-class-name').textContent = `${classInfo.class_name} (${classInfo.unit_code})`;
        classListView.style.display = 'none';
        classDetailView.style.display = 'block';
        loadEnrolledStudents();
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('report-start-date').value = today;
        document.getElementById('report-end-date').value = today;
        document.getElementById('report-preview-container').innerHTML = ''; // Clear old report previews
    }

    // --- DATA LOADING & API CALLS ---
    async function apiFetch(url, options = {}) {
        try {
            const response = await fetch(url, options);
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ message: response.statusText }));
                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
            }
            return response.json();
        } catch (error) {
            console.error('API Fetch Error:', error);
            showToast(`Error: ${error.message}`, 'error');
            throw error;
        }
    }

    async function loadClasses() {
        try {
            const classes = await apiFetch('api/classes.php?action=get_classes');
            renderClasses(classes);
        } catch (e) { /* handled by apiFetch */ }
    }

    async function loadEnrolledStudents() {
        if (!currentClassId) return;
        try {
            const students = await apiFetch(`api/enrollment.php?action=get_enrolled_students&class_id=${currentClassId}`);
            renderEnrolledStudents(students);
        } catch (e) { /* handled by apiFetch */ }
    }

    // --- RENDERING FUNCTIONS ---
    function renderClasses(classes) {
        classListContainer.innerHTML = '';
        if (classes.length === 0) {
            classListContainer.innerHTML = '<p class="empty-list-message">No classes found. Add one to get started!</p>';
            return;
        }
        classes.forEach(cls => {
            const subtitle = cls.schedule_summary && cls.schedule_summary.length > 0
                ? cls.schedule_summary
                : 'No schedule set';
            const classCard = `
                <div class="card class-card" data-class-info='${JSON.stringify(cls)}'>
                    <div class="card-content">
                        <h4>${cls.class_name} (${cls.unit_code || ''})</h4>
                        <p>${subtitle}</p>
                    </div>
                    <div class="card-actions">
                        <button class="button icon-button edit-class-btn" title="Edit Class"><span class="material-symbols-outlined">edit</span></button>
                        <button class="button icon-button delete-class-btn" title="Delete Class"><span class="material-symbols-outlined">delete</span></button>
                    </div>
                </div>`;
            classListContainer.insertAdjacentHTML('beforeend', classCard);
        });
    }

    function renderEnrolledStudents(students) {
        enrolledStudentListContainer.innerHTML = '';
        if (students.length === 0) {
            enrolledStudentListContainer.innerHTML = '<p class="empty-list-message">No students enrolled. Use "Add Student" to create and enroll a new student.</p>';
            return;
        }
        students.forEach(student => {
            const studentCard =  `
                <div class="card student-card" data-id="${student.id}" data-student-info='${JSON.stringify(student)}'>
                    <div class="card-content">
                        <h4>${student.last_name}, ${student.first_name}</h4>
                        <div class="stacon">
                        <p>ID: ${student.student_id_num}</p>
                         <p>Status: </p><span class="status-indicator status-${student.status.toLowerCase()}"> ${student.status}</span>
                        </div>
                    </div>
                    <div class="card-actions">
                        <a href="api/generate_qr.php?id=${student.student_id_num}&download=true"
                        download="QR_${student.first_name}_${student.last_name}.png"
                        title="Click to download QR code with name">
                            <img src="api/generate_qr.php?id=${student.student_id_num}" alt="QR Code" class="qr-code-thumb">
                        </a>
                        <button class="button view-attendance-btn" title="View Attendance History">History</button>
                         <button class="button icon-button edit-enrolled-student-btn" title="Edit Student"><span class="material-symbols-outlined">edit</span></button>
                    </div>
                </div>`;
            enrolledStudentListContainer.insertAdjacentHTML('beforeend', studentCard);
        });
    }

    const DAYS = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

    function getScheduleFromForm() {
        const schedule = [];
        DAYS.forEach(day => {
            const cb = document.querySelector(`.day-checkbox[data-day="${day}"]`);
            const start = document.querySelector(`.day-start[data-day="${day}"]`);
            const end = document.querySelector(`.day-end[data-day="${day}"]`);
            if (cb && cb.checked && start.value && end.value) {
                schedule.push({ day_of_week: day, start_time: start.value, end_time: end.value });
            }
        });
        return schedule;
    }

    function clearScheduleForm() {
        DAYS.forEach(day => {
            const cb = document.querySelector(`.day-checkbox[data-day="${day}"]`);
            const start = document.querySelector(`.day-start[data-day="${day}"]`);
            const end = document.querySelector(`.day-end[data-day="${day}"]`);
            if (cb && start && end) {
                cb.checked = false;
                start.value = '';
                end.value = '';
                start.disabled = true;
                end.disabled = true;
            }
        });
    }

    function populateScheduleForm(schedule) {
        clearScheduleForm();
        (schedule || []).forEach(row => {
            const day = row.day_of_week;
            const cb = document.querySelector(`.day-checkbox[data-day="${day}"]`);
            const start = document.querySelector(`.day-start[data-day="${day}"]`);
            const end = document.querySelector(`.day-end[data-day="${day}"]`);
            if (cb && start && end) {
                cb.checked = true;
                start.disabled = false;
                end.disabled = false;
                start.value = row.start_time?.substring(0,5) || '';
                end.value = row.end_time?.substring(0,5) || '';
            }
        });
    }

    // --- EVENT LISTENERS SETUP ---
    function setupEventListeners() {
        document.getElementById('class-modal')?.addEventListener('change', (e) => {
            if (!e.target || !e.target.matches('.day-checkbox')) return;
            const cb = e.target;
            const day = cb.dataset.day;
            const start = document.querySelector(`.day-start[data-day="${day}"]`);
            const end = document.querySelector(`.day-end[data-day="${day}"]`);
            if (start && end) {
                start.disabled = !cb.checked;
                end.disabled = !cb.checked;
                if (!cb.checked) { start.value = ''; end.value = ''; }
            }
        });

        document.getElementById('back-to-classes-link').addEventListener('click', (e) => { e.preventDefault(); showClassListView(); });
        document.querySelector('.tab-nav').addEventListener('click', e => {
            if (e.target.tagName === 'BUTTON') {
                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
                e.target.classList.add('active');
                document.getElementById(`tab-${e.target.dataset.tab}`).classList.add('active');
            }
        });
        document.getElementById('add-class-btn').addEventListener('click', () => openClassModal());
        document.getElementById('add-student-to-class-btn').addEventListener('click', () => openStudentModal());
        document.getElementById('import-students-btn').addEventListener('click', () => document.getElementById('csv-file-input').click());
        document.getElementById('delete-student-in-modal-btn').addEventListener('click', handleDeleteStudentInModal);
        document.getElementById('preview-report-btn').addEventListener('click', handlePreviewReport);
        document.getElementById('download-report-btn').addEventListener('click', handleDownloadReport);
        document.getElementById('download-history-btn').addEventListener('click', handleDownloadHistory);
        scannerOverlay.addEventListener('click', e => {
            if (e.target.id === 'scanner-overlay') {
                stopScanner();
            }
        });
        classModal.addEventListener('click', e => { if (e.target.id === 'class-modal' || e.target.id === 'cancel-class-modal') classModal.style.display = 'none'; });
        studentModal.addEventListener('click', e => { if (e.target.id === 'student-modal' || e.target.id === 'cancel-student-modal') studentModal.style.display = 'none'; });
        attendanceModal.addEventListener('click', e => { if (e.target.id === 'student-attendance-modal' || e.target.id === 'close-attendance-modal') attendanceModal.style.display = 'none'; });
        classForm.addEventListener('submit', handleClassForm);
        studentForm.addEventListener('submit', handleStudentForm);
        document.getElementById('csv-file-input').addEventListener('change', handleCsvImport);
        classListContainer.addEventListener('click', handleClassListClick);
        enrolledStudentListContainer.addEventListener('click', handleEnrolledStudentListClick);
        document.getElementById('start-scan-btn').addEventListener('click', toggleScanner);
    }

    // --- EVENT HANDLER FUNCTIONS ---
    function openClassModal(classInfo = null) {
        classForm.reset();
        clearScheduleForm();
        document.getElementById('class-id').value = '';
        if (classInfo) {
            document.getElementById('class-modal-title').textContent = 'Edit Class';
            document.getElementById('class-id').value = classInfo.id;
            document.getElementById('class-name').value = classInfo.class_name;
            document.getElementById('unit-code').value = classInfo.unit_code;

            // Fetch custom schedule for this class and populate
            fetch(`api/classes.php?action=get_class_schedule&class_id=${classInfo.id}`)
                .then(r => r.ok ? r.json() : Promise.reject())
                .then(data => {
                    if (data.success && Array.isArray(data.schedule)) {
                        populateScheduleForm(data.schedule);
                    }
                }).catch(() => {});
        } else {
            document.getElementById('class-modal-title').textContent = 'Add New Class';
        }
        classModal.style.display = 'flex';
    }

    async function handleClassForm(e) {
        e.preventDefault();
        const action = document.getElementById('class-id').value ? 'update_class' : 'add_class';
        const formData = new FormData(classForm);
        formData.append('action', action);

        // Always append schedule_json (even if empty) so backend can clear schedules
        const schedule = getScheduleFromForm();
        formData.append('schedule_json', JSON.stringify(schedule));

        const btn = document.getElementById('save-class-btn');
        const spin = btn?.querySelector('.spinner-inline');
        if (btn) { btn.classList.add('is-loading'); btn.disabled = true; }
        if (spin) { spin.style.display = 'inline-block'; }
        try {
            const result = await apiFetch('api/classes.php', { method: 'POST', body: formData });
            if (result.success) {
                showToast(result.message, 'success');
                classModal.style.display = 'none';
                loadClasses();
            }
        } catch (error) { /* Handled by apiFetch */ }
        finally {
            if (spin) { spin.style.display = 'none'; }
            if (btn) { btn.classList.remove('is-loading'); btn.disabled = false; }
        }
    }

    function handleClassListClick(e) {
        const card = e.target.closest('.class-card');
        if (!card) return;
        const classInfo = JSON.parse(card.dataset.classInfo);
        if (e.target.closest('.edit-class-btn')) {
            openClassModal(classInfo);
        } else if (e.target.closest('.delete-class-btn')) {
            if (confirm('Are you sure you want to delete this class and all its records?')) {
                const formData = new FormData();
                formData.append('action', 'delete_class');
                formData.append('class_id', classInfo.id);
                apiFetch('api/classes.php', { method: 'POST', body: formData }).then(loadClasses).catch(() => {});
            }
        } else {
            showClassDetailView(classInfo);
        }
    }

    function openStudentModal(studentInfo = null) {
        studentForm.reset();
        const deleteBtn = document.getElementById('delete-student-in-modal-btn');
        if (studentInfo) {
            document.getElementById('student-modal-title').textContent = 'Edit Student';
            document.getElementById('student-id').value = studentInfo.id;
            document.getElementById('student-id-num').value = studentInfo.student_id_num;
            document.getElementById('first-name').value = studentInfo.first_name;
            document.getElementById('last-name').value = studentInfo.last_name;
            document.getElementById('phone').value = studentInfo.phone;
            deleteBtn.style.display = 'inline-block';
        } else {
            document.getElementById('student-modal-title').textContent = 'Add New Student';
            document.getElementById('student-id').value = '';
            deleteBtn.style.display = 'none';
        }
        studentModal.style.display = 'flex';
    }

    async function handleStudentForm(e) {
        e.preventDefault();
        const studentId = document.getElementById('student-id').value;
        const action = studentId ? 'update_student' : 'add_and_enroll_student';
        const formData = new FormData(studentForm);
        formData.append('action', action);
        if (action === 'add_and_enroll_student') {
            formData.append('class_id', currentClassId);
        }
        const btn = document.getElementById('save-student-btn');
        const spin = btn?.querySelector('.spinner-inline');
        if (btn) { btn.classList.add('is-loading'); btn.disabled = true; }
        if (spin) { spin.style.display = 'inline-block'; }
        try {
            const result = await apiFetch('api/students.php', { method: 'POST', body: formData });
            if (result.success) {
                showToast(result.message, 'success');
                studentModal.style.display = 'none';
                loadEnrolledStudents();
            }
        } catch (error) { /* Handled by apiFetch */ }
        finally {
            if (spin) { spin.style.display = 'none'; }
            if (btn) { btn.classList.remove('is-loading'); btn.disabled = false; }
        }
    }

    async function handleEnrolledStudentListClick(e) {
        const card = e.target.closest('.student-card');
        if (!card) return;
        const studentInfo = JSON.parse(card.dataset.studentInfo);
        if (e.target.closest('.view-attendance-btn')) {
            currentStudentForHistory = studentInfo;
            try {
                const data = await apiFetch(`api/attendance.php?action=get_student_attendance_for_class&student_id=${studentInfo.id}&class_id=${currentClassId}`);
                if (data.success) {
                    const list = document.getElementById('student-attendance-list');
                    const title = document.getElementById('student-attendance-title');
                    title.textContent = `Attendance for ${studentInfo.first_name} ${studentInfo.last_name}`;
                    if (data.records.length > 0) {
                        let tableHTML = `<table class="data-table"><thead><tr><th>Date</th><th>Time</th><th>Status</th></tr></thead><tbody>`;
                        data.records.forEach(rec => {
                            tableHTML += `<tr><td>${rec.attendance_date}</td><td>${rec.attendance_time}</td><td><span class="status-${rec.status.toLowerCase()}">${rec.status}</span></td></tr>`;
                        });
                        tableHTML += `</tbody></table>`;
                        list.innerHTML = tableHTML;
                    } else {
                        list.innerHTML = '<p>No attendance records found for this student in this class.</p>';
                    }
                    attendanceModal.style.display = 'flex';
                }
            } catch(error) { /* Handled by apiFetch */ }
        } else if (e.target.closest('.edit-enrolled-student-btn')) {
            openStudentModal(studentInfo);
        }
    }

    async function handleDeleteStudentInModal() {
        const studentId = document.getElementById('student-id').value;
        if (!studentId) return;
        if (confirm('Are you sure you want to permanently delete this student? This action cannot be undone.')) {
            const formData = new FormData();
            formData.append('action', 'delete_student');
            formData.append('student_id', studentId);
            try {
                const result = await apiFetch('api/students.php', { method: 'POST', body: formData });
                if (result.success) {
                    showToast('Student deleted successfully.', 'success');
                    studentModal.style.display = 'none';
                    loadEnrolledStudents();
                }
            } catch (error) { /* Handled by apiFetch */ }
        }
    }

    function handleDownloadHistory() {
        if (!currentStudentForHistory || !currentClassId) {
            showToast('Error: Student or class context lost.', 'error');
            return;
        }
        const url = `api/import_export.php?action=export_student_history&student_id=${currentStudentForHistory.id}&class_id=${currentClassId}`;
        window.location.href = url;
    }

    async function handleCsvImport(e) {
        const file = e.target.files[0];
        if (!file || !currentClassId) return;
        showToast('Importing and enrolling students...', 'info');
        const formData = new FormData();
        formData.append('action', 'import_students');
        formData.append('student_csv', file);
        formData.append('class_id', currentClassId);
        try {
            const result = await apiFetch('api/import_export.php', { method: 'POST', body: formData });
            if (result.success) {
                showToast(result.message, 'success');
                loadEnrolledStudents();
            }
        } catch(error) { /* Handled by apiFetch */ }
        e.target.value = '';
    }

    async function handlePreviewReport() {
        const startDate = document.getElementById('report-start-date').value;
        const endDate = document.getElementById('report-end-date').value;
        const container = document.getElementById('report-preview-container');
        if (!startDate || !endDate) {
            showToast('Please select a start and end date.', 'error');
            return;
        }
        container.innerHTML = `<p>Generating report...</p>`;
        try {
            const response = await apiFetch(`api/import_export.php?action=get_attendance_report&class_id=${currentClassId}&start_date=${startDate}&end_date=${endDate}`);
            if (response.success) {
                const report = response.report;
                if (!report.students || report.students.length === 0) {
                    container.innerHTML = `<p>No enrolled students or no scheduled class days were found for the selected date range.</p>`;
                    return;
                }
                let tableHTML = `<table class="data-table"><thead><tr><th>Name</th>`;
                report.dates.forEach(date => {
                    tableHTML += `<th>${date}</th>`;
                });
                tableHTML += `<th>Total Attendance</th></tr></thead><tbody>`;
                report.students.forEach(studentRow => {
                    tableHTML += `<tr><td>${studentRow.name}</td>`;
                    report.dates.forEach(date => {
                        const status = studentRow.data[date] || 'N/A';
                        tableHTML += `<td class="status-${status.toLowerCase()}">${status}</td>`;
                    });
                    tableHTML += `<td>${studentRow.summary}</td></tr>`;
                });
                tableHTML += `</tbody></table>`;
                container.innerHTML = tableHTML;
            } else {
                 container.innerHTML = `<p>Error: ${response.message}</p>`;
            }
        } catch (error) {
            container.innerHTML = `<p>Could not load report preview. A server error occurred.</p>`;
        }
    }

    function handleDownloadReport() {
        const startDate = document.getElementById('report-start-date').value;
        const endDate = document.getElementById('report-end-date').value;
        if (!startDate || !endDate) {
            showToast('Please select a start and end date to download.', 'error');
            return;
        }
        window.location.href = `api/import_export.php?action=export_attendance&class_id=${currentClassId}&start_date=${startDate}&end_date=${endDate}`;
    }

    // --- QR SCANNER LOGIC (CONTINUOUS SCANNING) ---
    let isScannerActive = false;
    let isScanProcessing = false;
    const html5QrcodeScanner = new Html5QrcodeScanner("qr-reader", { fps: 10, qrbox: { width: 250, height: 250 } });
    const qrReaderDiv = document.getElementById('qr-reader');
    const startScanBtn = document.getElementById('start-scan-btn');

    function toggleScanner() {
        if (isScannerActive) {
            stopScanner();
        } else {
            startScanner();
        }
    }

    function startScanner() {
        if (!currentClassId) {
            showToast('Error: No class is selected for attendance.', 'error');
            return;
        }
        originalScannerParent = attendanceTakerSection.parentNode;
        scannerOverlay.appendChild(attendanceTakerSection);
        scannerOverlay.style.display = 'flex';
        qrReaderDiv.style.display = 'block';
        startScanBtn.textContent = 'Stop Scanning';
        startScanBtn.classList.add('destructive');
        isScannerActive = true;
        html5QrcodeScanner.render(onScanSuccess, onScanError);
    }

    async function stopScanner() {
        if (!isScannerActive) return;
        try {
            if (html5QrcodeScanner.getState() === 2) { // 2 is SCANNING state
               await html5QrcodeScanner.clear();
            }
        } catch (error) {
            console.warn("Error stopping the scanner (may be benign).", error);
        }
        scannerOverlay.style.display = 'none';
        if (originalScannerParent) {
            originalScannerParent.appendChild(attendanceTakerSection);
            originalScannerParent = null;
        }
        qrReaderDiv.style.display = 'none';
        startScanBtn.textContent = 'Start Scanning';
        startScanBtn.classList.remove('destructive');
        isScannerActive = false;
    }

    async function onScanSuccess(decodedText) {
        if (isScanProcessing) return;
        isScanProcessing = true;
        showToast(`Scanned: ${decodedText}. Processing...`, 'info');
        const formData = new FormData();
        formData.append('action', 'mark_present');
        formData.append('student_id_num', decodedText);
        formData.append('class_id', currentClassId);
        try {
            const result = await apiFetch('api/attendance.php', { method: 'POST', body: formData });
            if (result.success) {
                showToast(result.message, 'success');
                loadEnrolledStudents();
            }
        } catch (error) { /* handled by apiFetch */
        } finally {
            setTimeout(() => { isScanProcessing = false; }, 1500);
        }
    }

    function onScanError(errorMessage) { /* Can be ignored. */ }

    // --- UTILITY ---
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            toast.addEventListener('transitionend', () => toast.remove());
        }, 3000);
    }

    // --- KICK EVERYTHING OFF ---
    initializeDashboard();
});
