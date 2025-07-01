// Global variables for charts
let attendanceTrendChart;
let subjectPerformanceChart;
let weeklyPatternChart;
let timeSlotChart;

// Chart color schemes
const chartColors = {
    primary: '#1a73e8',
    secondary: '#34a853',
    accent: '#ea4335',
    warning: '#fbbc04',
    info: '#4285f4',
    success: '#34a853',
    danger: '#ea4335',
    gradients: {
        blue: ['#1a73e8', '#4285f4'],
        green: ['#34a853', '#4caf50'],
        red: ['#ea4335', '#f44336'],
        yellow: ['#fbbc04', '#ff9800'],
        purple: ['#9c27b0', '#673ab7']
    }
};

// Initialize attendance analytics
function initializeAttendanceAnalytics() {
    console.log('Initializing attendance analytics...');
    
    // Initialize charts
    initializeCharts();
    
    // Setup event listeners
    setupEventListeners();
    
    // Add animations
    addEntranceAnimations();
    
    // Initialize tooltips
    initializeTooltips();
    
    console.log('Attendance analytics initialized successfully');
}

// Initialize all charts
function initializeCharts() {
    // Set Chart.js defaults
    Chart.defaults.font.family = 'Poppins, sans-serif';
    Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim();
    Chart.defaults.borderColor = getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim();
    
    // Initialize each chart
    initializeAttendanceTrendChart();
    initializeSubjectPerformanceChart();
    initializeWeeklyPatternChart();
    initializeTimeSlotChart();
}

// Attendance Trend Chart
function initializeAttendanceTrendChart() {
    const ctx = document.getElementById('attendanceTrendChart');
    if (!ctx) return;
    
    const data = window.attendanceData?.weeklyData || [];
    
    attendanceTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(item => item.week),
            datasets: [{
                label: 'Attendance %',
                data: data.map(item => item.percentage),
                borderColor: chartColors.primary,
                backgroundColor: createGradient(ctx, chartColors.gradients.blue[0], chartColors.gradients.blue[1]),
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: chartColors.primary,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: chartColors.primary,
                    borderWidth: 2,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        title: function(context) {
                            return context[0].label;
                        },
                        label: function(context) {
                            const dataPoint = data[context.dataIndex];
                            return [
                                `Attendance: ${context.parsed.y}%`,
                                `Classes: ${dataPoint.attended}/${dataPoint.classes}`
                            ];
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    border: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    border: {
                        display: false
                    },
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            animation: {
                duration: 1000,
                easing: 'easeInOutQuart'
            }
        }
    });
}

// Subject Performance Pie Chart
function initializeSubjectPerformanceChart() {
    const ctx = document.getElementById('subjectPerformanceChart');
    if (!ctx) return;
    
    const subjectData = window.attendanceData?.subjectData || [];
    const excellentCount = subjectData.filter(s => s.status === 'excellent').length;
    const goodCount = subjectData.filter(s => s.status === 'good').length;
    const warningCount = subjectData.filter(s => s.status === 'warning').length;
    
    subjectPerformanceChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Excellent (>85%)', 'Good (75-85%)', 'Warning (<75%)'],
            datasets: [{
                data: [excellentCount, goodCount, warningCount],
                backgroundColor: [
                    chartColors.success,
                    chartColors.info,
                    chartColors.danger
                ],
                borderWidth: 0,
                hoverBorderWidth: 3,
                hoverBorderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: chartColors.primary,
                    borderWidth: 2,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return `${context.label}: ${context.parsed} subjects (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '60%',
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 1000
            }
        }
    });
}

// Weekly Pattern Chart
function initializeWeeklyPatternChart() {
    const ctx = document.getElementById('weeklyPatternChart');
    if (!ctx) return;
    
    // Mock weekly pattern data
    const weeklyPatternData = [
        { day: 'Monday', percentage: 92 },
        { day: 'Tuesday', percentage: 95 },
        { day: 'Wednesday', percentage: 88 },
        { day: 'Thursday', percentage: 90 },
        { day: 'Friday', percentage: 78 }
    ];
    
    weeklyPatternChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: weeklyPatternData.map(item => item.day.substr(0, 3)),
            datasets: [{
                label: 'Attendance %',
                data: weeklyPatternData.map(item => item.percentage),
                backgroundColor: weeklyPatternData.map(item => 
                    item.percentage >= 90 ? chartColors.success :
                    item.percentage >= 80 ? chartColors.info : chartColors.warning
                ),
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: chartColors.primary,
                    borderWidth: 2,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return `${context.parsed.y}% attendance`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    border: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    border: {
                        display: false
                    },
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            },
            animation: {
                duration: 800,
                easing: 'easeInOutQuart'
            }
        }
    });
}

// Time Slot Performance Chart
function initializeTimeSlotChart() {
    const ctx = document.getElementById('timeSlotChart');
    if (!ctx) return;
    
    // Mock time slot data
    const timeSlotData = [
        { slot: '8-10 AM', percentage: 95 },
        { slot: '10-12 PM', percentage: 92 },
        { slot: '12-2 PM', percentage: 85 },
        { slot: '2-4 PM', percentage: 82 },
        { slot: '4-6 PM', percentage: 78 }
    ];
    
    timeSlotChart = new Chart(ctx, {
        type: 'polarArea',
        data: {
            labels: timeSlotData.map(item => item.slot),
            datasets: [{
                data: timeSlotData.map(item => item.percentage),
                backgroundColor: [
                    'rgba(26, 115, 232, 0.7)',
                    'rgba(52, 168, 83, 0.7)',
                    'rgba(251, 188, 4, 0.7)',
                    'rgba(234, 67, 53, 0.7)',
                    'rgba(156, 39, 176, 0.7)'
                ],
                borderColor: [
                    'rgba(26, 115, 232, 1)',
                    'rgba(52, 168, 83, 1)',
                    'rgba(251, 188, 4, 1)',
                    'rgba(234, 67, 53, 1)',
                    'rgba(156, 39, 176, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: chartColors.primary,
                    borderWidth: 2,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.parsed}% attendance`;
                        }
                    }
                }
            },
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        display: false
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    angleLines: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                }
            },
            animation: {
                duration: 1200,
                easing: 'easeInOutQuart'
            }
        }
    });
}

