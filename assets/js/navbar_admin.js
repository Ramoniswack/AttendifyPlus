/**
 * Admin Navbar JavaScript Functions
 * Handles theme toggling, dropdown interactions, and initialization
 */

// ===== THEME MANAGEMENT ===== //

/**
 * Toggle between light and dark themes
 */
function toggleTheme() {
    const body = document.body;
    const isDarkMode = body.classList.contains('dark-mode');

    if (isDarkMode) {
        body.classList.remove('dark-mode');
        localStorage.setItem('theme', 'light');
    } else {
        body.classList.add('dark-mode');
        localStorage.setItem('theme', 'dark');
    }

    // Update theme-dependent elements
    updateThemeElements();
}

/**
 * Update theme icons and other theme-dependent elements
 */
function updateThemeElements() {
    const lightIcons = document.querySelectorAll('.light-icon');
    const darkIcons = document.querySelectorAll('.dark-icon');
    const isDarkMode = document.body.classList.contains('dark-mode');

    // Toggle theme icons visibility
    lightIcons.forEach(icon => {
        icon.style.display = isDarkMode ? 'none' : 'inline';
    });

    darkIcons.forEach(icon => {
        icon.style.display = isDarkMode ? 'inline' : 'none';
    });
}

/**
 * Initialize theme based on saved preference
 */
function initializeTheme() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }
    updateThemeElements();
}

// ===== DROPDOWN MANAGEMENT ===== //

/**
 * Auto-hide notification dropdowns when clicking outside
 */
function initializeDropdownBehavior() {
    document.addEventListener('click', function(e) {
        // Close notification dropdown if clicking outside
        if (!e.target.closest('.notification-dropdown')) {
            const notificationDropdowns = document.querySelectorAll('.notification-dropdown');
            notificationDropdowns.forEach(dropdown => {
                const parentButton = dropdown.previousElementSibling;
                const bsDropdown = bootstrap.Dropdown.getInstance(parentButton);
                if (bsDropdown) {
                    bsDropdown.hide();
                }
            });
        }
    });
}

/**
 * Handle notification badge updates
 * @param {number} count - Number of unread notifications
 */
function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count.toString();
            badge.style.display = 'inline';
        } else {
            badge.style.display = 'none';
        }
    }
}

/**
 * Add new notification to the dropdown
 * @param {Object} notification - Notification object
 */
function addNotification(notification) {
    const dropdown = document.querySelector('.notification-dropdown');
    if (!dropdown) return;

    const notificationHTML = `
        <li>
            <a class="dropdown-item" href="${notification.link || '#'}">
                <div class="notification-item">
                    <div class="notification-icon ${notification.iconClass || 'bg-info'}">
                        <i data-lucide="${notification.icon || 'bell'}"></i>
                    </div>
                    <div class="notification-content">
                        <span class="notification-title">${notification.title}</span>
                        <span class="notification-time">${notification.time}</span>
                    </div>
                </div>
            </a>
        </li>
    `;

    // Insert after the header and divider
    const firstDivider = dropdown.querySelector('.dropdown-divider');
    if (firstDivider && firstDivider.parentNode) {
        firstDivider.parentNode.insertAdjacentHTML('afterend', notificationHTML);
    }

    // Re-initialize Lucide icons for new content
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// ===== SIDEBAR INTEGRATION ===== //

/**
 * Close sidebar
 */
function closeSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const body = document.body;
    const overlay = document.querySelector('.sidebar-overlay');

    if (sidebar) {
        sidebar.classList.remove('active');
        body.classList.remove('sidebar-open');
        if (overlay) {
            overlay.classList.remove('active');
        }
    }
}

/**
 * Open sidebar
 */
function openSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const body = document.body;
    const overlay = document.querySelector('.sidebar-overlay');

    if (sidebar) {
        sidebar.classList.add('active');
        body.classList.add('sidebar-open');
        if (overlay) {
            overlay.classList.add('active');
        }
    }
}

/**
 * Close mobile navbar
 */
function closeMobileNavbar() {
    const navbarCollapse = document.getElementById('navbarContent');
    if (navbarCollapse && navbarCollapse.classList.contains('show')) {
        const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
        if (bsCollapse) {
            bsCollapse.hide();
        }
    }
}

