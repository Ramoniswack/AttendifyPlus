document.addEventListener('DOMContentLoaded', function() {
    // Initialize page
    initializeAttendance();
    updateAttendanceStats();
    
    // Add event listeners to all attendance radio buttons
    const attendanceInputs = document.querySelectorAll('input[name^="attendance["]');
    attendanceInputs.forEach(input => {
        input.addEventListener('change', function() {
            updateAttendanceStats();
            updateButtonStates(this);
            enableAutoSave(); // Trigger auto-save on change
        });
    });

    // Set initial button states for existing selections
    attendanceInputs.forEach(input => {
        if (input.checked) {
            updateButtonStates(input);
        }
    });

    // Initialize responsive features
    handleMobileOptimizations();
    
    // Set up window resize handler for responsive features
    window.addEventListener('resize', handleMobileOptimizations);
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
    
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize keyboard shortcuts help
    initializeKeyboardShortcuts();
}

// Form change handlers
function handleFormChange() {
    const form = document.getElementById('selectionForm');
    const button = form.querySelector('button[type="submit"]');
    
    if (button) {
        button.innerHTML = '<i data-lucide="loader-2" class="spinner"></i> Loading...';
        button.disabled = true;
    }
    
    console.log('Form changed, submitting...');
    form.submit();
}

function handleDateChange() {
    console.log('Date changed, submitting form...');
    handleFormChange();
}

function handleSemesterChange() {
    console.log('Semester changed, submitting form...');
    handleFormChange();
}

function handleSubjectChange() {
    console.log('Subject changed, submitting form...');
    handleFormChange();
}

// View mode switching
function switchToUpdateMode() {
    const completedView = document.getElementById('completedView');
    const markingMode = document.getElementById('markingMode');
    
    if (completedView) {
        completedView.style.display = 'none';
        completedView.style.opacity = '0';
    }
    if (markingMode) {
        markingMode.style.display = 'block';
        setTimeout(() => {
            markingMode.style.opacity = '1';
        }, 50);
    }
    
    updateAttendanceStats();
    showToast('Switched to update mode', 'info');
}

function cancelUpdate() {
    const completedView = document.getElementById('completedView');
    const markingMode = document.getElementById('markingMode');
    
    if (markingMode) {
        markingMode.style.display = 'none';
        markingMode.style.opacity = '0';
    }
    if (completedView) {
        completedView.style.display = 'block';
        setTimeout(() => {
            completedView.style.opacity = '1';
        }, 50);
    }
    
    showToast('Update cancelled', 'info');
}

// Bulk attendance actions
function markAllPresent() {
    const presentInputs = document.querySelectorAll('input[value="present"]');
    let count = 0;
    
    presentInputs.forEach(input => {
        if (!input.disabled) {
            input.checked = true;
            updateButtonStates(input);
            
            // Add subtle animation
            const row = input.closest('.student-row');
            if (row) {
                row.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    row.style.transform = '';
                }, 150);
            }
            count++;
        }
    });
    
    updateAttendanceStats();
    showToast(`${count} students marked as present`, 'success');
    
    // Re-initialize Lucide icons
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
}

function markAllAbsent() {
    const absentInputs = document.querySelectorAll('input[value="absent"]');
    let count = 0;
    
    absentInputs.forEach(input => {
        if (!input.disabled) {
            input.checked = true;
            updateButtonStates(input);
            
            // Add subtle animation
            const row = input.closest('.student-row');
            if (row) {
                row.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    row.style.transform = '';
                }, 150);
            }
            count++;
        }
    });
    
    updateAttendanceStats();
    showToast(`${count} students marked as absent`, 'warning');
    
    // Re-initialize Lucide icons
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
}

function markAllLate() {
    const lateInputs = document.querySelectorAll('input[value="late"]');
    let count = 0;
    
    lateInputs.forEach(input => {
        if (!input.disabled) {
            input.checked = true;
            updateButtonStates(input);
            
            // Add subtle animation
            const row = input.closest('.student-row');
            if (row) {
                row.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    row.style.transform = '';
                }, 150);
            }
            count++;
        }
    });
    
    updateAttendanceStats();
    showToast(`${count} students marked as late`, 'warning');
    
    // Re-initialize Lucide icons
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
}

