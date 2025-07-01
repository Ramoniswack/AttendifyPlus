// QR Code Scanner Implementation
let html5QrCode;
let isScanning = false;
let currentCameraId = null;
let cameras = [];

document.addEventListener('DOMContentLoaded', function() {
    initializeScanner();
    setupEventListeners();
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});

function initializeScanner() {
    updateStatus('camera', 'Initializing Scanner...', 'Please wait while we set up your camera');
    
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
            
            // Auto-start camera for immediate scanning
            setTimeout(() => {
                startScanning();
            }, 1000);
        } else {
            updateStatus('x-circle', 'No Camera Found', 'Please ensure you have a camera connected and grant permission');
        }
    }).catch(err => {
        console.error('Error getting cameras:', err);
        updateStatus('alert-circle', 'Camera Access Error', 'Unable to access camera. Please check permissions.');
    });
}

function setupEventListeners() {
    // Start scan button
    const startBtn = document.getElementById('startScanBtn');
    if (startBtn) {
        startBtn.addEventListener('click', startScanning);
    }
    
    // Stop scan button
    const stopBtn = document.getElementById('stopScanBtn');
    if (stopBtn) {
        stopBtn.addEventListener('click', stopScanning);
    }
    
    // Switch camera button
    const switchBtn = document.getElementById('switchCameraBtn');
    if (switchBtn) {
        switchBtn.addEventListener('click', switchCamera);
    }
    
    // Manual code input
    const submitBtn = document.getElementById('submitManualCode');
    if (submitBtn) {
        submitBtn.addEventListener('click', submitManualCode);
    }
    
    // Enter key for manual input
    const manualInput = document.getElementById('manualCodeInput');
    if (manualInput) {
        manualInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                submitManualCode();
            }
        });
    }
}

function updateStatus(icon, title, message) {
    const statusIndicator = document.getElementById('statusIndicator');
    const statusTitle = document.getElementById('statusTitle');
    const statusMessage = document.getElementById('statusMessage');
    
    if (statusIndicator) {
        statusIndicator.innerHTML = `<i data-lucide="${icon}" style="width: 24px; height: 24px;"></i>`;
    }
    if (statusTitle) statusTitle.textContent = title;
    if (statusMessage) statusMessage.textContent = message;
    
    // Re-initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
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
    
    if (!qrReader) {
        console.error('QR reader element not found');
        return;
    }
    
    // Initialize Html5Qrcode
    html5QrCode = new Html5Qrcode("qr-reader");
    
    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0,
        showTorchButtonIfSupported: true
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
        const overlay = document.querySelector('.scanner-overlay');
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
        const overlay = document.querySelector('.scanner-overlay');
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
    
    // Stop scanning immediately to prevent multiple scans
    stopScanning();
    
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
    updateStatus('loader', 'Processing...', 'Marking your attendance...');
    
    const formData = new FormData();
    formData.append('token', token);
    
   // In scan_qr.js, fix the fetch URL:
fetch('../../api/process_qr_attendance.php', {  // Add proper relative path
    method: 'POST',
    body: formData,
    credentials: 'same-origin'
})
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Attendance response:', data);
        if (data.success) {
            showSuccessModal(data);
            updateStatus('check-circle', 'Attendance Marked!', 'Your attendance has been recorded successfully');
            
            // Refresh page after 3 seconds to show updated attendance
            setTimeout(() => {
                window.location.reload();
            }, 3000);
        } else {
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

function submitManualCode() {
    const input = document.getElementById('manualCodeInput');
    if (!input) return;
    
    const code = input.value.trim();
    
    if (!code) {
        showToast('Please enter an attendance code', 'warning');
        return;
    }
    
    // Process the manual code
    processAttendance(code);
    input.value = '';
}

function showSuccessModal(data) {
    const modal = document.getElementById('successModal');
    const subjectElement = document.getElementById('successSubject');
    const messageElement = document.getElementById('successMessage');
    const timeElement = document.getElementById('successTime');
    
    if (subjectElement) {
        subjectElement.textContent = data.subject || 'Subject';
    }
    
    if (messageElement) {
        messageElement.textContent = data.message || 'Attendance marked successfully!';
    }
    
    if (timeElement) {
        timeElement.textContent = new Date().toLocaleString();
    }
    
    // Initialize and show modal
    if (typeof bootstrap !== 'undefined' && modal) {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
    
    // Re-initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Show toast as well
    showToast(data.message || 'Attendance marked successfully!', 'success');
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