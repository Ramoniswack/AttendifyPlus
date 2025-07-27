// QR Code Scanner Implementation - Mobile Optimized
let html5QrCode;
let fullscreenScanner;
let isScanning = false;
let isFullscreenScanning = false;
let currentCameraId = null;
let cameras = [];
let qrExpiryTimer = null;
let qrExpiryCountdown = null;
let autoRefreshTimer = null;
let hasFlashSupport = false;
let isFlashOn = false;

document.addEventListener('DOMContentLoaded', function() {
    initializeScanner();
    setupEventListeners();
    checkExpiredQRPreventAutoRefresh();
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});

function initializeScanner() {
    updateStatus('camera', 'Ready to Scan', 'Click "Start" to begin scanning');
    
    // Check if Html5Qrcode is available
    if (typeof Html5Qrcode === 'undefined') {
        updateStatus('alert-circle', 'Scanner Library Error', 'QR scanner library failed to load');
        return;
    }
    
    // Get available cameras
    Html5Qrcode.getCameras().then(devices => {
        if (devices && devices.length) {
            cameras = devices;
            currentCameraId = devices[0].id;
            updateStatus('camera', 'Camera Ready', `Found ${devices.length} camera(s). Ready to scan!`);
            showScannerControls();
        } else {
            updateStatus('x-circle', 'No Camera Found', 'Please ensure you have a camera connected and grant permission');
        }
    }).catch(err => {
        console.error('Error getting cameras:', err);
        updateStatus('alert-circle', 'Camera Access Error', 'Unable to access camera. Please check permissions.');
    });
}

function setupEventListeners() {
    // Mobile start scan button
    const mobileStartBtn = document.getElementById('mobileStartScanBtn');
    if (mobileStartBtn) {
        mobileStartBtn.addEventListener('click', startFullscreenScanning);
    }
    
    // Desktop start scan button
    const startBtn = document.getElementById('startScanBtn');
    if (startBtn) {
        startBtn.addEventListener('click', startScanning);
    }
    
    // Desktop stop scan button
    const stopBtn = document.getElementById('stopScanBtn');
    if (stopBtn) {
        stopBtn.addEventListener('click', stopScanning);
    }
    
    // Desktop switch camera button
    const switchBtn = document.getElementById('switchCameraBtn');
    if (switchBtn) {
        switchBtn.addEventListener('click', switchCamera);
    }
    
    // Fullscreen controls
    const exitFullscreenBtn = document.getElementById('exitFullscreenBtn');
    if (exitFullscreenBtn) {
        exitFullscreenBtn.addEventListener('click', exitFullscreenScanning);
    }
    
    const fullscreenSwitchBtn = document.getElementById('fullscreenSwitchBtn');
    if (fullscreenSwitchBtn) {
        fullscreenSwitchBtn.addEventListener('click', switchCameraFullscreen);
    }
    
    const fullscreenFlashBtn = document.getElementById('fullscreenFlashBtn');
    if (fullscreenFlashBtn) {
        fullscreenFlashBtn.addEventListener('click', toggleFlash);
    }
}

function updateStatus(icon, title, message) {
    // Update desktop status
    const statusIndicator = document.getElementById('statusIndicator');
    const statusTitle = document.getElementById('statusTitle');
    const statusMessage = document.getElementById('statusMessage');
    
    if (statusIndicator) {
        statusIndicator.innerHTML = `<i data-lucide="${icon}" style="width: 20px; height: 20px;"></i>`;
    }
    if (statusTitle) statusTitle.textContent = title;
    if (statusMessage) statusMessage.textContent = message;
    
    // Update mobile status
    const statusIndicatorModern = document.getElementById('statusIndicatorModern');
    const statusTextModern = document.getElementById('statusTextModern');
    
    if (statusIndicatorModern) {
        statusIndicatorModern.innerHTML = `<i data-lucide="${icon}" style="width: 16px; height: 16px;"></i>`;
        
        // Update classes for visual feedback
        statusIndicatorModern.className = 'status-indicator-modern';
        if (icon === 'scan' || icon === 'camera') {
            statusIndicatorModern.classList.add('scanning');
        }
    }
    
    if (statusTextModern) {
        statusTextModern.textContent = getShortStatus(title);
    }
    
    // Re-initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function getShortStatus(title) {
    switch(title) {
        case 'Scanning Active': return 'Scanning';
        case 'Scanner Stopped': return 'Stopped';
        case 'Camera Ready': return 'Ready';
        case 'Processing...': return 'Processing';
        case 'Attendance Marked!': return 'Success';
        case 'Failed': return 'Failed';
        default: return 'Ready';
    }
}

