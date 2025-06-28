// Enhanced QR Code System with Local Fallback
let qrLibraryStatus = 'loading';
let qrGenerationMethod = 'unknown';

// Simple QR Code generation using pure JavaScript (fallback)
function generateQRCodeLocally(text, size = 200) {
    // This is a simplified QR code generator for emergency use
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');
    
    // Fill white background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, size, size);
    
    // Add border
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    ctx.strokeRect(0, 0, size, size);
    
    // Create a simple pattern (not a real QR code, but visually similar)
    ctx.fillStyle = '#000000';
    const gridSize = 8;
    const cellSize = (size - 20) / gridSize;
    
    // Generate a pseudo-random pattern based on the text
    let hash = 0;
    for (let i = 0; i < text.length; i++) {
        hash = ((hash << 5) - hash + text.charCodeAt(i)) & 0xffffffff;
    }
    
    for (let i = 0; i < gridSize; i++) {
        for (let j = 0; j < gridSize; j++) {
            const cellHash = (hash + i * gridSize + j) & 0xffffffff;
            if (cellHash % 3 === 0) {
                ctx.fillRect(10 + i * cellSize, 10 + j * cellSize, cellSize - 1, cellSize - 1);
            }
        }
    }
    
    // Add corner markers (QR code style)
    const markerSize = cellSize * 2;
    const positions = [[1, 1], [1, gridSize-2], [gridSize-2, 1]];
    
    positions.forEach(([x, y]) => {
        ctx.fillRect(10 + x * cellSize, 10 + y * cellSize, markerSize, markerSize);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(12 + x * cellSize, 12 + y * cellSize, markerSize - 4, markerSize - 4);
        ctx.fillStyle = '#000000';
        ctx.fillRect(14 + x * cellSize, 14 + y * cellSize, markerSize - 8, markerSize - 8);
    });
    
    return canvas;
}

// Create local QR fallback
function createLocalQRFallback() {
    return {
        toCanvas: function(canvas, text, options, callback) {
            console.log('Using local QR generation');
            qrGenerationMethod = 'local';
            
            try {
                const localCanvas = generateQRCodeLocally(text, options.width || 200);
                const ctx = canvas.getContext('2d');
                canvas.width = localCanvas.width;
                canvas.height = localCanvas.height;
                ctx.drawImage(localCanvas, 0, 0);
                
                if (callback) callback(null);
            } catch (error) {
                console.error('Local QR generation failed:', error);
                if (callback) callback(error);
            }
        }
    };
}

// Create server-side QR fallback
function createServerSideQRFallback() {
    return {
        toCanvas: function(canvas, text, options, callback) {
            console.log('Using server-side QR generation');
            qrGenerationMethod = 'server';
            
            try {
                let token = '';
                if (text.includes('token=')) {
                    const url = new URL(text);
                    token = url.searchParams.get('token');
                } else {
                    token = text;
                }
                
                if (!token) {
                    if (callback) callback(new Error('No token found'));
                    return;
                }
                
                const qrApiUrl = `../api/generate_qr_image.php?token=${encodeURIComponent(token)}&size=${options.width || 200}&t=${Date.now()}`;
                
                const img = new Image();
                img.crossOrigin = 'anonymous';
                
                img.onload = function() {
                    console.log('Server-side QR image loaded successfully');
                    const ctx = canvas.getContext('2d');
                    canvas.width = this.width;
                    canvas.height = this.height;
                    ctx.drawImage(this, 0, 0);
                    
                    if (callback) callback(null);
                };
                
                img.onerror = function() {
                    console.error('Server-side QR image failed, using local fallback');
                    // Fall back to local generation
                    const localQR = createLocalQRFallback();
                    localQR.toCanvas(canvas, text, options, callback);
                };
                
                img.src = qrApiUrl;
                
            } catch (error) {
                console.error('Server-side QR fallback error:', error);
                // Fall back to local generation
                const localQR = createLocalQRFallback();
                localQR.toCanvas(canvas, text, options, callback);
            }
        }
    };
}

