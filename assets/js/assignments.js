// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\assets\js\assignments.js
const BASE_API_URL = '/AttendifyPlus/api/'; // Configurable base URL
let currentEditingAssignment = null;

document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing assignments page...');
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Apply theme from localStorage
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        document.documentElement.setAttribute('data-theme', 'dark');
    } else {
        document.body.classList.remove('dark-mode');
        document.documentElement.setAttribute('data-theme', 'light');
    }

    // Initialize theme toggle
    initializeThemeToggle();
    updateThemeElements();

    // Initialize search, filters, and tabs
    initializeSearch();
    initializeFilters();
    initializeTabs();

    // Initialize file upload
    initializeFileUpload();

    // Form submission handler
    const assignmentForm = document.getElementById('assignmentCreateForm');
    if (assignmentForm) {
        assignmentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitAssignmentForm();
        });
    }

    // Button event listeners
    const publishBtn = document.getElementById('publishAssignmentBtn');
    if (publishBtn) {
        publishBtn.onclick = function(e) {
            e.preventDefault();
            document.getElementById('assignmentStatus').value = 'active';
            submitAssignmentForm();
        };
    }

    const draftBtn = document.getElementById('saveDraftBtn');
    if (draftBtn) {
        draftBtn.onclick = function(e) {
            e.preventDefault();
            document.getElementById('assignmentStatus').value = 'draft';
            submitAssignmentForm();
        };
    }

    // Initialize tooltips
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }

    console.log('Assignments page initialized successfully');
});

// Theme Toggle Functionality
function initializeThemeToggle() {
    window.toggleTheme = function() {
        console.log('Theme toggle clicked');
        const isDark = document.body.classList.toggle('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
        updateThemeElements();
        console.log('Theme toggled to:', isDark ? 'dark' : 'light');
    };
}

function updateThemeElements() {
    const isDark = document.body.classList.contains('dark-mode');
    const lightIcon = document.querySelector('.theme-icon.light-icon');
    const darkIcon = document.querySelector('.theme-icon.dark-icon');

    if (lightIcon && darkIcon) {
        lightIcon.style.display = isDark ? 'inline' : 'none';
        darkIcon.style.display = isDark ? 'none' : 'inline';
    }

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Tabs Functionality
function initializeTabs() {
    const triggerTabList = [].slice.call(document.querySelectorAll('#assignmentTabs button'));
    triggerTabList.forEach(triggerEl => {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        triggerEl.addEventListener('click', function(event) {
            event.preventDefault();
            tabTrigger.show();
            console.log('Tab switched to:', event.target.getAttribute('data-bs-target'));
        });
    });
}

// Search Functionality
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchClearBtn = document.getElementById('searchClearBtn');

    if (!searchInput) return;

    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const searchTerm = this.value.trim().toLowerCase();
        searchClearBtn.style.display = searchTerm ? 'flex' : 'none';
        searchTimeout = setTimeout(() => performSearch(searchTerm), 300);
    });

    if (searchClearBtn) {
        searchClearBtn.addEventListener('click', function() {
            searchInput.value = '';
            this.style.display = 'none';
            performSearch('');
            searchInput.focus();
        });
    }

    const searchIcon = document.querySelector('.search-icon');
    if (searchIcon) {
        searchIcon.addEventListener('click', () => searchInput.focus());
    }

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            performSearch(this.value.trim().toLowerCase());
        }
    });
}

function performSearch(searchTerm) {
    const assignmentCards = document.querySelectorAll('.assignment-card');
    let visibleCount = 0;

    console.log('Performing search for:', searchTerm);

    assignmentCards.forEach(card => {
        const title = card.querySelector('.assignment-title')?.textContent.toLowerCase() || '';
        const description = card.querySelector('.assignment-description')?.textContent.toLowerCase() || '';
        const subject = card.querySelector('.subject-code')?.textContent.toLowerCase() || '';

        const matchesSearch = !searchTerm || title.includes(searchTerm) || description.includes(searchTerm) || subject.includes(searchTerm);
        const isVisible = matchesSearch && !card.classList.contains('filter-hidden');

        card.style.display = isVisible ? 'block' : 'none';
        card.classList.toggle('search-hidden', !isVisible);
        if (isVisible) visibleCount++;
    });

    updateEmptyStates();
    console.log(`Search completed. ${visibleCount} assignments visible.`);
}