function showScannerControls() {
    const startBtn = document.getElementById('startScanBtn');
    const switchBtn = document.getElementById('switchCameraBtn');
    
    if (startBtn) {
        startBtn.style.display = 'inline-block';
    }
    
    if (switchBtn && cameras.length > 1) {
        switchBtn.style.display = 'inline-block';
    }
}

function startScanning() {
    if (isScanning) return;

    const qrReader = document.getElementById('qr-reader');
    const startBtn = document.getElementById('startScanBtn');
    const stopBtn = document.getElementById('stopScanBtn');
    const scannerContainer = document.getElementById('scannerContainer');

    if (scannerContainer) {
        scannerContainer.style.display = '';
    }

    if (!qrReader) {
        console.error('QR reader element not found');
        return;
    }

    // Initialize Html5Qrcode for desktop
    html5QrCode = new Html5Qrcode("qr-reader");

    const config = {
        fps: 10,
        qrbox: { width: 200, height: 200 },
        aspectRatio: 1.0
    };

    html5QrCode.start(
        currentCameraId,
        config,
        (decodedText, decodedResult) => {
            console.log('QR Code detected:', decodedText);
            handleQRCodeDetected(decodedText);
        },
        (errorMessage) => {
            // Handle scan errors silently (common during scanning)
        }
    ).then(() => {
        isScanning = true;
        if (startBtn) startBtn.style.display = 'none';
        if (stopBtn) stopBtn.style.display = 'inline-block';
        updateStatus('scan', 'Scanning Active', 'Point your camera at the QR code');

        // Show scanner overlay
        const overlay = document.querySelector('.scanner-overlay-desktop');
        if (overlay) {
            overlay.style.display = 'block';
        }
    }).catch(err => {
        console.error('Error starting scanner:', err);
        updateStatus('alert-circle', 'Scanner Error', 'Failed to start camera. Please try again.');
        showToast('Failed to start camera. Please check permissions.', 'error');
    });
}

function stopScanning() {
    if (!isScanning || !html5QrCode) return;
    
    const startBtn = document.getElementById('startScanBtn');
    const stopBtn = document.getElementById('stopScanBtn');
    
    html5QrCode.stop().then(() => {
        isScanning = false;
        if (startBtn) startBtn.style.display = 'inline-block';
        if (stopBtn) stopBtn.style.display = 'none';
        updateStatus('camera', 'Scanner Stopped', 'Click "Start Scanning" to begin again');
        
        // Hide scanner overlay
        const overlay = document.querySelector('.scanner-overlay-desktop');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }).catch(err => {
        console.error('Error stopping scanner:', err);
    });
}

function switchCamera() {
    if (cameras.length <= 1) return;
    
    // Find next camera
    const currentIndex = cameras.findIndex(camera => camera.id === currentCameraId);
    const nextIndex = (currentIndex + 1) % cameras.length;
    currentCameraId = cameras[nextIndex].id;
    
    if (isScanning) {
        stopScanning();
        setTimeout(() => {
            startScanning();
        }, 500);
    }
    
    showToast(`Switched to camera: ${cameras[nextIndex].label || 'Camera ' + (nextIndex + 1)}`, 'info');
}