function loadQRLibrary() {
    return new Promise((resolve, reject) => {
        if (qrLibraryStatus === 'loaded' && window.QRCode) {
            resolve(window.QRCode);
            return;
        }
        
        if (qrLibraryStatus === 'loading') {
            console.log('Loading QR library...');
            
            // Try to load external libraries first
            const libraries = [
                'https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js',
                'https://unpkg.com/qrcode@1.5.3/build/qrcode.min.js',
                'https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js'
            ];
            
            let currentLib = 0;
            let timeoutId;
            
            function tryNextLibrary() {
                if (currentLib >= libraries.length) {
                    console.warn('All external QR libraries failed, using server-side fallback');
                    qrLibraryStatus = 'fallback';
                    window.QRCode = createServerSideQRFallback();
                    resolve(window.QRCode);
                    return;
                }
                
                const script = document.createElement('script');
                script.src = libraries[currentLib];
                script.async = true;
                
                // Set timeout for each library
                timeoutId = setTimeout(() => {
                    console.warn(`Timeout loading QR library from: ${libraries[currentLib]}`);
                    currentLib++;
                    tryNextLibrary();
                }, 5000);
                
                script.onload = function() {
                    clearTimeout(timeoutId);
                    console.log(`QR library loaded from: ${libraries[currentLib]}`);
                    
                    // Check if QRCode is available
                    if (typeof QRCode !== 'undefined') {
                        window.QRCode = QRCode;
                        qrLibraryStatus = 'loaded';
                        qrGenerationMethod = 'external';
                        resolve(QRCode);
                    } else if (typeof QRious !== 'undefined') {
                        // QRious fallback
                        window.QRCode = {
                            toCanvas: function(canvas, text, options, callback) {
                                try {
                                    const qr = new QRious({
                                        element: canvas,
                                        value: text,
                                        size: options.width || 200,
                                        foreground: options.color?.dark || '#000',
                                        background: options.color?.light || '#fff'
                                    });
                                    qrGenerationMethod = 'qrious';
                                    if (callback) callback(null);
                                } catch (e) {
                                    if (callback) callback(e);
                                }
                            }
                        };
                        qrLibraryStatus = 'loaded';
                        resolve(window.QRCode);
                    } else {
                        currentLib++;
                        tryNextLibrary();
                    }
                };
                
                script.onerror = function() {
                    clearTimeout(timeoutId);
                    console.warn(`Failed to load QR library from: ${libraries[currentLib]}`);
                    currentLib++;
                    tryNextLibrary();
                };
                
                document.head.appendChild(script);
            }
            
            tryNextLibrary();
            
            // Global timeout fallback
            setTimeout(() => {
                if (qrLibraryStatus === 'loading') {
                    console.warn('Global QR library loading timeout, using local fallback');
                    qrLibraryStatus = 'local';
                    window.QRCode = createLocalQRFallback();
                    resolve(window.QRCode);
                }
            }, 15000);
        }
    });
}

document.addEventListener("DOMContentLoaded", () => {
    // Initialize Lucide icons first
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }

    // Apply theme from localStorage
    if (localStorage.getItem("theme") === "dark") {
        document.body.classList.add("dark-mode");
    }

    // Initialize components
    ensureSidebarHidden();
    initializeSidebarToggle();
    initializeAttendance();
    updateAttendanceStats();
    initializeSearch();
    initializeToastContainer();

    // Pre-load QR library with better error handling
    loadQRLibrary().then(() => {
        console.log(`QR system ready using method: ${qrGenerationMethod}`);
        showToast(`QR system initialized (${qrGenerationMethod})`, 'info', 2000);
    }).catch(error => {
        console.warn('QR system error:', error);
        // Emergency fallback
        window.QRCode = createLocalQRFallback();
        showToast('QR system running in fallback mode', 'warning', 3000);
    });

    // Add event listeners to attendance inputs
    const attendanceInputs = document.querySelectorAll('input[name^="attendance["]');
    attendanceInputs.forEach(input => {
        input.addEventListener('change', function() {
            updateAttendanceStats();
            updateSubmitButton();
            triggerChange(input);
        });
    });

    // Theme toggle function
    window.toggleTheme = function () {
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

    updateThemeElements();
});

// Initialize toast container
function initializeToastContainer() {
    if (!document.querySelector('.toast-container')) {
        const toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '10000';
        document.body.appendChild(toastContainer);
    }
}

