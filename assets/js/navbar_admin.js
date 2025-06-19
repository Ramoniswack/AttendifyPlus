/**
 * Admin Navbar JavaScript Functions
 * Universal sidebar for both desktop and mobile
 */

// ===== THEME MANAGEMENT ===== //

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

    updateThemeElements();
    console.log('Theme toggled to:', isDarkMode ? 'light' : 'dark');
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

function initializeTheme() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }
    updateThemeElements();
    console.log('Theme initialized:', savedTheme || 'light');
}

// ===== UNIVERSAL SIDEBAR MANAGEMENT ===== //

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
        console.log('Sidebar closed');
    }
}

function openSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const body = document.body;
    const overlay = document.querySelector('.sidebar-overlay');

    if (sidebar) {
        // Close any open dropdowns first
        closeAllDropdowns();
        
        sidebar.classList.add('active');
        body.classList.add('sidebar-open');
        if (overlay) {
            overlay.classList.add('active');
        }
        console.log('Sidebar opened');
    }
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

function initializeSidebarToggle() {
    console.log('Initializing universal sidebar toggle...');
    
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
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    // Close sidebar when clicking overlay
    if (overlay) {
        overlay.addEventListener('click', function(e) {
            console.log('Overlay clicked, closing sidebar');
            closeSidebar();
        });
    }

    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    });
    
    console.log('Universal sidebar toggle initialized');
}

// ===== DROPDOWN MANAGEMENT ===== //

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

function initializeDropdownBehavior() {
    // Enhanced dropdown positioning
    document.addEventListener('show.bs.dropdown', function(e) {
        const dropdown = e.target.closest('.dropdown');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        // Close sidebar when dropdown opens on mobile
        if (window.innerWidth <= 991) {
            // Don't close sidebar, just ensure proper z-index
            if (menu) {
                menu.style.zIndex = '1056'; // Above sidebar
            }
        }
        
        // Ensure dropdown stays within viewport
        setTimeout(() => {
            const rect = menu.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            
            if (rect.right > viewportWidth) {
                menu.style.right = '0px';
                menu.style.left = 'auto';
            }
        }, 10);
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            closeAllDropdowns();
        }
    });

    // Close dropdowns when sidebar opens
    document.addEventListener('click', function(e) {
        if (e.target.closest('#sidebarToggle')) {
            closeAllDropdowns();
        }
    });

    console.log('Enhanced dropdown behavior initialized');
}

// ===== RESPONSIVE BEHAVIOR ===== //

function initializeResponsiveBehavior() {
    console.log('Initializing responsive behavior...');
    
    // Handle window resize
    window.addEventListener('resize', function() {
        // Close sidebar on resize for better UX
        if (window.innerWidth >= 992) {
            // Desktop: sidebar can stay open
            console.log('Desktop mode');
        } else {
            // Mobile: close sidebar on orientation change
            console.log('Mobile mode');
        }
        
        // Update theme elements
        updateThemeElements();
    });
    
    console.log('Responsive behavior initialized');
}

// ===== UTILITY FUNCTIONS ===== //

function updateNotificationBadge(count) {
    const desktopBadge = document.querySelector('.notification-badge');
    const mobileBadge = document.querySelector('.mobile-notification-badge');
    
    [desktopBadge, mobileBadge].forEach(badge => {
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count.toString();
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        }
    });
    
    console.log('Notification badge updated:', count);
}

function addNotification(notification) {
    const dropdowns = document.querySelectorAll('.notification-dropdown');
    
    dropdowns.forEach(dropdown => {
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

        const firstDivider = dropdown.querySelector('.dropdown-divider');
        if (firstDivider && firstDivider.parentNode) {
            firstDivider.parentNode.insertAdjacentHTML('afterend', notificationHTML);
        }
    });

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    console.log('Notification added:', notification.title);
}

// ===== INITIALIZATION ===== //

function initializeNavbar() {
    console.log('Starting enhanced navbar initialization...');
    
    try {
        // Initialize theme
        initializeTheme();
        
        // Initialize enhanced dropdowns
        initializeDropdownBehavior();
        
        // Initialize universal sidebar toggle
        initializeSidebarToggle();
        
        // Initialize responsive behavior
        initializeResponsiveBehavior();
        
        // Initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
            console.log('Lucide icons initialized');
        } else {
            console.warn('Lucide library not found');
        }
        
        console.log('Enhanced navbar initialized successfully');
        
    } catch (error) {
        console.error('Error initializing navbar:', error);
    }
}

// ===== EVENT LISTENERS ===== //

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing enhanced navbar...');
    initializeNavbar();
});

window.addEventListener('load', function() {
    console.log('Window loaded, finalizing navbar...');
    updateThemeElements();
});

// Theme sync across tabs
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
        console.log('Theme synced:', newTheme);
    }
});

// ===== EXPORT FUNCTIONS ===== //
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        toggleTheme,
        updateNotificationBadge,
        addNotification,
        initializeNavbar,
        closeSidebar,
        openSidebar
    };
}