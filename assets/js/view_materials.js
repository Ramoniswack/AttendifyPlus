// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\assets\js\view_materials.js

// Global variables
let currentFilters = {
    search: '',
    subject: '',
    type: ''
};

let allMaterials = [];
let currentPage = 1;
const materialsPerPage = 50;

// Initialize the materials page
function initializeMaterialsPage() {
    console.log('Initializing materials page...');
    
    // Cache DOM elements
    cacheElements();
    
    // Setup event listeners
    setupEventListeners();
    
    // Initialize filters from URL
    initializeFiltersFromURL();
    
    // Cache all materials data
    cacheMaterialsData();
    
    // Setup search functionality
    setupSearch();
    
    // Initialize icons
    lucide.createIcons();
    
    console.log('Materials page initialized successfully');
}

// Cache DOM elements
function cacheElements() {
    window.elements = {
        searchInput: document.getElementById('searchInput'),
        clearSearch: document.getElementById('clearSearch'),
        subjectFilter: document.getElementById('subjectFilter'),
        typeFilter: document.getElementById('typeFilter'),
        applyFilters: document.getElementById('applyFilters'),
        clearFilters: document.getElementById('clearFilters'),
        clearAllFilters: document.getElementById('clearAllFilters'),
        resultsCount: document.getElementById('resultsCount'),
        materialsGrid: document.getElementById('materialsGrid'),
        loadMoreBtn: document.getElementById('loadMoreBtn'),
        previewModal: document.getElementById('previewModal'),
        previewContent: document.getElementById('previewContent'),
        downloadFromPreview: document.getElementById('downloadFromPreview')
    };
}

// Setup event listeners
function setupEventListeners() {
    const { searchInput, clearSearch, subjectFilter, typeFilter, applyFilters, clearFilters, clearAllFilters, loadMoreBtn } = window.elements;
    
    // Search input events
    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleSearchInput, 300));
        searchInput.addEventListener('keypress', handleSearchKeypress);
    }
    
    // Clear search button
    if (clearSearch) {
        clearSearch.addEventListener('click', clearSearchInput);
    }
    
    // Filter events
    if (subjectFilter) {
        subjectFilter.addEventListener('change', handleFilterChange);
    }
    
    if (typeFilter) {
        typeFilter.addEventListener('change', handleFilterChange);
    }
    
    // Button events
    if (applyFilters) {
        applyFilters.addEventListener('click', applyCurrentFilters);
    }
    
    if (clearFilters) {
        clearFilters.addEventListener('click', clearAllFilters);
    }
    
    if (clearAllFilters) {
        clearAllFilters.addEventListener('click', clearAllCurrentFilters);
    }
    
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', loadMoreMaterials);
    }
    
    // Material card interactions
    setupMaterialCardEvents();
}

// Initialize filters from URL parameters
function initializeFiltersFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    
    currentFilters.search = urlParams.get('search') || '';
    currentFilters.subject = urlParams.get('subject_filter') || '';
    currentFilters.type = urlParams.get('type_filter') || '';
    
    // Update UI to reflect current filters
    updateFilterUI();
}

// Cache materials data for client-side filtering
function cacheMaterialsData() {
    const materialCards = document.querySelectorAll('.material-card');
    allMaterials = Array.from(materialCards).map(card => ({
        element: card,
        title: card.dataset.title || '',
        description: card.dataset.description || '',
        tags: card.dataset.tags || '',
        subject: card.dataset.subject || '',
        filetype: card.dataset.filetype || ''
    }));
    
    console.log(`Cached ${allMaterials.length} materials`);
}

// Setup search functionality
function setupSearch() {
    updateSearchVisibility();
    filterMaterials();
}

// Handle search input
function handleSearchInput(event) {
    const value = event.target.value.trim();
    currentFilters.search = value;
    updateSearchVisibility();
    filterMaterials();
}

// Handle search keypress (Enter key)
function handleSearchKeypress(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        applyCurrentFilters();
    }
}

// Clear search input
function clearSearchInput() {
    const { searchInput } = window.elements;
    if (searchInput) {
        searchInput.value = '';
        currentFilters.search = '';
        updateSearchVisibility();
        filterMaterials();
    }
}

// Update search clear button visibility
function updateSearchVisibility() {
    const { searchInput, clearSearch } = window.elements;
    if (clearSearch && searchInput) {
        clearSearch.style.display = searchInput.value.trim() ? 'block' : 'none';
    }
}

// Handle filter changes
function handleFilterChange() {
    currentFilters.subject = window.elements.subjectFilter?.value || '';
    currentFilters.type = window.elements.typeFilter?.value || '';
    filterMaterials();
}

// Apply current filters (update URL and reload if needed)
function applyCurrentFilters() {
    const params = new URLSearchParams();
    
    if (currentFilters.search) params.set('search', currentFilters.search);
    if (currentFilters.subject) params.set('subject_filter', currentFilters.subject);
    if (currentFilters.type) params.set('type_filter', currentFilters.type);
    
    const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    window.location.href = newUrl;
}

// Clear all filters
function clearAllCurrentFilters() {
    currentFilters = { search: '', subject: '', type: '' };
    updateFilterUI();
    filterMaterials();
    
    // Also update URL
    window.history.pushState({}, '', window.location.pathname);
}

// Update filter UI to reflect current filters
function updateFilterUI() {
    const { searchInput, subjectFilter, typeFilter } = window.elements;
    
    if (searchInput) searchInput.value = currentFilters.search;
    if (subjectFilter) subjectFilter.value = currentFilters.subject;
    if (typeFilter) typeFilter.value = currentFilters.type;
    
    updateSearchVisibility();
}