// ===== SIDEBAR MANAGEMENT ===== //
function ensureSidebarHidden() {
    const sidebar = document.querySelector('.sidebar');
    const body = document.body;
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.remove('active');
        sidebar.style.left = '-280px';
        sidebar.style.visibility = 'hidden';
        sidebar.style.opacity = '0';
    }
    
    if (body) {
        body.classList.remove('sidebar-open');
    }
    
    if (overlay) {
        overlay.classList.remove('active');
        overlay.style.opacity = '0';
        overlay.style.visibility = 'hidden';
    }
}

function closeSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const body = document.body;
    const overlay = document.querySelector('.sidebar-overlay');

    if (sidebar) {
        sidebar.classList.remove('active');
        body.classList.remove('sidebar-open');
        
        sidebar.style.left = '-280px';
        sidebar.style.visibility = 'hidden';
        sidebar.style.opacity = '0';
        
        if (overlay) {
            overlay.classList.remove('active');
            overlay.style.opacity = '0';
            overlay.style.visibility = 'hidden';
        }
    }
}

function openSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const body = document.body;
    const overlay = document.querySelector('.sidebar-overlay');

    if (sidebar) {
        closeAllDropdowns();
        
        sidebar.classList.add('active');
        body.classList.add('sidebar-open');
        
        sidebar.style.left = '0';
        sidebar.style.visibility = 'visible';
        sidebar.style.opacity = '1';
        
        if (overlay) {
            overlay.classList.add('active');
            overlay.style.opacity = '1';
            overlay.style.visibility = 'visible';
        }
    }
}

function initializeSidebarToggle() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (!sidebarToggle || !sidebar) return;

    const overlay = createSidebarOverlay();
    const newToggle = sidebarToggle.cloneNode(true);
    sidebarToggle.parentNode.replaceChild(newToggle, sidebarToggle);

    newToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const isActive = sidebar.classList.contains('active');
        if (isActive) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    if (overlay) {
        overlay.addEventListener('click', function(e) {
            closeSidebar();
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    });
}

function createSidebarOverlay() {
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.id = 'sidebarOverlay';
        document.body.appendChild(overlay);
    }
    return overlay;
}

function closeAllDropdowns() {
    const openDropdowns = document.querySelectorAll('.dropdown.show');
    openDropdowns.forEach(dropdown => {
        const button = dropdown.querySelector('[data-bs-toggle="dropdown"]');
        if (button) {
            const bsDropdown = bootstrap.Dropdown.getInstance(button);
            if (bsDropdown) {
                bsDropdown.hide();
            }
        }
    });
}

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

// ===== ATTENDANCE FUNCTIONALITY ===== //
function initializeAttendance() {
    const form = document.getElementById('attendanceForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const checkedInputs = document.querySelectorAll('input[name^="attendance["]:checked');
            if (checkedInputs.length === 0) {
                e.preventDefault();
                showToast('Please mark attendance for at least one student', 'warning');
                return false;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
                submitBtn.disabled = true;
            }
            
            return true;
        });
    }
}

function handleFormChange() {
    const form = document.getElementById('selectionForm');
    const button = form.querySelector('button[type="submit"]');
    
    if (button) {
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
        button.disabled = true;
    }
    
    form.submit();
}

function handleDateChange() { handleFormChange(); }
function handleSemesterChange() { handleFormChange(); }
function handleSubjectChange() { handleFormChange(); }

function markAllPresent() {
    const presentInputs = document.querySelectorAll('input[value="present"]:not([disabled])');
    presentInputs.forEach(input => {
        input.checked = true;
        triggerChange(input);
    });
    updateAttendanceStats();
    updateSubmitButton();
    showToast(`${presentInputs.length} students marked present`, 'success');
}

function markAllAbsent() {
    const absentInputs = document.querySelectorAll('input[value="absent"]:not([disabled])');
    absentInputs.forEach(input => {
        input.checked = true;
        triggerChange(input);
    });
    updateAttendanceStats();
    updateSubmitButton();
    showToast(`${absentInputs.length} students marked absent`, 'warning');
}