// Filter Functionality
function initializeFilters() {
    const subjectFilter = document.getElementById('subjectFilter');
    const statusFilter = document.getElementById('statusFilter');

    if (subjectFilter) subjectFilter.addEventListener('change', applyAllFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyAllFilters);
}

function applyAllFilters() {
    const subjectFilter = document.getElementById('subjectFilter');
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchInput');

    const selectedSubject = subjectFilter?.value || 'all';
    const selectedStatus = statusFilter?.value || 'all';
    const searchTerm = searchInput?.value.trim().toLowerCase() || '';

    const assignmentCards = document.querySelectorAll('.assignment-card');
    let visibleCount = 0;

    assignmentCards.forEach(card => {
        const subjectCode = card.getAttribute('data-subject') || '';
        const cardStatus = card.getAttribute('data-status') || '';
        const title = card.querySelector('.assignment-title')?.textContent.toLowerCase() || '';
        const description = card.querySelector('.assignment-description')?.textContent.toLowerCase() || '';

        const matchesSubject = selectedSubject === 'all' || subjectCode === selectedSubject;
        const matchesStatus = selectedStatus === 'all' || cardStatus === selectedStatus;
        const matchesSearch = !searchTerm || title.includes(searchTerm) || description.includes(searchTerm) || subjectCode.toLowerCase().includes(searchTerm);

        const isVisible = matchesSubject && matchesStatus && matchesSearch;

        card.style.display = isVisible ? 'block' : 'none';
        card.classList.toggle('filter-hidden', !isVisible);
        if (isVisible) card.style.animation = 'fadeIn 0.3s ease';
        if (isVisible) visibleCount++;
    });

    updateEmptyStates();
    console.log(`Filters applied. ${visibleCount} assignments visible.`);
}

function clearFilters() {
    const subjectFilter = document.getElementById('subjectFilter');
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchInput');
    const searchClearBtn = document.getElementById('searchClearBtn');

    if (subjectFilter) subjectFilter.value = 'all';
    if (statusFilter) statusFilter.value = 'all';
    if (searchInput) searchInput.value = '';
    if (searchClearBtn) searchClearBtn.style.display = 'none';

    const assignmentCards = document.querySelectorAll('.assignment-card');
    assignmentCards.forEach(card => {
        card.style.display = 'block';
        card.classList.remove('filter-hidden', 'search-hidden');
        card.style.animation = 'fadeIn 0.3s ease';
    });

    updateEmptyStates();
    console.log('All filters cleared');
}

// Utility Functions
function updateEmptyStates() {
    const tabs = ['active-assignments', 'draft-assignments', 'student-submissions'];

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

function createEmptyState(tabId) {
    const emptyState = document.createElement('div');
    emptyState.className = 'empty-state';

    const configs = {
        'active-assignments': {
            icon: 'clipboard-check',
            title: 'No matching assignments',
            message: 'Try adjusting your search or filter criteria.'
        },
        'draft-assignments': {
            icon: 'edit-3',
            title: 'No matching drafts',
            message: 'Try adjusting your search or filter criteria.'
        },
        'student-submissions': {
            icon: 'inbox',
            title: 'No Data Available',
            message: 'Select an assignment to view student submissions.'
        }
    };

    const config = configs[tabId] || configs['active-assignments'];

    emptyState.innerHTML = `
        <i data-lucide="${config.icon}" class="empty-state-icon"></i>
        <h4>${config.title}</h4>
        <p>${config.message}</p>
    `;

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    return emptyState;
}

function clearValidationErrors() {
    document.querySelectorAll('.invalid-feedback').forEach(el => el.innerHTML = '');
    const errorContainer = document.getElementById('assignmentFormErrors');
    if (errorContainer) errorContainer.innerHTML = '';
}

function refreshLucideIcons() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Assignment Actions
window.createAssignment = function() {
    console.log('Opening create assignment form...');
    showAssignmentForm('create');
    refreshLucideIcons();
};

window.createDraft = function() {
    console.log('Opening create draft form...');
    showAssignmentForm('draft');
    refreshLucideIcons();
};

window.editAssignment = function(assignmentId) {
    console.log('Fetching assignment:', assignmentId);
    fetch(`${BASE_API_URL}get_assignment.php?id=${assignmentId}`)
        .then(response => {
            console.log('Edit assignment response status:', response.status);
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Non-JSON response:', text);
                    throw new Error('Invalid server response');
                }
            });
        })
        .then(data => {
            if (data.success) {
                populateFormForEdit(data.assignment);
                refreshLucideIcons();
            } else {
                showError(data.message || 'Failed to load assignment data.');
            }
        })
        .catch(error => {
            console.error('Edit assignment error:', error);
            showError(`Failed to load assignment data: ${error.message}`);
        });
};

