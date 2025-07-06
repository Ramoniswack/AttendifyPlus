// Enhanced Projector QR Code System - Composer-based Local Generation
// Optimized for classroom projection: 400px size, 30px margin, high error correction

// ===== QR CODE FUNCTIONALITY ===== //
let qrTimer = null;
let pendingQRChecker = null;
let currentQRToken = null;
let attendanceFormLoaded = false;

function generateQR() {
    const button = document.querySelector('button[onclick="generateQR()"]');
    if (!button) {
        console.error('Generate QR button not found');
        return;
    }
    
    const originalText = button.innerHTML;
    
    // Check if required fields are selected
    const semesterSelect = document.querySelector('select[name="semester"]');
    const subjectSelect = document.querySelector('select[name="subject"]');
    const dateInput = document.querySelector('input[name="date"]');
    
    if (!semesterSelect?.value || !subjectSelect?.value || !dateInput?.value) {
        showToast('Please select semester, subject, and date first', 'warning');
        return;
    }
    
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';
    button.disabled = true;
    
    const formData = new FormData();
    formData.append('generate_qr', '1');
    formData.append('semester', semesterSelect.value);
    formData.append('subject', subjectSelect.value);
    formData.append('date', dateInput.value);
    
    console.log('Sending enhanced projector QR generation request...');
    
    fetch('attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Enhanced QR generation response:', data);
        if (data.success) {
            showToast('QR code generated successfully! Refreshing page...', 'success', 2000);
            
            // Refresh the page to show the active QR session
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast('Failed to generate QR: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error generating enhanced QR:', error);
        showToast('Failed to generate QR code: ' + error.message, 'error');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
        
        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }
    });
}

function displayProjectorQR(token) {
    console.log('Displaying Projector QR (400px, 30px margin) for token:', token);
    
    const canvas = document.getElementById('qrCanvas');
    const placeholder = document.getElementById('qrPlaceholder');
    
    if (!canvas) {
        console.error('QR Canvas not found');
        return;
    }
    
    // Use the enhanced projector QR from our Composer-based system
    const img = new Image();
    img.onload = function() {
        console.log('Enhanced projector QR image loaded successfully (400px, 30px margin)');
        const ctx = canvas.getContext('2d');
        
        // Set canvas to projector size (400px)
        canvas.width = 400;
        canvas.height = 400;
        ctx.drawImage(this, 0, 0, 400, 400);
        
        canvas.style.display = 'block';
        if (placeholder) {
            placeholder.style.display = 'none';
        }
        
        showToast('Projector QR code ready! (400px, 30px margin, high error correction)', 'success', 4000);
    };
    
    img.onerror = function() {
        console.warn('Enhanced projector QR failed, showing fallback...');
        showProjectorQRFallback(token, placeholder);
    };
    
    // Generate enhanced projector QR with 400px size and 30px margin
    img.src = `../../api/generate_qr_enhanced.php?token=${encodeURIComponent(token)}&mode=projector&t=${Date.now()}`;
}

