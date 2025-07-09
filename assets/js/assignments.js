docs\AttendifyPlus\assets\js\teacher_assignments.js
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
    initializeTabs();
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
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
        
        const matches = !searchTerm || 
                       title.includes(searchTerm) || 
                       subject.includes(searchTerm) || 
                       description.includes(searchTerm);
        
        if (matches) {
            card.style.display = 'block';
            card.style.animation = 'fadeIn 0.3s ease';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    return visibleCount;
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

// Update empty states
function updateEmptyStates() {
    const tabs = ['active', 'draft', 'graded'];
    
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
        'active': {
            icon: 'search',
            title: 'No matching assignments',
            message: 'Try adjusting your search or filter criteria.'
        },
        'draft': {
            icon: 'search',
            title: 'No matching drafts',
            message: 'Try adjusting your search or filter criteria.'
        },
        'graded': {
            icon: 'search',
            title: 'No matching assignments',
            message: 'Try adjusting your search or filter criteria.'
        }
    };
    
    const config = configs[tabId] || configs['active'];
    
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

// Assignment actions
function createAssignment() {
    console.log('Creating new assignment...');
    showNotification('info', 'Feature Coming Soon', 'Assignment creation feature will be available soon!');
}

function viewAssignment(assignmentId) {
    console.log('Viewing assignment:', assignmentId);
    showNotification('info', 'Feature Coming Soon', 'Assignment details view will be available soon!');
}

function editAssignment(assignmentId) {
    console.log('Editing assignment:', assignmentId);
    showNotification('info', 'Feature Coming Soon', 'Assignment editing feature will be available soon!');
}

function publishAssignment(assignmentId) {
    console.log('Publishing assignment:', assignmentId);
    
    if (confirm('Are you sure you want to publish this assignment? Students will be able to see and submit it.')) {
        // Simulate publishing
        showNotification('success', 'Assignment Published', 'Your assignment is now live for students!');
        
        // Move from draft to active (simulation)
        setTimeout(() => {
            // This would be handled by backend in real implementation
            location.reload();
        }, 1500);
    }
}

function viewSubmissions(assignmentId) {
    console.log('Viewing submissions for assignment:', assignmentId);
    showNotification('info', 'Feature Coming Soon', 'Submission management will be available soon!');
}

function viewGrades(assignmentId) {
    console.log('Viewing grades for assignment:', assignmentId);
    showNotification('info', 'Feature Coming Soon', 'Grade analytics will be available soon!');
}

function exportGrades(assignmentId) {
    console.log('Exporting grades for assignment:', assignmentId);
    showNotification('info', 'Feature Coming Soon', 'Grade export feature will be available soon!');
}

// Utility functions
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