function handleQRCodeDetected(qrData) {
    console.log('Processing QR code:', qrData);
    
    // Stop all scanning immediately to prevent multiple scans
    if (isScanning) stopScanning();
    if (isFullscreenScanning) exitFullscreenScanning();
    
    // Extract token from QR data
    let token = '';
    
    try {
        if (qrData.includes('token=')) {
            // Extract token from URL
            const url = new URL(qrData);
            token = url.searchParams.get('token');
        } else if (qrData.includes('scan_qr.php')) {
            // Extract token from URL path
            const urlParts = qrData.split('?');
            if (urlParts.length > 1) {
                const urlParams = new URLSearchParams(urlParts[1]);
                token = urlParams.get('token');
            }
        } else {
            // Assume the entire QR data is the token
            token = qrData.trim();
        }
    } catch (e) {
        console.warn('Error parsing QR data as URL:', e);
        // Fallback: treat entire string as token
        token = qrData.trim();
    }
    
    if (!token) {
        showToast('Invalid QR code format', 'error');
        updateStatus('x-circle', 'Invalid QR Code', 'The scanned code is not a valid attendance QR code');
        return;
    }
    
    // Process attendance
    processAttendance(token);
}

function processAttendance(token) {
    console.log('=== PROCESSING ATTENDANCE ===');
    console.log('Token received:', token);
    console.log('Token length:', token.length);
    
    updateStatus('loader', 'Processing...', 'Marking your attendance...');
    
    const formData = new FormData();
    formData.append('token', token);
    
    // Generate device fingerprint for unique device identification
    const deviceFingerprint = generateDeviceFingerprint();
    formData.append('device_fingerprint', deviceFingerprint);
    
    console.log('Sending request to:', '../../api/process_qr_attendance.php');
    console.log('FormData contents:', Array.from(formData.entries()));
    console.log('Device fingerprint:', deviceFingerprint);
    
   // In scan_qr.js, fix the fetch URL:
fetch('../../api/process_qr_attendance.php', {  // Add proper relative path
    method: 'POST',
    body: formData,
    credentials: 'same-origin'
})
    .then(response => {
        console.log('Response received:', response);
        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Attendance response:', data);
        if (data.success) {
            // Update for pending approval
            if (data.status === 'pending') {
                showSuccessModal(data, true); // Pass true for pending status
                updateStatus('clock', 'QR Scanned!', 'Pending teacher approval - attendance not yet saved');
            } else {
                showSuccessModal(data);
                updateStatus('check-circle', 'Attendance Marked!', 'Your attendance has been recorded successfully');
            }
            
            // Page refresh removed - let user continue scanning without disruption
            // Allow scanning again after 3 seconds
            setTimeout(() => {
                updateStatus('camera', 'Ready to Scan', 'You can start scanning again');
            }, 3000);
        } else {
            console.log('Error response data:', data);
            
            // Log debug information if available
            if (data.debug_info) {
                console.log('=== DEVICE FINGERPRINT DEBUG INFO ===');
                console.log('Current fingerprint:', data.debug_info.current_fingerprint);
                console.log('Registered fingerprint:', data.debug_info.registered_fingerprint);
                console.log('Student ID:', data.debug_info.student_id);
                console.log('Fingerprint match:', data.debug_info.current_fingerprint === data.debug_info.registered_fingerprint);
            }
            
            showToast(data.message || 'Failed to mark attendance', 'error');
            updateStatus('x-circle', 'Failed', data.message || 'Failed to mark attendance');
            
            // Allow scanning again after 2 seconds
            setTimeout(() => {
                updateStatus('camera', 'Ready to Scan', 'You can start scanning again');
            }, 2000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error. Please try again.', 'error');
        updateStatus('wifi-off', 'Network Error', 'Please check your connection and try again');
        
        // Allow scanning again after 2 seconds
        setTimeout(() => {
            updateStatus('camera', 'Ready to Scan', 'You can start scanning again');
        }, 2000);
    });
}

function showSuccessModal(data, isPending = false) {
    const modal = document.getElementById('successModal');
    const titleElement = document.getElementById('successModalTitle');
    const messageElement = document.getElementById('successModalMessage');
    const subjectElement = document.getElementById('successSubject');
    const timeElement = document.getElementById('successTime');
    
    // Update modal content
    if (titleElement) {
        if (isPending) {
            titleElement.textContent = 'QR Code Scanned!';
            titleElement.className = 'success-title mb-2 text-warning';
        } else {
            titleElement.textContent = 'Attendance Marked!';
            titleElement.className = 'success-title mb-2';
        }
    }
    
    if (messageElement) {
        if (isPending) {
            messageElement.innerHTML = `
                <div class="mb-2">${data.message || 'QR scanned successfully!'}</div>
                <div class="alert alert-warning py-2 mt-2 mb-0">
                    <i data-lucide="info" style="width: 14px; height: 14px;"></i>
                    <strong>Pending Teacher Approval</strong><br>
                    <small>Your attendance will be saved when the teacher clicks "Save Attendance"</small>
                </div>
            `;
        } else {
            messageElement.textContent = data.message || 'Your attendance has been recorded successfully';
        }
    }
    
    if (subjectElement) {
        subjectElement.textContent = data.subject || 'Subject';
    }
    
    if (timeElement) {
        timeElement.textContent = new Date().toLocaleString();
    }
    
    // Initialize and show modal
    if (typeof bootstrap !== 'undefined' && modal) {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        // Auto-hide modal after 4 seconds for better UX
        setTimeout(() => {
            bsModal.hide();
        }, isPending ? 5000 : 4000);
    }
    
    // Re-initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Show toast as well
    const toastType = isPending ? 'warning' : 'success';
    const toastMessage = isPending ? 'QR scanned! Waiting for teacher approval...' : (data.message || 'Attendance marked successfully!');
    showToast(toastMessage, toastType, isPending ? 4000 : 3000);
}

function showToast(message, type = 'info', duration = 5000) {
    let toastContainer = document.getElementById('toastContainer');
    
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '10000';
        document.body.appendChild(toastContainer);
    }
    
    const toastId = 'toast-' + Date.now();
    const iconMap = {
        'success': 'check-circle',
        'error': 'x-circle',
        'warning': 'alert-triangle',
        'info': 'info'
    };
    
    const colorMap = {
        'success': 'bg-success',
        'error': 'bg-danger',
        'warning': 'bg-warning',
        'info': 'bg-info'
    };
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white ${colorMap[type]} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    toast.setAttribute('id', toastId);
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i data-lucide="${iconMap[type]}" class="me-2" style="width: 18px; height: 18px;"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Initialize Bootstrap toast
    if (typeof bootstrap !== 'undefined') {
        const bsToast = new bootstrap.Toast(toast, { delay: duration });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }
}

// Device fingerprint generation for unique device identification
function generateDeviceFingerprint() {
    const fingerprint = [
        navigator.userAgent,
        navigator.language,
        screen.width + 'x' + screen.height,
        screen.colorDepth,
        navigator.hardwareConcurrency || 'unknown',
        navigator.platform,
        !!window.sessionStorage,
        !!window.localStorage,
        navigator.cookieEnabled,
        navigator.onLine
    ].join('|');
    
    // Simple hash function
    let hash = 0;
    for (let i = 0; i < fingerprint.length; i++) {
        const char = fingerprint.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash = hash & hash; // Convert to 32-bit integer
    }
    
    return Math.abs(hash).toString(16);
}

// Test function to debug device fingerprint
function testDeviceFingerprint() {
    const fingerprint = generateDeviceFingerprint();
    console.log('=== DEVICE FINGERPRINT TEST ===');
    console.log('Generated fingerprint:', fingerprint);
    console.log('Fingerprint length:', fingerprint.length);
    
    // Log the components used to generate the fingerprint
    const components = [
        navigator.userAgent,
        navigator.language,
        screen.width + 'x' + screen.height,
        screen.colorDepth,
        navigator.hardwareConcurrency || 'unknown',
        navigator.platform,
        !!window.sessionStorage,
        !!window.localStorage,
        navigator.cookieEnabled,
        navigator.onLine
    ];
    
    console.log('Fingerprint components:');
    components.forEach((component, index) => {
        console.log(`  ${index + 1}. ${component}`);
    });
    
    return fingerprint;
}

// Make test function available globally for debugging
window.testDeviceFingerprint = testDeviceFingerprint;

// Check if the student has any pending QR scans
function checkPendingQRScans() {
    // Get student ID from hidden input
    const studentIdField = document.getElementById('student-id');
    if (!studentIdField) {
        console.warn('Student ID field not found');
        return;
    }
    
    const studentId = studentIdField.value;
    
    fetch('../../api/check_qr_pending.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `student_id=${studentId}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Pending QR scan check:', data);
        
        if (data.success && data.has_pending_scans) {
            // Show pending status
            updateStatus('clock', 'QR Scan Pending', `You have ${data.pending_count} pending QR scan${data.pending_count !== 1 ? 's' : ''}`);
            
            // Display pending banner if not already showing
            if (!document.querySelector('.pending-banner')) {
                const pendingBanner = document.createElement('div');
                pendingBanner.className = 'alert alert-warning pending-banner';
                pendingBanner.innerHTML = `
                    <h4><i data-lucide="clock" class="me-2"></i>Pending Approval</h4>
                    <p>Your QR attendance has been recorded and is pending teacher approval.</p>
                    <p class="mb-0">Please wait for your teacher to save the attendance.</p>
                `;
                
                // Insert at the top of the scanner container
                const scannerContainer = document.querySelector('.scanner-container');
                if (scannerContainer) {
                    scannerContainer.prepend(pendingBanner);
                    // Initialize Lucide icons in the banner
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            }
            
            // Allow continued scanning - don't disable scanner
            // User can manually stop scanning if they want
        }
    })
    .catch(error => {
        console.error('Error checking pending QR scans:', error);
    });
}