window.publishAssignment = function(assignmentId) {
    if (!confirm('Are you sure you want to publish this assignment? Students will be able to see and submit to it.')) {
        return;
    }
    console.log('Publishing assignment:', assignmentId);
    const publishBtn = document.querySelector(`button[onclick="publishAssignment(${assignmentId})"]`);
    const originalText = publishBtn.innerHTML;
    publishBtn.innerHTML = '<i data-lucide="loader" class="animate-spin"></i> Publishing...';
    publishBtn.disabled = true;

    fetch(`${BASE_API_URL}publish_assignment.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ assignment_id: assignmentId })
    })
    .then(response => {
        console.log('Publish response status:', response.status);
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Non-JSON response:', text);
                throw new Error(`Invalid server response: ${text.substring(0, 200)}...`);
            }
        });
    })
    .then(data => {
        if (data.success) {
            showSuccess(data.message || 'Assignment published successfully!');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showError(data.message || 'Failed to publish assignment.');
        }
    })
    .catch(error => {
        console.error('Publish error:', error);
        showError(`Error publishing assignment: ${error.message}`);
    })
    .finally(() => {
        publishBtn.innerHTML = originalText;
        publishBtn.disabled = false;
        refreshLucideIcons();
    });
};

window.viewAssignmentAnalytics = function(assignmentId) {
    console.log('Fetching analytics for assignment:', assignmentId);
    fetch(`${BASE_API_URL}get_assignment_analytics.php?assignment_id=${assignmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAnalyticsModal(data.analytics);
            } else {
                showError(data.message || 'Failed to load analytics.');
            }
        })
        .catch(error => {
            console.error('Analytics fetch error:', error);
            showError(`Error loading analytics: ${error.message}`);
        });
};

window.viewSubmissions = function(assignmentId) {
    const assignmentSelect = document.getElementById('assignmentSelect');
    if (assignmentSelect) {
        assignmentSelect.value = assignmentId;
        loadStudentSubmissions();
        bootstrap.Tab.getOrCreateInstance(document.getElementById('submissions-tab')).show();
    }
};

// File Upload Functionality
function initializeFileUpload() {
    const fileInput = document.getElementById('assignmentFile');
    const uploadArea = document.querySelector('.file-upload-area');

    if (!fileInput || !uploadArea) return;

    fileInput.addEventListener('change', handleFileSelect);
    uploadArea.addEventListener('dragover', handleDragOver);
    uploadArea.addEventListener('dragleave', handleDragLeave);
    uploadArea.addEventListener('drop', handleFileDrop);
}

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (file) validateAndShowFile(file);
}

function handleDragOver(event) {
    event.preventDefault();
    event.currentTarget.classList.add('dragover');
}

function handleDragLeave(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('dragover');
}

function handleFileDrop(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('dragover');
    const files = event.dataTransfer.files;
    const fileInput = document.getElementById('assignmentFile');
    if (files.length > 0 && fileInput) {
        fileInput.files = files;
        validateAndShowFile(files[0]);
    }
}

function validateAndShowFile(file) {
    const maxSize = 10 * 1024 * 1024; // 10MB
    if (!isValidFileType(file)) {
        showError('Invalid file type. Please upload PDF, DOC, or DOCX files only.');
        clearFileSelection();
        return;
    }
    if (file.size > maxSize) {
        showError('File size exceeds 10MB limit.');
        clearFileSelection();
        return;
    }
    showFileUploadSuccess(file);
    setTimeout(() => showFileInfo(file), 1600);
}

