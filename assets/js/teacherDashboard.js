document.addEventListener("DOMContentLoaded", () => {
  // Initialize Lucide icons
  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }

  // Apply theme from localStorage
  if (localStorage.getItem("theme") === "dark") {
    document.body.classList.add("dark-mode");
  }

  // Sidebar toggle functionality
  const toggleBtn = document.getElementById("sidebarToggle");
  const sidebar = document.getElementById("sidebar");
  const overlay = document.querySelector(".sidebar-overlay");

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener("click", () => {
      sidebar.classList.toggle("active");
      document.body.classList.toggle("sidebar-open");
      overlay?.classList.toggle("show");
    });
  }

  if (overlay) {
    overlay.addEventListener("click", () => {
      sidebar?.classList.remove("active");
      document.body.classList.remove("sidebar-open");
      overlay.classList.remove("show");
    });
  }

  // Toggle theme and save
  window.toggleTheme = function () {
    const isDark = document.body.classList.toggle("dark-mode");
    localStorage.setItem("theme", isDark ? "dark" : "light");
    updateChartsForTheme();
  };

  // Chart.js - Global defaults
  Chart.defaults.responsive = true;
  Chart.defaults.maintainAspectRatio = false;
  Chart.defaults.plugins.legend.display = false;

  const attendanceCtx = document.getElementById("attendanceChart");
  if (attendanceCtx) {
    window.attendanceChart = new Chart(attendanceCtx, {
      type: "line",
      data: {
        labels: ["Mon", "Tue", "Wed", "Thu", "Fri"],
        datasets: [{
          label: "Attendance %",
          data: [85, 82, 78, 88, 90],
          borderColor: "#3b82f6",
          backgroundColor: "rgba(59,130,246,0.2)",
          tension: 0.4,
          fill: true
        }]
      },
      options: {
        plugins: {
          legend: { display: true },
          tooltip: {
            backgroundColor: "#000",
            titleColor: "#fff",
            bodyColor: "#fff"
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            ticks: { callback: val => val + "%" },
            grid: {}
          },
          x: { grid: {} }
        }
      }
    });
  }

  const assignmentCtx = document.getElementById("assignmentChart");
  if (assignmentCtx) {
    window.assignmentChart = new Chart(assignmentCtx, {
      type: "bar",
      data: {
        labels: ["Week 1", "Week 2", "Week 3", "Week 4"],
        datasets: [{
          label: "Submissions",
          data: [32, 45, 38, 50],
          backgroundColor: "#8b5cf6"
        }]
      },
      options: {
        plugins: {
          legend: { display: true },
          tooltip: {
            backgroundColor: "#000",
            titleColor: "#fff",
            bodyColor: "#fff"
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {}
          },
          x: { grid: {} }
        }
      }
    });
  }

  const doughnutData = [
    { id: "readingChart", labels: ["Read", "Unread"], data: [65, 35], color: "#10b981" },
    { id: "completionChart", labels: ["Completed", "Pending"], data: [72, 28], color: "#3b82f6" },
    { id: "classChart", labels: ["Present", "Absent"], data: [80, 20], color: "#f59e0b" }
  ];

  doughnutData.forEach(({ id, labels, data, color }) => {
    const el = document.getElementById(id);
    if (el) {
      new Chart(el, {
        type: "doughnut",
        data: {
          labels,
          datasets: [{
            data,
            backgroundColor: [color, "#e5e7eb"],
            borderWidth: 0
          }]
        },
        options: {
          cutout: "70%",
          plugins: {
            legend: { position: "bottom" },
            tooltip: {
              backgroundColor: "#000",
              titleColor: "#fff",
              bodyColor: "#fff"
            }
          }
        }
      });
    }
  });

  updateChartsForTheme(); // Apply current theme to charts
});

function updateChartsForTheme() {
  const isDark = document.body.classList.contains("dark-mode");
  const gridColor = isDark ? "#374151" : "#e5e7eb";
  const textColor = isDark ? "#f3f4f6" : "#1f2937";

  Chart.defaults.color = textColor;

  if (window.attendanceChart) {
    window.attendanceChart.options.scales.y.grid.color = gridColor;
    window.attendanceChart.options.scales.x.grid.color = gridColor;
    window.attendanceChart.update();
  }

  if (window.assignmentChart) {
    window.assignmentChart.options.scales.y.grid.color = gridColor;
    window.assignmentChart.options.scales.x.grid.color = gridColor;
    window.assignmentChart.update();
  }
}
