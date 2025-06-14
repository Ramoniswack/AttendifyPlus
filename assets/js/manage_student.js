document.addEventListener("DOMContentLoaded", () => {
  if (typeof lucide !== "undefined") lucide.createIcons();

  // Theme
    if (localStorage.getItem("theme") === "dark") {
      document.body.classList.add("dark-mode");
    }

    window.toggleTheme = function () {
      const isDark = document.body.classList.toggle("dark-mode");
      localStorage.setItem("theme", isDark ? "dark" : "light");
    };

  // Sidebar
  const sidebarToggle = document.getElementById("sidebarToggle");
  const sidebarOverlay = document.querySelector(".sidebar-overlay");
  sidebarToggle?.addEventListener("click", () => {
    document.getElementById("sidebar")?.classList.toggle("active");
    document.body.classList.toggle("sidebar-open");
    sidebarOverlay?.classList.toggle("show");
  });
  sidebarOverlay?.addEventListener("click", () => {
    document.getElementById("sidebar")?.classList.remove("active");
    document.body.classList.remove("sidebar-open");
    sidebarOverlay.classList.remove("show");
  });

  // Add student
  const addStudentForm = document.getElementById("addStudentForm");
  addStudentForm?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const formData = new FormData(addStudentForm);
    formData.append("action", "add");

    try {
      const res = await fetch("student_crud.php", {
        method: "POST",
        body: formData,
      });

      if (res.ok) {
        alert("Student added successfully!");
        fetchStudents(); // reload student list
        addStudentForm.reset();
        bootstrap.Modal.getInstance(
          document.getElementById("addStudentModal")
        ).hide();
      } else {
        alert("Failed to add student.");
      }
    } catch (err) {
      alert("Error adding student: " + err.message);
    }
  });

  // Filter inputs
  const filterDepartment = document.getElementById("filterDepartment");
  const filterYear = document.getElementById("filterYear");
  const searchName = document.getElementById("searchName");

  [filterDepartment, filterYear, searchName].forEach((input) => {
    input?.addEventListener("input", fetchStudents);
  });

  // Load students on page load
  fetchStudents();

  async function fetchStudents() {
    const department = filterDepartment.value.trim();
    const joinYear = filterYear.value.trim();
    const name = searchName.value.trim();

    const params = new URLSearchParams();
    if (department) params.append("department", department);
    if (joinYear) params.append("joinYear", joinYear);
    if (name) params.append("name", name);

    try {
      const res = await fetch("search_students.php?" + params.toString());
      const students = await res.json();

      const tbody = document.getElementById("studentsBody");
      const thead = document.getElementById("studentsHeader");

      tbody.innerHTML = "";

      if (students.length > 0) {
        thead.style.display = "";
        students.forEach((s) => {
          const row = document.createElement("tr");
          row.dataset.id = s.StudentID;

          row.innerHTML = `
            <td>${s.FullName}</td>
            <td>${s.DepartmentName}</td>
            <td>${s.BatchID}</td>
            <td>${s.RollNo}</td>
            <td>${s.JoinYear}</td>
            <td>${s.Status}</td>
            <td>
              <button class="btn btn-sm btn-warning edit-btn"><i data-lucide="edit"></i></button>
              <button class="btn btn-sm btn-danger delete-btn"><i data-lucide="trash-2"></i></button>
            </td>
          `;
          tbody.appendChild(row);
        });

        lucide.createIcons();
        attachActions();
      } else {
        thead.style.display = "none";
        tbody.innerHTML =
          '<tr><td colspan="7" class="text-center">No results found.</td></tr>';
      }
    } catch (err) {
      console.error("Error loading students:", err);
    }
  }

  function attachActions() {
    document.querySelectorAll(".delete-btn").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const row = btn.closest("tr");
        const studentId = row.dataset.id;
        if (!confirm(`Delete student ${studentId}?`)) return;

        try {
          const res = await fetch("student_crud.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `action=delete&id=${encodeURIComponent(studentId)}`,
          });

          if (res.ok) {
            alert("Deleted.");
            fetchStudents();
          } else {
            alert("Failed to delete.");
          }
        } catch (err) {
          alert("Error deleting: " + err.message);
        }
      });
    });

    document.querySelectorAll(".edit-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        const row = btn.closest("tr");
        const studentId = row.dataset.id;
        alert(`Edit student ${studentId} — coming soon.`);
      });
    });
  }
});