function showFileInfo(file) {
    const fileInfo = document.getElementById('fileInfo');
    const fileName = fileInfo.querySelector('.file-name');
    const fileSize = fileInfo.querySelector('.file-size');
    const uploadArea = document.querySelector('.file-upload-area');

    if (file) {
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        // Do NOT hide or remove the file input!
        // Only update the info display
        uploadArea.style.display = 'block';
        fileInfo.style.display = 'block';
        fileInfo.classList.add('show');
        refreshLucideIcons();
        console.log('File selected:', file.name, '(' + formatFileSize(file.size) + ')');
    }
}

function removeFile() {
    clearFileSelection();
    clearFileInfo();
}

function clearFileSelection() {
    const fileInput = document.getElementById('assignmentFile');
    if (fileInput) fileInput.value = '';
}

function clearFileInfo() {
    const fileInfo = document.getElementById('fileInfo');
    const uploadArea = document.querySelector('.file-upload-area');

    if (fileInfo) {
        fileInfo.style.display = 'none';
        fileInfo.classList.remove('show');
        fileInfo.querySelector('.file-name').textContent = 'No file selected';
        fileInfo.querySelector('.file-size').textContent = '';
        const downloadLink = document.getElementById('existingFileDownload');
        if (downloadLink) downloadLink.remove();
    }
    if (uploadArea) uploadArea.style.display = 'block';
}

function formatFileSize(bytes) {
    if (!bytes) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function isValidFileType(file) {
    const validExtensions = ['pdf', 'doc', 'docx'];
    const validMimeTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    const extension = getFileExtension(file.name);
    return validExtensions.includes(extension) || validMimeTypes.includes(file.type);
}

function getFileExtension(filename) {
    return filename.split('.').pop().toLowerCase();
}

function showFileUploadSuccess(file) {
    const uploadArea = document.querySelector('.file-upload-area');
    if (uploadArea) {
        const originalContent = uploadArea.innerHTML;
        uploadArea.innerHTML = `
            <div class="file-upload-content">
                <h5 style="color: #28a745;">File uploaded successfully!</h5>
                <p class="text-muted">${file.name} (${formatFileSize(file.size)})</p>
            </div>
        `;
        refreshLucideIcons();
        setTimeout(() => {
            uploadArea.innerHTML = originalContent;
            refreshLucideIcons();
        }, 1500);
    }
}

// Form Validation
function validateAssignmentForm() {
    const title = document.getElementById('assignmentTitle');
    const subject = document.getElementById('assignmentSubject');
    const dueDate = document.getElementById('assignmentDueDate');
    let valid = true;
    clearValidationErrors();

    if (!title || !title.value.trim()) {
        showValidationError(title, 'Title is required');
        valid = false;
    }
    if (!subject || !subject.value.trim()) {
        showValidationError(subject, 'Subject is required');
        valid = false;
    }
    if (!dueDate || !dueDate.value.trim()) {
        showValidationError(dueDate, 'Due date is required');
        valid = false;
    }
    return valid;
}

function showValidationError(input, message) {
    if (!input) return;
    const feedback = input.nextElementSibling;
    if (feedback && feedback.classList.contains('invalid-feedback')) {
        feedback.textContent = message;
        input.classList.add('is-invalid');
    }
}

// Form Submission
function submitAssignmentForm() {
    const form = document.getElementById('assignmentCreateForm');
    if (!form || !validateAssignmentForm()) return;

    // Debug: log all file inputs in the DOM at submit time
    const allFileInputs = document.querySelectorAll('input[type="file"][name="assignment_file"]');
    allFileInputs.forEach((input, idx) => {
        console.log(`File input [${idx}]:`, input, input.files);
    });

    const formData = new FormData(form);
    // Always get the file input from inside the form at submit time
    const fileInput = form.querySelector('input[type="file"][name="assignment_file"]');
    let file = null;
    if (fileInput && fileInput.files && fileInput.files.length > 0) {
        file = fileInput.files[0];
        formData.set('assignment_file', file);
    } else {
        showError('Please select a file before submitting.');
        return;
    }
    if (currentEditingAssignment) {
        formData.set('assignment_id', currentEditingAssignment.AssignmentID);
    }

    // Debug: log FormData entries
    console.log('FormData entries:', Array.from(formData.entries()));

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    const isEditing = !!currentEditingAssignment;
    submitBtn.innerHTML = `<i data-lucide="loader" class="animate-spin"></i> ${isEditing ? 'Updating' : 'Publishing'}...`;
    submitBtn.disabled = true;

    const endpoint = isEditing ? `${BASE_API_URL}edit_assignment.php` : `${BASE_API_URL}create_assignment.php`;
    console.log('Submitting to:', endpoint, 'FormData:', Array.from(formData.entries()));

    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Submission response status:', response.status);
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(`HTTP ${response.status}: ${text}`);
            });
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Non-JSON response:', text);
                throw new Error(`Invalid server response: ${text.substring(0, 200)}...`);
            }
        });
    })
    .then(data => {
        if (data.success) {
            showAssignmentSuccess(data, isEditing);
            clearFileSelection(); // Only clear after success
            clearFileInfo();      // Only clear after success
            closeAssignmentForm();
            currentEditingAssignment = null;
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showAssignmentError(data, isEditing);
        }
    })
    .catch(error => {
        console.error('Submission error:', error);
        showError(`Error ${isEditing ? 'updating' : 'creating'} assignment: ${error.message}`);
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        refreshLucideIcons();
    });
}