// Start checking for pending QR scans when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initial check
    checkPendingQRScans();
    
    // Set up periodic checking every 2 minutes (reduced from 30 seconds)
    setInterval(checkPendingQRScans, 120000);
});

// Handle page visibility changes
document.addEventListener('visibilitychange', function() {
    if (document.hidden && isScanning) {
        stopScanning();
    }
});

// Handle page unload
window.addEventListener('beforeunload', function() {
    if (isScanning && html5QrCode) {
        html5QrCode.stop();
    }
});

// QR Code Expiry Management
function startQRExpiryCheck() {
    // Check for QR expiry every 5 seconds
    qrExpiryTimer = setInterval(checkQRExpiry, 5000);
    
    // Start countdown display
    startExpiryCountdown();
}

function checkQRExpiry() {
    // Make a quick check to see if any QR codes have expired
    fetch('../../api/check_qr_pending.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `student_id=${window.studentData.studentId}&check_expiry=1`
    })
    .then(response => response.json())
    .then(data => {
        if (data.qr_expired) {
            showQRExpiredMessage();
        }
    })
    .catch(error => {
        console.log('QR expiry check failed:', error);
    });
}

function startExpiryCountdown() {
    let countdown = 60; // 60 seconds
    
    // Create countdown display element
    const countdownElement = document.createElement('div');
    countdownElement.id = 'qr-countdown';
    countdownElement.className = 'alert alert-info text-center mt-2';
    countdownElement.innerHTML = `
        <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
        QR codes expire in <strong><span id="countdown-time">60</span></strong> seconds
    `;
    
    // Insert countdown above scanner controls
    const scannerControls = document.querySelector('.scanner-controls');
    if (scannerControls && !document.getElementById('qr-countdown')) {
        scannerControls.parentNode.insertBefore(countdownElement, scannerControls);
        // Initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
    
    qrExpiryCountdown = setInterval(() => {
        countdown--;
        const timeElement = document.getElementById('countdown-time');
        if (timeElement) {
            timeElement.textContent = countdown;
            
            // Change color as countdown gets lower
            const countdownContainer = document.getElementById('qr-countdown');
            if (countdown <= 10) {
                countdownContainer.className = 'alert alert-danger text-center mt-2';
            } else if (countdown <= 30) {
                countdownContainer.className = 'alert alert-warning text-center mt-2';
            }
        }
        
        if (countdown <= 0) {
            clearInterval(qrExpiryCountdown);
            showQRExpiredMessage();
        }
    }, 1000);
}

function showQRExpiredMessage() {
    // Clear existing timers
    if (qrExpiryTimer) clearInterval(qrExpiryTimer);
    if (qrExpiryCountdown) clearInterval(qrExpiryCountdown);
    
    // Stop scanning if active
    if (isScanning) {
        stopScanning();
    }
    
    // Update status
    updateStatus('clock-off', 'QR Codes Expired', 'All QR codes have expired. Page will refresh automatically.');
    
    // Show expiry message
    const countdownElement = document.getElementById('qr-countdown');
    if (countdownElement) {
        countdownElement.className = 'alert alert-danger text-center mt-2';
        countdownElement.innerHTML = `
            <i data-lucide="refresh-cw" style="width: 16px; height: 16px;"></i>
            <strong>QR Codes Expired!</strong> Refreshing page in <span id="refresh-countdown">5</span> seconds...
        `;
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
    
    // Show toast notification
    showToast('QR codes have expired. Page will refresh automatically.', 'warning', 8000);
    
    // Start refresh countdown
    let refreshCountdown = 5;
    const refreshTimer = setInterval(() => {
        refreshCountdown--;
        const refreshElement = document.getElementById('refresh-countdown');
        if (refreshElement) {
            refreshElement.textContent = refreshCountdown;
        }
        
        if (refreshCountdown <= 0) {
            clearInterval(refreshTimer);
            window.location.reload();
        }
    }, 1000);
}

// Auto-refresh functionality
function setupAutoRefresh() {
    // Auto-refresh page every 90 seconds (30 seconds after QR expiry)
    autoRefreshTimer = setInterval(() => {
        console.log('Auto-refreshing page to get fresh QR codes...');
        window.location.reload();
    }, 90000); // 90 seconds
}

// Clear all timers when page is unloaded
window.addEventListener('beforeunload', function() {
    if (qrExpiryTimer) clearInterval(qrExpiryTimer);
    if (qrExpiryCountdown) clearInterval(qrExpiryCountdown);
    if (autoRefreshTimer) clearInterval(autoRefreshTimer);
    if (isScanning && html5QrCode) {
        html5QrCode.stop();
    }
});

// Mobile Fullscreen Scanner Functions
function startFullscreenScanning() {
    const fullscreenElement = document.getElementById('fullscreenScanner');
    if (!fullscreenElement) return;
    
    // Show fullscreen scanner
    fullscreenElement.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Initialize fullscreen scanner
    fullscreenScanner = new Html5Qrcode("fullscreen-qr-reader");
    
    const config = {
        fps: 15,
        qrbox: { width: 280, height: 280 },
        aspectRatio: 1.0,
        showTorchButtonIfSupported: true,
        supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
    };
    
    fullscreenScanner.start(
        currentCameraId,
        config,
        (decodedText, decodedResult) => {
            console.log('QR Code detected in fullscreen:', decodedText);
            handleQRCodeDetected(decodedText);
        },
        (errorMessage) => {
            // Handle scan errors silently
        }
    ).then(() => {
        isFullscreenScanning = true;
        updateStatus('scan', 'Scanning Active', 'Point your camera at the QR code');
        
        // Show camera controls if multiple cameras available
        if (cameras.length > 1) {
            const switchBtn = document.getElementById('fullscreenSwitchBtn');
            if (switchBtn) switchBtn.style.display = 'inline-block';
        }
        
        // Check for flash support
        checkFlashSupport();
        
    }).catch(err => {
        console.error('Error starting fullscreen scanner:', err);
        exitFullscreenScanning();
        showToast('Failed to start camera. Please check permissions.', 'error');
    });
}

function exitFullscreenScanning() {
    const fullscreenElement = document.getElementById('fullscreenScanner');
    if (!fullscreenElement) return;
    
    if (isFullscreenScanning && fullscreenScanner) {
        fullscreenScanner.stop().then(() => {
            isFullscreenScanning = false;
            fullscreenElement.style.display = 'none';
            document.body.style.overflow = '';
            updateStatus('camera', 'Ready to Scan', 'Click "Start" to begin scanning');
        }).catch(err => {
            console.error('Error stopping fullscreen scanner:', err);
            isFullscreenScanning = false;
            fullscreenElement.style.display = 'none';
            document.body.style.overflow = '';
        });
    } else {
        fullscreenElement.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function switchCameraFullscreen() {
    if (cameras.length <= 1) return;
    
    // Find next camera
    const currentIndex = cameras.findIndex(camera => camera.id === currentCameraId);
    const nextIndex = (currentIndex + 1) % cameras.length;
    currentCameraId = cameras[nextIndex].id;
    
    if (isFullscreenScanning) {
        exitFullscreenScanning();
        setTimeout(() => {
            startFullscreenScanning();
        }, 500);
    }
    
    showToast(`Switched to camera: ${cameras[nextIndex].label || 'Camera ' + (nextIndex + 1)}`, 'info');
}

function checkFlashSupport() {
    // Check if torch/flash is supported
    if (fullscreenScanner && fullscreenScanner.getRunningTrackCapabilities) {
        const capabilities = fullscreenScanner.getRunningTrackCapabilities();
        if (capabilities && capabilities.torch) {
            hasFlashSupport = true;
            const flashBtn = document.getElementById('fullscreenFlashBtn');
            if (flashBtn) flashBtn.style.display = 'inline-block';
        }
    }
}

function toggleFlash() {
    if (!hasFlashSupport || !fullscreenScanner) return;
    
    try {
        if (isFlashOn) {
            fullscreenScanner.applyVideoConstraints({
                advanced: [{ torch: false }]
            });
            isFlashOn = false;
            const flashBtn = document.getElementById('fullscreenFlashBtn');
            if (flashBtn) {
                flashBtn.innerHTML = '<i data-lucide="flashlight"></i>';
                flashBtn.classList.remove('btn-light');
                flashBtn.classList.add('btn-outline-light');
            }
        } else {
            fullscreenScanner.applyVideoConstraints({
                advanced: [{ torch: true }]
            });
            isFlashOn = true;
            const flashBtn = document.getElementById('fullscreenFlashBtn');
            if (flashBtn) {
                flashBtn.innerHTML = '<i data-lucide="flashlight-off"></i>';
                flashBtn.classList.remove('btn-outline-light');
                flashBtn.classList.add('btn-light');
            }
        }
        
        // Re-initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    } catch (err) {
        console.error('Error toggling flash:', err);
        showToast('Flash not supported on this device', 'warning');
    }
}

// Prevent auto-refresh on expired QR codes
function checkExpiredQRPreventAutoRefresh() {
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    
    if (token) {
        // Check if QR token is still valid before auto-scanning
        fetch('../../api/check_qr_pending.php', {
            method: 'POST',
            body: new URLSearchParams({ 'token': token }),
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.valid) {
                // QR is still valid, can proceed
                updateStatus('camera', 'QR Code Ready', 'Valid QR code detected - you can scan now');
                showToast('Valid QR code detected!', 'success', 3000);
            } else {
                // QR is expired or invalid
                updateStatus('clock', 'QR Code Expired', 'This QR code has expired. Please ask your teacher for a new one.');
                showToast('QR code has expired. Please ask your teacher for a new one.', 'warning', 5000);
                
                // Clear the token from URL without refreshing
                const newUrl = window.location.pathname;
                window.history.replaceState(null, '', newUrl);
            }
        })
        .catch(error => {
            console.warn('Error checking QR validity:', error);
            // Don't show error to user, just proceed normally
        });
    }
}