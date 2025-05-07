function toggleTheme() {
  const isDark = document.body.classList.toggle("dark-mode");
  localStorage.setItem('theme', isDark ? 'dark' : 'light');
}

window.onload = () => {
  if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('dark-mode');
  }
};
