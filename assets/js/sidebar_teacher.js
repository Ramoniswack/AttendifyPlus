/*
 * Universal Sidebar JavaScript for Teacher Dashboard
 * Ensures consistent sidebar behavior across all teacher pages
 */

// Enhanced sidebar functionality with smooth animations
function initializeUniversalSidebar() {
    console.log('Initializing universal sidebar...');
    
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (!sidebarToggle || !sidebar) {
        console.log('Sidebar elements not found');
        return;
    }

    const overlay = createSidebarOverlay();

    // Remove existing listeners by cloning
    const newToggle = sidebarToggle.cloneNode(true);
    sidebarToggle.parentNode.replaceChild(newToggle, sidebarToggle);

    newToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Sidebar toggle clicked');
        
        const isActive = sidebar.classList.contains('active');
        if (isActive) {
            closeSidebarSmooth();
        } else {
            openSidebarSmooth();
        }
    });

    // Close sidebar when clicking overlay
    if (overlay) {
        overlay.addEventListener('click', function(e) {
            console.log('Overlay clicked, closing sidebar');
            closeSidebarSmooth();
        });
    }

    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            closeSidebarSmooth();
        }
    });
    
    console.log('Universal sidebar initialized');
}

function createSidebarOverlay() {
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.id = 'sidebarOverlay';
        document.body.appendChild(overlay);
        console.log('Sidebar overlay created');
    }
    return overlay;
}

function openSidebarSmooth() {
    const sidebar = document.querySelector('.sidebar');
    const body = document.body;
    const overlay = document.querySelector('.sidebar-overlay');

    if (sidebar) {
        // Close any open dropdowns first
        closeAllDropdowns();
        
        // Apply active states - let CSS handle the animation
        sidebar.classList.add('active');
        body.classList.add('sidebar-open');
        
        if (overlay) {
            overlay.classList.add('active');
        }
        
        console.log('Sidebar opened smoothly');
    }
}

function closeSidebarSmooth() {
    const sidebar = document.querySelector('.sidebar');
    const body = document.body;
    const overlay = document.querySelector('.sidebar-overlay');

    if (sidebar) {
        // Remove active states - let CSS handle the animation
        sidebar.classList.remove('active');
        body.classList.remove('sidebar-open');
        
        if (overlay) {
            overlay.classList.remove('active');
        }
        
        console.log('Sidebar closed smoothly');
    }
}

function ensureSidebarHidden() {
    console.log('Ensuring sidebar is hidden on load...');
    
    const sidebar = document.querySelector('.sidebar');
    const body = document.body;
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.remove('active');
    }
    
    if (body) {
        body.classList.remove('sidebar-open');
    }
    
    if (overlay) {
        overlay.classList.remove('active');
    }
    
    console.log('Sidebar set to hidden state');
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

// ===== THEME FUNCTIONALITY ===== //

// Theme toggle function
window.toggleTheme = function () {
    const isDark = document.body.classList.toggle("dark-mode");
    const newTheme = isDark ? "dark" : "light";
    localStorage.setItem("theme", newTheme);
    document.documentElement.setAttribute('data-theme', newTheme);
    updateThemeElements();
    console.log('Theme switched to:', newTheme);
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

// Apply theme from localStorage on load
function applyStoredTheme() {
    const savedTheme = localStorage.getItem("theme");
    if (savedTheme === "dark") {
        document.body.classList.add("dark-mode");
        document.documentElement.setAttribute('data-theme', 'dark');
    } else {
        document.body.classList.remove("dark-mode");
        document.documentElement.setAttribute('data-theme', 'light');
    }
    updateThemeElements();
}

// Initialize on DOM content loaded
document.addEventListener('DOMContentLoaded', function() {
    // Force sidebar to be hidden on load
    ensureSidebarHidden();
    
    // Initialize universal sidebar functionality
    initializeUniversalSidebar();
    
    // Apply stored theme
    applyStoredTheme();
});

// Export functions for global use
window.initializeUniversalSidebar = initializeUniversalSidebar;
window.createSidebarOverlay = createSidebarOverlay;
window.openSidebarSmooth = openSidebarSmooth;
window.closeSidebarSmooth = closeSidebarSmooth;
window.ensureSidebarHidden = ensureSidebarHidden;