function saveDraft() {
    document.getElementById('assignmentStatus').value = 'draft';
    submitAssignmentForm();
}

function showAssignmentSuccess(data, isEditing) {
    let msg = data.message || (isEditing ? 'Assignment updated successfully!' : 'Assignment created successfully!');
    let details = '';
    if (data.assignment_id) details += `Assignment ID: ${data.assignment_id}\n`;
    if (data.attachment_filename) details += `File: ${data.attachment_filename}\n`;
    if (data.attachment_filesize) details += `Size: ${formatFileSize(data.attachment_filesize)}\n`;
    showSuccess(msg + (details ? '\n' + details : ''));
}

function showAssignmentError(data, isEditing) {
    showError(data.message || (isEditing ? 'Failed to update assignment.' : 'Failed to create assignment.'));
}

// Assignment Editing
function populateFormForEdit(assignment) {
    currentEditingAssignment = assignment;
    const formSection = document.getElementById('assignmentForm');
    const formTitle = document.getElementById('formTitle');
    const submitButtonText = document.getElementById('submitButtonText');

    if (!formSection) return;

    formTitle.innerHTML = '<i data-lucide="edit-3"></i> Edit Assignment';
    submitButtonText.textContent = 'Update Assignment';

    document.getElementById('assignmentTitle').value = assignment.Title || '';
    document.getElementById('assignmentDescription').value = assignment.Description || '';
    document.getElementById('assignmentInstructions').value = assignment.Instructions || '';
    document.getElementById('assignmentSubject').value = assignment.SubjectID || '';
    document.getElementById('assignmentDueDate').value = assignment.DueDate ? assignment.DueDate.replace(' ', 'T') : '';
    document.getElementById('assignmentPoints').value = assignment.MaxPoints || 100;
    document.getElementById('submissionType').value = assignment.SubmissionType || 'both';
    document.getElementById('allowLateSubmissions').checked = assignment.AllowLateSubmissions == 1;
    document.getElementById('assignmentNotes').value = assignment.GradingRubric || '';
    document.getElementById('assignmentStatus').value = assignment.Status || 'draft';

    const fileInfo = document.getElementById('fileInfo');
    const fileName = fileInfo.querySelector('.file-name');
    const fileSize = fileInfo.querySelector('.file-size');
    if (assignment.AttachmentFileName && assignment.AttachmentPath) {
        fileName.textContent = assignment.AttachmentFileName;
        let downloadLink = document.getElementById('existingFileDownload');
        if (!downloadLink) {
            downloadLink = document.createElement('a');
            downloadLink.id = 'existingFileDownload';
            downloadLink.href = `/${assignment.AttachmentPath}`;
            downloadLink.target = '_blank';
            downloadLink.className = 'btn btn-sm btn-outline-success ms-2';
            downloadLink.innerHTML = '<i data-lucide="file-text"></i> Download';
            fileName.appendChild(downloadLink);
        } else {
            downloadLink.href = `/${assignment.AttachmentPath}`;
        }
        fileSize.textContent = `Existing file (${formatFileSize(assignment.AttachmentFileSize || 0)})`;
        fileInfo.style.display = 'block';
        fileInfo.classList.add('show');
    } else {
        clearFileInfo();
    }

    formSection.style.display = 'block';
    formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });

    setTimeout(() => {
        document.getElementById('assignmentTitle').focus();
        refreshLucideIcons();
    }, 300);
}

