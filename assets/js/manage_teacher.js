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


});