// Reset form
function resetForm() {
    const attendanceInputs = document.querySelectorAll('input[name^="attendance["]');
    let count = 0;
    
    attendanceInputs.forEach(input => {
        if (!input.disabled && input.checked) {
            input.checked = false;
            updateButtonStates(input);
            count++;
        }
    });
    
    updateAttendanceStats();
    showToast(`Reset ${count} attendance records`, 'info');
}

// Update button visual states
function updateButtonStates(radio) {
    const row = radio.closest('.student-row');
    if (!row) return;

    // Reset all labels in this row
    const labels = row.querySelectorAll('label');
    labels.forEach(label => {
        const forAttr = label.getAttribute('for');
        if (forAttr && forAttr.includes('present')) {
            label.classList.remove('btn-success');
            label.classList.add('btn-outline-success');
        } else if (forAttr && forAttr.includes('absent')) {
            label.classList.remove('btn-danger');
            label.classList.add('btn-outline-danger');
        } else if (forAttr && forAttr.includes('late')) {
            label.classList.remove('btn-warning');
            label.classList.add('btn-outline-warning');
        }
    });

    // Set active state for the selected radio button
    if (radio.checked) {
        const selectedLabel = document.querySelector(`label[for="${radio.id}"]`);
        if (selectedLabel) {
            if (radio.value === 'present') {
                selectedLabel.classList.remove('btn-outline-success');
                selectedLabel.classList.add('btn-success');
            } else if (radio.value === 'absent') {
                selectedLabel.classList.remove('btn-outline-danger');
                selectedLabel.classList.add('btn-danger');
            } else if (radio.value === 'late') {
                selectedLabel.classList.remove('btn-outline-warning');
                selectedLabel.classList.add('btn-warning');
            }
        }
    }
}

// Update attendance statistics
function updateAttendanceStats() {
    const presentCount = document.querySelectorAll('input[value="present"]:checked').length;
    const absentCount = document.querySelectorAll('input[value="absent"]:checked').length;
    const lateCount = document.querySelectorAll('input[value="late"]:checked').length;
    
    // Update all counter elements
    const presentCountElements = document.querySelectorAll('#presentCount');
    const absentCountElements = document.querySelectorAll('#absentCount');
    const lateCountElements = document.querySelectorAll('#lateCount');
    
    presentCountElements.forEach(el => {
        if (el) {
            el.textContent = presentCount;
            animateStatUpdate(el, 'success');
        }
    });
    
    absentCountElements.forEach(el => {
        if (el) {
            el.textContent = absentCount;
            animateStatUpdate(el, 'danger');
        }
    });
    
    lateCountElements.forEach(el => {
        if (el) {
            el.textContent = lateCount;
            animateStatUpdate(el, 'warning');
        }
    });
    
    // Update submit button state
    updateSubmitButtonState(presentCount, absentCount, lateCount);
    
    // Update progress indicator if exists
    updateProgressIndicator(presentCount, absentCount, lateCount);
}

// Update submit button state
function updateSubmitButtonState(presentCount, absentCount, lateCount) {
    const submitBtn = document.querySelector('button[type="submit"]');
    const totalStudents = document.querySelectorAll('input[name^="attendance["]').length / 3; // 3 options per student
    const markedStudents = presentCount + absentCount + lateCount;
    
    if (submitBtn && totalStudents > 0) {
        const progress = Math.round((markedStudents / totalStudents) * 100);
        
        if (markedStudents === totalStudents) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('btn-outline-primary');
            submitBtn.classList.add('btn-primary');
        } else {
            submitBtn.disabled = true;
            submitBtn.classList.remove('btn-primary');
            submitBtn.classList.add('btn-outline-primary');
        }
        
        // Update button text with progress
        const isUpdate = submitBtn.textContent.includes('Update');
        const baseText = isUpdate ? 'Update Attendance' : 'Save Attendance';
        
        if (markedStudents < totalStudents) {
            submitBtn.innerHTML = `<i data-lucide="save"></i> ${baseText} (${markedStudents}/${totalStudents})`;
        } else {
            submitBtn.innerHTML = `<i data-lucide="save"></i> ${baseText}`;
        }
        
        // Re-initialize Lucide icons
        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }
    }
}