// Create gradient for charts
function createGradient(ctx, color1, color2) {
    const canvas = ctx.canvas;
    const context = canvas.getContext('2d');
    const gradient = context.createLinearGradient(0, 0, 0, canvas.height);
    gradient.addColorStop(0, color1 + '40');
    gradient.addColorStop(1, color2 + '10');
    return gradient;
}

// Setup event listeners
function setupEventListeners() {
    // Trend period toggle
    const trendRadios = document.querySelectorAll('input[name="trendPeriod"]');
    trendRadios.forEach(radio => {
        radio.addEventListener('change', handleTrendPeriodChange);
    });
    
    // Subject detail buttons
    const detailButtons = document.querySelectorAll('.subject-item .btn');
    detailButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            // Button click handled by onclick attribute
        });
    });
    
    // Metric cards hover effects
    const metricCards = document.querySelectorAll('.metric-card');
    metricCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(-4px)';
        });
    });
    
    // Timeline item interactions
    const timelineItems = document.querySelectorAll('.timeline-item');
    timelineItems.forEach(item => {
        item.addEventListener('click', function() {
            this.classList.toggle('expanded');
        });
    });
    
    // Goal progress animations
    animateGoalProgress();
    
    // Recommendation actions
    setupRecommendationActions();
}

// Handle trend period change
function handleTrendPeriodChange(event) {
    const period = event.target.id;
    updateTrendChart(period);
}

// Update trend chart based on period
function updateTrendChart(period) {
    if (!attendanceTrendChart) return;
    
    let data;
    if (period === 'monthly') {
        data = window.attendanceData?.monthlyTrends || [];
        attendanceTrendChart.data.labels = data.map(item => item.month);
        attendanceTrendChart.data.datasets[0].data = data.map(item => item.percentage);
    } else {
        data = window.attendanceData?.weeklyData || [];
        attendanceTrendChart.data.labels = data.map(item => item.week);
        attendanceTrendChart.data.datasets[0].data = data.map(item => item.percentage);
    }
    
    attendanceTrendChart.update('active');
}

// Add entrance animations
function addEntranceAnimations() {
    // Animate metric cards
    const metricCards = document.querySelectorAll('.metric-card');
    metricCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('fade-in-up');
    });
    
    // Animate analytics cards
    const analyticsCards = document.querySelectorAll('.analytics-card');
    analyticsCards.forEach((card, index) => {
        card.style.animationDelay = `${0.4 + index * 0.15}s`;
        card.classList.add('slide-in-right');
    });
    
    // Animate progress bars
    setTimeout(() => {
        animateProgressBars();
    }, 800);
}

// Animate progress bars
function animateProgressBars() {
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });
    
    // Animate metric progress bars
    const metricProgressBars = document.querySelectorAll('.metric-progress .progress-bar');
    metricProgressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 200);
    });
}

// Animate goal progress
function animateGoalProgress() {
    const goalProgressBars = document.querySelectorAll('.goal-progress .progress-bar');
    goalProgressBars.forEach((bar, index) => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 500 + index * 200);
    });
}

// Initialize tooltips
function initializeTooltips() {
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Add custom tooltips for day indicators
    const dayIndicators = document.querySelectorAll('.day-indicator');
    dayIndicators.forEach(indicator => {
        indicator.addEventListener('mouseenter', showDayTooltip);
        indicator.addEventListener('mouseleave', hideDayTooltip);
    });
}

