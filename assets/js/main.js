// =============================================================================
// main.js — Client-Side Logic for NutriShift
// =============================================================================

// ─────────────────────────────────────────────────────────────────────────────
// 1. DARK / LIGHT THEME TOGGLE
// We store the user's preference in localStorage so it persists across visits.
// ─────────────────────────────────────────────────────────────────────────────
(function initTheme() {
    const root        = document.documentElement;      // the <html> element
    const savedTheme  = localStorage.getItem('ns_theme') || 'dark';
    root.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);
})();

function updateThemeIcon(theme) {
    const icon = document.getElementById('theme-icon');
    if (icon) icon.textContent = theme === 'dark' ? '🌙' : '☀️';
}

document.addEventListener('DOMContentLoaded', function () {

    // Attach theme toggle button listener
    const toggleBtn = document.getElementById('theme-toggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const root    = document.documentElement;
            const current = root.getAttribute('data-theme');
            const next    = current === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            localStorage.setItem('ns_theme', next);
            updateThemeIcon(next);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. AUTH TABS (Login / Register switch on index.php)
    // ─────────────────────────────────────────────────────────────────────────
    const tabs   = document.querySelectorAll('.auth-tab');
    const panels = document.querySelectorAll('.auth-panel');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const target = this.dataset.tab;

            tabs.forEach(function (t) { t.classList.remove('active'); });
            panels.forEach(function (p) { p.classList.remove('active'); });

            this.classList.add('active');
            const panel = document.getElementById('panel-' + target);
            if (panel) panel.classList.add('active');
        });
    });

    // If server sent back a registration error, switch to the register tab
    if (document.getElementById('show-register')) {
        const regTab = document.querySelector('[data-tab="register"]');
        if (regTab) regTab.click();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. DELETE CONFIRMATION
    // Intercept all links/buttons with class "confirm-delete" and ask the user
    // before proceeding. This prevents accidental deletions.
    // ─────────────────────────────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        const el = e.target.closest('.confirm-delete');
        if (!el) return;

        const message = el.dataset.confirmMsg || 'Are you sure you want to delete this? This cannot be undone.';
        if (!window.confirm(message)) {
            e.preventDefault();   // Stop the link/form from proceeding
            e.stopPropagation();
        }
    });

    // ─────────────────────────────────────────────────────────────────────────
    // 4. MODAL HELPERS
    // Generic open/close logic for any modal on the page.
    // ─────────────────────────────────────────────────────────────────────────
    window.openModal = function (id) {
        const m = document.getElementById(id);
        if (m) m.classList.add('open');
    };

    window.closeModal = function (id) {
        const m = document.getElementById(id);
        if (m) m.classList.remove('open');
    };

    // Close modal when clicking the dark overlay behind it
    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    // Close modal with the Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(function (m) {
                m.classList.remove('open');
            });
        }
    });

    // ─────────────────────────────────────────────────────────────────────────
    // 5. POPULATE EDIT MODALS WITH DATA
    // When an "Edit" button is clicked, pull data from its data-* attributes
    // and fill the modal form fields automatically.
    // ─────────────────────────────────────────────────────────────────────────

    // Edit Cycle modal
    document.querySelectorAll('.btn-edit-cycle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit_cycle_id').value         = this.dataset.id;
            document.getElementById('edit_cycle_name').value       = this.dataset.name;
            document.getElementById('edit_cycle_start').value      = this.dataset.start;
            document.getElementById('edit_cycle_end').value        = this.dataset.end;
            document.getElementById('edit_target_calories').value  = this.dataset.calories;
            openModal('modal-edit-cycle');
        });
    });

    // Edit Meal modal
    document.querySelectorAll('.btn-edit-meal').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit_meal_id').value       = this.dataset.id;
            document.getElementById('edit_meal_name').value     = this.dataset.name;
            document.getElementById('edit_meal_cal').value      = this.dataset.calories;
            document.getElementById('edit_meal_protein').value  = this.dataset.protein;
            document.getElementById('edit_meal_carbs').value    = this.dataset.carbs;
            document.getElementById('edit_meal_fat').value      = this.dataset.fat;
            openModal('modal-edit-meal');
        });
    });

    // Edit User modal (admin page)
    document.querySelectorAll('.btn-edit-user').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit_user_id').value       = this.dataset.id;
            document.getElementById('edit_user_username').value = this.dataset.username;
            document.getElementById('edit_user_email').value    = this.dataset.email;
            document.getElementById('edit_user_role').value     = this.dataset.role;
            openModal('modal-edit-user');
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    // 6. AUTO-DISMISS FLASH ALERTS after 5 seconds
    // ─────────────────────────────────────────────────────────────────────────
    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity .5s ease';
            alert.style.opacity    = '0';
            setTimeout(function () { alert.remove(); }, 500);
        }, 5000);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // 7. CALORIE PROGRESS BAR — make the fill bar "overflow" red when over limit
    // ─────────────────────────────────────────────────────────────────────────
    document.querySelectorAll('.progress-bar-fill').forEach(function (bar) {
        const pct = parseFloat(bar.dataset.pct) || 0;
        bar.style.width = Math.min(pct, 100) + '%';
        if (pct > 100) bar.classList.add('over');
    });

});
