// Students Analytics JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Auto-submit form when subject changes
    const subjectSelect = document.getElementById('subject');
    if (subjectSelect) {
        subjectSelect.addEventListener('change', function() {
            this.form.submit();
        });
    }

    // Auto-submit form when student changes
    const studentSelect = document.getElementById('student');
    if (studentSelect) {
        studentSelect.addEventListener('change', function() {
            this.form.submit();
        });
    }

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Add loading states to buttons
    const exportBtn = document.querySelector('button[onclick="exportStudentReport()"]');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            this.innerHTML = '<i data-lucide="loader-2" class="spinning"></i> Exporting...';
            this.disabled = true;
            
            // Re-enable after 3 seconds
            setTimeout(() => {
                this.innerHTML = '<i data-lucide="download"></i> Export Report';
                this.disabled = false;
            }, 3000);
        });
    }
});

// Export student report function
function exportStudentReport() {
    const subject = document.querySelector('select[name="subject"]').value;
    const student = document.querySelector('select[name="student"]').value;

    if (subject && student) {
        const url = `export_student_report.php?subject=${subject}&student=${student}`;
        window.open(url, '_blank');
    } else {
        alert('Please select both subject and student before exporting.');
    }
}

// Chart configuration for dark mode
function getChartColors(isDarkMode) {
    if (isDarkMode) {
        return {
            background: 'rgba(255, 255, 255, 0.1)',
            border: '#00ffc8',
            text: '#e2e8f0',
            grid: 'rgba(255, 255, 255, 0.1)'
        };
    } else {
        return {
            background: 'rgba(0, 123, 255, 0.1)',
            border: '#007bff',
            text: '#333',
            grid: 'rgba(0, 0, 0, 0.1)'
        };
    }
}

// Initialize charts with dark mode support
function initializeCharts() {
    const isDarkMode = document.body.classList.contains('dark-mode');
    const colors = getChartColors(isDarkMode);

    // Attendance Distribution Chart
    const attendanceCtx = document.getElementById('attendanceChart');
    if (attendanceCtx) {
        new Chart(attendanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late'],
                datasets: [{
                    data: [
                        parseInt(attendanceCtx.dataset.present || 0),
                        parseInt(attendanceCtx.dataset.absent || 0),
                        parseInt(attendanceCtx.dataset.late || 0)
                    ],
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
                    borderWidth: 2,
                    borderColor: isDarkMode ? '#1f1f1f' : '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: colors.text,
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    }

    // Attendance Trend Chart
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        const trendData = JSON.parse(trendCtx.dataset.trend || '[]');
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.map(item => new Date(item.DateTime).toLocaleDateString()),
                datasets: [{
                    label: 'Attendance Status',
                    data: trendData.map(item => {
                        if (item.Status === 'present') return 3;
                        if (item.Status === 'late') return 2;
                        return 1;
                    }),
                    borderColor: colors.border,
                    backgroundColor: colors.background,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: colors.border,
                    pointBorderColor: isDarkMode ? '#1f1f1f' : '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            color: colors.grid
                        },
                        ticks: {
                            color: colors.text
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 3,
                        grid: {
                            color: colors.grid
                        },
                        ticks: {
                            stepSize: 1,
                            color: colors.text,
                            callback: function(value) {
                                if (value === 3) return 'Present';
                                if (value === 2) return 'Late';
                                if (value === 1) return 'Absent';
                                return '';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
}

// Dark mode toggle handler
function handleDarkModeToggle() {
    const isDarkMode = document.body.classList.contains('dark-mode');
    
    // Reinitialize charts with new colors
    setTimeout(() => {
        initializeCharts();
    }, 100);
}

// Export functions for other pages
function exportClassAnalytics() {
    const subject = document.querySelector('select[name="subject"]').value;
    if (subject) {
        const url = `export_class_analytics.php?subject=${subject}`;
        window.open(url, '_blank');
    } else {
        alert('Please select a subject before exporting.');
    }
}

function exportAttendanceReport() {
    const subject = document.querySelector('select[name="subject"]').value;
    const date = document.querySelector('input[name="date"]').value;
    const month = document.querySelector('input[name="month"]').value;

    if (subject && date) {
        const url = `export_attendance_report.php?subject=${subject}&date=${date}&month=${month}`;
        window.open(url, '_blank');
    } else {
        alert('Please select both subject and date before exporting.');
    }
}

// Utility functions
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function calculatePercentage(value, total) {
    if (total === 0) return 0;
    return Math.round((value / total) * 100);
}

// Add spinning animation for loading states
const style = document.createElement('style');
style.textContent = `
    .spinning {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style); 