// Update progress indicator
function updateProgressIndicator(presentCount, absentCount, lateCount) {
    const totalStudents = document.querySelectorAll('input[name^="attendance["]').length / 3;
    const markedStudents = presentCount + absentCount + lateCount;
    const progress = totalStudents > 0 ? Math.round((markedStudents / totalStudents) * 100) : 0;
    
    const progressBars = document.querySelectorAll('.attendance-progress');
    progressBars.forEach(bar => {
        bar.style.width = progress + '%';
        bar.setAttribute('aria-valuenow', progress);
    });
    
    const progressTexts = document.querySelectorAll('.progress-text');
    progressTexts.forEach(text => {
        text.textContent = `${progress}% Complete (${markedStudents}/${totalStudents})`;
    });
}

// Animate stat updates
function animateStatUpdate(element, type) {
    if (!element) return;
    
    element.style.transform = 'scale(1.2)';
    element.style.transition = 'transform 0.3s ease';
    
    // Add color pulse effect
    const originalColor = element.style.color;
    const colors = {
        success: '#28a745',
        danger: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    
    if (colors[type]) {
        element.style.color = colors[type];
    }
    
    setTimeout(() => {
        element.style.transform = 'scale(1)';
        element.style.color = originalColor;
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
        
        // Add loading state to submit button
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
            submitBtn.disabled = true;
            
            // Restore button if submission fails
            setTimeout(() => {
                if (submitBtn.disabled) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            }, 10000);
        }
        
        showToast('Submitting attendance...', 'info');
        return true;
    });
}

// QR Code functionality
let qrTimer = null;
let qrCountdown = 60;

function generateQR() {
    const button = document.querySelector('button[onclick="generateQR()"]');
    const originalText = button.innerHTML;
    
    // Add loading state
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';
    button.disabled = true;
    
    const formData = new FormData();
    formData.append('generate_qr', '1');
    formData.append('semester', document.querySelector('select[name="semester"]').value);
    formData.append('subject', document.querySelector('select[name="subject"]').value);
    formData.append('date', document.querySelector('input[name="date"]').value);
    
    fetch('attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayQR(data.qr_token);
            startQRTimer();
            showToast('QR code generated successfully', 'success');
        } else {
            showToast('Failed to generate QR: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to generate QR code', 'error');
    })
    .finally(() => {
        // Reset button state
        button.innerHTML = originalText;
        button.disabled = false;
        
        // Re-initialize Lucide icons
        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }
    });
}

function displayQR(token) {
    const canvas = document.getElementById('qrCanvas');
    const placeholder = document.getElementById('qrPlaceholder');
    
    if (!canvas || !placeholder) return;
    
    const qrData = `${window.location.origin}/attendifyplus/views/scan.php?token=${token}`;
    
    // Responsive QR size
    const containerWidth = canvas.parentElement.offsetWidth;
    const qrSize = Math.min(containerWidth - 40, 250);
    
    // Store token for theme switching
    canvas.dataset.token = token;
    
    QRCode.toCanvas(canvas, qrData, {
        width: qrSize,
        margin: 2,
        color: {
            dark: document.body.classList.contains('dark-mode') ? '#00ffc8' : '#1A73E8',
            light: '#FFFFFF'
        }
    }, function(error) {
        if (error) {
            console.error('QR Error:', error);
            showToast('Error displaying QR code', 'error');
            return;
        }
        
        placeholder.style.display = 'none';
        canvas.style.display = 'block';
    });
}

function startQRTimer() {
    const timerElement = document.getElementById('qrTimer');
    const buttonText = document.getElementById('qrButtonText');
    const countdownElement = document.getElementById('countdown');
    
    if (!timerElement || !buttonText || !countdownElement) return;
    
    timerElement.style.display = 'block';
    buttonText.textContent = 'Regenerate QR';
    
    qrCountdown = 60;
    countdownElement.textContent = qrCountdown;
    
    // Clear any existing timer
    if (qrTimer) {
        clearInterval(qrTimer);
    }
    
    // Start new timer
    qrTimer = setInterval(() => {
        qrCountdown--;
        countdownElement.textContent = qrCountdown;
        
        // Change color as it approaches expiry
        if (qrCountdown <= 10) {
            countdownElement.style.color = '#dc3545';
            countdownElement.style.fontWeight = 'bold';
        } else if (qrCountdown <= 30) {
            countdownElement.style.color = '#ffc107';
        }
        
        if (qrCountdown <= 0) {
            clearInterval(qrTimer);
            resetQR();
            showToast('QR code expired', 'warning');
        }
    }, 1000);
}