// Filter materials based on current filters
function filterMaterials() {
    if (!allMaterials.length) return;
    
    const searchTerm = currentFilters.search.toLowerCase();
    const subjectFilter = currentFilters.subject;
    const typeFilter = currentFilters.type;
    
    let visibleCount = 0;
    
    allMaterials.forEach(material => {
        let show = true;
        
        // Search filter
        if (searchTerm) {
            const searchMatch = material.title.includes(searchTerm) ||
                              material.description.includes(searchTerm) ||
                              material.tags.includes(searchTerm);
            if (!searchMatch) show = false;
        }
        
        // Subject filter
        if (subjectFilter && material.subject !== subjectFilter) {
            show = false;
        }
        
        // Type filter
        if (typeFilter) {
            const allowedTypes = typeFilter.split(',');
            if (!allowedTypes.includes(material.filetype)) {
                show = false;
            }
        }
        
        // Show/hide element
        material.element.style.display = show ? '' : 'none';
        if (show) {
            visibleCount++;
            material.element.classList.add('fade-in');
        }
    });
    
    // Update results count
    updateResultsCount(visibleCount, allMaterials.length);
}

// Update results count display
function updateResultsCount(visible, total) {
    const { resultsCount } = window.elements;
    if (resultsCount) {
        resultsCount.textContent = `Showing ${visible} of ${total} materials`;
    }
}

// Setup material card events
function setupMaterialCardEvents() {
    // Add hover effects and interactions
    const materialCards = document.querySelectorAll('.material-card');
    
    materialCards.forEach(card => {
        // Add click-to-preview functionality
        card.addEventListener('click', (e) => {
            // Don't trigger if clicking on action buttons
            if (e.target.closest('.material-actions') || e.target.closest('.material-actions-primary')) {
                return;
            }
            
            const materialId = extractMaterialId(card);
            if (materialId) {
                previewMaterial(materialId);
            }
        });
        
        // Add keyboard navigation
        card.setAttribute('tabindex', '0');
        card.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const materialId = extractMaterialId(card);
                if (materialId) {
                    previewMaterial(materialId);
                }
            }
        });
    });
}

// Extract material ID from card element
function extractMaterialId(card) {
    const downloadLink = card.querySelector('a[href*="download_material.php"]');
    if (downloadLink) {
        const url = new URL(downloadLink.href);
        return url.searchParams.get('id');
    }
    return null;
}

// Preview material function
function previewMaterial(materialId) {
    console.log('Previewing material:', materialId);
    
    const { previewModal, previewContent, downloadFromPreview } = window.elements;
    
    if (!previewModal) return;
    
    // Show modal
    const modal = new bootstrap.Modal(previewModal);
    modal.show();
    
    // Set loading state
    previewContent.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading preview...</span>
            </div>
            <p class="mt-3 text-muted">Loading material preview...</p>
        </div>
    `;
    
    // Store material ID for download button
    if (downloadFromPreview) {
        downloadFromPreview.onclick = () => {
            window.open(`download_material.php?id=${materialId}`, '_blank');
        };
    }
    
    // Simulate preview loading (replace with actual preview logic)
    setTimeout(() => {
        previewContent.innerHTML = `
            <div class="text-center p-4">
                <i data-lucide="file-text" style="width: 64px; height: 64px;" class="text-primary mb-3"></i>
                <h5>Material Preview</h5>
                <p class="text-muted">Preview functionality will be available soon.</p>
                <p class="text-muted">Click download to access the full material.</p>
            </div>
        `;
        lucide.createIcons();
    }, 1000);
}

// Share material function
function shareMaterial(materialId) {
    console.log('Sharing material:', materialId);
    
    // Create share URL
    const shareUrl = `${window.location.origin}${window.location.pathname}?material=${materialId}`;
    
    // Copy to clipboard
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(shareUrl).then(() => {
            showToast('Share link copied to clipboard!', 'success');
        }).catch(() => {
            showFallbackShare(shareUrl);
        });
    } else {
        showFallbackShare(shareUrl);
    }
}

// Fallback share method
function showFallbackShare(url) {
    const textArea = document.createElement('textarea');
    textArea.value = url;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        showToast('Share link copied to clipboard!', 'success');
    } catch (err) {
        showToast('Unable to copy link. Please copy manually: ' + url, 'warning');
    }
    
    textArea.remove();
}

// Load more materials (if pagination is needed)
function loadMoreMaterials() {
    console.log('Loading more materials...');
    
    const { loadMoreBtn } = window.elements;
    if (loadMoreBtn) {
        loadMoreBtn.innerHTML = `
            <div class="spinner-border spinner-border-sm me-2" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            Loading More...
        `;
        loadMoreBtn.disabled = true;
    }
    
    // Simulate loading (replace with actual API call)
    setTimeout(() => {
        if (loadMoreBtn) {
            loadMoreBtn.innerHTML = `
                <i data-lucide="plus"></i>
                Load More Materials
            `;
            loadMoreBtn.disabled = false;
            lucide.createIcons();
        }
        
        showToast('No more materials to load', 'info');
    }, 1000);
}

// Utility function: Debounce
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Utility function: Show toast notification
function showToast(message, type = 'info') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'error' ? 'danger' : 'primary'} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    // Add to page
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '1055';
        document.body.appendChild(toastContainer);
    }
    
    toastContainer.appendChild(toast);
    
    // Show toast
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    // Remove from DOM after hiding
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

// Export functions for global access
window.previewMaterial = previewMaterial;
window.shareMaterial = shareMaterial;
window.initializeMaterialsPage = initializeMaterialsPage;

// Auto-initialize if DOM is already loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeMaterialsPage);
} else {
    initializeMaterialsPage();
}