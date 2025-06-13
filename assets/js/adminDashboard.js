document.addEventListener("DOMContentLoaded", () => {
  if (typeof lucide !== "undefined") lucide.createIcons();

  if (localStorage.getItem("theme") === "dark") {
    document.body.classList.add("dark-mode");
  }

  document.getElementById("sidebarToggle")?.addEventListener("click", () => {
    document.getElementById("sidebar")?.classList.toggle("active");
    document.body.classList.toggle("sidebar-open");
    document.querySelector(".sidebar-overlay")?.classList.toggle("show");
  });

  document.querySelector(".sidebar-overlay")?.addEventListener("click", () => {
    document.getElementById("sidebar")?.classList.remove("active");
    document.body.classList.remove("sidebar-open");
    document.querySelector(".sidebar-overlay")?.classList.remove("show");
  });

  window.toggleTheme = function () {
    const isDark = document.body.classList.toggle("dark-mode");
    localStorage.setItem("theme", isDark ? "dark" : "light");
  };

  // Example Chart (Admin Specific)
  const adminChart = document.getElementById("adminAnalyticsChart");
  if (adminChart) {
    new Chart(adminChart, {
      type: "bar",
      data: {
        labels: ["Students", "Teachers", "Admins", "Seminars"],
        datasets: [{
          label: "Counts",
          data: [350, 25, 3, 7],
          backgroundColor: ["#3b82f6", "#10b981", "#f59e0b", "#ef4444"]
        }]
      },
      options: {
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: "#000",
            titleColor: "#fff",
            bodyColor: "#fff"
          }
        },
        scales: {
          y: { beginAtZero: true },
          x: {}
        }
      }
    });
  }
});
