document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
    
    // Apply theme from localStorage
    if (localStorage.getItem("theme") === "dark") {
        document.body.classList.add("dark-mode");
    }
    
    // Initialize search and filter functionality
    initializeSearch();
    initializeFilters();
    initializeFileUpload();
    initializeTabs();
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize drag and drop for upload zone
    initializeDragAndDrop();
    
    // Auto-refresh assignment status
    setInterval(updateAssignmentTimeRemaining, 60000); // Update every minute
});

// Initialize tabs functionality
function initializeTabs() {
    const triggerTabList = [].slice.call(document.querySelectorAll('#assignmentTabs button'));
    triggerTabList.forEach(function (triggerEl) {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            tabTrigger.show();
            
            // Track active tab for analytics
            const tabId = event.target.getAttribute('data-bs-target');
            console.log('Tab switched to:', tabId);
        });
    });
}

// Search functionality
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        // Add search icon click functionality
        const searchIcon = document.querySelector('.search-icon');
        if (searchIcon) {
            searchIcon.addEventListener('click', function() {
                searchInput.focus();
            });
        }
        
        // Debounced search
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const searchTerm = this.value;
            
            searchTimeout = setTimeout(() => {
                filterAssignments(searchTerm.toLowerCase());
                
                // Show/hide empty states
                updateEmptyStates();
            }, 300);
        });
        
        // Clear search on escape
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                filterAssignments('');
                updateEmptyStates();
            }
        });
    }
}

// Filter functionality
function initializeFilters() {
    const subjectFilter = document.getElementById('subjectFilter');
    if (subjectFilter) {
        subjectFilter.addEventListener('change', function() {
            const selectedSubject = this.value;
            filterAssignmentsBySubject(selectedSubject);
            updateEmptyStates();
            
            // Update filter badge
            updateFilterBadge(selectedSubject);
        });
    }
}