function showProjectorQRFallback(token, placeholder) {
    console.log('Showing enhanced QR fallback for projector mode');
    
    if (placeholder) {
        const baseUrl = window.location.origin + window.location.pathname.replace('attendance.php', '');
        const qrData = `${baseUrl}scan_qr.php?token=${token}`;
        
        placeholder.innerHTML = `
            <div class="p-3 border rounded bg-warning text-dark text-center">
                <h6 class="mb-2">
                    <i data-lucide="alert-triangle" style="width: 20px; height: 20px;"></i>
                    Enhanced QR Generation Failed
                </h6>
                <p class="small mb-2">Use direct link instead:</p>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" class="form-control" value="${qrData}" readonly onclick="this.select()">
                    <button class="btn btn-primary btn-sm" onclick="copyToClipboard('${qrData}')">
                        <i data-lucide="copy" style="width: 14px; height: 14px;"></i> Copy
                    </button>
                </div>
                <small class="text-muted">Students can use this link directly in their browser</small>
            </div>
        `;
        placeholder.style.display = 'block';
        
        // Re-initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
    
    showToast('Enhanced QR generation failed, but direct link is available!', 'warning', 4000);
}

// Copy QR link to clipboard
function copyQRLink(token) {
    const baseUrl = window.location.origin + window.location.pathname.replace('attendance.php', '');
    const qrUrl = `${baseUrl}scan_qr.php?token=${token}`;
    copyToClipboard(qrUrl);
}

// QR Timer Functions - 60 Second Countdown
function startQRTimer() {
    console.log('Starting QR timer with 60-second countdown');
    
    // Clear any existing timer
    if (qrTimer) {
        clearInterval(qrTimer);
        qrTimer = null;
    }
    
    // Set initial countdown to 60 seconds
    let timeLeft = 60;
    
    const countdownElement = document.getElementById('countdown');
    const qrCountdownElement = document.getElementById('qrCountdown');
    
    // Update countdown display
    function updateCountdown() {
        if (countdownElement) {
            countdownElement.textContent = timeLeft;
        }
        if (qrCountdownElement) {
            if (timeLeft > 10) {
                qrCountdownElement.textContent = `(${timeLeft}s remaining)`;
                qrCountdownElement.className = 'text-success fw-bold ms-1';
            } else if (timeLeft > 0) {
                qrCountdownElement.textContent = `(${timeLeft}s remaining)`;
                qrCountdownElement.className = 'text-warning fw-bold ms-1';
            } else {
                qrCountdownElement.textContent = '(Expired)';
                qrCountdownElement.className = 'text-danger fw-bold ms-1';
            }
        }
    }
    
    // Initial update
    updateCountdown();
    
    // Start countdown timer
    qrTimer = setInterval(() => {
        timeLeft--;
        updateCountdown();
        
        if (timeLeft <= 0) {
            clearInterval(qrTimer);
            qrTimer = null;
            handleQRExpiry();
        }
    }, 1000);
}

function handleQRExpiry() {
    console.log('QR code has expired after 60 seconds');
    
    // Stop pending QR check
    stopPendingQRCheck();
    
    // Show expiry message
    showToast('QR code has expired. Generate a new QR code for attendance.', 'warning', 8000);
    
    // Update QR display with expiry message
    const canvas = document.getElementById('qrCanvas');
    const placeholder = document.getElementById('qrPlaceholder');
    
    if (canvas) {
        canvas.style.display = 'none';
    }
    if (placeholder) {
        placeholder.innerHTML = `
            <div class="p-4 border border-danger border-dashed rounded mb-3 text-center">
                <i data-lucide="clock-off" style="width: 48px; height: 48px;" class="mb-2 text-danger"></i>
                <div class="text-danger fw-bold">QR Code Expired</div>
                <small class="text-muted">Generate a new QR code to continue attendance</small>
            </div>
        `;
        placeholder.style.display = 'block';
        
        // Initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
    
    // Reset current token
    currentQRToken = null;
}

function deactivateQR() {
    if (qrTimer) {
        clearInterval(qrTimer);
        qrTimer = null;
    }
    
    stopPendingQRCheck(); // Stop checking for pending QR scans
    
    // Call API to deactivate QR session
    fetch('../../api/deactivate_qr.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: ''
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('QR session deactivated successfully', 'success');
        } else {
            showToast('Failed to deactivate QR session: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error deactivating QR:', error);
        showToast('QR session deactivated locally', 'info');
    });
    
    // Hide QR elements
    const canvas = document.getElementById('qrCanvas');
    const placeholder = document.getElementById('qrPlaceholder');
    
    if (canvas) canvas.style.display = 'none';
    if (placeholder) {
        placeholder.innerHTML = `
            <div class="p-4 border border-dashed rounded mb-3 text-muted text-center">
                <i data-lucide="qr-code" style="width: 48px; height: 48px;" class="mb-2"></i>
                <div>Projector QR Code will appear here</div>
                <small class="text-muted">400px size, 30px margin - optimized for classroom projection</small>
            </div>
        `;
        placeholder.style.display = 'block';
        
        // Initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
    
    // Reset countdown display
    const countdownElement = document.getElementById('countdown');
    const qrCountdownElement = document.getElementById('qrCountdown');
    
    if (countdownElement) {
        countdownElement.textContent = '--';
    }
    if (qrCountdownElement) {
        qrCountdownElement.textContent = '';
        qrCountdownElement.className = '';
    }
    
    // Reset current token
    currentQRToken = null;
    
    showToast('QR code deactivated', 'info');
    
    // Don't auto-reload - let the user control navigation
    // setTimeout(() => {
    //     window.location.reload();
    // }, 1000);
}

// Check for pending QR scans periodically
function startPendingQRCheck(qrToken) {
    currentQRToken = qrToken;
    
    if (pendingQRChecker) {
        clearInterval(pendingQRChecker);
    }
    
    console.log('Starting pending QR scan checker for token:', qrToken);
    
    // Update polling status
    const statusDiv = document.getElementById('pollingStatus');
    if (statusDiv) {
        statusDiv.textContent = 'Polling: Active (every 3s)';
    }
    
    // Add visual indicator that polling is active
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn && !submitBtn.parentElement.querySelector('.qr-polling-indicator')) {
        const indicator = document.createElement('div');
        indicator.className = 'qr-polling-indicator alert alert-info py-1 px-2 mt-2 mb-0';
        indicator.innerHTML = `
            <small>
                <i data-lucide="wifi" style="width: 12px; height: 12px;" class="me-1 qr-pulse"></i>
                <span class="indicator-text">Checking for QR scans every 3 seconds...</span>
                <span class="badge bg-primary ms-2" id="qrScanCount">0 scans</span>
            </small>
            <style>
                .qr-pulse { 
                    animation: pulse 2s infinite; 
                }
                @keyframes pulse {
                    0% { opacity: 1; }
                    50% { opacity: 0.5; }
                    100% { opacity: 1; }
                }
            </style>
        `;
        submitBtn.parentElement.appendChild(indicator);
        
        // Re-initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
    
    // Check immediately
    checkPendingQRScans(qrToken);
    
    // Then check every 10 seconds (reduced to minimize page disruption)
    pendingQRChecker = setInterval(() => {
        checkPendingQRScans(qrToken);
    }, 10000);
}

// Start QR scan polling for attendance form (even without active QR session)
function startQRScanPolling() {
    const subjectSelect = document.querySelector('select[name="subject"]');
    const dateInput = document.querySelector('input[name="date"]');
    const attendanceForm = document.querySelector('#attendanceForm');
    
    console.log('startQRScanPolling called:', {
        hasSubject: !!subjectSelect?.value,
        hasDate: !!dateInput?.value,
        hasForm: !!attendanceForm
    });
    
    // Only start polling if we have an attendance form with subject and date
    if (attendanceForm && subjectSelect?.value && dateInput?.value) {
        console.log('Starting QR scan polling for loaded attendance form...');
        startPendingQRCheck(currentQRToken || 'session_polling');
        attendanceFormLoaded = true;
    } else {
        console.log('Conditions not met for QR polling:', {
            form: !!attendanceForm,
            subject: subjectSelect?.value || 'none',
            date: dateInput?.value || 'none'
        });
    }
}

function checkPendingQRScans(qrToken) {
    const subjectSelect = document.querySelector('select[name="subject"]');
    const dateInput = document.querySelector('input[name="date"]');
    
    if (!subjectSelect?.value || !dateInput?.value) {
        console.log('QR Scan polling: Missing subject or date');
        return;
    }
    
    console.log(`QR Scan polling: Checking for scans (subject: ${subjectSelect.value}, date: ${dateInput.value})`);
    
    // Update polling status indicator
    const statusDiv = document.getElementById('pollingStatus');
    if (statusDiv) {
        statusDiv.textContent = 'Polling: Active';
        statusDiv.className = 'text-success';
    }
    
    // Use the new pending QR attendance API
    const requestData = {};
    
    // Use session ID if available (from QR generation), otherwise use subject and date
    if (window.currentSessionID) {
        requestData.session_id = window.currentSessionID;
        console.log('QR Scan polling: Using session ID:', window.currentSessionID);
    } else {
        requestData.subject_id = subjectSelect.value;
        requestData.date = dateInput.value;
        console.log('QR Scan polling: Using subject/date fallback');
    }
    
    fetch('../../api/get_pending_qr_attendance.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        },
        body: new URLSearchParams(requestData)
    })
    .then(response => {
        console.log('QR Scan polling: Response received', {
            status: response.status,
            statusText: response.statusText,
            ok: response.ok
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.text(); // Get as text first for debugging
    })
    .then(responseText => {
        console.log('QR Scan polling: Raw response:', responseText.substring(0, 200) + '...');
        
        try {
            const data = JSON.parse(responseText);
            console.log('QR Scan polling: Parsed data:', data);
            
            // Update status to show successful polling
            if (statusDiv) {
                statusDiv.textContent = `Polling: Active (Last: ${new Date().toLocaleTimeString()})`;
                statusDiv.className = 'text-success';
            }
            
            if (data.success && data.pending_count > 0) {
                console.log(`Found ${data.pending_count} QR scans, updating UI`);
                
                // Update polling indicator
                const scanCountBadge = document.getElementById('qrScanCount');
                if (scanCountBadge) {
                    scanCountBadge.textContent = `${data.pending_count} scan${data.pending_count !== 1 ? 's' : ''}`;
                    scanCountBadge.className = 'badge bg-success ms-2';
                }
                
                updateUIWithPendingScans(data.pending_attendance);
                
                // Show notification for new scans (with rate limiting)
                const lastNotified = localStorage.getItem('lastQRNotified') || '0';
                const lastNotifiedTime = localStorage.getItem('lastQRNotifiedTime') || '0';
                const currentCount = data.pending_count.toString();
                const currentTime = Date.now();
                
                // Only notify if count changed or more than 10 seconds since last notification
                if (lastNotified !== currentCount || (currentTime - parseInt(lastNotifiedTime)) > 10000) {
                    // Create more prominent notification
                    const message = data.pending_count === 1 
                        ? `🎯 1 student scanned QR code! Check attendance list.`
                        : `🎯 ${data.pending_count} students scanned QR codes! Check attendance list.`;
                    
                    showToast(message, 'success', 5000);
                    
                    // Also play a subtle sound notification if available
                    try {
                        if ('vibrate' in navigator) {
                            navigator.vibrate(100);
                        }
                    } catch (e) {
                        // Ignore vibration errors
                    }
                    
                    localStorage.setItem('lastQRNotified', currentCount);
                    localStorage.setItem('lastQRNotifiedTime', currentTime.toString());
                    
                    console.log(`🔔 Notification sent for ${data.pending_count} QR scan(s)`);
                }
            } else if (data.success) {
                console.log('QR Scan polling: No pending scans found');
                
                // Update polling indicator to show no scans
                const scanCountBadge = document.getElementById('qrScanCount');
                if (scanCountBadge) {
                    scanCountBadge.textContent = '0 scans';
                    scanCountBadge.className = 'badge bg-primary ms-2';
                }
            } else {
                console.warn('QR Scan polling: API returned error:', data.message);
                if (statusDiv) {
                    statusDiv.textContent = `Polling: Error - ${data.message}`;
                    statusDiv.className = 'text-warning';
                }
                
                // Check if the error indicates QR session expired
                if (data.message && (data.message.includes('expired') || data.message.includes('No active QR'))) {
                    console.log('QR session expired, stopping polling and refreshing page');
                    stopPendingQRCheck();
                    showToast('QR session expired - page will refresh', 'warning', 3000);
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                }
            }
        } catch (parseError) {
            console.error('QR Scan polling: JSON parse error:', parseError);
            console.error('QR Scan polling: Raw response causing error:', responseText.substring(0, 500));
            
            if (statusDiv) {
                statusDiv.textContent = 'Polling: Parse Error';
                statusDiv.className = 'text-danger';
            }
        }
    })
    .catch(error => {
        console.warn('Error checking QR scans:', error);
        
        // Update status to show error
        if (statusDiv) {
            statusDiv.textContent = `Polling: Network Error - ${error.message}`;
            statusDiv.className = 'text-danger';
        }
        
        // Only show toast for network errors occasionally to avoid spam
        const lastErrorTime = localStorage.getItem('lastPollingErrorTime') || '0';
        const currentTime = Date.now();
        if ((currentTime - parseInt(lastErrorTime)) > 30000) { // 30 seconds
            showToast('QR polling temporarily unavailable. Retrying...', 'warning', 2000);
            localStorage.setItem('lastPollingErrorTime', currentTime.toString());
        }
    });
}

function updateUIWithPendingScans(pendingScans) {
    console.log('updateUIWithPendingScans called with:', pendingScans);
    
    pendingScans.forEach((scan, index) => {
        console.log(`Processing scan ${index + 1}:`, scan);
        
        // Find the student's row and update it
        const studentRow = document.querySelector(`input[name="attendance[${scan.student_id}]"]`)?.closest('.student-item');
        
        console.log(`Student row found for ID ${scan.student_id}:`, !!studentRow);
        
        if (studentRow) {
            // Update the student info to show QR scanned status
            const studentInfo = studentRow.querySelector('.student-info');
            
            // Remove existing pending badge if any
            const existingBadge = studentInfo.querySelector('.badge.bg-warning');
            if (existingBadge) {
                existingBadge.remove();
            }
            
            // Remove any existing scan time info
            const existingTimeInfo = studentInfo.querySelector('small.text-muted');
            if (existingTimeInfo && existingTimeInfo.textContent.includes('Scanned:')) {
                existingTimeInfo.remove();
            }
            
            // Add pending QR badge if not already present
            if (!studentInfo.querySelector('.badge.bg-warning')) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-warning text-dark ms-2';
                badge.innerHTML = `
                    <i data-lucide="clock" style="width: 10px; height: 10px;"></i>
                    QR Scanned - Pending Save
                `;
                studentInfo.appendChild(badge);
                
                // Add scan time
                const timeInfo = document.createElement('small');
                timeInfo.className = 'text-muted d-block';
                timeInfo.textContent = `Scanned: ${new Date(scan.scanned_at).toLocaleTimeString()}`;
                studentInfo.appendChild(timeInfo);
                
                console.log(`Added QR badge and time for student ${scan.student_id}`);
            }
            
            // Auto-select "Present (QR)" for QR scanned students and disable manual buttons
            const allStudentRadios = studentRow.querySelectorAll(`input[name="attendance[${scan.student_id}]"]`);
            let presentRadio = null;
            let presentLabel = null;
            
            // Find the present radio button (multiple possible selectors)
            presentRadio = studentRow.querySelector(`input[id="p${scan.student_id}"]`) || 
                          studentRow.querySelector(`input[name="attendance[${scan.student_id}]"][value="present"]`);
            
            if (presentRadio) {
                presentLabel = studentRow.querySelector(`label[for="${presentRadio.id}"]`);
            }
            
            console.log(`Radio elements found for student ${scan.student_id}:`, {
                radio: !!presentRadio,
                label: !!presentLabel,
                radioCount: allStudentRadios.length,
                radioId: presentRadio?.id,
                radioValue: presentRadio?.value
            });
            
            if (presentRadio && allStudentRadios.length > 0) {
                // Uncheck all radio buttons first
                allStudentRadios.forEach(radio => {
                    radio.checked = false;
                    const label = studentRow.querySelector(`label[for="${radio.id}"]`);
                    if (label) {
                        // Reset all labels to default style
                        label.className = 'btn btn-outline-secondary';
                        // Reset text to original
                        if (radio.value === 'present') {
                            label.textContent = 'Present';
                        } else if (radio.value === 'absent') {
                            label.textContent = 'Absent';
                        } else if (radio.value === 'late') {
                            label.textContent = 'Late';
                        }
                    }
                });
                
                // Check the present radio and style as QR
                presentRadio.checked = true;
                
                // Update present label to show QR status
                if (presentLabel) {
                    presentLabel.className = 'btn btn-warning';
                    presentLabel.textContent = 'Present (QR)';
                    presentLabel.style.fontWeight = 'bold';
                }
                
                // Disable other radio buttons to prevent manual override until saved
                allStudentRadios.forEach(radio => {
                    if (radio !== presentRadio) {
                        radio.disabled = true;
                        const label = studentRow.querySelector(`label[for="${radio.id}"]`);
                        if (label) {
                            label.className = 'btn btn-outline-secondary disabled';
                            label.style.opacity = '0.6';
                            label.title = 'QR scanned - save attendance to enable manual changes';
                        }
                    }
                });
                
                // Mark the row as QR scanned
                studentRow.dataset.qrScanned = 'true';
                studentRow.dataset.attendanceMethod = 'qr';
                
                // Trigger change event to ensure proper handling
                presentRadio.dispatchEvent(new Event('change', { bubbles: true }));
                
                console.log(`✅ Successfully set QR attendance for student ${scan.student_id}`);
            } else {
                console.error(`❌ Could not find radio buttons for student ${scan.student_id}`);
            }
                
            // Update border color
            const oldClasses = studentRow.className;
            studentRow.className = studentRow.className.replace(/border-\w+/, 'border-warning');
            console.log(`Updated row border for student ${scan.student_id}: ${oldClasses} -> ${studentRow.className}`);
            
            // Add visual pulse effect for QR scanned students
            addQRScanPulseEffect(studentRow);
            
            console.log(`✅ Successfully updated UI for student ${scan.student_id}`);
            
            // Update counter
            updateAttendanceCount();
            
            // Re-initialize Lucide icons for this row
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        } else {
            console.warn(`Could not find student row for ID: ${scan.student_id}`);
        }
    });
    
    // Update attendance count after processing all scans
    try {
        if (typeof updateAttendanceCount === 'function') {
            updateAttendanceCount();
        } else {
            // Fallback implementation if the function is not found
            console.warn('updateAttendanceCount function not found, using fallback');
            
            // Count students by attendance status
            const presentCount = document.querySelectorAll('input[value="present"]:checked').length;
            const absentCount = document.querySelectorAll('input[value="absent"]:checked').length;
            const lateCount = document.querySelectorAll('input[value="late"]:checked').length;
            const totalStudents = document.querySelectorAll('.student-item').length;
            const unmarkedCount = totalStudents - (presentCount + absentCount + lateCount);
            
            // Update UI elements if they exist
            updateCountIfExists('present-count', presentCount);
            updateCountIfExists('absent-count', absentCount);
            updateCountIfExists('late-count', lateCount);
            updateCountIfExists('unmarked-count', unmarkedCount);
            updateCountIfExists('total-count', totalStudents);
        }
    } catch (e) {
        console.error('Error updating attendance count:', e);
    }
    
    console.log('Finished processing all QR scans');
}

/**
 * Helper function to update count elements if they exist
 */
function updateCountIfExists(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = value;
    }
}

/**
 * Handles the QR code scan from a student device
 * @param {string} data - The QR code data
 */
function handleQrScan(data) {
    console.log("QR Code scanned: ", data);
    
    // Send scan data to server
    $.ajax({
        url: '../../api/process_qr_attendance.php',
        type: 'POST',
        data: {
            qr_data: data,
            student_id: getCurrentStudentId()
        },
        success: function(response) {
            try {
                let result = JSON.parse(response);
                if (result.success) {
                    // Update UI to show pending status
                    $("#scan-status").html('<div class="alert alert-success">Attendance marked via QR scan!</div>');
                    
                    // Disable manual attendance options
                    disableManualAttendanceOptions();
                } else {
                    $("#scan-status").html('<div class="alert alert-danger">Error: ' + result.message + '</div>');
                }
            } catch (e) {
                $("#scan-status").html('<div class="alert alert-danger">Error processing response</div>');
                console.error("Error parsing response:", e, response);
            }
        },
        error: function() {
            $("#scan-status").html('<div class="alert alert-danger">Network error while processing QR scan</div>');
        }
    });
}

/**
 * Gets the current student ID from the page
 * @returns {number} - Student ID
 */
function getCurrentStudentId() {
    // This should be replaced with your actual method of getting the current student ID
    // You might have it in a hidden input, data attribute, or from the session
    const studentIdElement = document.getElementById('student-id');
    return studentIdElement ? studentIdElement.value : null;
}

/**
 * Disables manual attendance options when QR is used
 */
function disableManualAttendanceOptions() {
    // Get student ID
    const studentId = getCurrentStudentId();
    
    // Disable all radio buttons for this student
    $(`input[name="attendance[${studentId}]"]`).prop('disabled', true);
    
    // Add a visual indicator that QR was used
    $(`#student-row-${studentId}`).addClass('qr-scanned');
    $(`#student-row-${studentId} .status-cell`).append('<span class="badge bg-success ms-2">QR Verified</span>');
    
    // Mark as present automatically
    $(`input[name="attendance[${studentId}]"][value="present"]`).prop('checked', true);
}

function stopPendingQRCheck() {
    if (pendingQRChecker) {
        clearInterval(pendingQRChecker);
        pendingQRChecker = null;
        currentQRToken = null;
        localStorage.removeItem('lastNotified');
        console.log('Stopped pending QR scan checker');
        
        // Update polling status
        const statusDiv = document.getElementById('pollingStatus');
        if (statusDiv) {
            statusDiv.textContent = 'Polling: Stopped';
        }
        
        // Remove visual indicator
        const indicator = document.querySelector('.qr-polling-indicator');
        if (indicator) {
            indicator.remove();
        }
    }
}

// ===== ATTENDANCE FORM FUNCTIONS ===== //
function updateAttendanceCount() {
    const attendanceInputs = document.querySelectorAll('input[name^="attendance["]:checked');
    const totalStudents = document.querySelectorAll('input[name^="attendance["][value="present"]').length; // Count unique students by present inputs
    const markedCount = attendanceInputs.length; // Count checked inputs
    
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        submitBtn.innerHTML = `<i data-lucide="save"></i> Save Attendance (${markedCount}/${totalStudents})`;
        
        // Re-initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
    
    // Update counters
    const presentCount = document.querySelectorAll('input[value="present"]:checked').length;
    const absentCount = document.querySelectorAll('input[value="absent"]:checked').length;
    const lateCount = document.querySelectorAll('input[value="late"]:checked').length;
    
    const presentCountEl = document.getElementById('presentCount');
    const absentCountEl = document.getElementById('absentCount');
    const lateCountEl = document.getElementById('lateCount');
    
    if (presentCountEl) presentCountEl.textContent = presentCount;
    if (absentCountEl) absentCountEl.textContent = absentCount;
    if (lateCountEl) lateCountEl.textContent = lateCount;
}

function updateRadioButtonStyling(radioInput) {
    if (!radioInput.checked) return;
    
    // Get the student row
    const studentRow = radioInput.closest('.student-item');
    if (!studentRow) return;
    
    // Reset all labels in this row to outline style
    const allLabels = studentRow.querySelectorAll('label.btn');
    allLabels.forEach(label => {
        const input = document.getElementById(label.getAttribute('for'));
        if (input && input.value === 'present') {
            label.className = 'btn btn-outline-success';
        } else if (input && input.value === 'absent') {
            label.className = 'btn btn-outline-danger';
        } else if (input && input.value === 'late') {
            label.className = 'btn btn-outline-warning';
        }
    });
    
    // Update the selected label to solid style
    const selectedLabel = document.querySelector(`label[for="${radioInput.id}"]`);
    if (selectedLabel) {
        if (radioInput.value === 'present') {
            selectedLabel.className = 'btn btn-success';
            studentRow.className = studentRow.className.replace(/border-\w+/, 'border-success');
        } else if (radioInput.value === 'absent') {
            selectedLabel.className = 'btn btn-danger';
            studentRow.className = studentRow.className.replace(/border-\w+/, 'border-danger');
        } else if (radioInput.value === 'late') {
            selectedLabel.className = 'btn btn-warning';
            studentRow.className = studentRow.className.replace(/border-\w+/, 'border-warning');
        }
    }
}

function setupStudentSearch() {
    const searchInput = document.getElementById('studentSearch');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const studentItems = document.querySelectorAll('.student-item');
        let visibleCount = 0;
        
        studentItems.forEach(item => {
            const studentName = item.querySelector('.fw-medium')?.textContent.toLowerCase() || '';
            const studentId = item.querySelector('small')?.textContent.toLowerCase() || '';
            
            if (studentName.includes(searchTerm) || studentId.includes(searchTerm)) {
                item.style.display = 'block';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        
        if (searchTerm) {
            showToast(`Found ${visibleCount} students`, 'info', 2000);
        }
    });
}

// ===== BULK ATTENDANCE ACTIONS ===== //
function markAllPresent() {
    const studentItems = document.querySelectorAll('.student-item');
    studentItems.forEach(item => {
        const presentRadio = item.querySelector('input[value="present"]');
        if (presentRadio && !presentRadio.disabled) {
            presentRadio.checked = true;
            // Uncheck other options for this student
            const absentRadio = item.querySelector('input[value="absent"]');
            const lateRadio = item.querySelector('input[value="late"]');
            if (absentRadio) absentRadio.checked = false;
            if (lateRadio) lateRadio.checked = false;
            
            // Update styling
            updateRadioButtonStyling(presentRadio);
        }
    });
    updateAttendanceCount();
    // Call the PHP-defined function to update save button state
    if (typeof updateAttendanceCounter === 'function') {
        updateAttendanceCounter();
    }
    showToast('All students marked as Present', 'success', 2000);
}

function markAllAbsent() {
    const studentItems = document.querySelectorAll('.student-item');
    studentItems.forEach(item => {
        const absentRadio = item.querySelector('input[value="absent"]');
        if (absentRadio && !absentRadio.disabled) {
            absentRadio.checked = true;
            // Uncheck other options for this student
            const presentRadio = item.querySelector('input[value="present"]');
            const lateRadio = item.querySelector('input[value="late"]');
            if (presentRadio) presentRadio.checked = false;
            if (lateRadio) lateRadio.checked = false;
            
            // Update styling
            updateRadioButtonStyling(absentRadio);
        }
    });
    updateAttendanceCount();
    // Call the PHP-defined function to update save button state
    if (typeof updateAttendanceCounter === 'function') {
        updateAttendanceCounter();
    }
    showToast('All students marked as Absent', 'warning', 2000);
}

function markAllLate() {
    const studentItems = document.querySelectorAll('.student-item');
    studentItems.forEach(item => {
        const lateRadio = item.querySelector('input[value="late"]');
        if (lateRadio && !lateRadio.disabled) {
            lateRadio.checked = true;
            // Uncheck other options for this student
            const presentRadio = item.querySelector('input[value="present"]');
            const absentRadio = item.querySelector('input[value="absent"]');
            if (presentRadio) presentRadio.checked = false;
            if (absentRadio) absentRadio.checked = false;
            
            // Update styling
            updateRadioButtonStyling(lateRadio);
        }
    });
    updateAttendanceCount();
    // Call the PHP-defined function to update save button state
    if (typeof updateAttendanceCounter === 'function') {
        updateAttendanceCounter();
    }
    showToast('All students marked as Late', 'warning', 2000);
}

function resetForm() {
    if (confirm('Are you sure you want to reset all attendance selections?')) {
        const studentItems = document.querySelectorAll('.student-item');
        studentItems.forEach(item => {
            const radioInputs = item.querySelectorAll('input[name^="attendance["]');
            radioInputs.forEach(input => {
                if (!input.disabled) {
                    input.checked = false;
                }
            });
            
            // Reset all labels to outline style
            const labels = item.querySelectorAll('label.btn');
            labels.forEach(label => {
                const input = document.getElementById(label.getAttribute('for'));
                if (input && input.value === 'present') {
                    label.className = 'btn btn-outline-success';
                } else if (input && input.value === 'absent') {
                    label.className = 'btn btn-outline-danger';
                } else if (input && input.value === 'late') {
                    label.className = 'btn btn-outline-warning';
                }
            });
            
            // Reset row border
            item.className = item.className.replace(/border-\w+/, 'border-light');
        });
        updateAttendanceCount();
        // Call the PHP-defined function to update save button state
        if (typeof updateAttendanceCounter === 'function') {
            updateAttendanceCounter();
        }
        showToast('Attendance form reset', 'info', 2000);
    }
}

// ===== FORM HANDLING FUNCTIONS ===== //
function handleDateChange() {
    const form = document.getElementById('selectionForm');
    if (form) {
        form.submit();
    }
}

function handleSemesterChange() {
    const form = document.getElementById('selectionForm');
    if (form) {
        form.submit();
    }
}

function handleSubjectChange() {
    const form = document.getElementById('selectionForm');
    if (form) {
        form.submit();
    }
}

// ===== CANCEL ATTENDANCE FUNCTIONALITY ===== //
function cancelAttendance() {
    if (!confirm('Are you sure you want to cancel attendance? This will clear all QR scans and reset the form.')) {
        return;
    }

    const semesterSelect = document.querySelector('select[name="semester"]');
    const subjectSelect = document.querySelector('select[name="subject"]');
    const dateInput = document.querySelector('input[name="date"]');
    
    if (!semesterSelect?.value || !subjectSelect?.value || !dateInput?.value) {
        showToast('Cannot cancel - missing required fields', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('cancel_attendance', '1');
    formData.append('semester', semesterSelect.value);
    formData.append('subject', subjectSelect.value);
    formData.append('date', dateInput.value);

    const cancelBtn = document.querySelector('button[onclick="cancelAttendance()"]');
    if (cancelBtn) {
        const originalText = cancelBtn.innerHTML;
        cancelBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cancelling...';
        cancelBtn.disabled = true;
        
        fetch('attendance.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.ok) {
                // Stop QR checking
                stopPendingQRCheck();
                
                showToast('Attendance cancelled successfully', 'success', 2000);
                
                // Reload the page to refresh the state
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                throw new Error('Failed to cancel attendance');
            }
        })
        .catch(error => {
            console.error('Error cancelling attendance:', error);
            showToast('Failed to cancel attendance: ' + error.message, 'error');
        })
        .finally(() => {
            if (cancelBtn) {
                cancelBtn.innerHTML = originalText;
                cancelBtn.disabled = false;
            }
        });
    }
}

// ===== UTILITY FUNCTIONS ===== //
function showToast(message, type = 'info', duration = 5000) {
    console.log(`Toast: ${message} (${type})`);
    
    // Remove any existing simple toasts
    const existingToasts = document.querySelectorAll('.simple-toast');
    existingToasts.forEach(toast => toast.remove());
    
    // Create simple toast
    const toast = document.createElement('div');
    toast.className = 'simple-toast alert alert-dismissible fade show position-fixed';
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px;';
    
    // Set alert type
    const alertType = type === 'error' ? 'danger' : type === 'warning' ? 'warning' : type === 'success' ? 'success' : 'info';
    toast.classList.add(`alert-${alertType}`);
    
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-remove after duration
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, duration);
}

function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Copied to clipboard!', 'success', 2000);
        }).catch(err => {
            console.error('Failed to copy to clipboard:', err);
            fallbackCopyToClipboard(text);
        });
    } else {
        fallbackCopyToClipboard(text);
    }
}

function fallbackCopyToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.opacity = '0';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showToast('Copied to clipboard!', 'success', 2000);
        } else {
            showToast('Failed to copy to clipboard', 'error');
        }
    } catch (err) {
        console.error('Fallback: Unable to copy to clipboard', err);
        showToast('Unable to copy to clipboard', 'error');
    } finally {
        document.body.removeChild(textArea);
    }
}

// ===== INITIALIZATION ===== //
document.addEventListener('DOMContentLoaded', function() {
    console.log('Enhanced Projector QR Attendance System loaded');
    
    // Check if we have the attendance form elements for debugging
    const subjectSelect = document.querySelector('select[name="subject"]');
    const dateInput = document.querySelector('input[name="date"]');
    const attendanceForm = document.querySelector('#attendanceForm');
    
    console.log('DOM Content Loaded - Elements check:', {
        subjectSelect: !!subjectSelect,
        subjectValue: subjectSelect?.value || 'none',
        dateInput: !!dateInput,
        dateValue: dateInput?.value || 'none',
        attendanceForm: !!attendanceForm
    });
    
    // Setup attendance form listeners
    const attendanceInputs = document.querySelectorAll('input[name^="attendance["]');
    console.log(`Found ${attendanceInputs.length} attendance input elements`);
    
    attendanceInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.checked) {
                // Since these are radio buttons, ensure only one is checked per student
                const studentId = this.name.match(/\[(\d+)\]/)[1];
                const allStudentInputs = document.querySelectorAll(`input[name="attendance[${studentId}]"]`);
                allStudentInputs.forEach(otherInput => {
                    if (otherInput !== this) {
                        otherInput.checked = false;
                    }
                });
                
                updateAttendanceCount();
                updateRadioButtonStyling(this);
            }
        });
    });
    
    // Setup student search
    setupStudentSearch();
    
    // Initialize counters
    updateAttendanceCount();
    
    // Setup form submission handler to stop polling
    if (attendanceForm) {
        attendanceForm.addEventListener('submit', function() {
            console.log('Form submission detected, stopping QR polling');
            stopPendingQRCheck();
        });
    }
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Auto-generate QR if there's an active session
    const currentQRToken = document.querySelector('input[value*="token="]');
    if (currentQRToken) {
        const token = currentQRToken.value.match(/token=([^&]+)/)?.[1];
        if (token) {
            console.log('Found active QR session, displaying projector QR');
            displayProjectorQR(token);
            // Start polling for QR scans on the existing session
            startPendingQRCheck(token);
        }
    } else {
        // Start QR scan polling for attendance form if loaded (only if no active QR session)
        startQRScanPolling();
    }
});