/**
 * Handle sidebar toggle functionality
 */
function initializeSidebarToggle() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const body = document.body;
    const overlay = document.querySelector('.sidebar-overlay') || createSidebarOverlay();

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Close mobile navbar if open
            closeMobileNavbar();
            
            // Toggle sidebar
            const isActive = sidebar.classList.contains('active');
            if (isActive) {
                closeSidebar();
            } else {
                openSidebar();
            }
            
            console.log('Sidebar toggled:', !isActive);
        });

        // Close sidebar when clicking overlay
        if (overlay) {
            overlay.addEventListener('click', function() {
                closeSidebar();
            });
        }
    }
}

/**
 * Create sidebar overlay if it doesn't exist
 */
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

// ===== RESPONSIVE BEHAVIOR ===== //

/**
 * Handle responsive navbar behavior
 */
function initializeResponsiveBehavior() {
    const navbarCollapse = document.getElementById('navbarContent');
    const navbarToggler = document.querySelector('.navbar-toggler');

    // Listen for navbar collapse events
    if (navbarCollapse) {
        // When navbar is about to show
        navbarCollapse.addEventListener('show.bs.collapse', function() {
            // Close sidebar when mobile navbar opens
            closeSidebar();
            console.log('Mobile navbar opening, sidebar closed');
        });

        // When navbar is hidden
        navbarCollapse.addEventListener('hidden.bs.collapse', function() {
            console.log('Mobile navbar closed');
        });
    }

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(e) {
        if (navbarCollapse && navbarToggler) {
            const isClickInsideNav = navbarCollapse.contains(e.target) || 
                                   navbarToggler.contains(e.target) ||
                                   e.target.closest('.dropdown-menu');
            
            if (!isClickInsideNav && navbarCollapse.classList.contains('show')) {
                closeMobileNavbar();
            }
        }
    });
}

// ===== UTILITY FUNCTIONS ===== //

/**
 * Show loading state for profile avatar
 */
