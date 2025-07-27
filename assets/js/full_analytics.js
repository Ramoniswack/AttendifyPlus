// Full Analytics JavaScript
document.addEventListener('DOMContentLoaded', function() {
    console.log('Full Analytics initialized');
    
    // Initialize Lucide icons
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }

    // Initialize charts
    initializeCharts();
    
    // Initialize search and filter functionality
    initializeSearchAndFilters();
    
    // Initialize theme
    initializeTheme();
});



// ===== CHART INITIALIZATION =====
function initializeCharts() {
    // Department Chart
    const departmentCtx = document.getElementById('departmentChart');
    if (departmentCtx && deptStats) {
        new Chart(departmentCtx, {
            type: 'bar',
            data: {
                labels: deptStats.map(item => item.DepartmentName),
                datasets: [{
                    label: 'Students',
                    data: deptStats.map(item => item.student_count),
                    backgroundColor: 'rgba(26, 115, 232, 0.8)',
                    borderColor: 'rgba(26, 115, 232, 1)',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                }, {
                    label: 'Teachers',
                    data: deptStats.map(item => item.teacher_count),
                    backgroundColor: 'rgba(220, 53, 69, 0.8)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            color: getComputedStyle(document.body).getPropertyValue('--text-light')
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(26, 115, 232, 0.5)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            color: getComputedStyle(document.body).getPropertyValue('--text-light'),
                            font: {
                                size: 12
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: getComputedStyle(document.body).getPropertyValue('--text-light'),
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    }

    // Attendance Method Chart
    const attendanceMethodCtx = document.getElementById('attendanceMethodChart');
    if (attendanceMethodCtx && attendanceStats) {
        new Chart(attendanceMethodCtx, {
            type: 'doughnut',
            data: {
                labels: ['QR Code', 'Manual'],
                datasets: [{
                    data: [attendanceStats.qr, attendanceStats.manual],
                    backgroundColor: [
                        'rgba(25, 135, 84, 0.8)',
                        'rgba(255, 193, 7, 0.8)'
                    ],
                    borderColor: [
                        'rgba(25, 135, 84, 1)',
                        'rgba(255, 193, 7, 1)'
                    ],
                    borderWidth: 2,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            color: getComputedStyle(document.body).getPropertyValue('--text-light')
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(26, 115, 232, 0.5)',
                        borderWidth: 1,
                        cornerRadius: 8
                    }
                }
            }
        });
    }
}

// ===== SEARCH AND FILTER FUNCTIONALITY =====
function initializeSearchAndFilters() {
    const searchInput = document.getElementById('searchInput');
    const departmentFilter = document.getElementById('departmentFilter');
    const semesterFilter = document.getElementById('semesterFilter');
    const userTypeFilter = document.getElementById('userTypeFilter');

    // Search functionality
    if (searchInput) {
        searchInput.addEventListener('input', filterTables);
    }

    // Filter functionality
    if (departmentFilter) {
        departmentFilter.addEventListener('change', filterTables);
    }

    if (semesterFilter) {
        semesterFilter.addEventListener('change', filterTables);
    }

    if (userTypeFilter) {
        userTypeFilter.addEventListener('change', filterTables);
    }
}

function filterTables() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const departmentFilter = document.getElementById('departmentFilter').value;
    const semesterFilter = document.getElementById('semesterFilter').value;
    const userTypeFilter = document.getElementById('userTypeFilter').value;

    // Filter teachers table
    const teacherRows = document.querySelectorAll('.teacher-row');
    teacherRows.forEach(row => {
        const name = row.dataset.name || '';
        const department = row.dataset.department || '';
        const contact = row.dataset.contact || '';

        let showRow = true;

        // Search filter
        if (searchTerm) {
            const searchMatch = name.includes(searchTerm) || 
                               department.toLowerCase().includes(searchTerm) || 
                               contact.includes(searchTerm);
            if (!searchMatch) showRow = false;
        }

        // Department filter
        if (departmentFilter && department !== departmentFilter) {
            showRow = false;
        }

        // User type filter
        if (userTypeFilter && userTypeFilter !== 'teacher') {
            showRow = false;
        }

        row.style.display = showRow ? '' : 'none';
    });

    // Filter students table
    const studentRows = document.querySelectorAll('.student-row');
    studentRows.forEach(row => {
        const name = row.dataset.name || '';
        const department = row.dataset.department || '';
        const semester = row.dataset.semester || '';
        const year = row.dataset.year || '';
        const contact = row.dataset.contact || '';

        let showRow = true;

        // Search filter
        if (searchTerm) {
            const searchMatch = name.includes(searchTerm) || 
                               department.toLowerCase().includes(searchTerm) || 
                               contact.includes(searchTerm);
            if (!searchMatch) showRow = false;
        }

        // Department filter
        if (departmentFilter && department !== departmentFilter) {
            showRow = false;
        }

        // Semester filter
        if (semesterFilter && semester !== semesterFilter) {
            showRow = false;
        }

        // User type filter
        if (userTypeFilter && userTypeFilter !== 'student') {
            showRow = false;
        }

        row.style.display = showRow ? '' : 'none';
    });
}

// ===== INDIVIDUAL ANALYTICS FUNCTIONS =====
function viewTeacherAnalytics(teacherId) {
    console.log('Viewing teacher analytics for ID:', teacherId);
    
    // Show loading state
    const modal = new bootstrap.Modal(document.getElementById('individualAnalyticsModal'));
    modal.show();
    
    document.getElementById('analyticsModalTitle').innerHTML = '<i data-lucide="bar-chart-3"></i> Loading Teacher Analytics...';
    document.getElementById('analyticsModalBody').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3">Loading analytics...</p></div>';
    
    // Fetch teacher analytics with proper error handling
    fetch(`../../api/get_teacher_analytics.php?teacher_id=${teacherId}`)
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        })
        .then(data => {
            console.log('Teacher analytics response:', data);
            if (data.success) {
                displayTeacherAnalytics(data.teacher, data.analytics);
            } else {
                document.getElementById('analyticsModalBody').innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load teacher analytics.'}</div>`;
            }
        })
        .catch(error => {
            console.error('Error fetching teacher analytics:', error);
            document.getElementById('analyticsModalBody').innerHTML = `<div class="alert alert-danger">Error loading teacher analytics: ${error.message}</div>`;
        });
}

function viewStudentAnalytics(studentId) {
    console.log('Viewing student analytics for ID:', studentId);
    
    // Show loading state
    const modal = new bootstrap.Modal(document.getElementById('individualAnalyticsModal'));
    modal.show();
    
    document.getElementById('analyticsModalTitle').innerHTML = '<i data-lucide="bar-chart-3"></i> Loading Student Analytics...';
    document.getElementById('analyticsModalBody').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3">Loading analytics...</p></div>';
    
    // Fetch student analytics with proper error handling
    fetch(`../../api/get_student_analytics.php?student_id=${studentId}`)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Student analytics response:', data);
            if (data.success) {
                displayStudentAnalytics(data.student, data.analytics);
            } else {
                document.getElementById('analyticsModalBody').innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load student analytics.'}</div>`;
            }
        })
        .catch(error => {
            console.error('Error fetching student analytics:', error);
            document.getElementById('analyticsModalBody').innerHTML = '<div class="alert alert-danger">Error loading student analytics. Please try again.</div>';
        });
}

function displayTeacherAnalytics(teacher, analytics) {
    document.getElementById('analyticsModalTitle').innerHTML = `<i data-lucide="bar-chart-3"></i> ${teacher.FullName} - Analytics`;
    
    const modalBody = document.getElementById('analyticsModalBody');
    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Teacher Information</h6>
                        <p><strong>Name:</strong> ${teacher.FullName}</p>
                        <p><strong>Department:</strong> ${teacher.DepartmentName || 'Not Assigned'}</p>
                        <p><strong>Contact:</strong> ${teacher.Contact || 'N/A'}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Quick Stats</h6>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="stat-number text-primary">${analytics.total_subjects || 0}</div>
                                <div class="stat-label">Subjects</div>
                            </div>
                            <div class="col-6">
                                <div class="stat-number text-success">${analytics.total_assignments || 0}</div>
                                <div class="stat-label">Assignments</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Attendance Sessions</h6>
                        <div style="height: 200px; position: relative;">
                            <canvas id="teacherAttendanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Assignment Submissions</h6>
                        <div style="height: 200px; position: relative;">
                            <canvas id="teacherAssignmentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Recent Activities</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Activity</th>
                                        <th>Date</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${analytics.recent_activities ? analytics.recent_activities.map(activity => `
                                        <tr>
                                            <td>${activity.type}</td>
                                            <td>${activity.date}</td>
                                            <td>${activity.details}</td>
                                        </tr>
                                    `).join('') : '<tr><td colspan="3">No recent activities</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Initialize teacher-specific charts
    setTimeout(() => {
        initializeTeacherCharts(analytics);
        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }
    }, 100);
    
    // Update download button
    document.getElementById('downloadIndividualReport').onclick = () => downloadTeacherReport(teacher.TeacherID);
}

function displayStudentAnalytics(student, analytics) {
    document.getElementById('analyticsModalTitle').innerHTML = `<i data-lucide="bar-chart-3"></i> ${student.FullName} - Analytics`;
    
    const modalBody = document.getElementById('analyticsModalBody');
    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Student Information</h6>
                        <p><strong>Name:</strong> ${student.FullName}</p>
                        <p><strong>Department:</strong> ${student.DepartmentName}</p>
                        <p><strong>Semester:</strong> ${student.SemesterNumber}</p>
                        <p><strong>Join Year:</strong> ${student.JoinYear}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Quick Stats</h6>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="stat-number text-primary">${analytics.attendance_percentage || 0}%</div>
                                <div class="stat-label">Attendance</div>
                            </div>
                            <div class="col-6">
                                <div class="stat-number text-success">${analytics.total_submissions || 0}</div>
                                <div class="stat-label">Submissions</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Attendance Trend</h6>
                        <div style="height: 200px; position: relative;">
                            <canvas id="studentAttendanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Subject Performance</h6>
                        <div style="height: 200px; position: relative;">
                            <canvas id="studentPerformanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Recent Activities</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Activity</th>
                                        <th>Date</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${analytics.recent_activities ? analytics.recent_activities.map(activity => `
                                        <tr>
                                            <td>${activity.type}</td>
                                            <td>${activity.date}</td>
                                            <td>${activity.details}</td>
                                        </tr>
                                    `).join('') : '<tr><td colspan="3">No recent activities</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Initialize student-specific charts
    setTimeout(() => {
        initializeStudentCharts(analytics);
        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }
    }, 100);
    
    // Update download button
    document.getElementById('downloadIndividualReport').onclick = () => downloadStudentReport(student.StudentID);
}

// ===== CHART INITIALIZATION FOR INDIVIDUAL ANALYTICS =====
function initializeTeacherCharts(analytics) {
    // Teacher attendance chart
    const attendanceCtx = document.getElementById('teacherAttendanceChart');
    if (attendanceCtx && analytics.attendance_data) {
        new Chart(attendanceCtx, {
            type: 'line',
            data: {
                labels: analytics.attendance_data.labels || [],
                datasets: [{
                    label: 'Attendance Sessions',
                    data: analytics.attendance_data.values || [],
                    borderColor: 'rgba(26, 115, 232, 1)',
                    backgroundColor: 'rgba(26, 115, 232, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Teacher assignment chart
    const assignmentCtx = document.getElementById('teacherAssignmentChart');
    if (assignmentCtx && analytics.assignment_data) {
        new Chart(assignmentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Submitted', 'Graded', 'Pending'],
                datasets: [{
                    data: [
                        analytics.assignment_data.submitted || 0,
                        analytics.assignment_data.graded || 0,
                        analytics.assignment_data.pending || 0
                    ],
                    backgroundColor: [
                        'rgba(25, 135, 84, 0.8)',
                        'rgba(26, 115, 232, 0.8)',
                        'rgba(255, 193, 7, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

function initializeStudentCharts(analytics) {
    // Student attendance chart
    const attendanceCtx = document.getElementById('studentAttendanceChart');
    if (attendanceCtx && analytics.attendance_trend) {
        new Chart(attendanceCtx, {
            type: 'line',
            data: {
                labels: analytics.attendance_trend.labels || [],
                datasets: [{
                    label: 'Attendance Rate',
                    data: analytics.attendance_trend.values || [],
                    borderColor: 'rgba(25, 135, 84, 1)',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }

    // Student performance chart
    const performanceCtx = document.getElementById('studentPerformanceChart');
    if (performanceCtx && analytics.subject_performance) {
        new Chart(performanceCtx, {
            type: 'bar',
            data: {
                labels: analytics.subject_performance.labels || [],
                datasets: [{
                    label: 'Performance',
                    data: analytics.subject_performance.values || [],
                    backgroundColor: 'rgba(26, 115, 232, 0.8)',
                    borderColor: 'rgba(26, 115, 232, 1)',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }
}

// ===== DOWNLOAD FUNCTIONS =====
function downloadTeacherReport(teacherId) {
    console.log('Downloading teacher report for ID:', teacherId);
    
    // Show loading indicator
    const downloadBtn = event.target;
    const originalText = downloadBtn.innerHTML;
    downloadBtn.innerHTML = '<i data-lucide="loader-2" class="spinning"></i> Downloading...';
    downloadBtn.disabled = true;
    
    // Create a hidden iframe to handle the download
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = `../../api/download_teacher_report.php?teacher_id=${teacherId}`;
    
    iframe.onload = function() {
        // Remove iframe after download
        setTimeout(() => {
            document.body.removeChild(iframe);
            downloadBtn.innerHTML = originalText;
            downloadBtn.disabled = false;
        }, 1000);
    };
    
    iframe.onerror = function() {
        console.error('Download failed');
        downloadBtn.innerHTML = originalText;
        downloadBtn.disabled = false;
        alert('Download failed. Please try again.');
    };
    
    document.body.appendChild(iframe);
}

function downloadStudentReport(studentId) {
    console.log('Downloading student report for ID:', studentId);
    
    // Show loading indicator
    const downloadBtn = event.target;
    const originalText = downloadBtn.innerHTML;
    downloadBtn.innerHTML = '<i data-lucide="loader-2" class="spinning"></i> Downloading...';
    downloadBtn.disabled = true;
    
    // Create a hidden iframe to handle the download
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = `../../api/download_student_report.php?student_id=${studentId}`;
    
    iframe.onload = function() {
        // Remove iframe after download
        setTimeout(() => {
            document.body.removeChild(iframe);
            downloadBtn.innerHTML = originalText;
            downloadBtn.disabled = false;
        }, 1000);
    };
    
    iframe.onerror = function() {
        console.error('Download failed');
        downloadBtn.innerHTML = originalText;
        downloadBtn.disabled = false;
        alert('Download failed. Please try again.');
    };
    
    document.body.appendChild(iframe);
}

function exportTeachersAnalytics() {
    console.log('Exporting all teachers analytics');
    window.open('../../api/export_teachers_analytics.php', '_blank');
}

function exportStudentsAnalytics() {
    console.log('Exporting all students analytics');
    window.open('../../api/export_students_analytics.php', '_blank');
}

function exportAllAnalytics() {
    console.log('Exporting all analytics');
    window.open('../../api/export_all_analytics.php', '_blank');
}

// ===== UTILITY FUNCTIONS =====
function refreshAnalytics() {
    console.log('Refreshing analytics');
    location.reload();
}

function initializeTheme() {
    // Check for saved theme preference or default to light mode
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
    }

    // Theme toggle function
    window.toggleTheme = function() {
        const isDark = document.body.classList.toggle('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        
        // Reinitialize charts with new theme colors
        setTimeout(() => {
            initializeCharts();
        }, 100);
    };
}

// ===== AUTO-REFRESH NOTIFICATIONS =====
setInterval(function() {
    // Refresh notification count if needed
    const notificationBadge = document.querySelector('.notification-badge');
    if (notificationBadge) {
        fetch('../../api/get_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.unread_count > 0) {
                    notificationBadge.textContent = data.unread_count;
                    notificationBadge.style.display = 'inline';
                } else {
                    notificationBadge.style.display = 'none';
                }
            })
            .catch(error => console.error('Error fetching notifications:', error));
    }
}, 30000); // Check every 30 seconds 