// Handle page unload to cleanup polling
window.addEventListener('beforeunload', function() {
    stopPendingQRCheck();
});

// Handle page visibility change to pause/resume timer
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible' && qrTimer) {
        console.log('Page visible again, timer continues');
    } else if (document.visibilityState === 'hidden' && qrTimer) {
        console.log('Page hidden, timer continues in background');
    }
});

// Error handling
window.addEventListener('error', function(e) {
    console.error('JavaScript error:', e.error);
    showToast('An error occurred. Please refresh the page.', 'error');
});

// Function to reset QR scanned students' buttons after attendance is saved
function resetQRScannedButtons() {
    console.log('Resetting QR scanned buttons after attendance save');
    
    // Find all student rows that were QR scanned
    const qrScannedRows = document.querySelectorAll('.student-item[data-qr-scanned="true"]');
    
    qrScannedRows.forEach(row => {
        const studentId = row.dataset.studentId || row.querySelector('input[name^="attendance["]')?.name.match(/\d+/)?.[0];
        
        if (studentId) {
            console.log(`Resetting buttons for student ${studentId}`);
            
            // Re-enable all radio buttons
            const allRadios = row.querySelectorAll('input[type="radio"]');
            allRadios.forEach(radio => {
                radio.disabled = false;
                const label = row.querySelector(`label[for="${radio.id}"]`);
                if (label) {
                    label.style.opacity = '1';
                    label.title = '';
                    label.classList.remove('disabled');
                }
            });
            
            // Remove QR scanned indicators
            row.removeAttribute('data-qr-scanned');
            row.removeAttribute('data-attendance-method');
            
            // Remove QR badges and scan time
            const qrBadge = row.querySelector('.badge.bg-warning');
            if (qrBadge) qrBadge.remove();
            
            const scanTime = row.querySelector('small.text-muted');
            if (scanTime && scanTime.textContent.includes('Scanned:')) {
                scanTime.remove();
            }
            
            // Reset border color
            row.className = row.className.replace(/border-\w+/, 'border-secondary');
        }
    });
}