// Student Submissions
let currentAssignmentId = null;

function loadStudentSubmissions() {
    const assignmentSelect = document.getElementById('assignmentSelect');
    const assignmentId = assignmentSelect.value;
    const submissionsTableContainer = document.getElementById('submissionsTableContainer');
    const submissionsSummary = document.getElementById('submissionsSummary');
    const submissionsLoading = document.getElementById('submissionsLoading');
    const emptySubmissions = document.getElementById('emptySubmissions');
    const exportBtn = document.getElementById('exportSubmissionsBtn');

    if (!assignmentId) {
        submissionsTableContainer.style.display = 'none';
        submissionsSummary.style.display = 'none';
        exportBtn.disabled = true;
        currentAssignmentId = null;
        emptySubmissions.style.display = 'block';
        return;
    }

    currentAssignmentId = assignmentId;
    submissionsTableContainer.style.display = 'block';
    submissionsSummary.style.display = 'block';
    submissionsLoading.style.display = 'block';
    emptySubmissions.style.display = 'none';

    fetch(`${BASE_API_URL}get_student_submissions.php?assignment_id=${assignmentId}`)
        .then(response => response.json())
        .then(data => {
            submissionsLoading.style.display = 'none';
            if (data.success) {
                displaySubmissionsSummary(data.summary);
                displaySubmissionsTable(data.students);
                exportBtn.disabled = false;
                document.getElementById('submissionsCount').textContent = data.summary.total || 0;
            } else {
                showError('Failed to load submissions: ' + (data.message || 'Unknown error'));
                emptySubmissions.style.display = 'block';
                exportBtn.disabled = true;
            }
        })
        .catch(error => {
            submissionsLoading.style.display = 'none';
            emptySubmissions.style.display = 'block';
            exportBtn.disabled = true;
            showError('Error loading submissions: ' + error.message);
        });
}

function displaySubmissionsSummary(summary) {
    document.getElementById('totalStudents').textContent = summary.total || 0;
    document.getElementById('submittedCount').textContent = summary.submitted || 0;
    document.getElementById('viewedCount').textContent = summary.viewed || 0;
    document.getElementById('pendingCount').textContent = summary.pending || 0;
}

function displaySubmissionsTable(students) {
    const tableBody = document.getElementById('submissionsTableBody');
    tableBody.innerHTML = '';

    students.forEach(student => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <div class="student-info">
                    <span class="student-name">${escapeHtml(student.Name)}</span>
                    <span class="student-number">${escapeHtml(student.StudentNumber)}</span>
                </div>
            </td>
            <td><span class="status-badge ${student.Status}">${capitalizeFirst(student.Status)}</span></td>
            <td>
                ${student.SubmittedAt ? `
                    <div class="submission-date">${new Date(student.SubmittedAt).toLocaleDateString()}</div>
                    <small class="text-muted">${new Date(student.SubmittedAt).toLocaleTimeString()}</small>
                ` : '<span class="text-muted">Not submitted</span>'}
            </td>
            <td>
                ${student.Grade !== null ? `
                    <span class="grade-display ${getGradeClass(student.Grade)}">${student.Grade}/${student.MaxGrade}</span>
                ` : '<span class="text-muted">Not graded</span>'}
            </td>
            <td>
                ${student.FileName ? `
                    <div class="file-info">
                        <a href="#" class="file-name" onclick="downloadSubmission(${student.StudentID}, ${currentAssignmentId})">
                            ${escapeHtml(student.FileName)}
                        </a>
                        <span class="file-size">${formatFileSize(student.FileSize)}</span>
                    </div>
                ` : student.SubmissionText ? `
                    <div class="text-submission">
                        <span class="text-preview">${escapeHtml(student.SubmissionText.substring(0, 50))}${student.SubmissionText.length > 50 ? '...' : ''}</span>
                    </div>
                ` : '<span class="text-muted">No submission</span>'}
            </td>
            <td>
                <div class="view-count">
                    <i data-lucide="eye"></i>
                    <span>${student.ViewCount || 0}</span>
                </div>
            </td>
            <td>
                <div class="submission-actions">
                    ${student.Status === 'submitted' ? `
                        <button class="btn btn-sm btn-primary" onclick="gradeSubmission(${student.StudentID}, ${currentAssignmentId})">
                            <i data-lucide="edit-3"></i> Grade
                        </button>
                    ` : ''}
                    <button class="btn btn-sm btn-outline-secondary" onclick="viewStudentDetails(${student.StudentID})">
                        <i data-lucide="user"></i> View
                    </button>
                </div>
            </td>
        `;
        tableBody.appendChild(row);
    });

    refreshLucideIcons();
}

function exportSubmissions() {
    if (!currentAssignmentId) {
        showError('Please select an assignment first');
        return;
    }
    const link = document.createElement('a');
    link.href = `${BASE_API_URL}export_submissions.php?assignment_id=${currentAssignmentId}`;
    link.download = '';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showSuccess('Export started! The file will download shortly.');
}

function gradeSubmission(studentId, assignmentId) {
    console.log('Opening grading interface for student ID:', studentId, 'Assignment ID:', assignmentId);
    fetch(`${BASE_API_URL}get_submission_details.php?student_id=${studentId}&assignment_id=${assignmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showGradingModal(data.submission);
            } else {
                showError(data.message || 'Failed to load submission details.');
            }
        })
        .catch(error => {
            console.error('Submission details fetch error:', error);
            showError(`Error loading submission: ${error.message}`);
        });
}

