// QR Scanner JavaScript

let html5QrcodeScanner = null;
let isScanning = false;
let currentCameraId = null;
let availableCameras = [];
let scanTimeout = null;

// Initialize scanner when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('QR Scanner initializing...');
    
    // Initialize Lucide icons
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
    
    initializeScanner();
    setupEventListeners();
    checkCameraPermissions();
    
    // Apply theme from localStorage
    if (localStorage.getItem("theme") === "dark") {
        document.body.classList.add("dark-mode");
    }
    
    // Auto-start scanning if on mobile
    if (isMobileDevice()) {
        setTimeout(() => {
            startScanning();
        }, 1000);
    }
});

function initializeScanner() {
    console.log('Initializing QR Scanner...');
    
    // Get available cameras
    Html5Qrcode.getCameras().then(devices => {
        availableCameras = devices;
        console.log('Available cameras:', devices);
        
        if (devices && devices.length > 0) {
            // Prefer back camera on mobile
            const backCamera = devices.find(device => 
                device.label.toLowerCase().includes('back') || 
                device.label.toLowerCase().includes('rear') ||
                device.label.toLowerCase().includes('environment')
            );
            currentCameraId = backCamera ? backCamera.id : devices[0].id;
            
            updateStatus('ready', 'Camera Ready', 'Tap "Start Scanning" to begin');
            
            // Show switch camera button if multiple cameras
            if (devices.length > 1) {
                document.getElementById('switchCameraBtn').style.display = 'inline-block';
            }
            
            // Show camera info
            updateCameraInfo(devices);
        } else {
            updateStatus('error', 'No Camera Found', 'Please check camera permissions');
        }
    }).catch(err => {
        console.error('Error getting cameras:', err);
        updateStatus('error', 'Camera Error', 'Unable to access camera');
        showPermissionPrompt();
    });
}

function setupEventListeners() {
    // Start scanner button
    const startBtn = document.getElementById('startScanBtn');
    if (startBtn) {
        startBtn.addEventListener('click', startScanning);
    }
    
    // Stop scanner button
    const stopBtn = document.getElementById('stopScanBtn');
    if (stopBtn) {
        stopBtn.addEventListener('click', stopScanning);
    }
    
    // Switch camera button
    const switchBtn = document.getElementById('switchCameraBtn');
    if (switchBtn) {
        switchBtn.addEventListener('click', switchCamera);
    }
    
    // Manual code submission
    const submitBtn = document.getElementById('submitManualCode');
    if (submitBtn) {
        submitBtn.addEventListener('click', submitManualCode);
    }
    
    const manualInput = document.getElementById('manualCodeInput');
    if (manualInput) {
        manualInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                submitManualCode();
            }
        });
    }
    
    // Flash toggle
    const flashBtn = document.getElementById('flashToggleBtn');
    if (flashBtn) {
        flashBtn.addEventListener('click', toggleFlash);
    }
    
    // Vibration toggle
    const vibrateBtn = document.getElementById('vibrateToggleBtn');
    if (vibrateBtn) {
        vibrateBtn.addEventListener('click', toggleVibration);
    }
}

function checkCameraPermissions() {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => {
                console.log('Camera permission granted');
                stream.getTracks().forEach(track => track.stop());
                updateStatus('ready', 'Camera Permission Granted', 'Ready to scan');
            })
            .catch(err => {
                console.error('Camera permission denied:', err);
                showPermissionPrompt();
            });
    } else {
        console.error('getUserMedia not supported');
        updateStatus('error', 'Browser Not Supported', 'Please use a modern browser with camera support');
    }
}

function showPermissionPrompt() {
    const container = document.getElementById('scannerContainer');
    container.innerHTML = `
        <div class="permission-prompt">
            <div class="permission-icon">
                <i data-lucide="camera-off" style="width: 48px; height: 48px;"></i>
            </div>
            <h5>Camera Permission Needed</h5>
            <p class="text-muted mb-3">Please allow camera access to scan QR codes</p>
            <button class="btn btn-primary" onclick="requestCameraPermission()">
                <i data-lucide="camera"></i> Allow Camera Access
            </button>
            <div class="mt-3">
                <small class="text-muted">
                    If permission was denied, please:
                    <br>1. Click the camera icon in your browser's address bar
                    <br>2. Select "Allow" for camera access
                    <br>3. Refresh this page
                </small>
            </div>
        </div>
    `;
    
    // Recreate icons after innerHTML change
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
}

function requestCameraPermission() {
    navigator.mediaDevices.getUserMedia({ video: true })
        .then(stream => {
            console.log('Camera permission granted');
            stream.getTracks().forEach(track => track.stop());
            location.reload(); // Reload to reinitialize
        })
        .catch(err => {
            console.error('Camera permission still denied:', err);
            showPermissionPrompt();
        });
}

