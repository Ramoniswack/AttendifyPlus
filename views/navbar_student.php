<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\navbar_student.php
include 'sidebar_student_dashboard.php';
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
            <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard_student.php">
                <div class="brand-text">
                    <span class="brand-main">Attendify+</span>
                    <span class="brand-sub">Student Portal</span>
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

            <!-- Quick QR Scanner -->
            <a href="scan_qr.php" class="btn navbar-btn" title="Quick QR Scanner">
                <i data-lucide="qr-code"></i>
                <span class="btn-text d-none d-xxl-inline">Scan QR</span>
            </a>

            <!-- Notifications -->
            <div class="dropdown notification-dropdown-wrapper">
                <button class="btn navbar-btn notification-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i data-lucide="bell"></i>
                    <span class="notification-badge">2</span>
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
                                    <i data-lucide="file-plus"></i>
                                </div>
                                <div class="notification-content">
                                    <span class="notification-title">New Assignment Posted</span>
                                    <span class="notification-time">10 minutes ago</span>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#">
                            <div class="notification-item">
                                <div class="notification-icon bg-warning">
                                    <i data-lucide="clock"></i>
                                </div>
                                <div class="notification-content">
                                    <span class="notification-title">Assignment Due Tomorrow</span>
                                    <span class="notification-time">2 hours ago</span>
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
                                    <span class="notification-title">Attendance Marked</span>
                                    <span class="notification-time">1 day ago</span>
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
                                <i data-lucide="graduation-cap"></i>
                            </div>
                            <div class="profile-details">
                                <span class="profile-name"><?= htmlspecialchars($_SESSION['Username']) ?></span>
                                <span class="profile-role">Student</span>
                            </div>
                        </div>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <a class="dropdown-item" href="profile_student.php">
                            <i data-lucide="user"></i> My Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="attendance_history.php">
                            <i data-lucide="calendar-check"></i> My Attendance
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="my_subjects_student.php">
                            <i data-lucide="book-open"></i> My Subjects
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="view_assignments.php">
                            <i data-lucide="clipboard-list"></i> My Assignments
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
            <!-- Quick QR Scanner (Mobile) -->
            <a href="scan_qr.php" class="btn navbar-btn-mobile" title="Scan QR">
                <i data-lucide="qr-code"></i>
            </a>

            <!-- Theme Toggle -->
            <button class="btn navbar-btn-mobile theme-toggle-btn" onclick="toggleTheme()" title="Toggle Theme">
                <i data-lucide="sun" class="theme-icon light-icon"></i>
                <i data-lucide="moon" class="theme-icon dark-icon"></i>
            </button>

            <!-- Notifications -->
            <div class="dropdown">
                <button class="btn navbar-btn-mobile notification-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i data-lucide="bell"></i>
                    <span class="mobile-notification-badge">2</span>
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
                                    <i data-lucide="file-plus"></i>
                                </div>
                                <div class="notification-content">
                                    <span class="notification-title">New Assignment</span>
                                    <span class="notification-time">10 mins ago</span>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#">
                            <div class="notification-item">
                                <div class="notification-icon bg-warning">
                                    <i data-lucide="clock"></i>
                                </div>
                                <div class="notification-content">
                                    <span class="notification-title">Assignment Due</span>
                                    <span class="notification-time">2 hours ago</span>
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
                                <i data-lucide="graduation-cap"></i>
                            </div>
                            <div class="profile-details">
                                <span class="profile-name"><?= htmlspecialchars($_SESSION['Username']) ?></span>
                                <span class="profile-role">Student</span>
                            </div>
                        </div>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <a class="dropdown-item" href="profile_student.php">
                            <i data-lucide="user"></i> Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="attendance_history.php">
                            <i data-lucide="calendar-check"></i> Attendance
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="my_subjects_student.php">
                            <i data-lucide="book-open"></i> Subjects
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="view_assignments.php">
                            <i data-lucide="clipboard-list"></i> Assignments
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