// Show day tooltip
function showDayTooltip(event) {
    const tooltip = document.createElement('div');
    tooltip.className = 'custom-tooltip';
    tooltip.textContent = event.target.getAttribute('title');
    tooltip.style.position = 'absolute';
    tooltip.style.background = 'rgba(0, 0, 0, 0.8)';
    tooltip.style.color = 'white';
    tooltip.style.padding = '0.5rem';
    tooltip.style.borderRadius = '0.25rem';
    tooltip.style.fontSize = '0.875rem';
    tooltip.style.zIndex = '1000';
    tooltip.style.pointerEvents = 'none';
    
    document.body.appendChild(tooltip);
    
    const rect = event.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
    
    event.target._tooltip = tooltip;
}

// Hide day tooltip
function hideDayTooltip(event) {
    if (event.target._tooltip) {
        event.target._tooltip.remove();
        delete event.target._tooltip;
    }
}

// Setup recommendation actions
function setupRecommendationActions() {
    const recommendationItems = document.querySelectorAll('.recommendation-item');
    recommendationItems.forEach(item => {
        item.addEventListener('click', function() {
            const priority = this.classList.contains('priority-high') ? 'high' : 
                           this.classList.contains('priority-medium') ? 'medium' : 'low';
            handleRecommendationClick(this, priority);
        });
    });
}

// Handle recommendation click
function handleRecommendationClick(element, priority) {
    // Add pulse animation
    element.classList.add('pulse');
    setTimeout(() => {
        element.classList.remove('pulse');
    }, 2000);
    
    // Show action based on priority
    if (priority === 'high') {
        showToast('High priority recommendation noted! Consider taking immediate action.', 'warning');
    } else if (priority === 'medium') {
        showToast('Medium priority recommendation saved for review.', 'info');
    } else {
        showToast('Keep up the excellent work!', 'success');
    }
}

// Utility Functions

// View subject details
function viewSubjectDetails(subjectCode) {
    console.log('Viewing details for subject:', subjectCode);
    
    // Create modal content (you can expand this)
    const modalHtml = `
        <div class="modal fade" id="subjectDetailModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i data-lucide="book-open"></i>
                            ${subjectCode} - Detailed Analysis
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Detailed attendance analysis for ${subjectCode} will be displayed here.</p>
                        <p class="text-muted">This feature will show:</p>
                        <ul class="text-muted">
                            <li>Daily attendance records</li>
                            <li>Monthly trends</li>
                            <li>Comparison with class average</li>
                            <li>Upcoming classes</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary">Download Report</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal
    const existingModal = document.getElementById('subjectDetailModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add new modal
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('subjectDetailModal'));
    modal.show();
    
    // Initialize icons
    lucide.createIcons();
}

// View full history
function viewFullHistory() {
    console.log('Viewing full attendance history');
    showToast('Full attendance history feature coming soon!', 'info');
}

// Export report
function exportReport() {
    console.log('Exporting attendance report');
    
    // Show export modal
    const modal = new bootstrap.Modal(document.getElementById('exportModal'));
    modal.show();
}

// Download report
function downloadReport() {
    console.log('Downloading report with selected options');
    
    const includeCharts = document.getElementById('includeCharts').checked;
    const includeDetails = document.getElementById('includeDetails').checked;
    const includeRecommendations = document.getElementById('includeRecommendations').checked;
    
    // Simulate download
    showToast('Generating PDF report...', 'info');
    
    setTimeout(() => {
        showToast('Report downloaded successfully!', 'success');
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
        modal.hide();
    }, 2000);
}

// Show toast notification
function showToast(message, type = 'info') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'error' ? 'danger' : 'primary'} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    // Add to page
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '1055';
        document.body.appendChild(toastContainer);
    }
    
    toastContainer.appendChild(toast);
    
    // Show toast
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    // Remove from DOM after hiding
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

// Update chart colors based on theme
function updateChartColors() {
    const isDarkMode = document.body.classList.contains('dark-mode');
    
    if (isDarkMode) {
        Chart.defaults.color = '#a0aec0';
        Chart.defaults.borderColor = '#4a5568';
    } else {
        Chart.defaults.color = '#718096';
        Chart.defaults.borderColor = '#e2e8f0';
    }
    
    // Update existing charts
    [attendanceTrendChart, subjectPerformanceChart, weeklyPatternChart, timeSlotChart].forEach(chart => {
        if (chart) {
            chart.update();
        }
    });
}

// Resize charts on window resize
function handleWindowResize() {
    [attendanceTrendChart, subjectPerformanceChart, weeklyPatternChart, timeSlotChart].forEach(chart => {
        if (chart) {
            chart.resize();
        }
    });
}

// Initialize resize handler
window.addEventListener('resize', debounce(handleWindowResize, 250));

// Theme change handler
function handleThemeChange() {
    updateChartColors();
}

// Debounce utility
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Export functions for global access
window.initializeAttendanceAnalytics = initializeAttendanceAnalytics;
window.viewSubjectDetails = viewSubjectDetails;
window.viewFullHistory = viewFullHistory;
window.exportReport = exportReport;
window.downloadReport = downloadReport;
window.handleThemeChange = handleThemeChange;

// Auto-initialize if DOM is already loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAttendanceAnalytics);
} else {
    initializeAttendanceAnalytics();
}