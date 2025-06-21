<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\navbar_admin.php
include 'sidebar_admin_dashboard.php';
?>

<nav class="navbar navbar-expand-lg navbar-dark admin-navbar">
    <div class="container-fluid">
        <!-- Left Section: Sidebar Toggle + Brand -->
        <div class="d-flex align-items-center">
            <!-- Universal Sidebar Toggle -->
            <button class="btn navbar-toggle-btn me-3" id="sidebarToggle" title="Toggle Sidebar">
                <i data-lucide="menu" class="navbar-icon"></i>
            </button>

            <!-- Brand - Always on Left -->
            <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard_admin.php">
                <div class="brand-text">
                    <span class="brand-main">Attendify+</span>
                    <span class="brand-sub">Admin Panel</span>
                </div>
            </a>
        </div>

        <!-- Right Section: Desktop Controls -->
        <div class="d-none d-lg-flex navbar-nav ms-auto align-items-center gap-2">
            <!-- User Welcome Message -->
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
                                <div class="notification-icon bg-warning">
                                    <i data-lucide="alert-triangle"></i>
                                </div>
                                <div class="notification-content">
                                    <span class="notification-title">System Alert</span>
                                    <span class="notification-time">5 minutes ago</span>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#">
                            <div class="notification-item">
                                <div class="notification-icon bg-info">
                                    <i data-lucide="user-plus"></i>
                                </div>
                                <div class="notification-content">
                                    <span class="notification-title">New Teacher Registered</span>
                                    <span class="notification-time">15 minutes ago</span>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#">
                            <div class="notification-item">
                                <div class="notification-icon bg-success">
                                    <i data-lucide="database"></i>
                                </div>
                                <div class="notification-content">
                                    <span class="notification-title">Database Backup Complete</span>
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
                        <i data-lucide="shield"></i>
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
                        <a class="dropdown-item" href="system_settings.php">
                            <i data-lucide="settings"></i> System Settings
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="admin_logs.php">
                            <i data-lucide="file-text"></i> Admin Logs
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="backup_restore.php">
                            <i data-lucide="database"></i> Backup & Restore
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

        <!-- Right Section: Mobile Controls -->
        <div class="d-flex d-lg-none align-items-center gap-1 ms-auto">
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
                                <div class="notification-icon bg-warning">
                                    <i data-lucide="alert-triangle"></i>
                                </div>
                                <div class="notification-content">
                                    <span class="notification-title">System Alert</span>
                                    <span class="notification-time">5 mins ago</span>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#">
                            <div class="notification-item">
                                <div class="notification-icon bg-info">
                                    <i data-lucide="user-plus"></i>
                                </div>
                                <div class="notification-content">
                                    <span class="notification-title">New Teacher</span>
                                    <span class="notification-time">15 mins ago</span>
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
                        <i data-lucide="shield"></i>
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
                        <a class="dropdown-item" href="system_settings.php">
                            <i data-lucide="settings"></i> Settings
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="admin_logs.php">
                            <i data-lucide="file-text"></i> Logs
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="backup_restore.php">
                            <i data-lucide="database"></i> Backup
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