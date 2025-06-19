document.addEventListener('DOMContentLoaded', function() {
    // Initialize page
    initializeAttendance();
    updateAttendanceStats();
    
    // Add event listeners to all attendance radio buttons
    const attendanceInputs = document.querySelectorAll('input[name^="attendance["]');
    attendanceInputs.forEach(input => {
        input.addEventListener('change', updateAttendanceStats);
    });
});

// Initialize attendance functionality
function initializeAttendance() {
    console.log('Initializing attendance page...');
    
    // Apply theme from localStorage
    if (localStorage.getItem("theme") === "dark") {
        document.body.classList.add("dark-mode");
    }
    
    // Initialize Lucide icons
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
    
    // Set up form validation
    setupFormValidation();
}

// Form change handlers
function handleDateChange() {
    console.log('Date changed, submitting form...');
    document.getElementById('selectionForm').submit();
}

function handleSemesterChange() {
    console.log('Semester changed, submitting form...');
    document.getElementById('selectionForm').submit();
}

function handleSubjectChange() {
    console.log('Subject changed, submitting form...');
    document.getElementById('selectionForm').submit();
}

// Bulk attendance actions
function markAllPresent() {
    const presentInputs = document.querySelectorAll('input[value="present"]');
    presentInputs.forEach(input => {
        if (!input.disabled) {
            input.checked = true;
            // Trigger animation
            const card = input.closest('.student-card');
            if (card) {
                card.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    card.style.transform = '';
                }, 150);
            }
        }
    });
    updateAttendanceStats();
    showToast('All students marked as present', 'success');
}

function markAllAbsent() {
    const absentInputs = document.querySelectorAll('input[value="absent"]');
    absentInputs.forEach(input => {
        if (!input.disabled) {
            input.checked = true;
            // Trigger animation
            const card = input.closest('.student-card');
            if (card) {
                card.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    card.style.transform = '';
                }, 150);
            }
        }
    });
    updateAttendanceStats();
    showToast('All students marked as absent', 'warning');
}

// Reset form
function resetForm() {
    const attendanceInputs = document.querySelectorAll('input[name^="attendance["]');
    attendanceInputs.forEach(input => {
        if (!input.disabled) {
            input.checked = false;
        }
    });
    updateAttendanceStats();
    showToast('Attendance form reset', 'info');
}

// Update attendance statistics
function updateAttendanceStats() {
    const presentCount = document.querySelectorAll('input[value="present"]:checked').length;
    const absentCount = document.querySelectorAll('input[value="absent"]:checked').length;
    const lateCount = document.querySelectorAll('input[value="late"]:checked').length;
    
    // Update counters
    const presentCountEl = document.getElementById('presentCount');
    const absentCountEl = document.getElementById('absentCount');
    const lateCountEl = document.getElementById('lateCount');
    
    if (presentCountEl) presentCountEl.textContent = presentCount;
    if (absentCountEl) absentCountEl.textContent = absentCount;
    if (lateCountEl) lateCountEl.textContent = lateCount;
    
    // Update submit button state
    const submitBtn = document.getElementById('submitBtn');
    const totalStudents = document.querySelectorAll('input[name^="attendance["]').length / 3; // 3 options per student
    const markedStudents = presentCount + absentCount + lateCount;
    
    if (submitBtn) {
        submitBtn.disabled = markedStudents < totalStudents;
        if (markedStudents === totalStudents) {
            submitBtn.innerHTML = '<i data-lucide="save"></i> Submit Attendance (' + markedStudents + '/' + totalStudents + ')';
        } else {
            submitBtn.innerHTML = '<i data-lucide="save"></i> Submit Attendance (' + markedStudents + '/' + totalStudents + ')';
        }
        
        // Re-initialize Lucide icons for the button
        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }
    }
    
    // Add visual feedback to stats
    animateStatUpdate(presentCountEl, 'success');
    animateStatUpdate(absentCountEl, 'danger');
    animateStatUpdate(lateCountEl, 'warning');
}