function resetQR() {
    const canvas = document.getElementById('qrCanvas');
    const placeholder = document.getElementById('qrPlaceholder');
    const timerElement = document.getElementById('qrTimer');
    const buttonText = document.getElementById('qrButtonText');
    const countdownElement = document.getElementById('countdown');
    
    if (canvas) {
        canvas.style.display = 'none';
        canvas.removeAttribute('data-token');
    }
    if (placeholder) placeholder.style.display = 'block';
    if (timerElement) timerElement.style.display = 'none';
    if (buttonText) buttonText.textContent = 'Generate QR';
    
    if (countdownElement) {
        countdownElement.style.color = '';
        countdownElement.style.fontWeight = '';
    }
    
    if (qrTimer) {
        clearInterval(qrTimer);
        qrTimer = null;
    }
}

// Toast notification system
function showToast(message, type = 'info', duration = 5000) {
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
            <button class="toast-close" onclick="this.parentElement.parentElement.remove()">
                <i data-lucide="x"></i>
            </button>
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
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 500;
        min-width: 280px;
        max-width: 400px;
        animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        transition: all 0.3s ease;
        border-left: 4px solid rgba(255,255,255,0.3);
    `;
    
    // Mobile responsiveness for toast
    if (window.innerWidth <= 768) {
        toast.style.cssText += `
            right: 10px;
            left: 10px;
            min-width: auto;
            max-width: none;
        `;
    }
    
    // Add CSS for toast close button
    const closeButton = toast.querySelector('.toast-close');
    if (closeButton) {
        closeButton.style.cssText = `
            background: none;
            border: none;
            color: white;
            opacity: 0.7;
            cursor: pointer;
            padding: 0;
            margin-left: auto;
            transition: opacity 0.2s;
        `;
    }
    
    // Add animation keyframes if not already added
    if (!document.querySelector('#toast-animations')) {
        const styleSheet = document.createElement('style');
        styleSheet.id = 'toast-animations';
        styleSheet.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            .toast-close:hover {
                opacity: 1 !important;
            }
            @media (max-width: 768px) {
                .toast-notification {
                    right: 10px !important;
                    left: 10px !important;
                    min-width: auto !important;
                    max-width: none !important;
                }
            }
        `;
        document.head.appendChild(styleSheet);
    }
    
    document.body.appendChild(toast);
    
    // Initialize Lucide icons for the toast
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
    
    // Auto-remove after specified duration
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.animation = 'slideOutRight 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 400);
        }
    }, duration);
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
        warning: '#f39c12',
        info: '#17a2b8'
    };
    return colors[type] || '#17a2b8';
}

// Theme management
window.toggleTheme = function() {
    const isDark = document.body.classList.toggle("dark-mode");
    localStorage.setItem("theme", isDark ? "dark" : "light");
    updateThemeElements();
    
    // Update QR code colors if displayed
    const canvas = document.getElementById('qrCanvas');
    if (canvas && canvas.style.display !== 'none') {
        const currentToken = canvas.dataset.token;
        if (currentToken) {
            displayQR(currentToken);
        }
    }
    
    showToast(`Switched to ${isDark ? 'dark' : 'light'} mode`, 'info', 2000);
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
function initializeTooltips() {
    const tooltipElements = [
        { selector: '[onclick="markAllPresent()"]', text: 'Mark all students as present (Ctrl+Shift+P)' },
        { selector: '[onclick="markAllAbsent()"]', text: 'Mark all students as absent (Ctrl+Shift+A)' },
        { selector: '[onclick="resetForm()"]', text: 'Reset all attendance selections (Ctrl+Shift+R)' },
        { selector: '[onclick="generateQR()"]', text: 'Generate QR code for student self-marking (Ctrl+Shift+Q)' }
    ];
    
    tooltipElements.forEach(({ selector, text }) => {
        const element = document.querySelector(selector);
        if (element) {
            element.title = text;
            element.setAttribute('data-bs-toggle', 'tooltip');
            element.setAttribute('data-bs-placement', 'top');
        }
    });
    
    // Initialize Bootstrap tooltips if available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}

// Keyboard shortcuts
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Only trigger if not typing in input fields
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
            return;
        }
        
        // Ctrl/Cmd + Shift + P: Mark all present
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'p') {
            e.preventDefault();
            markAllPresent();
        }
        
        // Ctrl/Cmd + Shift + A: Mark all absent
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'a') {
            e.preventDefault();
            markAllAbsent();
        }
        
        // Ctrl/Cmd + Shift + L: Mark all late
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'l') {
            e.preventDefault();
            markAllLate();
        }
        
        // Ctrl/Cmd + Shift + R: Reset form
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'r') {
            e.preventDefault();
            resetForm();
        }
        
        // Ctrl/Cmd + Shift + Q: Generate QR
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'q') {
            e.preventDefault();
            const qrButton = document.querySelector('[onclick="generateQR()"]');
            if (qrButton && !qrButton.disabled) {
                generateQR();
            }
        }
        
        // Ctrl/Cmd + Shift + S: Submit form
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 's') {
            e.preventDefault();
            const submitButton = document.querySelector('button[type="submit"]');
            if (submitButton && !submitButton.disabled) {
                submitButton.click();
            }
        }
        
        // Escape: Cancel operations
        if (e.key === 'Escape') {
            const updateMode = document.getElementById('markingMode');
            const completedView = document.getElementById('completedView');
            if (updateMode && updateMode.style.display !== 'none' && completedView) {
                cancelUpdate();
            }
        }
    });
    
    // Show keyboard shortcuts help on Ctrl+?
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === '?') {
            e.preventDefault();
            showKeyboardShortcutsHelp();
        }
    });
}

