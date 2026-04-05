// ═══════════════════════════════════════════════════════════════
// GESTION DU THÈME (Clair / Sombre)
// ═══════════════════════════════════════════════════════════════

(function () {
    const saved = localStorage.getItem('theme');
    if (saved === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    } else if (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
})();

function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
    updateThemeIcon(next);
}

function updateThemeIcon(theme) {
    document.querySelectorAll('.theme-toggle i').forEach(icon => icon.remove());
    const btns = document.querySelectorAll('.theme-toggle');
    btns.forEach(btn => {
        const i = document.createElement('i');
        i.className = 'bi ' + (theme === 'dark' ? 'bi-moon-stars-fill' : 'bi-sun-fill');
        btn.appendChild(i);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    updateThemeIcon(document.documentElement.getAttribute('data-theme') || 'light');
});

// ═══════════════════════════════════════════════════════════════
// TOAST NOTIFICATIONS
// ═══════════════════════════════════════════════════════════════

function showToast(message, type = 'info') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const icons = { success: 'bi-check-circle-fill', error: 'bi-exclamation-circle-fill', info: 'bi-info-circle-fill' };
    const icon = icons[type] || icons.info;

    const toast = document.createElement('div');
    toast.className = 'toast-modern toast-' + type;
    toast.innerHTML = '<i class="bi ' + icon + '"></i><span class="toast-text">' + message + '</span>';
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}