// Animate stat updates
function animateStatUpdate(element, type) {
    if (!element) return;
    
    element.style.transform = 'scale(1.1)';
    element.style.transition = 'transform 0.3s ease';
    
    setTimeout(() => {
        element.style.transform = 'scale(1)';
    }, 300);
}

// Form validation
function setupFormValidation() {
    const form = document.getElementById('attendanceForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        const attendanceInputs = document.querySelectorAll('input[name^="attendance["]:checked');
        const totalStudents = document.querySelectorAll('input[name^="attendance["]').length / 3;
        
        if (attendanceInputs.length < totalStudents) {
            e.preventDefault();
            showToast('Please mark attendance for all students', 'error');
            return false;
        }
        
        showToast('Submitting attendance...', 'info');
        return true;
    });
}

// Toast notification system
function showToast(message, type = 'info') {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.toast-notification');
    existingToasts.forEach(toast => toast.remove());
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <i data-lucide="${getToastIcon(type)}"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Style the toast
    toast.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: ${getToastColor(type)};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 500;
        min-width: 250px;
        animation: slideInRight 0.3s ease-out;
        transition: all 0.3s ease;
    `;
    
    // Add animation keyframes if not already added
    if (!document.querySelector('#toast-animations')) {
        const style = document.createElement('style');
        style.id = 'toast-animations';
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    }
    
    document.body.appendChild(toast);
    
    // Initialize Lucide icons for the toast
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 300);
    }, 3000);
}

function getToastIcon(type) {
    const icons = {
        success: 'check-circle',
        error: 'alert-circle',
        warning: 'alert-triangle',
        info: 'info'
    };
    return icons[type] || 'info';
}

function getToastColor(type) {
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    return colors[type] || '#17a2b8';
}

// Theme management
window.toggleTheme = function() {
    const isDark = document.body.classList.toggle("dark-mode");
    localStorage.setItem("theme", isDark ? "dark" : "light");
    updateThemeElements();
};

function updateThemeElements() {
    const lightIcons = document.querySelectorAll('.light-icon');
    const darkIcons = document.querySelectorAll('.dark-icon');
    const isDarkMode = document.body.classList.contains('dark-mode');

    lightIcons.forEach(icon => {
        icon.style.display = isDarkMode ? 'none' : 'inline';
    });

    darkIcons.forEach(icon => {
        icon.style.display = isDarkMode ? 'inline' : 'none';
    });
}

// Enhanced user experience features
document.addEventListener('DOMContentLoaded', function() {
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + A: Mark all present
        if ((e.ctrlKey || e.metaKey) && e.key === 'a' && e.shiftKey) {
            e.preventDefault();
            markAllPresent();
        }
        
        // Ctrl/Cmd + D: Mark all absent
        if ((e.ctrlKey || e.metaKey) && e.key === 'd' && e.shiftKey) {
            e.preventDefault();
            markAllAbsent();
        }
        
        // Ctrl/Cmd + R: Reset form
        if ((e.ctrlKey || e.metaKey) && e.key === 'r' && e.shiftKey) {
            e.preventDefault();
            resetForm();
        }
    });
    
    // Add loading states
    const form = document.getElementById('selectionForm');
    if (form) {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i data-lucide="loader-2"></i> Loading...';
                submitBtn.disabled = true;
            }
        });
    }
    
    // Add tooltips for accessibility
    addTooltips();
});

function addTooltips() {
    const tooltipElements = [
        { selector: '[onclick="markAllPresent()"]', text: 'Keyboard shortcut: Ctrl+Shift+A' },
        { selector: '[onclick="markAllAbsent()"]', text: 'Keyboard shortcut: Ctrl+Shift+D' },
        { selector: '[onclick="resetForm()"]', text: 'Keyboard shortcut: Ctrl+Shift+R' }
    ];
    
    tooltipElements.forEach(({ selector, text }) => {
        const element = document.querySelector(selector);
        if (element) {
            element.title = text;
        }
    });
}