// Filter assignments by search term
function filterAssignments(searchTerm) {
    const assignmentCards = document.querySelectorAll('.assignment-card');
    let visibleCount = 0;
    
    assignmentCards.forEach(card => {
        const title = card.querySelector('.assignment-title')?.textContent.toLowerCase() || '';
        const subject = card.querySelector('.assignment-subject')?.textContent.toLowerCase() || '';
        const description = card.querySelector('.assignment-description')?.textContent.toLowerCase() || '';
        const teacher = card.querySelector('.assignment-teacher')?.textContent.toLowerCase() || '';
        
        const matches = !searchTerm || 
                       title.includes(searchTerm) || 
                       subject.includes(searchTerm) || 
                       description.includes(searchTerm) ||
                       teacher.includes(searchTerm);
        
        if (matches) {
            card.style.display = 'block';
            card.style.animation = 'fadeIn 0.3s ease';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Update search results count
    updateSearchResultsCount(searchTerm, visibleCount);
}

// Filter assignments by subject
function filterAssignmentsBySubject(selectedSubject) {
    const assignmentCards = document.querySelectorAll('.assignment-card');
    let visibleCount = 0;
    
    assignmentCards.forEach(card => {
        const subjectCode = card.querySelector('.subject-code')?.textContent || '';
        
        if (!selectedSubject || subjectCode === selectedSubject) {
            card.style.display = 'block';
            card.style.animation = 'fadeIn 0.3s ease';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    return visibleCount;
}

// Update search results count
function updateSearchResultsCount(searchTerm, count) {
    let resultsIndicator = document.querySelector('.search-results-indicator');
    
    if (searchTerm && count >= 0) {
        if (!resultsIndicator) {
            resultsIndicator = document.createElement('div');
            resultsIndicator.className = 'search-results-indicator text-muted mt-2';
            document.querySelector('.assignments-header').appendChild(resultsIndicator);
        }
        
        resultsIndicator.innerHTML = `
            <small>
                <i data-lucide="search" style="width: 14px; height: 14px;"></i>
                Found ${count} assignment${count !== 1 ? 's' : ''} for "${searchTerm}"
                ${count === 0 ? '<button class="btn btn-link btn-sm p-0 ms-2" onclick="clearSearch()">Clear search</button>' : ''}
            </small>
        `;
        
        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }
    } else if (resultsIndicator) {
        resultsIndicator.remove();
    }
}

// Clear search
function clearSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.value = '';
        filterAssignments('');
        updateEmptyStates();
    }
}

// Update filter badge
function updateFilterBadge(selectedSubject) {
    let filterBadge = document.querySelector('.filter-badge');
    
    if (selectedSubject) {
        if (!filterBadge) {
            filterBadge = document.createElement('span');
            filterBadge.className = 'filter-badge badge bg-secondary ms-2';
            document.querySelector('.header-actions').appendChild(filterBadge);
        }
        
        filterBadge.innerHTML = `
            ${selectedSubject}
            <button class="btn-close btn-close-white ms-1" onclick="clearSubjectFilter()" style="font-size: 0.8em;"></button>
        `;
    } else if (filterBadge) {
        filterBadge.remove();
    }
}

// Clear subject filter
function clearSubjectFilter() {
    const subjectFilter = document.getElementById('subjectFilter');
    if (subjectFilter) {
        subjectFilter.value = '';
        filterAssignmentsBySubject('');
        updateEmptyStates();
        updateFilterBadge('');
    }
}

// Update empty states
function updateEmptyStates() {
    const tabs = ['upcoming', 'past-due', 'completed'];
    
    tabs.forEach(tabId => {
        const tabPane = document.getElementById(tabId);
        if (!tabPane) return;
        
        const visibleCards = tabPane.querySelectorAll('.assignment-card[style*="block"], .assignment-card:not([style*="none"])');
        const emptyState = tabPane.querySelector('.empty-state');
        const grid = tabPane.querySelector('.assignments-grid');
        
        if (visibleCards.length === 0 && grid) {
            if (!emptyState) {
                const emptyStateEl = createEmptyState(tabId);
                grid.style.display = 'none';
                tabPane.appendChild(emptyStateEl);
            }
        } else if (emptyState && visibleCards.length > 0) {
            emptyState.remove();
            if (grid) grid.style.display = 'grid';
        }
    });
}

// Create empty state element
function createEmptyState(tabId) {
    const emptyState = document.createElement('div');
    emptyState.className = 'empty-state';
    
    const configs = {
        'upcoming': {
            icon: 'search',
            title: 'No matching assignments',
            message: 'Try adjusting your search or filter criteria.'
        },
        'past-due': {
            icon: 'search',
            title: 'No matching assignments',
            message: 'Try adjusting your search or filter criteria.'
        },
        'completed': {
            icon: 'search',
            title: 'No matching assignments',
            message: 'Try adjusting your search or filter criteria.'
        }
    };
    
    const config = configs[tabId] || configs['upcoming'];
    
    emptyState.innerHTML = `
        <i data-lucide="${config.icon}" class="empty-icon"></i>
        <h4>${config.title}</h4>
        <p>${config.message}</p>
    `;
    
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
    
    return emptyState;
}

// Open submission modal
function openSubmissionModal(assignment) {
    console.log('Opening submission modal for:', assignment);
    
    // Populate modal with assignment data
    document.getElementById('assignmentId').value = assignment.id;
    document.getElementById('modalAssignmentTitle').textContent = assignment.title;
    document.getElementById('modalSubject').textContent = assignment.subject;
    document.getElementById('modalDueDate').textContent = 'Due: ' + formatDate(assignment.due_date);
    document.getElementById('modalPoints').textContent = assignment.points + ' points';
    document.getElementById('modalDescription').textContent = assignment.description;
    
    // Set accepted file formats
    const acceptedFormats = assignment.file_types ? assignment.file_types.join(', ') : '.pdf, .doc, .docx, .txt, .jpg, .png, .zip';
    document.getElementById('acceptedFormats').textContent = acceptedFormats;
    document.getElementById('maxFileSize').textContent = assignment.max_size || '10MB';
    
    // Update file input accept attribute
    const fileInput = document.getElementById('fileUpload');
    if (assignment.file_types) {
        fileInput.setAttribute('accept', assignment.file_types.join(','));
    }
    
    // Clear previous form data
    document.getElementById('submissionForm').reset();
    document.getElementById('fileList').innerHTML = '';
    
    // Reset checkboxes
    const checkboxes = document.querySelectorAll('.submission-checklist input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = false);
    
    // Clear selected files
    if (window.selectedFiles) {
        window.selectedFiles = [];
    }
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('submissionModal'));
    modal.show();
    
    // Re-initialize icons after modal content change
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
    
    // Focus on textarea
    setTimeout(() => {
        document.getElementById('submissionText').focus();
    }, 500);
}

// View assignment details
function viewAssignment(assignmentId) {
    console.log('Viewing assignment:', assignmentId);
    
    // Create assignment details modal
    const modal = createAssignmentDetailsModal(assignmentId);
    document.body.appendChild(modal);
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Remove modal after hiding
    modal.addEventListener('hidden.bs.modal', function() {
        modal.remove();
    });
}

// Create assignment details modal
function createAssignmentDetailsModal(assignmentId) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="eye"></i>
                        Assignment Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-4">
                        <i data-lucide="loader" style="width: 32px; height: 32px;" class="text-muted rotating"></i>
                        <p class="text-muted mt-2">Loading assignment details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    `;
    
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
    
    // Simulate loading assignment details
    setTimeout(() => {
        const modalBody = modal.querySelector('.modal-body');
        modalBody.innerHTML = `
            <div class="assignment-info-card">
                <h6>Assignment #${assignmentId}</h6>
                <p class="text-muted">This feature will show detailed assignment information, requirements, and submission guidelines.</p>
            </div>
            <div class="alert alert-info">
                <i data-lucide="info" class="me-2"></i>
                Detailed assignment view is coming soon!
            </div>
        `;
        
        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }
    }, 1000);
    
    return modal;
}

// View submission details
function viewSubmission(assignmentId) {
    console.log('Viewing submission:', assignmentId);
    
    // Create submission details modal
    const modal = createSubmissionDetailsModal(assignmentId);
    document.body.appendChild(modal);
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Remove modal after hiding
    modal.addEventListener('hidden.bs.modal', function() {
        modal.remove();
    });
}

// Create submission details modal
function createSubmissionDetailsModal(assignmentId) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="file-text"></i>
                        Submission Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-4">
                        <i data-lucide="loader" style="width: 32px; height: 32px;" class="text-muted rotating"></i>
                        <p class="text-muted mt-2">Loading submission details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    `;
    
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
    
    // Simulate loading submission details
    setTimeout(() => {
        const modalBody = modal.querySelector('.modal-body');
        modalBody.innerHTML = `
            <div class="submission-info-card">
                <h6>Submission #${assignmentId}</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <strong>Submitted:</strong> Dec 15, 2024 2:30 PM
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong> <span class="badge bg-success">Graded</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Score:</strong> 85/100
                    </div>
                    <div class="col-md-6">
                        <strong>Grade:</strong> <span class="badge bg-info">B+</span>
                    </div>
                </div>
                
                <div class="feedback-section">
                    <h6><i data-lucide="message-circle"></i> Teacher Feedback</h6>
                    <p class="feedback-text">Good work overall! Your solution demonstrates a solid understanding of the concepts. Consider adding more comments to your code for better readability.</p>
                </div>
                
                <div class="mt-3">
                    <h6><i data-lucide="paperclip"></i> Submitted Files</h6>
                    <div class="file-item">
                        <div class="file-info">
                            <i data-lucide="file" style="width: 18px; height: 18px;"></i>
                            <div>
                                <div class="file-name">assignment_solution.pdf</div>
                                <div class="file-size">2.5 MB</div>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-primary">
                            <i data-lucide="download" style="width: 14px; height: 14px;"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }
    }, 1000);
    
    return modal;
}

// File upload functionality
function initializeFileUpload() {
    const fileInput = document.getElementById('fileUpload');
    const fileList = document.getElementById('fileList');
    window.selectedFiles = [];
    
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            addFiles(files);
        });
    }
    
    function addFiles(files) {
        files.forEach(file => {
            // Check file size (10MB limit)
            if (file.size > 10 * 1024 * 1024) {
                showNotification('error', 'File Too Large', `${file.name} exceeds the 10MB limit.`);
                return;
            }
            
            // Check if file already exists
            if (!window.selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
                window.selectedFiles.push(file);
            }
        });
        updateFileList();
    }
    
    function updateFileList() {
        if (!fileList) return;
        
        fileList.innerHTML = '';
        
        if (window.selectedFiles.length === 0) {
            fileList.innerHTML = '<p class="text-muted text-center py-3">No files selected</p>';
            return;
        }
        
        window.selectedFiles.forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.innerHTML = `
                <div class="file-info">
                    <i data-lucide="${getFileIcon(file.name)}" style="width: 18px; height: 18px;"></i>
                    <div>
                        <div class="file-name">${file.name}</div>
                        <div class="file-size">${formatFileSize(file.size)}</div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger file-remove" onclick="removeFile(${index})" title="Remove file">
                    <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                </button>
            `;
            fileList.appendChild(fileItem);
        });
        
        // Re-initialize Lucide icons
        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }
        
        // Update upload zone text
        updateUploadZoneText();
    }
    
    // Make removeFile function global
    window.removeFile = function(index) {
        window.selectedFiles.splice(index, 1);
        updateFileList();
        
        // Clear file input
        const fileInput = document.getElementById('fileUpload');
        if (fileInput) {
            fileInput.value = '';
        }
    };
    
    // Make getSelectedFiles function global
    window.getSelectedFiles = function() {
        return window.selectedFiles || [];
    };
    
    // Make addFiles function global for drag and drop
    window.addFiles = addFiles;
}

// Initialize drag and drop
function initializeDragAndDrop() {
    const uploadZone = document.querySelector('.upload-zone');
    if (!uploadZone) return;
    
    uploadZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('drag-over');
    });
    
    uploadZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        // Only remove if leaving the upload zone entirely
        if (!uploadZone.contains(e.relatedTarget)) {
            this.classList.remove('drag-over');
        }
    });
    
    uploadZone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        
        const files = Array.from(e.dataTransfer.files);
        if (window.addFiles) {
            window.addFiles(files);
        }
    });
}

// Update upload zone text
function updateUploadZoneText() {
    const uploadZone = document.querySelector('.upload-zone');
    const uploadContent = uploadZone?.querySelector('.upload-content h6');
    
    if (uploadContent && window.selectedFiles) {
        if (window.selectedFiles.length > 0) {
            uploadContent.textContent = `${window.selectedFiles.length} file(s) selected - Click to add more`;
        } else {
            uploadContent.textContent = 'Drop files here or click to browse';
        }
    }
}

// Get file icon based on extension
function getFileIcon(filename) {
    const extension = filename.split('.').pop().toLowerCase();
    const icons = {
        'pdf': 'file-text',
        'doc': 'file-text',
        'docx': 'file-text',
        'txt': 'file-text',
        'jpg': 'image',
        'jpeg': 'image',
        'png': 'image',
        'gif': 'image',
        'zip': 'archive',
        'rar': 'archive',
        'java': 'code',
        'cpp': 'code',
        'py': 'code',
        'js': 'code',
        'html': 'code',
        'css': 'code'
    };
    
    return icons[extension] || 'file';
}

// Submit assignment
function submitAssignment() {
    const form = document.getElementById('submissionForm');
    const submitBtn = document.getElementById('submitBtn');
    const assignmentId = document.getElementById('assignmentId').value;
    const submissionText = document.getElementById('submissionText').value;
    
    // Validate form
    if (!validateSubmission()) {
        return;
    }
    
    // Show loading state
    submitBtn.classList.add('btn-loading');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
    
    // Create FormData
    const formData = new FormData();
    formData.append('assignment_id', assignmentId);
    formData.append('submission_text', submissionText);
    
    // Add files
    const selectedFiles = window.getSelectedFiles ? window.getSelectedFiles() : [];
    selectedFiles.forEach((file, index) => {
        formData.append(`files[${index}]`, file);
    });
    
    // Simulate API call (replace with actual submission)
    setTimeout(() => {
        // Reset button state
        submitBtn.classList.remove('btn-loading');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i data-lucide="send"></i> Submit Assignment';
        
        // Show success message
        showNotification('success', 'Assignment Submitted!', 'Your assignment has been submitted successfully.');
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('submissionModal'));
        if (modal) {
            modal.hide();
        }
        
        // Update UI to show submission
        updateAssignmentCard(assignmentId, 'submitted');
        
        // Re-initialize icons
        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }
        
        // Show celebration animation
        showCelebrationAnimation();
        
    }, 2000);
}

// Validate submission
function validateSubmission() {
    const checkboxes = document.querySelectorAll('.submission-checklist input[type="checkbox"]');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    if (!allChecked) {
        showNotification('warning', 'Incomplete Checklist', 'Please complete all checklist items before submitting.');
        
        // Highlight unchecked items
        checkboxes.forEach(cb => {
            if (!cb.checked) {
                cb.closest('.form-check').classList.add('border', 'border-warning', 'rounded', 'p-2');
                setTimeout(() => {
                    cb.closest('.form-check').classList.remove('border', 'border-warning', 'rounded', 'p-2');
                }, 3000);
            }
        });
        
        return false;
    }
    
    const selectedFiles = window.getSelectedFiles ? window.getSelectedFiles() : [];
    const submissionText = document.getElementById('submissionText').value.trim();
    
    if (selectedFiles.length === 0 && !submissionText) {
        const result = confirm('No files or text provided. Do you want to submit an empty assignment?');
        if (!result) {
            return false;
        }
    }
    
    return true;
}

// Update assignment card after submission
function updateAssignmentCard(assignmentId, status) {
    const cards = document.querySelectorAll('.assignment-card');
    cards.forEach(card => {
        const cardId = card.getAttribute('data-assignment-id') || assignmentId;
        if (cardId == assignmentId) {
            if (status === 'submitted') {
                const submitBtn = card.querySelector('.btn-submit');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i data-lucide="check"></i> Submitted';
                    submitBtn.classList.remove('btn-primary', 'btn-warning');
                    submitBtn.classList.add('btn-success');
                    submitBtn.disabled = true;
                    submitBtn.onclick = null;
                }
                
                // Add submitted badge
                const meta = card.querySelector('.assignment-meta');
                if (meta && !meta.querySelector('.badge-success')) {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-success ms-2';
                    badge.textContent = 'Submitted';
                    meta.appendChild(badge);
                }
                
                // Update card class
                card.classList.add('submitted-card');
                
                // Add submitted animation
                card.style.animation = 'pulse 0.5s ease-in-out';
                setTimeout(() => {
                    card.style.animation = '';
                }, 500);
            }
        }
    });
    
    // Re-initialize icons
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
    
    // Update tab counts
    updateTabCounts();
}

// Update tab counts
function updateTabCounts() {
    const upcomingCount = document.querySelectorAll('#upcoming .assignment-card:not(.submitted-card)').length;
    const pastDueCount = document.querySelectorAll('#past-due .assignment-card:not(.submitted-card)').length;
    const completedCount = document.querySelectorAll('#completed .assignment-card, .submitted-card').length;
    
    // Update badges
    const upcomingBadge = document.querySelector('#upcoming-tab .badge');
    const pastDueBadge = document.querySelector('#past-due-tab .badge');
    const completedBadge = document.querySelector('#completed-tab .badge');
    
    if (upcomingBadge) upcomingBadge.textContent = upcomingCount;
    if (pastDueBadge) pastDueBadge.textContent = pastDueCount;
    if (completedBadge) completedBadge.textContent = completedCount;
}

// Show celebration animation
function showCelebrationAnimation() {
    // Create confetti effect
    const colors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8'];
    for (let i = 0; i < 50; i++) {
        setTimeout(() => {
            createConfetti(colors[Math.floor(Math.random() * colors.length)]);
        }, i * 50);
    }
}

// Create confetti particle
function createConfetti(color) {
    const confetti = document.createElement('div');
    confetti.style.cssText = `
        position: fixed;
        width: 10px;
        height: 10px;
        background: ${color};
        top: -10px;
        left: ${Math.random() * 100}vw;
        z-index: 9999;
        pointer-events: none;
        border-radius: 50%;
    `;
    
    document.body.appendChild(confetti);
    
    const animation = confetti.animate([
        { transform: 'translateY(0) rotate(0deg)', opacity: 1 },
        { transform: `translateY(100vh) rotate(720deg)`, opacity: 0 }
    ], {
        duration: 3000,
        easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
    });
    
    animation.onfinish = () => confetti.remove();
}

// Update assignment time remaining
function updateAssignmentTimeRemaining() {
    const assignmentCards = document.querySelectorAll('.assignment-card:not(.completed-card)');
    
    assignmentCards.forEach(card => {
        const dueInfo = card.querySelector('.due-info span');
        const timeRemaining = card.querySelector('.time-remaining span');
        
        if (dueInfo && timeRemaining) {
            // Extract due date from the text (this would be better with data attributes)
            const dueText = dueInfo.textContent;
            // This is a simplified version - in reality, you'd store the due date in a data attribute
            console.log('Updating time remaining for:', dueText);
        }
    });
}

// Utility functions
function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit' 
    };
    return date.toLocaleDateString('en-US', options);
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function showNotification(type, title, message) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'error' ? 'danger' : 'info'} alert-dismissible fade show notification-toast`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i data-lucide="${type === 'success' ? 'check-circle' : type === 'warning' ? 'alert-triangle' : type === 'error' ? 'x-circle' : 'info'}" class="me-2"></i>
            <div>
                <strong>${title}</strong><br>
                <span>${message}</span>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
    
    // Re-initialize icons
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
}

// Theme toggle function
window.toggleTheme = function() {
    const isDark = document.body.classList.toggle("dark-mode");
    localStorage.setItem("theme", isDark ? "dark" : "light");
    
    // Re-initialize icons after theme change
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
};

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.02); }
    }
    
    @keyframes rotating {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .rotating {
        animation: rotating 1s linear infinite;
    }
    
    .drag-over {
        border-color: var(--teams-blue) !important;
        background: var(--teams-light-blue) !important;
    }
    
    .notification-toast {
        animation: slideInRight 0.3s ease-out;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);

// Export functions for global access
window.openSubmissionModal = openSubmissionModal;
window.viewAssignment = viewAssignment;
window.viewSubmission = viewSubmission;
window.submitAssignment = submitAssignment;
window.clearSearch = clearSearch;
window.clearSubjectFilter = clearSubjectFilter;