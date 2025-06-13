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

function searchTable() {
  var input = document.getElementById("searchInput").value.toLowerCase();
  var table = document.getElementById("teacherTable");
  var trs = table.getElementsByTagName("tr");

  for (var i = 1; i < trs.length; i++) {
    var id = trs[i].cells[0].textContent.toLowerCase();
    var name = trs[i].cells[1].textContent.toLowerCase();

    if (id.includes(input) || name.includes(input)) {
      trs[i].style.display = "";
    } else {
      trs[i].style.display = "none";
    }
  }
}

document.getElementById("teacherForm").addEventListener("submit", function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  fetch("teacher_action.php", {
    method: "POST",
    body: formData,
  })
  .then(res => res.json())
  .then(data => {
    alert(data.message);
    if (data.success) {
      location.reload(); // Reload to update table
    }
  });
});

// Open modal for adding
function addTable() {
  document.getElementById("teacherForm").reset();
  document.getElementById("teacherId").value = "";
  document.getElementById("teacherModalLabel").innerText = "Add Teacher";
  new bootstrap.Modal(document.getElementById("teacherModal")).show();
}

// Open modal for editing selected row
function editTable() {
  const selectedRow = document.querySelector("tr.selected");
  if (!selectedRow) return alert("Select a row first.");
  document.getElementById("teacherId").value = selectedRow.cells[0].innerText;
  document.getElementById("teacherName").value = selectedRow.cells[1].innerText;
  document.getElementById("teacherDepartment").value = selectedRow.cells[2].innerText;
  document.getElementById("teacherEmail").value = selectedRow.cells[3].innerText;
  document.getElementById("teacherModalLabel").innerText = "Edit Teacher";
  new bootstrap.Modal(document.getElementById("teacherModal")).show();
}

// Delete selected row
function deleteTable() {
  const selectedRow = document.querySelector("tr.selected");
  if (!selectedRow) return alert("Select a row first.");
  if (!confirm("Are you sure you want to delete this teacher?")) return;

  const id = selectedRow.cells[0].innerText;

  fetch("teacher_action.php", {
    method: "POST",
    body: new URLSearchParams({ action: "delete", id }),
  })
  .then(res => res.json())
  .then(data => {
    alert(data.message);
    if (data.success) {
      location.reload();
    }
  });
}

// Add selection feature
document.querySelectorAll("#teacherTable tbody tr").forEach(row => {
  row.addEventListener("click", function () {
    document.querySelectorAll("#teacherTable tbody tr").forEach(r => r.classList.remove("selected"));
    this.classList.add("selected");
  });
});


