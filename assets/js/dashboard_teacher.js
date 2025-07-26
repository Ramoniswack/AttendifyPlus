document.addEventListener("DOMContentLoaded", () => {
  // Initialize Lucide icons
  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }

  // Apply theme from localStorage
  if (localStorage.getItem("theme") === "dark") {
    document.body.classList.add("dark-mode");
  }

  // Note: Sidebar functionality now handled by sidebar_teacher.js

  // Theme toggle function
  window.toggleTheme = function () {
    const isDark = document.body.classList.toggle("dark-mode");
    localStorage.setItem("theme", isDark ? "dark" : "light");
    updateThemeElements();
    updateChartsForTheme();
  };

  // Update theme elements
  updateThemeElements();
});

// ===== SIDEBAR MANAGEMENT NOW IN SIDEBAR_TEACHER.JS ===== //

function ensureSidebarHidden() {
  console.log('Ensuring sidebar is hidden on load...');
  
  const sidebar = document.querySelector('.sidebar');
  const body = document.body;
  const overlay = document.querySelector('.sidebar-overlay');
  
  if (sidebar) {
    sidebar.classList.remove('active');
    sidebar.style.left = '-280px';
    sidebar.style.visibility = 'hidden';
    sidebar.style.opacity = '0';
  }
  
  if (body) {
    body.classList.remove('sidebar-open');
  }
  
  if (overlay) {
    overlay.classList.remove('active');
    overlay.style.opacity = '0';
    overlay.style.visibility = 'hidden';
  }
  
  console.log('Sidebar forced to hidden state');
}

function closeSidebar() {
  const sidebar = document.querySelector('.sidebar');
  const body = document.body;
  const overlay = document.querySelector('.sidebar-overlay');

  if (sidebar) {
    sidebar.classList.remove('active');
    body.classList.remove('sidebar-open');
    
    sidebar.style.left = '-280px';
    sidebar.style.visibility = 'hidden';
    sidebar.style.opacity = '0';
    
    if (overlay) {
      overlay.classList.remove('active');
      overlay.style.opacity = '0';
      overlay.style.visibility = 'hidden';
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
    
    sidebar.style.left = '0';
    sidebar.style.visibility = 'visible';
    sidebar.style.opacity = '1';
    
    if (overlay) {
      overlay.classList.add('active');
      overlay.style.opacity = '1';
      overlay.style.visibility = 'visible';
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
  console.log('Initializing sidebar toggle...');
  
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
  
  console.log('Sidebar toggle initialized');
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

// ===== THEME MANAGEMENT ===== //

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

function updateChartsForTheme() {
  // Check if Chart.js is available before proceeding
  if (typeof Chart === 'undefined') {
    console.log('Chart.js not available, skipping chart theme update');
    return;
  }
  
  const isDark = document.body.classList.contains("dark-mode");
  const gridColor = isDark ? "#374151" : "#e5e7eb";
  const textColor = isDark ? "#f3f4f6" : "#1f2937";

  Chart.defaults.color = textColor;

  // Update existing charts if they exist
  if (window.attendanceChart && typeof window.attendanceChart.update === 'function') {
    window.attendanceChart.options.scales.y.grid.color = gridColor;
    window.attendanceChart.options.scales.x.grid.color = gridColor;
    window.attendanceChart.update();
  }

  if (window.assignmentChart && typeof window.assignmentChart.update === 'function') {
    window.assignmentChart.options.scales.y.grid.color = gridColor;
    window.assignmentChart.options.scales.x.grid.color = gridColor;
    window.assignmentChart.update();
  }
}

// Handle page resize
window.addEventListener('resize', function() {
  closeSidebar();
  updateThemeElements();
});

// Handle page load
window.addEventListener('load', function() {
  ensureSidebarHidden();
  updateThemeElements();
});