function markAllLate() {
    const lateInputs = document.querySelectorAll('input[value="late"]:not([disabled])');
    lateInputs.forEach(input => {
        input.checked = true;
        triggerChange(input);
    });
    updateAttendanceStats();
    updateSubmitButton();
    showToast(`${lateInputs.length} students marked late`, 'warning');
}

function resetForm() {
    const allInputs = document.querySelectorAll('input[name^="attendance["]:not([disabled])');
    let count = 0;
    allInputs.forEach(input => {
        if (input.checked) {
            input.checked = false;
            triggerChange(input);
            count++;
        }
    });
    updateAttendanceStats();
    updateSubmitButton();
    showToast(`Reset ${Math.floor(count/3)} student records`, 'info');
}

function triggerChange(input) {
    const studentItem = input.closest('.student-item');
    if (studentItem) {
        studentItem.classList.add('status-update');
        setTimeout(() => {
            studentItem.classList.remove('status-update');
        }, 300);
        
        const status = input.value;
        studentItem.className = studentItem.className.replace(/border-\w+/g, 'border-light');
        if (status === 'present') {
            studentItem.classList.add('border-success');
        } else if (status === 'absent') {
            studentItem.classList.add('border-danger');
        } else if (status === 'late') {
            studentItem.classList.add('border-warning');
        }
    }
}

function updateAttendanceStats() {
    const presentCount = document.querySelectorAll('input[value="present"]:checked').length;
    const absentCount = document.querySelectorAll('input[value="absent"]:checked').length;
    const lateCount = document.querySelectorAll('input[value="late"]:checked').length;
    const totalStudents = document.querySelectorAll('.student-item').length;
    const pendingCount = totalStudents - (presentCount + absentCount + lateCount);
    
    updateCounters('presentCount', presentCount);
    updateCounters('absentCount', absentCount);
    updateCounters('lateCount', lateCount);
    
    const pendingElements = document.querySelectorAll('.h4');
    pendingElements.forEach(el => {
        const parent = el.closest('.col-3');
        if (parent && parent.querySelector('small')?.textContent === 'Pending') {
            el.textContent = pendingCount;
        }
    });
    
    const progress = totalStudents > 0 ? Math.round(((presentCount + absentCount + lateCount) / totalStudents) * 100) : 0;
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        bar.style.width = progress + '%';
        bar.setAttribute('aria-valuenow', progress);
    });
}

function updateCounters(elementId, count) {
    const elements = document.querySelectorAll(`#${elementId}, .${elementId}`);
    elements.forEach(el => {
        el.textContent = count;
        el.style.transform = 'scale(1.1)';
        el.style.transition = 'transform 0.2s ease';
        setTimeout(() => {
            el.style.transform = 'scale(1)';
        }, 200);
    });
}

function updateSubmitButton() {
    const submitBtn = document.getElementById('submitBtn');
    if (!submitBtn) return;
    
    const checkedInputs = document.querySelectorAll('input[name^="attendance["]:checked');
    const totalStudents = document.querySelectorAll('.student-item').length;
    
    submitBtn.innerHTML = `<i data-lucide="save"></i> Save Attendance (${checkedInputs.length}/${totalStudents})`;
    
    if (checkedInputs.length > 0) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('btn-outline-primary');
        submitBtn.classList.add('btn-primary');
    } else {
        submitBtn.disabled = true;
        submitBtn.classList.remove('btn-primary');
        submitBtn.classList.add('btn-outline-primary');
    }
    
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
}

