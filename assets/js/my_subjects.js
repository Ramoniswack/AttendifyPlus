// My Subjects JavaScript

let currentSubjectId = null;
let attendanceChart = null;
let assignmentChart = null;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Add hover effects for subject cards
    const subjectCards = document.querySelectorAll('.subject-card');
    subjectCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Add click effects for buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            // Add ripple effect
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = event.clientX - rect.left - size / 2;
            const y = event.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
});

// View Subject Details Function
function viewSubjectDetails(subjectId, subjectCode, subjectName) {
    currentSubjectId = subjectId;
    
    // Update modal title
    document.getElementById('modalSubjectTitle').textContent = `${subjectCode} - ${subjectName}`;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('subjectDetailsModal'));
    modal.show();
    
    // Show loading state
    document.getElementById('modalLoading').style.display = 'block';
    document.getElementById('modalContent').style.display = 'none';
    
    // Fetch subject details
    fetch(`../../api/get_student_subject_details.php?subject_id=${subjectId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Hide loading and show content
            document.getElementById('modalLoading').style.display = 'none';
            document.getElementById('modalContent').style.display = 'block';
            
            // Update statistics
            updateModalStatistics(data.statistics);
            
            // Update attendance history
            updateAttendanceHistory(data.attendance_history);
            
            // Update assignment history
            updateAssignmentHistory(data.assignment_history);
            
            // Create charts
            createAttendanceChart(data.statistics);
            createAssignmentChart(data.statistics);
            
        })
        .catch(error => {
            console.error('Error fetching subject details:', error);
            document.getElementById('modalLoading').innerHTML = `
                <div class="text-center py-4">
                    <i data-lucide="alert-circle" style="width: 48px; height: 48px;" class="text-danger mb-3"></i>
                    <p class="text-danger">Error loading subject details</p>
                    <button class="btn btn-outline-primary btn-sm" onclick="viewSubjectDetails(${subjectId}, '${subjectCode}', '${subjectName}')">
                        <i data-lucide="refresh-cw"></i>
                        Try Again
                    </button>
                </div>
            `;
            lucide.createIcons();
        });
}

// Update modal statistics
function updateModalStatistics(stats) {
    document.getElementById('modalAttendanceRate').textContent = `${stats.attendance_rate}%`;
    document.getElementById('modalAssignmentCompletion').textContent = `${stats.assignment_completion}%`;
    document.getElementById('modalAverageGrade').textContent = stats.average_grade ? `${stats.average_grade}/${stats.max_points}` : 'N/A';
}

// Update attendance history table
function updateAttendanceHistory(history) {
    const tbody = document.getElementById('attendanceHistoryBody');
    tbody.innerHTML = '';
    
    if (history.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No attendance records found</td></tr>';
        return;
    }
    
    history.forEach(record => {
        const row = document.createElement('tr');
        const statusBadge = getStatusBadge(record.Status);
        const methodBadge = getMethodBadge(record.Method);
        
        row.innerHTML = `
            <td>${new Date(record.DateTime).toLocaleDateString()}</td>
            <td>${statusBadge}</td>
            <td>${methodBadge}</td>
            <td><small class="text-muted">${new Date(record.DateTime).toLocaleTimeString()}</small></td>
        `;
        tbody.appendChild(row);
    });
}

// Update assignment history table
function updateAssignmentHistory(history) {
    const tbody = document.getElementById('assignmentHistoryBody');
    tbody.innerHTML = '';
    
    if (history.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No assignments found</td></tr>';
        return;
    }
    
    history.forEach(assignment => {
        const row = document.createElement('tr');
        const statusBadge = getAssignmentStatusBadge(assignment.Status, assignment.SubmittedAt);
        const gradeText = assignment.Grade ? `${assignment.Grade}/${assignment.MaxPoints}` : '-';
        const lateIndicator = assignment.IsLate ? ' <span class="text-danger">(Late)</span>' : '';
        
        row.innerHTML = `
            <td><strong>${assignment.Title}</strong></td>
            <td>${assignment.DueDate ? new Date(assignment.DueDate).toLocaleDateString() : 'N/A'}</td>
            <td>${assignment.SubmittedAt ? new Date(assignment.SubmittedAt).toLocaleDateString() + lateIndicator : 'Not Submitted'}</td>
            <td>${statusBadge}</td>
            <td>${gradeText}</td>
        `;
        tbody.appendChild(row);
    });
}

// Create attendance chart
function createAttendanceChart(stats) {
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    
    if (attendanceChart) {
        attendanceChart.destroy();
    }
    
    attendanceChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent', 'Late'],
            datasets: [{
                data: [stats.present_count, stats.absent_count, stats.late_count],
                backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 10,
                        usePointStyle: true
                    }
                }
            }
        }
    });
}

// Create assignment chart
function createAssignmentChart(stats) {
    const ctx = document.getElementById('assignmentChart').getContext('2d');
    
    if (assignmentChart) {
        assignmentChart.destroy();
    }
    
    const submitted = stats.submitted_count;
    const notSubmitted = stats.total_assignments - stats.submitted_count;
    
    assignmentChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Submitted', 'Not Submitted'],
            datasets: [{
                data: [submitted, notSubmitted],
                backgroundColor: ['#17a2b8', '#6c757d'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 10,
                        usePointStyle: true
                    }
                }
            }
        }
    });
}

// Helper functions for badges
function getStatusBadge(status) {
    const badges = {
        'present': '<span class="badge bg-success">Present</span>',
        'absent': '<span class="badge bg-danger">Absent</span>',
        'late': '<span class="badge bg-warning text-dark">Late</span>'
    };
    return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
}

function getMethodBadge(method) {
    const badges = {
        'qr': '<span class="badge bg-info">QR Code</span>',
        'manual': '<span class="badge bg-secondary">Manual</span>'
    };
    return badges[method] || '<span class="badge bg-secondary">Unknown</span>';
}

function getAssignmentStatusBadge(status, submittedAt) {
    if (!submittedAt) {
        return '<span class="badge bg-secondary">Not Submitted</span>';
    }
    
    const badges = {
        'submitted': '<span class="badge bg-warning">Pending</span>',
        'graded': '<span class="badge bg-info">Graded</span>',
        'returned': '<span class="badge bg-primary">Returned</span>'
    };
    return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
}

// Export subject report
function exportSubjectReport() {
    if (!currentSubjectId) {
        alert('No subject selected for export');
        return;
    }
    
    const url = `../../api/export_student_subject_report.php?subject_id=${currentSubjectId}`;
    window.open(url, '_blank');
}

// Add CSS for ripple effect
const style = document.createElement('style');
style.textContent = `
    .btn {
        position: relative;
        overflow: hidden;
    }
    
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple-animation 0.6s linear;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    /* Dark mode ripple */
    body.dark-mode .ripple {
        background: rgba(0, 255, 200, 0.3);
    }
    
    /* Modal dark mode support */
    body.dark-mode .modal-content {
        background: var(--card-dark);
        color: var(--text-dark);
    }
    
    body.dark-mode .modal-header {
        border-bottom-color: var(--border-dark);
    }
    
    body.dark-mode .modal-footer {
        border-top-color: var(--border-dark);
    }
    
    body.dark-mode .card.bg-light {
        background: var(--border-dark) !important;
    }
    
    body.dark-mode .stat-card {
        background: var(--card-dark);
        border: 1px solid var(--border-dark);
    }
    
    body.dark-mode .stat-value {
        color: var(--accent-dark);
    }
    
    body.dark-mode .stat-label {
        color: var(--text-dark);
    }
    
    body.dark-mode .stat-icon {
        color: var(--accent-dark);
    }
`;
document.head.appendChild(style);

// Performance tracking
function trackPerformanceMetrics() {
    const subjects = document.querySelectorAll('.subject-card');
    const metrics = {
        totalSubjects: subjects.length,
        attendanceRates: [],
        assignmentCompletion: [],
        averageGrades: []
    };
    
    subjects.forEach(subject => {
        // Extract attendance rate
        const attendanceText = subject.querySelector('.text-muted');
        if (attendanceText && attendanceText.textContent.includes('%')) {
            const rate = parseInt(attendanceText.textContent);
            if (!isNaN(rate)) {
                metrics.attendanceRates.push(rate);
            }
        }
        
        // Extract assignment completion
        const assignmentText = subject.querySelector('.text-muted');
        if (assignmentText && assignmentText.textContent.includes('/')) {
            const parts = assignmentText.textContent.split('/');
            if (parts.length === 2) {
                const submitted = parseInt(parts[0]);
                const total = parseInt(parts[1]);
                if (!isNaN(submitted) && !isNaN(total) && total > 0) {
                    metrics.assignmentCompletion.push((submitted / total) * 100);
                }
            }
        }
    });
    
    // Calculate averages
    const avgAttendance = metrics.attendanceRates.length > 0 
        ? metrics.attendanceRates.reduce((a, b) => a + b, 0) / metrics.attendanceRates.length 
        : 0;
    
    const avgAssignmentCompletion = metrics.assignmentCompletion.length > 0 
        ? metrics.assignmentCompletion.reduce((a, b) => a + b, 0) / metrics.assignmentCompletion.length 
        : 0;
    
    console.log('Performance Metrics:', {
        totalSubjects: metrics.totalSubjects,
        averageAttendance: Math.round(avgAttendance) + '%',
        averageAssignmentCompletion: Math.round(avgAssignmentCompletion) + '%'
    });
    
    return metrics;
}

// Auto-refresh performance data (optional)
function refreshPerformanceData() {
    // This could be used to refresh data periodically
    // For now, just track current metrics
    trackPerformanceMetrics();
}

// Initialize performance tracking
document.addEventListener('DOMContentLoaded', function() {
    // Track initial metrics
    setTimeout(trackPerformanceMetrics, 1000);
    
    // Set up periodic refresh (every 5 minutes)
    setInterval(refreshPerformanceData, 300000);
});

// Responsive adjustments
function handleResponsiveLayout() {
    const cards = document.querySelectorAll('.subject-card');
    const container = document.querySelector('.container-fluid');
    
    if (window.innerWidth < 768) {
        cards.forEach(card => {
            card.style.marginBottom = '1rem';
        });
    } else {
        cards.forEach(card => {
            card.style.marginBottom = '0';
        });
    }
}

// Listen for window resize
window.addEventListener('resize', handleResponsiveLayout);

// Initialize responsive layout
document.addEventListener('DOMContentLoaded', handleResponsiveLayout);
