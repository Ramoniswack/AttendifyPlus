<nav class="navbar navbar-expand-lg navbar-dark  " style="background-color: var(--accent-light);">
    <div class="container-fluid">
        <button class="btn text-white me-2" id="sidebarToggle">
            <span style="font-size: 24px;">☰</span>
        </button>
        <a class="navbar-brand d-flex align-items-center gap-2" href="#">
            <span>Attendify+ | Admin</span>
        </a>
        <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
            <span class="navbar-text text-white">Welcome, <?= htmlspecialchars($_SESSION['Username']) ?></span>
            <button class="btn btn-outline-light btn-sm" onclick="toggleTheme()" title="Toggle Theme">
                <i data-lucide="moon" class="me-1"></i>Theme
            </button>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">
                <i data-lucide="log-out" class="me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>