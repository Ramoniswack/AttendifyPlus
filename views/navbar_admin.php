\
<nav class="navbar navbar-expand-lg navbar-dark admin-navbar">
    <div class="container-fluid">
        <!-- Sidebar Toggle Button -->
        <button class="btn navbar-toggle-btn me-3" id="sidebarToggle" title="Toggle Sidebar">
            <i data-lucide="menu" class="navbar-icon"></i>
        </button>

        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard_admin.php">
            <div class="brand-text">
                <span class="brand-main">Attendify+</span>
                <span class="brand-sub">Admin Panel</span>
            </div>
        </a>

        <!-- Mobile Menu Toggle -->
        <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
            <i data-lucide="more-vertical"></i>
        </button>

        <!-- Navbar Content -->
        <div class="collapse navbar-collapse" id="navbarContent">
            <div class="navbar-nav ms-auto d-flex align-items-center gap-2">
                <!-- User Welcome Message -->
                <div class="navbar-text welcome-text d-none d-md-block">
                    <span class="welcome-label">Welcome,</span>
                    <span class="welcome-name"><?= htmlspecialchars($_SESSION['Username']) ?></span>
                </div>

                <!-- Theme Toggle -->
                <button class="btn navbar-btn theme-toggle-btn" onclick="toggleTheme()" title="Toggle Dark/Light Mode">
                    <i data-lucide="sun" class="theme-icon light-icon"></i>
                    <i data-lucide="moon" class="theme-icon dark-icon"></i>
                    <span class="btn-text d-none d-xl-inline">Theme</span>
                </button>

                <!-- Notifications -->
                <div class="dropdown">
                    <button class="btn navbar-btn notification-btn" type="button" data-bs-toggle="dropdown" title="Notifications">
                        <i data-lucide="bell"></i>
                        <span class="notification-badge">3</span>
                        <span class="btn-text d-none d-xl-inline">Alerts</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end notification-dropdown">
                        <li class="dropdown-header">
                            <i data-lucide="bell"></i> Recent Notifications
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item" href="#">
                                <div class="notification-item">
                                    <div class="notification-icon bg-info">
                                        <i data-lucide="user-plus"></i>
                                    </div>
                                    <div class="notification-content">
                                        <span class="notification-title">New Teacher Added</span>
                                        <span class="notification-time">2 minutes ago</span>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#">
                                <div class="notification-item">
                                    <div class="notification-icon bg-success">
                                        <i data-lucide="check-circle"></i>
                                    </div>
                                    <div class="notification-content">
                                        <span class="notification-title">Subject Updated</span>
                                        <span class="notification-time">5 minutes ago</span>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#">
                                <div class="notification-item">
                                    <div class="notification-icon bg-warning">
                                        <i data-lucide="alert-circle"></i>
                                    </div>
                                    <div class="notification-content">
                                        <span class="notification-title">System Backup</span>
                                        <span class="notification-time">1 hour ago</span>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-center" href="#">View All Notifications</a></li>
                    </ul>
                </div>

                <!-- Profile Dropdown -->
                <div class="dropdown">
                    <button class="btn navbar-btn profile-btn" type="button" data-bs-toggle="dropdown" title="Profile Menu">
                        <div class="profile-avatar">
                            <i data-lucide="user"></i>
                        </div>
                        <span class="btn-text d-none d-xl-inline"><?= htmlspecialchars($_SESSION['Username']) ?></span>
                        <i data-lucide="chevron-down" class="dropdown-arrow d-none d-xl-inline"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
                        <li class="dropdown-header">
                            <div class="profile-info">
                                <div class="profile-avatar-large">
                                    <i data-lucide="shield-check"></i>
                                </div>
                                <div class="profile-details">
                                    <span class="profile-name"><?= htmlspecialchars($_SESSION['Username']) ?></span>
                                    <span class="profile-role">Administrator</span>
                                </div>
                            </div>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item" href="profile_admin.php">
                                <i data-lucide="user"></i> My Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="settings_admin.php">
                                <i data-lucide="settings"></i> Settings
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="activity_logs.php">
                                <i data-lucide="activity"></i> Activity Logs
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item text-danger" href="../logout.php">
                                <i data-lucide="log-out"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<style>
    /* ===== ADMIN NAVBAR STYLES ===== */
    .admin-navbar {
        background: linear-gradient(135deg, var(--accent-light) 0%, #1557b0 100%) !important;
        box-shadow: 0 2px 20px rgba(26, 115, 232, 0.3);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        min-height: 60px;
        z-index: 1060;
        transition: all 0.3s ease;
    }

    body.dark-mode .admin-navbar {
        background: linear-gradient(135deg, #065f46 0%, #047857 100%) !important;
        box-shadow: 0 2px 20px rgba(6, 95, 70, 0.4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* ===== NAVBAR TOGGLE BUTTON ===== */
    .navbar-toggle-btn {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        border-radius: 10px;
        padding: 8px 12px;
        transition: all 0.3s ease;
    }

    .navbar-toggle-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.3);
        color: white;
        transform: translateY(-1px);
    }

    body.dark-mode .navbar-toggle-btn {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.2);
        color: white;
    }

    body.dark-mode .navbar-toggle-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.3);
        color: white;
    }

    .navbar-icon {
        width: 20px;
        height: 20px;
    }

    /* ===== BRAND STYLING ===== */
    .navbar-brand {
        text-decoration: none;
        color: white !important;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .navbar-brand:hover {
        color: rgba(255, 255, 255, 0.9) !important;
        transform: translateY(-1px);
    }

    body.dark-mode .navbar-brand {
        color: white !important;
    }

    body.dark-mode .navbar-brand:hover {
        color: rgba(255, 255, 255, 0.9) !important;
    }

    .brand-text {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }

    .brand-main {
        font-size: 1.25rem;
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    .brand-sub {
        font-size: 0.8rem;
        opacity: 0.8;
        font-weight: 400;
    }

    /* ===== WELCOME TEXT ===== */
    .welcome-text {
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.95rem;
        margin-right: 1rem;
    }

    body.dark-mode .welcome-text {
        color: rgba(255, 255, 255, 0.9);
    }

    .welcome-label {
        font-weight: 400;
        margin-right: 0.5rem;
    }

    .welcome-name {
        font-weight: 600;
        background: rgba(255, 255, 255, 0.1);
        padding: 2px 8px;
        border-radius: 4px;
    }

    body.dark-mode .welcome-name {
        background: rgba(255, 255, 255, 0.15);
    }

    /* ===== NAVBAR BUTTONS ===== */
    .navbar-btn {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        border-radius: 8px;
        padding: 8px 12px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .navbar-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.3);
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 255, 255, 0.2);
    }

    body.dark-mode .navbar-btn {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.2);
        color: white;
    }

    body.dark-mode .navbar-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.3);
        color: white;
        box-shadow: 0 4px 12px rgba(255, 255, 255, 0.2);
    }

    /* ===== THEME TOGGLE BUTTON ===== */
    .theme-toggle-btn {
        position: relative;
    }

    .theme-icon {
        width: 16px;
        height: 16px;
        transition: all 0.3s ease;
    }

    .dark-icon {
        display: none;
    }

    body.dark-mode .light-icon {
        display: none;
    }

    body.dark-mode .dark-icon {
        display: inline;
    }

    /* ===== NOTIFICATION BUTTON ===== */
    .notification-btn {
        position: relative;
    }

    .notification-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #ff4757;
        color: white;
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 10px;
        min-width: 18px;
        text-align: center;
        font-weight: 600;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }
    }

    /* ===== PROFILE BUTTON ===== */
    .profile-btn {
        min-width: auto;
    }

    .profile-avatar {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    body.dark-mode .profile-avatar {
        background: rgba(255, 255, 255, 0.2);
    }

    .profile-avatar i {
        width: 16px;
        height: 16px;
    }

    .dropdown-arrow {
        width: 14px;
        height: 14px;
        transition: transform 0.3s ease;
    }

    .dropdown.show .dropdown-arrow {
        transform: rotate(180deg);
    }

    /* ===== DROPDOWN MENUS ===== */
    .dropdown-menu {
        background: var(--card-light);
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        padding: 0.5rem 0;
        min-width: 220px;
        margin-top: 0.5rem;
    }

    body.dark-mode .dropdown-menu {
        background: var(--card-dark);
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }

    .dropdown-header {
        padding: 0.75rem 1rem;
        font-weight: 600;
        color: var(--text-light);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    body.dark-mode .dropdown-header {
        color: var(--text-dark);
    }

    .dropdown-item {
        padding: 0.5rem 1rem;
        color: var(--text-light);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }

    body.dark-mode .dropdown-item {
        color: var(--text-dark);
    }

    .dropdown-item:hover {
        background: var(--hover-light);
        color: var(--accent-light);
    }

    body.dark-mode .dropdown-item:hover {
        background: var(--hover-dark);
        color: #047857;
    }

    .dropdown-item.text-danger {
        color: #dc3545 !important;
    }

    .dropdown-item.text-danger:hover {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545 !important;
    }

    /* ===== PROFILE DROPDOWN SPECIFIC ===== */
    .profile-dropdown {
        min-width: 280px;
    }

    .profile-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 0;
    }

    .profile-avatar-large {
        background: var(--accent-light);
        border-radius: 50%;
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    body.dark-mode .profile-avatar-large {
        background: #047857;
        color: white;
    }

    .profile-avatar-large i {
        width: 24px;
        height: 24px;
    }

    .profile-details {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .profile-name {
        font-weight: 600;
        color: var(--text-light);
    }

    body.dark-mode .profile-name {
        color: var(--text-dark);
    }

    .profile-role {
        font-size: 0.8rem;
        color: var(--text-muted-light);
    }

    body.dark-mode .profile-role {
        color: var(--text-muted-dark);
    }

    /* ===== NOTIFICATION DROPDOWN SPECIFIC ===== */
    .notification-dropdown {
        min-width: 320px;
        max-height: 400px;
        overflow-y: auto;
    }

    .notification-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.25rem 0;
    }

    .notification-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .notification-icon i {
        width: 16px;
        height: 16px;
    }

    .notification-content {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        flex: 1;
    }

    .notification-title {
        font-weight: 500;
        font-size: 0.9rem;
        color: var(--text-light);
    }

    body.dark-mode .notification-title {
        color: var(--text-dark);
    }

    .notification-time {
        font-size: 0.8rem;
        color: var(--text-muted-light);
    }

    body.dark-mode .notification-time {
        color: var(--text-muted-dark);
    }

    /* ===== MOBILE RESPONSIVE ===== */
    @media (max-width: 991.98px) {
        .navbar-collapse {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            margin-top: 1rem;
            padding: 1rem;
        }

        body.dark-mode .navbar-collapse {
            background: rgba(255, 255, 255, 0.1);
        }

        .navbar-nav {
            flex-direction: column;
            gap: 0.5rem;
        }

        .welcome-text {
            order: -1;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .navbar-btn {
            justify-content: center;
            width: 100%;
        }

        .btn-text {
            display: inline !important;
        }
    }

    @media (max-width: 768px) {
        .brand-text {
            font-size: 0.9rem;
        }

        .brand-main {
            font-size: 1.1rem;
        }

        .brand-sub {
            font-size: 0.75rem;
        }
    }

    @media (max-width: 576px) {
        .navbar-toggle-btn {
            padding: 6px 10px;
        }

        .navbar-icon {
            width: 18px;
            height: 18px;
        }

        .dropdown-menu {
            min-width: 200px;
        }

        .profile-dropdown {
            min-width: 240px;
        }

        .notification-dropdown {
            min-width: 280px;
        }

        .brand-main {
            font-size: 1rem;
        }

        .brand-sub {
            font-size: 0.7rem;
        }
    }

    /* ===== NAVBAR TOGGLER ===== */
    .navbar-toggler {
        border: none;
        padding: 0.25rem 0.5rem;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 6px;
    }

    .navbar-toggler:focus {
        box-shadow: none;
    }

    .navbar-toggler i {
        color: white;
        width: 20px;
        height: 20px;
    }

    body.dark-mode .navbar-toggler {
        background: rgba(255, 255, 255, 0.1);
    }

    body.dark-mode .navbar-toggler i {
        color: white;
    }
</style>

<script>
    // Enhanced theme toggle functionality
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

        // Update theme icons
        updateThemeElements();
    }

    function updateThemeElements() {
        const lightIcons = document.querySelectorAll('.light-icon');
        const darkIcons = document.querySelectorAll('.dark-icon');
        const isDarkMode = document.body.classList.contains('dark-mode');

        // Update theme icons
        lightIcons.forEach(icon => {
            icon.style.display = isDarkMode ? 'none' : 'inline';
        });

        darkIcons.forEach(icon => {
            icon.style.display = isDarkMode ? 'inline' : 'none';
        });
    }

    // Initialize theme on page load
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
        }
        updateThemeElements();

        // Initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });

    // Auto-hide notifications after interaction
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.notification-dropdown')) {
            const notificationDropdowns = document.querySelectorAll('.notification-dropdown');
            notificationDropdowns.forEach(dropdown => {
                const bsDropdown = bootstrap.Dropdown.getInstance(dropdown.previousElementSibling);
                if (bsDropdown) {
                    bsDropdown.hide();
                }
            });
        }
    });
</script>