function showKeyboardShortcutsHelp() {
    const shortcuts = [
        { key: 'Ctrl+Shift+P', action: 'Mark all students present' },
        { key: 'Ctrl+Shift+A', action: 'Mark all students absent' },
        { key: 'Ctrl+Shift+L', action: 'Mark all students late' },
        { key: 'Ctrl+Shift+R', action: 'Reset all selections' },
        { key: 'Ctrl+Shift+Q', action: 'Generate QR code' },
        { key: 'Ctrl+Shift+S', action: 'Submit attendance' },
        { key: 'Escape', action: 'Cancel update mode' },
        { key: 'Ctrl+?', action: 'Show this help' }
    ];
    
    let helpHtml = '<div style="text-align: left;"><h6>Keyboard Shortcuts:</h6><ul style="list-style: none; padding: 0;">';
    shortcuts.forEach(shortcut => {
        helpHtml += `<li style="margin: 0.5rem 0;"><kbd style="background: #f8f9fa; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">${shortcut.key}</kbd> - ${shortcut.action}</li>`;
    });
    helpHtml += '</ul></div>';
    
    showToast(helpHtml, 'info', 10000);
}

// Auto-save functionality
let autoSaveTimer = null;
let autoSaveEnabled = false;

function enableAutoSave() {
    if (!autoSaveEnabled) return;
    
    // Clear existing timer
    if (autoSaveTimer) {
        clearTimeout(autoSaveTimer);
    }
    
    // Set timer for 30 seconds after last change
    autoSaveTimer = setTimeout(() => {
        const form = document.getElementById('attendanceForm');
        const checkedInputs = document.querySelectorAll('input[name^="attendance["]:checked');
        const totalInputs = document.querySelectorAll('input[name^="attendance["]').length / 3;
        
        if (form && checkedInputs.length === totalInputs) {
            showToast('Auto-saving attendance...', 'info', 3000);
            
            // Create a hidden submission to save progress
            const formData = new FormData(form);
            formData.append('auto_save', '1');
            
            fetch('attendance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Auto-save completed', 'success', 2000);
                } else {
                    console.warn('Auto-save failed:', data.error);
                }
            })
            .catch(error => {
                console.warn('Auto-save error:', error);
            });
        }
    }, 30000);
}

function toggleAutoSave() {
    autoSaveEnabled = !autoSaveEnabled;
    const message = autoSaveEnabled ? 'Auto-save enabled' : 'Auto-save disabled';
    showToast(message, 'info', 2000);
    localStorage.setItem('autoSaveEnabled', autoSaveEnabled);
}

// Mobile optimizations
function handleMobileOptimizations() {
    const isMobile = window.innerWidth <= 768;
    
    // Optimize student rows for mobile
    const studentRows = document.querySelectorAll('.student-row');
    studentRows.forEach(row => {
        if (isMobile) {
            row.classList.add('mobile-optimized');
        } else {
            row.classList.remove('mobile-optimized');
        }
    });
    
    // Adjust QR code size for mobile
    const qrCanvas = document.getElementById('qrCanvas');
    if (qrCanvas && qrCanvas.style.display !== 'none') {
        const token = qrCanvas.dataset.token;
        if (token) {
            displayQR(token);
        }
    }
    
    // Handle virtual keyboard on mobile
    if (isMobile) {
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                setTimeout(() => {
                    this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            });
        });
    }
}