function viewStudentDetails(studentId) {
    console.log('Fetching student details for student ID:', studentId);
    fetch(`${BASE_API_URL}get_student_details.php?student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showStudentDetailsModal(data.student);
            } else {
                showError(data.message || 'Failed to load student details.');
            }
        })
        .catch(error => {
            console.error('Student details fetch error:', error);
            showError(`Error loading student details: ${error.message}`);
        });
}

function downloadSubmission(studentId, assignmentId) {
    console.log('Initiating download for student ID:', studentId, 'Assignment ID:', assignmentId);
    const link = document.createElement('a');
    link.href = `${BASE_API_URL}download_submission.php?student_id=${studentId}&assignment_id=${assignmentId}`;
    link.download = '';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showSuccess('Download started!');
}

// Notification Functions
function showSuccess(message) {
    const notification = document.createElement('div');
    notification.className = 'alert alert-success alert-dismissible fade show';
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '10000';
    notification.innerHTML = `
        <i data-lucide="check-circle"></i> ${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(notification);
    refreshLucideIcons();
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function showError(message) {
    const notification = document.createElement('div');
    notification.className = 'alert alert-danger alert-dismissible fade show';
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '10000';
    notification.innerHTML = `
        <i data-lucide="alert-circle"></i> ${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(notification);
    refreshLucideIcons();
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

function showInfo(message) {
    const notification = document.createElement('div');
    notification.className = 'alert alert-info alert-dismissible fade show';
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '10000';
    notification.innerHTML = `
        <i data-lucide="info"></i> ${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(notification);
    refreshLucideIcons();
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Form Management
function showAssignmentForm(mode = 'create') {
    const formSection = document.getElementById('assignmentForm');
    const formTitle = document.getElementById('formTitle');
    const submitButtonText = document.getElementById('submitButtonText');
    const form = document.getElementById('assignmentCreateForm');

    if (!formSection || !form) return;

    clearValidationErrors();
    currentEditingAssignment = null;

    formTitle.innerHTML = '<i data-lucide="plus-circle"></i> Create New Assignment';
    submitButtonText.textContent = 'Publish Assignment';
    document.getElementById('assignmentStatus').value = mode === 'draft' ? 'draft' : 'active';

    formSection.style.display = 'block';
    formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });

    setTimeout(() => {
        document.getElementById('assignmentTitle').focus();
        refreshLucideIcons();
    }, 300);
}

function closeAssignmentForm() {
    const formSection = document.getElementById('assignmentForm');
    const form = document.getElementById('assignmentCreateForm');

    if (formSection && form) {
        form.reset();
        clearValidationErrors();
        formSection.style.display = 'none';
        currentEditingAssignment = null;
        console.log('Assignment form closed');
    }
}

// Additional Utility Functions
function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function capitalizeFirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function getGradeClass(grade) {
    if (grade >= 90) return 'grade-excellent';
    if (grade >= 75) return 'grade-good';
    if (grade >= 60) return 'grade-pass';
    return 'grade-fail';
}

function showAnalyticsModal(analytics) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'analyticsModal';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assignment Analytics</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Total Students:</strong> ${analytics.total_students || 0}</p>
                    <p><strong>Submissions:</strong> ${analytics.submissions || 0}</p>
                    <p><strong>Views:</strong> ${analytics.views || 0}</p>
                    <p><strong>Pending:</strong> ${analytics.pending || 0}</p>
                    <p><strong>Average Grade:</strong> ${analytics.avg_grade ? analytics.avg_grade.toFixed(2) : 'N/A'}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    modal.addEventListener('hidden.bs.modal', () => modal.remove());
    refreshLucideIcons();
}

function showGradingModal(submission) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'gradingModal';
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Grade Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Student:</strong> ${escapeHtml(submission.Name)}</p>
                    <p><strong>Assignment:</strong> ${escapeHtml(submission.AssignmentTitle)}</p>
                    ${submission.FileName ? `
                        <p><strong>File:</strong> <a href="${BASE_API_URL}download_submission.php?student_id=${submission.StudentID}&assignment_id=${submission.AssignmentID}" target="_blank">${escapeHtml(submission.FileName)}</a></p>
                    ` : ''}
                    ${submission.SubmissionText ? `
                        <p><strong>Text Submission:</strong> ${escapeHtml(submission.SubmissionText)}</p>
                    ` : ''}
                    <div class="form-group">
                        <label for="gradeInput">Grade (0-${submission.MaxPoints})</label>
                        <input type="number" class="form-control" id="gradeInput" min="0" max="${submission.MaxPoints}" value="${submission.Grade || ''}">
                    </div>
                    <div class="form-group mt-3">
                        <label for="feedbackInput">Feedback</label>
                        <textarea class="form-control" id="feedbackInput" rows="4">${escapeHtml(submission.Feedback || '')}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitGrade(${submission.StudentID}, ${submission.AssignmentID})">Submit Grade</button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    modal.addEventListener('hidden.bs.modal', () => modal.remove());
    refreshLucideIcons();
}

function submitGrade(studentId, assignmentId) {
    const gradeInput = document.getElementById('gradeInput');
    const feedbackInput = document.getElementById('feedbackInput');
    const grade = parseFloat(gradeInput.value);
    const feedback = feedbackInput.value;

    if (isNaN(grade) || grade < 0 || grade > parseFloat(gradeInput.max)) {
        showError('Please enter a valid grade between 0 and ' + gradeInput.max);
        return;
    }

    const submitBtn = document.querySelector('#gradingModal .btn-primary');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i data-lucide="loader" class="animate-spin"></i> Submitting...';
    submitBtn.disabled = true;

    fetch(`${BASE_API_URL}submit_grade.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            student_id: studentId,
            assignment_id: assignmentId,
            grade: grade,
            feedback: feedback
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('Grade submitted successfully!');
            bootstrap.Modal.getInstance(document.getElementById('gradingModal')).hide();
            loadStudentSubmissions();
        } else {
            showError(data.message || 'Failed to submit grade.');
        }
    })
    .catch(error => {
        console.error('Grade submission error:', error);
        showError(`Error submitting grade: ${error.message}`);
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        refreshLucideIcons();
    });
}

function showStudentDetailsModal(student) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'studentDetailsModal';
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Student Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Name:</strong> ${escapeHtml(student.Name)}</p>
                    <p><strong>Student Number:</strong> ${escapeHtml(student.StudentNumber)}</p>
                    <p><strong>Email:</strong> ${escapeHtml(student.Email || 'N/A')}</p>
                    <p><strong>Department:</strong> ${escapeHtml(student.DepartmentName || 'N/A')}</p>
                    <p><strong>Semester:</strong> ${escapeHtml(student.Semester || 'N/A')}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    modal.addEventListener('hidden.bs.modal', () => modal.remove());
    refreshLucideIcons();
}