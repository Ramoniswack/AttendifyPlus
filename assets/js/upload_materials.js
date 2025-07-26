// Teams-style Upload Materials JS
// Tab switching, drag-and-drop, sidebar, and search

document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabFiles = document.getElementById('tab-files');
    const tabUpload = document.getElementById('tab-upload');
    const filesSection = document.getElementById('filesSection');
    const uploadSection = document.getElementById('uploadSection');
    tabFiles && tabFiles.addEventListener('click', () => {
        tabFiles.classList.add('active');
        tabUpload.classList.remove('active');
        filesSection.style.display = '';
        uploadSection.style.display = 'none';
    });
    tabUpload && tabUpload.addEventListener('click', () => {
        tabUpload.classList.add('active');
        tabFiles.classList.remove('active');
        filesSection.style.display = 'none';
        uploadSection.style.display = '';
    });
    // Drag and drop
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('material_file');
    if (uploadArea && fileInput) {
        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', e => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        uploadArea.addEventListener('dragleave', e => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        });
        uploadArea.addEventListener('drop', e => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
            }
        });
    }
    // Search filter
    const searchInput = document.getElementById('teamsSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.material-item').forEach(item => {
                const title = item.dataset.title || '';
                const desc = item.dataset.description || '';
                const tags = item.dataset.tags || '';
                item.style.display = (title.includes(term) || desc.includes(term) || tags.includes(term)) ? '' : 'none';
            });
            updateMaterialsCount();
        });
    }
    function updateMaterialsCount() {
        const visible = Array.from(document.querySelectorAll('.material-item')).filter(i => i.style.display !== 'none').length;
        const countEl = document.getElementById('materialsCount');
        if (countEl) countEl.textContent = `${visible} found`;
    }
    updateMaterialsCount();
    // Sidebar expand/collapse
    const sidebar = document.getElementById('teamsSidebar');
    if (sidebar) {
        sidebar.addEventListener('mouseenter', () => sidebar.classList.add('expanded'));
        sidebar.addEventListener('mouseleave', () => sidebar.classList.remove('expanded'));
    }
});