function startScanning() {
    if (isScanning) return;
    
    console.log('Starting QR scan...');
    
    try {
        const config = {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0,
            disableFlip: false,
            videoConstraints: {
                facingMode: currentCameraId ? undefined : "environment"
            }
        };
        
        html5QrcodeScanner = new Html5Qrcode("qr-reader");
        
        html5QrcodeScanner.start(
            currentCameraId || { facingMode: "environment" },
            config,
            onScanSuccess,
            onScanFailure
        ).then(() => {
            isScanning = true;
            updateScanningUI(true);
            updateStatus('scanning', 'Scanning Active', 'Point camera at QR code');
            
            // Auto-stop after 2 minutes to save battery
            scanTimeout = setTimeout(() => {
                if (isScanning) {
                    stopScanning();
                    showMessage('info', 'Scan Timeout', 'Scanner stopped to save battery. Tap start to resume.');
                }
            }, 120000);
            
        }).catch(err => {
            console.error('Error starting scanner:', err);
            updateStatus('error', 'Scanner Error', 'Failed to start camera');
            isScanning = false;
        });
        
    } catch (error) {
        console.error('Scanner initialization error:', error);
        updateStatus('error', 'Scanner Error', 'Failed to initialize scanner');
    }
}

function stopScanning() {
    if (!isScanning) return;
    
    console.log('Stopping QR scan...');
    
    if (html5QrcodeScanner) {
        html5QrcodeScanner.stop().then(() => {
            console.log('Scanner stopped successfully');
        }).catch(err => {
            console.error('Error stopping scanner:', err);
        });
    }
    
    isScanning = false;
    updateScanningUI(false);
    updateStatus('ready', 'Scanner Stopped', 'Tap start to resume scanning');
    
    if (scanTimeout) {
        clearTimeout(scanTimeout);
        scanTimeout = null;
    }
}

function switchCamera() {
    if (availableCameras.length <= 1) return;
    
    // Find current camera index
    const currentIndex = availableCameras.findIndex(camera => camera.id === currentCameraId);
    const nextIndex = (currentIndex + 1) % availableCameras.length;
    currentCameraId = availableCameras[nextIndex].id;
    
    console.log('Switching to camera:', availableCameras[nextIndex].label);
    
    // Restart scanner with new camera
    if (isScanning) {
        stopScanning();
        setTimeout(() => {
            startScanning();
        }, 500);
    }
    
    updateCameraInfo(availableCameras);
    showMessage('info', 'Camera Switched', `Now using: ${availableCameras[nextIndex].label}`);
}

function onScanSuccess(decodedText, decodedResult) {
    console.log('QR Code scanned:', decodedText);
    
    // Stop scanning immediately
    stopScanning();
    
    // Vibrate if enabled
    if (localStorage.getItem('vibrateEnabled') !== 'false' && navigator.vibrate) {
        navigator.vibrate([200, 100, 200]);
    }
    
    // Show success feedback
    updateStatus('success', 'QR Code Detected!', 'Processing attendance...');
    
    // Play success sound
    playSuccessSound();
    
    // Process the QR code
    processQRCode(decodedText);
}

function onScanFailure(error) {
    // Ignore frequent scan failures (normal behavior)
    if (error.includes('No QR code found')) {
        return;
    }
    console.log('Scan error:', error);
}

function processQRCode(qrData) {
    console.log('Processing QR code:', qrData);
    
    // Extract token from QR data
    let token = null;
    
    try {
        // Check if it's a URL with token parameter
        if (qrData.includes('token=')) {
            const url = new URL(qrData);
            token = url.searchParams.get('token');
        } else {
            // Assume the QR data is the token itself
            token = qrData;
        }
        
        if (!token) {
            throw new Error('No token found in QR code');
        }
        
        // Submit attendance with token
        submitAttendance(token);
        
    } catch (error) {
        console.error('Error processing QR code:', error);
        updateStatus('error', 'Invalid QR Code', 'This QR code is not valid for attendance');
        
        // Allow scanning again after 3 seconds
        setTimeout(() => {
            updateStatus('ready', 'Ready to Scan', 'Tap start to try again');
        }, 3000);
    }
}

function submitAttendance(token) {
    console.log('Submitting attendance with token:', token);
    
    // Show loading state
    updateStatus('loading', 'Marking Attendance...', 'Please wait...');
    
    // Prepare form data
    const formData = new FormData();
    formData.append('token', token);
    formData.append('method', 'qr');
    
    // Submit to server
    fetch('process_attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Attendance response:', data);
        
        if (data.success) {
            updateStatus('success', 'Attendance Marked!', data.message || 'Your attendance has been recorded');
            showSuccessAnimation();
            
            // Show attendance details if available
            if (data.subject && data.teacher) {
                setTimeout(() => {
                    showAttendanceDetails(data);
                }, 2000);
            }
            
        } else {
            updateStatus('error', 'Attendance Failed', data.message || 'Failed to mark attendance');
        }
        
        // Allow new scan after 5 seconds
        setTimeout(() => {
            updateStatus('ready', 'Ready for Next Scan', 'Tap start to scan again');
        }, 5000);
        
    })
    .catch(error => {
        console.error('Attendance submission error:', error);
        updateStatus('error', 'Network Error', 'Please check your connection and try again');
        
        // Allow retry after 3 seconds
        setTimeout(() => {
            updateStatus('ready', 'Ready to Retry', 'Tap start to try again');
        }, 3000);
    });
}