// Function to handle attendance form submission
function handleAttendanceSubmit(event) {
    const form = event.target;
    
    // Show loading state
    const submitBtn = form.querySelector('#submitBtn');
    if (submitBtn) {
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        submitBtn.disabled = true;
        
        // Reset button text after a delay if form doesn't redirect
        setTimeout(() => {
            if (submitBtn) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        }, 5000);
    }
    
    // Note: After successful save, the page will redirect and reload,
    // so resetQRScannedButtons() will be handled by the page reload
}

// Initialize form submission handler when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const attendanceForm = document.querySelector('form[method="post"]');
    if (attendanceForm) {
        attendanceForm.addEventListener('submit', handleAttendanceSubmit);
    }
});

// Add visual pulse effect for QR scanned students
function addQRScanPulseEffect(studentRow) {
    // Add a subtle pulse animation to highlight QR scanned students
    studentRow.style.animation = 'qr-scan-pulse 1.5s ease-in-out';
    
    setTimeout(() => {
        if (studentRow) {
            studentRow.style.animation = '';
        }
    }, 1500);
}

// Add CSS for pulse effect
if (!document.getElementById('qr-scan-styles')) {
    const style = document.createElement('style');
    style.id = 'qr-scan-styles';
    style.textContent = `
        @keyframes qr-scan-pulse {
            0% { background-color: rgba(255, 193, 7, 0.1); }
            50% { background-color: rgba(255, 193, 7, 0.3); }
            100% { background-color: rgba(255, 193, 7, 0.1); }
        }
        
        .student-item[data-qr-scanned="true"] {
            background-color: rgba(255, 193, 7, 0.05);
            border-left: 4px solid #ffc107 !important;
        }
        
        .qr-status-indicator {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #ffc107;
            color: #212529;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }
    `;
    document.head.appendChild(style);
}