// Student row click handlers for mobile
function initializeStudentRowHandlers() {
    const studentRows = document.querySelectorAll('.student-row');
    studentRows.forEach(row => {
        const studentName = row.querySelector('.student-name');
        if (studentName) {
            studentName.addEventListener('click', function() {
                const controls = row.querySelector('.attendance-controls');
                const currentChecked = row.querySelector('input:checked');
                
                // Cycle through: none -> present -> absent -> late -> none
                if (!currentChecked) {
                    row.querySelector('input[value="present"]').checked = true;
                } else if (currentChecked.value === 'present') {
                    currentChecked.checked = false;
                    row.querySelector('input[value="absent"]').checked = true;
                } else if (currentChecked.value === 'absent') {
                    currentChecked.checked = false;
                    row.querySelector('input[value="late"]').checked = true;
                } else {
                    currentChecked.checked = false;
                }
                
                updateButtonStates(row.querySelector('input:checked') || currentChecked);
                updateAttendanceStats();
            });
        }
    });
}

// Search functionality
function initializeSearchFunctionality() {
    const searchInput = document.getElementById('studentSearch');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const studentRows = document.querySelectorAll('.student-row');
        
        studentRows.forEach(row => {
            const studentName = row.querySelector('.student-name').textContent.toLowerCase();
            const studentProgram = row.querySelector('.student-program');
            const programText = studentProgram ? studentProgram.textContent.toLowerCase() : '';
            
            if (studentName.includes(searchTerm) || programText.includes(searchTerm)) {
                row.style.display = 'flex';
            } else {
                row.style.display = 'none';
            }
        });
    });
}

// Export functionality
function exportAttendance(format = 'csv') {
    const studentRows = document.querySelectorAll('.student-row');
    const data = [];
    
    studentRows.forEach(row => {
        const studentName = row.querySelector('.student-name').textContent;
        const studentProgram = row.querySelector('.student-program')?.textContent || '';
        const checkedInput = row.querySelector('input:checked');
        const status = checkedInput ? checkedInput.value : 'not_marked';
        
        data.push({
            name: studentName,
            program: studentProgram,
            status: status
        });
    });
    
    if (format === 'csv') {
        exportToCSV(data);
    } else if (format === 'json') {
        exportToJSON(data);
    }
}

function exportToCSV(data) {
    const headers = ['Name', 'Program', 'Status'];
    const csvContent = [
        headers.join(','),
        ...data.map(row => [
            `"${row.name}"`,
            `"${row.program}"`,
            row.status
        ].join(','))
    ].join('\n');
    
    downloadFile(csvContent, 'attendance.csv', 'text/csv');
}

function exportToJSON(data) {
    const jsonContent = JSON.stringify(data, null, 2);
    downloadFile(jsonContent, 'attendance.json', 'application/json');
}

function downloadFile(content, filename, mimeType) {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
}

// Initialize all functionality when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Load auto-save preference
    autoSaveEnabled = localStorage.getItem('autoSaveEnabled') === 'true';
    
    // Initialize additional features
    initializeStudentRowHandlers();
    initializeSearchFunctionality();
    
    // Set up periodic stats update
    setInterval(updateAttendanceStats, 5000);
    
    console.log('Attendance system fully initialized');
});

// Error handling
window.addEventListener('error', function(e) {
    console.error('JavaScript error:', e.error);
    showToast('An error occurred. Please refresh the page.', 'error');
});

// Unload handler
window.addEventListener('beforeunload', function(e) {
    const form = document.getElementById('attendanceForm');
    if (form) {
        const checkedInputs = document.querySelectorAll('input[name^="attendance["]:checked');
        const totalInputs = document.querySelectorAll('input[name^="attendance["]').length / 3;
        
        if (checkedInputs.length > 0 && checkedInputs.length < totalInputs) {
            e.preventDefault();
            e.returnValue = 'You have unsaved attendance data. Are you sure you want to leave?';
            return e.returnValue;
        }
    }
});