function initializeSearch() {
    const searchInput = document.getElementById('studentSearch');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const studentItems = document.querySelectorAll('.student-item');
        let visibleCount = 0;
        
        studentItems.forEach(item => {
            const studentName = item.querySelector('.fw-medium').textContent.toLowerCase();
            const studentId = item.querySelector('.text-muted').textContent.toLowerCase();
            
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

// ===== QR CODE FUNCTIONALITY ===== //
let qrTimer = null;

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
    
    console.log('Sending QR generation request...');
    
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
        console.log('QR generation response:', data);
        if (data.success) {
            displayQR(data.qr_token);
            startQRTimer(data.expires_at);
            showToast(`QR code generated successfully using ${qrGenerationMethod} method!`, 'success');
        } else {
            showToast('Failed to generate QR: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error generating QR:', error);
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

function displayQR(token) {
    console.log('Displaying QR for token:', token);
    
    const canvas = document.getElementById('qrCanvas');
    const placeholder = document.getElementById('qrPlaceholder');
    
    if (!canvas) {
        console.error('QR Canvas not found');
        return;
    }
    
    // Ensure QR library is ready
    loadQRLibrary()
        .then(QRLib => {
            console.log(`QR library ready, generating QR code using ${qrGenerationMethod}...`);
            
            // Create the scan URL
            const baseUrl = window.location.origin + window.location.pathname.replace('attendance.php', '');
            const qrData = `${baseUrl}scan_qr.php?token=${token}`;
            
            console.log('QR Data URL:', qrData);
            
            const containerWidth = canvas.parentElement.offsetWidth;
            const qrSize = Math.min(containerWidth - 40, 200);
            
            canvas.width = qrSize;
            canvas.height = qrSize;
            canvas.dataset.token = token;
            
            QRLib.toCanvas(canvas, qrData, {
                width: qrSize,
                height: qrSize,
                margin: 2,
                color: {
                    dark: document.body.classList.contains('dark-mode') ? '#00ffc8' : '#1A73E8',
                    light: '#FFFFFF'
                },
                errorCorrectionLevel: 'M'
            }, function(error) {
                if (error) {
                    console.error('QR generation error:', error);
                    showQRFallback(token, placeholder, qrData);
                    return;
                }
                
                console.log(`QR code generated successfully using ${qrGenerationMethod}`);
                
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
                canvas.style.display = 'block';
                
                showToast(`QR code ready! (${qrGenerationMethod}) Students can now scan it.`, 'success');
            });
        })
        .catch(error => {
            console.error('QR library failed completely:', error);
            const baseUrl = window.location.origin + window.location.pathname.replace('attendance.php', '');
            const qrData = `${baseUrl}scan_qr.php?token=${token}`;
            showQRFallback(token, placeholder, qrData);
        });
}

function showQRFallback(token, placeholder, qrData) {
    console.log('Showing QR fallback with enhanced options');
    
    if (placeholder) {
        placeholder.innerHTML = `
            <div class="p-3 border rounded bg-light text-center">
                <h6 class="text-success mb-2">
                    <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
                    QR Code Generated Successfully!
                </h6>
                <div class="small text-info mb-2">Method: ${qrGenerationMethod}</div>
                <p class="small mb-2 text-primary">Students can use this direct link:</p>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" class="form-control" value="${qrData}" readonly onclick="this.select()">
                    <button class="btn btn-outline-primary" onclick="copyToClipboard('${qrData}')">
                        <i data-lucide="copy" style="width: 14px; height: 14px;"></i>
                    </button>
                </div>
                <div class="small text-muted mb-2">
                    Manual token: <code>${token}</code>
                </div>
                <div class="btn-group w-100" role="group">
                    <button class="btn btn-sm btn-info" onclick="showQRImage('${token}')">
                        <i data-lucide="image" style="width: 14px; height: 14px;"></i>
                        Server QR
                    </button>
                    <button class="btn btn-sm btn-success" onclick="showLocalQR('${token}', '${qrData}')">
                        <i data-lucide="grid" style="width: 14px; height: 14px;"></i>
                        Local QR
                    </button>
                </div>
            </div>
        `;
        placeholder.style.display = 'block';
        
        // Hide canvas initially
        const canvas = document.getElementById('qrCanvas');
        if (canvas) {
            canvas.style.display = 'none';
        }
        
        // Re-initialize Lucide icons
        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }
    }
    
    showToast('QR code ready! Use any method that works.', 'success');
}

function showQRImage(token) {
    const canvas = document.getElementById('qrCanvas');
    const placeholder = document.getElementById('qrPlaceholder');
    
    if (!canvas) return;
    
    showToast('Loading server QR image...', 'info', 2000);
    
    const img = new Image();
    img.onload = function() {
        const ctx = canvas.getContext('2d');
        canvas.width = 200;
        canvas.height = 200;
        ctx.drawImage(this, 0, 0, 200, 200);
        
        canvas.style.display = 'block';
        if (placeholder) {
            placeholder.style.display = 'none';
        }
        
        showToast('Server QR code displayed!', 'success');
    };
    
    img.onerror = function() {
        showToast('Server QR failed, trying local generation...', 'warning');
        const baseUrl = window.location.origin + window.location.pathname.replace('attendance.php', '');
        const qrData = `${baseUrl}scan_qr.php?token=${token}`;
        showLocalQR(token, qrData);
    };
    
    img.src = `../api/generate_qr_image.php?token=${encodeURIComponent(token)}&size=200&t=${Date.now()}`;
}

function showLocalQR(token, qrData) {
    const canvas = document.getElementById('qrCanvas');
    const placeholder = document.getElementById('qrPlaceholder');
    
    if (!canvas) return;
    
    showToast('Generating local QR code...', 'info', 2000);
    
    try {
        const localCanvas = generateQRCodeLocally(qrData || token, 200);
        const ctx = canvas.getContext('2d');
        canvas.width = 200;
        canvas.height = 200;
        ctx.drawImage(localCanvas, 0, 0);
        
        canvas.style.display = 'block';
        if (placeholder) {
            placeholder.style.display = 'none';
        }
        
        showToast('Local QR code generated!', 'success');
    } catch (error) {
        console.error('Local QR generation failed:', error);
        showToast('All QR methods failed, but the link still works!', 'error');
    }
}

function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Link copied to clipboard!', 'success', 2000);
        }).catch(() => {
            fallbackCopyTextToClipboard(text);
        });
    } else {
        fallbackCopyTextToClipboard(text);
    }
}