function showProfileLoading() {
    const avatar = document.querySelector('.profile-avatar i');
    if (avatar) {
        avatar.setAttribute('data-lucide', 'loader-2');
        avatar.classList.add('animate-spin');
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

/**
 * Hide loading state for profile avatar
 */
function hideProfileLoading() {
    const avatar = document.querySelector('.profile-avatar i');
    if (avatar) {
        avatar.setAttribute('data-lucide', 'user');
        avatar.classList.remove('animate-spin');
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

/**
 * Update profile information
 * @param {Object} profileData - Profile data object
 */
function updateProfileInfo(profileData) {
    const profileName = document.querySelector('.profile-name');
    const welcomeName = document.querySelector('.welcome-name');
    
    if (profileName && profileData.name) {
        profileName.textContent = profileData.name;
    }
    
    if (welcomeName && profileData.name) {
        welcomeName.textContent = profileData.name;
    }
}

// ===== INITIALIZATION ===== //

/**
 * Initialize all navbar functionality
 */
function initializeNavbar() {
    // Initialize theme
    initializeTheme();
    
    // Initialize dropdowns
    initializeDropdownBehavior();
    
    // Initialize sidebar toggle
    initializeSidebarToggle();
    
    // Initialize responsive behavior
    initializeResponsiveBehavior();
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    console.log('Admin Navbar initialized successfully');
}

// ===== EVENT LISTENERS ===== //

/**
 * DOM Content Loaded Event
 */
document.addEventListener('DOMContentLoaded', function() {
    initializeNavbar();
});

/**
 * Window Load Event (for additional initialization)
 */
window.addEventListener('load', function() {
    // Any additional initialization after full page load
    updateThemeElements();
});

/**
 * Storage Event (for theme sync across tabs)
 */
window.addEventListener('storage', function(e) {
    if (e.key === 'theme') {
        const newTheme = e.newValue;
        const body = document.body;
        
        if (newTheme === 'dark') {
            body.classList.add('dark-mode');
        } else {
            body.classList.remove('dark-mode');
        }
        
        updateThemeElements();
    }
});

// ===== EXPORT FOR MODULE USAGE ===== //
// If using as a module, export the main functions
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        toggleTheme,
        updateNotificationBadge,
        addNotification,
        updateProfileInfo,
        initializeNavbar,
        closeSidebar,
        openSidebar,
        closeMobileNavbar
    };
}
// Replace your existing initializeSidebarToggle function with this enhanced version:

/**
 * Handle sidebar toggle functionality
 */
function initializeSidebarToggle() {
    console.log('Initializing sidebar toggle...');
    
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const body = document.body;
    let overlay = document.querySelector('.sidebar-overlay');

    // Create overlay if it doesn't exist
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.id = 'sidebarOverlay';
        body.appendChild(overlay);
        console.log('Sidebar overlay created');
    }

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Sidebar toggle clicked');
            
            // Check if mobile navbar is open
            const navbarCollapse = document.getElementById('navbarContent');
            const isMobileNavOpen = navbarCollapse && navbarCollapse.classList.contains('show');
            
            // If mobile navbar is open, don't allow sidebar to open
            if (isMobileNavOpen) {
                console.log('Mobile navbar is open, preventing sidebar toggle');
                return; // Exit early, don't toggle sidebar
            }
            
            // Check screen size - don't allow sidebar on small screens
            if (window.innerWidth < 992) {
                console.log('Screen too small for sidebar, preventing toggle');
                return; // Exit early on mobile devices
            }
            
            // Close mobile navbar if open (just in case)
            closeMobileNavbar();
            
            // Toggle sidebar
            const isActive = sidebar.classList.contains('active');
            if (isActive) {
                closeSidebar();
            } else {
                openSidebar();
            }
            
            console.log('Sidebar toggled:', !isActive);
        });

        // Close sidebar when clicking overlay
        if (overlay) {
            overlay.addEventListener('click', function() {
                console.log('Overlay clicked, closing sidebar');
                closeSidebar();
            });
        }
        
        console.log('Sidebar toggle initialized successfully');
    } else {
        console.error('Sidebar elements not found:', {
            toggle: !!sidebarToggle,
            sidebar: !!sidebar
        });
    }
}

/**
 * Enhanced responsive behavior
 */
function initializeResponsiveBehavior() {
    console.log('Initializing responsive behavior...');
    
    const navbarCollapse = document.getElementById('navbarContent');
    const navbarToggler = document.querySelector('.navbar-toggler');

    // Listen for navbar collapse events
    if (navbarCollapse) {
        // When mobile navbar is about to show
        navbarCollapse.addEventListener('show.bs.collapse', function() {
            console.log('Mobile navbar opening, closing sidebar and disabling sidebar toggle');
            
            // Close sidebar immediately
            closeSidebar();
            
            // Disable sidebar toggle button
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.style.pointerEvents = 'none';
                sidebarToggle.style.opacity = '0.5';
            }
        });

        // When mobile navbar is hidden
        navbarCollapse.addEventListener('hidden.bs.collapse', function() {
            console.log('Mobile navbar closed, re-enabling sidebar toggle');
            
            // Re-enable sidebar toggle button
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.style.pointerEvents = 'auto';
                sidebarToggle.style.opacity = '1';
            }
        });
        
        console.log('Responsive behavior initialized');
    }

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(e) {
        if (navbarCollapse && navbarToggler) {
            const isClickInsideNav = navbarCollapse.contains(e.target) || 
                                   navbarToggler.contains(e.target) ||
                                   e.target.closest('.dropdown-menu');
            
            if (!isClickInsideNav && navbarCollapse.classList.contains('show')) {
                closeMobileNavbar();
            }
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        const navbarCollapse = document.getElementById('navbarContent');
        
        // If screen becomes large and mobile menu is open, close it
        if (window.innerWidth >= 992 && navbarCollapse && navbarCollapse.classList.contains('show')) {
            closeMobileNavbar();
        }
        
        // If screen becomes small and sidebar is open, close it
        if (window.innerWidth < 992) {
            closeSidebar();
        }
    });
}

// Keep all your other existing functions unchanged...
// toggleTheme(), updateThemeElements(), initializeTheme(), etc.