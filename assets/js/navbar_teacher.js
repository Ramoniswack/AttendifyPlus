/**
 * Teacher Navbar JavaScript Functions
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

// ===== NOTIFICATION FUNCTIONS ===== //

function fetchAndRenderNotifications() {
    fetch('../../api/get_notifications.php')
        .then(res => res.json())
        .then(data => {
            console.log('Raw notification data from API:', data);
            renderNotificationDropdown(data);
            updateNotificationBadge(data.filter(n => !n.is_read).length);
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
        });
}

function renderNotificationDropdown(notifications) {
    // Ensure notifications are sorted by latest first (double-check sorting)
    const sortedNotifications = [...notifications].sort((a, b) => {
        const dateA = new Date(a.created_at);
        const dateB = new Date(b.created_at);
        if (dateA.getTime() === dateB.getTime()) {
            return b.id - a.id; // If same time, higher ID first
        }
        return dateB - dateA; // Latest first
    });
    
    console.log('Rendering notifications in order from API:', sortedNotifications.map(n => ({ id: n.id, title: n.title, message: n.message, created_at: n.created_at })));
    
    const dropdowns = document.querySelectorAll('.notification-dropdown');
    dropdowns.forEach(dropdown => {
        // Clear existing dynamic notifications
        dropdown.querySelectorAll('.dynamic-notification').forEach(el => el.remove());
        
        // Insert Mark All as Read button after header
        const header = dropdown.querySelector('.dropdown-header');
        if (header && !dropdown.querySelector('.mark-all-read-btn')) {
            const markAllBtn = document.createElement('button');
            markAllBtn.className = 'dropdown-item mark-all-read-btn';
            markAllBtn.type = 'button';
            markAllBtn.textContent = 'Mark All as Read';
            markAllBtn.onclick = function(e) {
                e.preventDefault();
                markAllNotificationsAsRead();
            };
            header.insertAdjacentElement('afterend', markAllBtn);
        }
        
        // Insert notifications in correct order (latest first)
        sortedNotifications.forEach(n => {
            const li = document.createElement('li');
            li.className = 'dynamic-notification';
            li.innerHTML = `
                <a class="dropdown-item${n.is_read ? '' : ' fw-bold'}" href="#" onclick="markNotificationAsRead(${n.id})">
                    <div class="notification-item">
                        <div class="notification-icon bg-${n.type || 'info'}">
                            <i data-lucide="${n.icon || 'bell'}"></i>
                        </div>
                        <div class="notification-content">
                            <span class="notification-title">${n.title}</span>
                            <span class="notification-message">${n.message}</span>
                            <span class="notification-time">${new Date(n.created_at).toLocaleString()}</span>
                        </div>
                    </div>
                </a>
            `;
            const firstDivider = dropdown.querySelector('.dropdown-divider');
            if (firstDivider) firstDivider.parentNode.insertBefore(li, firstDivider.nextSibling);
        });
        
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
}

// Function to mark notification as read
function markNotificationAsRead(notificationId) {
    fetch('../../api/mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the notification to show as read (remove bold)
            const notificationElement = document.querySelector(`[onclick="markNotificationAsRead(${notificationId})"]`);
            if (notificationElement) {
                notificationElement.classList.remove('fw-bold');
            }
            // Refresh notifications to update badge count only
            fetchAndRenderNotifications();
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

function markAllNotificationsAsRead() {
    fetch('../../api/mark_all_notifications_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            fetchAndRenderNotifications();
        }
    });
}

function updateNotificationBadge(unreadCount) {
    const desktopBadge = document.querySelector('.notification-badge');
    const mobileBadge = document.querySelector('.mobile-notification-badge');
    [desktopBadge, mobileBadge].forEach(badge => {
        if (badge) {
            if (unreadCount > 0) {
                badge.textContent = unreadCount > 99 ? '99+' : unreadCount.toString();
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        }
    });
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
    fetchAndRenderNotifications(); // Fetch on page load
    setInterval(fetchAndRenderNotifications, 30000); // Auto-refresh every 30 seconds
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
        initializeNavbar,
        closeSidebar,
        openSidebar
    };
}

