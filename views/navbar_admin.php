<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\navbar_admin.php
include 'sidebar_admin_dashboard.php';
?>

<nav class="navbar navbar-expand-lg navbar-dark admin-navbar">
    <div class="container-fluid">
        <!-- Universal Sidebar Toggle (Works on both desktop and mobile) -->
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

        <!-- Desktop Navbar Content -->
        <div class="d-none d-lg-flex navbar-nav ms-auto align-items-center gap-2">
            <!-- User Welcome Message (Desktop only) -->
            <div class="navbar-text welcome-text">
                <span class="welcome-label">Welcome,</span>
                <span class="welcome-name"><?= htmlspecialchars($_SESSION['Username']) ?></span>
            </div>

            <!-- Theme Toggle -->
            <button class="btn navbar-btn theme-toggle-btn" onclick="toggleTheme()" title="Toggle Dark/Light Mode">
                <i data-lucide="sun" class="theme-icon light-icon"></i>
                <i data-lucide="moon" class="theme-icon dark-icon"></i>
                <span class="btn-text d-none d-xxl-inline">Theme</span>
            </button>

            <!-- Notifications -->
            <div class="dropdown notification-dropdown-wrapper">
                <button class="btn navbar-btn notification-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i data-lucide="bell"></i>
                    <span class="notification-badge">3</span>
                    <span class="btn-text d-none d-xxl-inline">Alerts</span>
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
            <div class="dropdown profile-dropdown-wrapper">
                <button class="btn navbar-btn profile-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Profile Menu">
                    <div class="profile-avatar">
                        <i data-lucide="user"></i>
                    </div>
                    <span class="btn-text d-none d-xxl-inline"><?= htmlspecialchars($_SESSION['Username']) ?></span>
                    <i data-lucide="chevron-down" class="dropdown-arrow d-none d-xxl-inline"></i>
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

        <!-- Mobile Action Buttons (Theme, Notifications, Profile only) -->
        <div class="d-flex d-lg-none align-items-center gap-1">
            <!-- Theme Toggle -->
            <button class="btn navbar-btn-mobile theme-toggle-btn" onclick="toggleTheme()" title="Toggle Theme">
                <i data-lucide="sun" class="theme-icon light-icon"></i>
                <i data-lucide="moon" class="theme-icon dark-icon"></i>
            </button>

            <!-- Notifications -->
            <div class="dropdown">
                <button class="btn navbar-btn-mobile notification-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i data-lucide="bell"></i>
                    <span class="mobile-notification-badge">3</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end notification-dropdown mobile-dropdown">
                    <li class="dropdown-header">
                        <i data-lucide="bell"></i> Notifications
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
                                    <span class="notification-time">2 mins ago</span>
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
                                    <span class="notification-time">5 mins ago</span>
                                </div>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Profile -->
            <div class="dropdown">
                <button class="btn navbar-btn-mobile profile-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Profile">
                    <div class="profile-avatar-small">
                        <i data-lucide="user"></i>
                    </div>
                </button>
                <ul class="dropdown-menu dropdown-menu-end profile-dropdown mobile-dropdown">
                    <li class="dropdown-header">
                        <div class="profile-info-mobile">
                            <div class="profile-avatar-medium">
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
</nav>

<link rel="stylesheet" href="../assets/css/navbar_admin.css">