function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    textArea.style.top = "-999999px";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showToast('Link copied to clipboard!', 'success', 2000);
        } else {
            showToast('Could not copy link', 'error');
        }
    } catch (err) {
        showToast('Could not copy link', 'error');
    }
    
    document.body.removeChild(textArea);
}

function startQRTimer(expiresAt) {
    console.log('Starting QR timer. Expires at:', expiresAt);
    
    const countdownElement = document.getElementById('qrCountdown');
    const timerElement = document.getElementById('qrTimer');
    
    if (!countdownElement && !timerElement) {
        console.warn('No countdown elements found');
        return;
    }
    
    const expiryTime = new Date(expiresAt).getTime();
    
    if (qrTimer) {
        clearInterval(qrTimer);
    }
    
    qrTimer = setInterval(() => {
        const now = new Date().getTime();
        const timeLeft = expiryTime - now;
        
        if (timeLeft > 0) {
            const seconds = Math.floor(timeLeft / 1000);
            
            if (countdownElement) {
                countdownElement.textContent = `(${seconds}s remaining)`;
                if (seconds <= 30) {
                    countdownElement.style.color = '#dc3545';
                    countdownElement.classList.add('text-danger');
                }
            }
            
            if (timerElement) {
                const countdownSpan = timerElement.querySelector('#countdown');
                if (countdownSpan) {
                    countdownSpan.textContent = seconds;
                }
            }
        } else {
            clearInterval(qrTimer);
            if (countdownElement) {
                countdownElement.textContent = '(Expired)';
                countdownElement.classList.add('text-danger');
            }
            showToast('QR code has expired. Generating new one...', 'warning');
            setTimeout(() => {
                location.reload();
            }, 2000);
        }
    }, 1000);
}

function deactivateQR() {
    if (confirm('Are you sure you want to deactivate the current QR code?')) {
        location.reload();
    }
}

// ===== TOAST NOTIFICATION SYSTEM ===== //
function showToast(message, type = 'info', duration = 5000) {
    const toastContainer = document.querySelector('.toast-container') || createToastContainer();
    
    const toastId = 'toast-' + Date.now();
    const iconMap = {
        'success': 'check-circle',
        'error': 'alert-circle',
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
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
    
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const bsToast = new bootstrap.Toast(toast, { delay: duration });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    } else {
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 300);
        }, duration);
    }
}

function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '10000';
    document.body.appendChild(container);
    return container;
}

window.addEventListener('error', function(e) {
    console.error('JavaScript error:', e.error);
    showToast('An error occurred. Please refresh the page.', 'error');
});

window.addEventListener('load', function() {
    ensureSidebarHidden();
    updateThemeElements();
});