/**
 * Process pending QR scans by calling the API
 * @param {number} attendanceID - The attendance session ID
 */
function processPendingQRScans(attendanceID) {
    console.log('Processing pending QR scans for attendance ID:', attendanceID);
    
    // Call the API to get and process pending scans
    $.ajax({
        url: '../../api/get_pending_qr_attendance.php',
        type: 'POST',
        data: {
            attendance_id: attendanceID
        },
        success: function(response) {
            console.log('QR processing response:', response);
            
            if (response.success && response.scans && response.scans.length > 0) {
                // Process each scan and update UI
                response.scans.forEach(scan => {
                    // Find the student's row in the attendance table
                    const studentRow = document.querySelector(`input[name="attendance[${scan.StudentID}]"]`)?.closest('.student-item');
                    
                    if (studentRow) {
                        // Find the present radio button
                        const presentRadio = studentRow.querySelector(`input[name="attendance[${scan.StudentID}]"][value="present"]`);
                        
                        if (presentRadio) {
                            // Check the present radio button
                            presentRadio.checked = true;
                            
                            // Disable all radio buttons for this student
                            studentRow.querySelectorAll(`input[name="attendance[${scan.StudentID}]"]`).forEach(radio => {
                                radio.disabled = true;
                            });
                            
                            // Add a badge to show QR verified
                            const studentInfo = studentRow.querySelector('.student-info');
                            if (studentInfo && !studentInfo.querySelector('.qr-badge')) {
                                const badge = document.createElement('span');
                                badge.className = 'badge bg-success ms-2 qr-badge';
                                badge.textContent = 'QR Verified';
                                studentInfo.appendChild(badge);
                            }
                            
                            console.log(`Updated UI for student ${scan.StudentID} based on QR scan`);
                        }
                    }
                });
                
                // Show a notification
                showToast(`${response.scans.length} QR scan${response.scans.length !== 1 ? 's' : ''} processed`, 'success');
                
                // Update attendance count
                updateAttendanceCount();
            }
        },
        error: function(xhr, status, error) {
            console.error('Error processing QR scans:', error);
            showToast('Error processing QR scans', 'error');
        }
    });
}