function submitManualCode() {
    const input = document.getElementById('manualCodeInput');
    const code = input.value.trim();
    
    if (!code) {
        showMessage('warning', 'Empty Code', 'Please enter a code');
        return;
    }
    
    console.log('Submitting manual code:', code);
    input.value = '';
    
    // Process as if it was scanned
    processQRCode(code);
}

function updateStatus(type, title, message) {
    const statusDiv = document.getElementById('scanStatus');
    const iconMap = {
        'ready': 'camera',
        'scanning': 'scan',
        'success': 'check-circle',
        'error': 'x-circle',
        'loading': 'loader',
        'warning': 'alert-triangle'
    };
    
    const colorMap = {
        'ready': 'text-primary',
        'scanning': 'text-info',
        'success': 'text-success',
        'error': 'text-danger',
        'loading': 'text-warning',
        'warning': 'text-warning'
    };
    
    statusDiv.className = `scan-status ${colorMap[type] || 'text-muted'}`;
    statusDiv.innerHTML = `
        <div class="status-icon ${type === 'loading' ? 'rotating' : ''}">
            <i data-lucide="${iconMap[type] || 'info'}"></i>
        </div>
        <div class="status-content">
            <div class="status-title">${title}</div>
            <div class="status-message">${message}</div>
        </div>
    `;
    
    // Recreate icons
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
}

function updateScanningUI(scanning) {
    const startBtn = document.getElementById('startScanBtn');
    const stopBtn = document.getElementById('stopScanBtn');
    const switchBtn = document.getElementById('switchCameraBtn');
    
    if (startBtn) startBtn.style.display = scanning ? 'none' : 'block';
    if (stopBtn) stopBtn.style.display = scanning ? 'block' : 'none';
    if (switchBtn) switchBtn.disabled = scanning;
}

function updateCameraInfo(cameras) {
    const current = cameras.find(c => c.id === currentCameraId);
    const infoDiv = document.getElementById('cameraInfo');
    
    if (infoDiv && current) {
        infoDiv.innerHTML = `
            <small class="text-muted">
                <i data-lucide="camera" style="width: 14px; height: 14px;"></i>
                ${current.label || 'Camera ' + (cameras.indexOf(current) + 1)}
            </small>
        `;
        
        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }
    }
}

function showMessage(type, title, message) {
    // Create toast notification
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <strong>${title}</strong><br>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    // Add to toast container
    const container = document.getElementById('toastContainer');
    if (container) {
        container.appendChild(toast);
        
        // Show toast
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Remove after hiding
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }
}

function showSuccessAnimation() {
    const container = document.getElementById('scannerContainer');
    container.classList.add('success-flash');
    
    setTimeout(() => {
        container.classList.remove('success-flash');
    }, 1000);
}

function showAttendanceDetails(data) {
    const modal = document.getElementById('attendanceModal');
    if (modal) {
        document.getElementById('modalSubject').textContent = data.subject || 'N/A';
        document.getElementById('modalTeacher').textContent = data.teacher || 'N/A';
        document.getElementById('modalTime').textContent = new Date().toLocaleString();
        document.getElementById('modalStatus').textContent = data.status || 'Present';
        
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
}

function toggleFlash() {
    // Note: Flash control is limited in web browsers
    // This is a placeholder for future implementation
    const btn = document.getElementById('flashToggleBtn');
    const isOn = btn.classList.contains('active');
    
    if (isOn) {
        btn.classList.remove('active');
        btn.innerHTML = '<i data-lucide="zap-off"></i>';
    } else {
        btn.classList.add('active');
        btn.innerHTML = '<i data-lucide="zap"></i>';
    }
    
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
    
    showMessage('info', 'Flash', isOn ? 'Flash turned off' : 'Flash turned on');
}

function toggleVibration() {
    const enabled = localStorage.getItem('vibrateEnabled') !== 'false';
    localStorage.setItem('vibrateEnabled', !enabled);
    
    const btn = document.getElementById('vibrateToggleBtn');
    if (!enabled) {
        btn.classList.add('active');
        btn.innerHTML = '<i data-lucide="smartphone"></i>';
        showMessage('info', 'Vibration', 'Vibration enabled');
    } else {
        btn.classList.remove('active');
        btn.innerHTML = '<i data-lucide="phone-off"></i>';
        showMessage('info', 'Vibration', 'Vibration disabled');
    }
    
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
}

function playSuccessSound() {
    // Create and play a success sound
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
    oscillator.frequency.setValueAtTime(1000, audioContext.currentTime + 0.1);
    
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
    
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.3);
}

function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

// Theme toggle function
window.toggleTheme = function() {
    const isDark = document.body.classList.toggle("dark-mode");
    localStorage.setItem("theme", isDark ? "dark" : "light");
};

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (isScanning) {
        stopScanning();
    }
});

// Handle page visibility change
document.addEventListener('visibilitychange', function() {
    if (document.hidden && isScanning) {
        // Page is hidden, stop scanning to save battery
        stopScanning();
        console.log('Page hidden, scanner stopped');
    }
});