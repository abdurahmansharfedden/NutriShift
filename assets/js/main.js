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

    // ─────────────────────────────────────────────────────────────────────────
    // 8. RESET SMART LOG UI when the Add Meal modal is closed
    // So it is blank and ready the next time the user opens it.
    // ─────────────────────────────────────────────────────────────────────────
    document.querySelectorAll('.modal-close, .modal-overlay').forEach(function (el) {
        el.addEventListener('click', function () {
            var textarea = document.getElementById('smart_log_input');
            var status   = document.getElementById('smart-log-status');
            var btnText  = document.getElementById('smart-log-btn-text');
            var btn      = document.getElementById('smart-log-btn');
            if (textarea) textarea.value = '';
            if (status)   { status.textContent = ''; status.hidden = true; status.className = 'smart-log-status'; }
            if (btnText)  btnText.innerHTML = '⚡ Estimate';
            if (btn)      btn.disabled = false;
        });
    });

});

// =============================================================================
// 8. SMART LOG — AI Macro Estimator
// =============================================================================
// TEACHING NOTE: This function is declared in the GLOBAL scope (outside of
// DOMContentLoaded) so it can be called directly from the HTML onclick attribute
// (onclick="runSmartLog()"). Functions inside DOMContentLoaded are local and
// cannot be reached from inline HTML event handlers.
// =============================================================================
function runSmartLog() {

    // ── Grab DOM references ──────────────────────────────────────────────────
    var textarea = document.getElementById('smart_log_input');
    var btn      = document.getElementById('smart-log-btn');
    var btnText  = document.getElementById('smart-log-btn-text');
    var status   = document.getElementById('smart-log-status');

    var mealText = textarea ? textarea.value.trim() : '';

    // Basic client-side guard — the server validates too, but this is faster UX.
    if (!mealText) {
        showSmartStatus(status, 'error', '⚠️ Please describe your meal first.');
        textarea.focus();
        return;
    }

    // ── Set loading state ────────────────────────────────────────────────────
    // TEACHING NOTE: We disable the button and show a spinner to give the user
    // feedback that the request is in-flight. Without this, they might click
    // the button multiple times and flood the API.
    btn.disabled    = true;
    btnText.innerHTML = '<span class="spinner"></span> Thinking…';
    showSmartStatus(status, 'loading', '🤖 Asking AI to estimate macros…');

    // ── Send the request to our PHP backend ──────────────────────────────────
    // TEACHING NOTE: fetch() is the modern browser API for making HTTP requests
    // without reloading the page (this is called an "AJAX" or "XHR" request).
    // We send JSON in the body. The 'Content-Type: application/json' header
    // tells the server what format we're sending.
    //
    // fetch() returns a Promise. .then() runs when the response arrives.
    // .catch() runs if the network fails entirely (no internet, server down).
    fetch('calculate_macros.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ meal_text: mealText })
    })
    .then(function (response) {
        // TEACHING NOTE: response.json() reads the response body and parses it
        // from a JSON string into a plain JavaScript object. It also returns a
        // Promise, so we chain another .then() to work with the parsed data.
        return response.json();
    })
    .then(function (data) {

        // Reset button regardless of success or error
        btn.disabled    = false;
        btnText.innerHTML = '⚡ Estimate';

        if (data.error) {
            showSmartStatus(status, 'error', '❌ ' + data.error);
            return;
        }

        // ── Auto-fill the form fields ────────────────────────────────────────
        // TEACHING NOTE: We set the .value property on each <input> element.
        // Then we add the CSS class 'ai-filled' which triggers a keyframe
        // animation (purple glow → fade out) so the user notices the change.
        var macros = data.macros;
        var fields = {
            'calories': macros.calories,
            'protein':  macros.protein,
            'carbs':    macros.carbs,
            'fat':      macros.fat
        };

        Object.keys(fields).forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.value = fields[id];
            // Trigger highlight animation: remove then re-add class
            el.classList.remove('ai-filled');
            // A tiny timeout forces the browser to re-render before re-adding
            setTimeout(function () { el.classList.add('ai-filled'); }, 10);
        });

        // If the food_name field is empty, pre-fill it with the meal description
        // (truncated to 80 chars so it fits in the DB column).
        var nameField = document.getElementById('food_name');
        if (nameField && !nameField.value.trim()) {
            nameField.value = mealText.substring(0, 80);
        }

        showSmartStatus(
            status, 'ok',
            '✅ Macros estimated: ' + macros.calories + ' kcal · ' +
            macros.protein + 'g protein · ' + macros.carbs + 'g carbs · ' + macros.fat + 'g fat. ' +
            'Review and click "Log Meal" to save.'
        );
    })
    .catch(function (err) {
        // Network-level failure (server unreachable, timeout, etc.)
        btn.disabled    = false;
        btnText.innerHTML = '⚡ Estimate';
        showSmartStatus(status, 'error', '❌ Network error. Please check your connection and try again.');
        console.error('Smart Log fetch error:', err);
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: show a styled status message inside the Smart Log section
// ─────────────────────────────────────────────────────────────────────────────
function showSmartStatus(el, type, message) {
    if (!el) return;
    el.hidden      = false;
    el.textContent = message;
    // Replace any previous status class with the new one
    el.className   = 'smart-log-status status-' + type;
}

// =============================================================================
// 9. AI WEEKLY WORKOUT GENERATOR
// =============================================================================
// TEACHING NOTE: This mirrors the runSmartLog() pattern exactly:
//   - Declared in GLOBAL scope so it works with onclick="generateWorkoutProgram()"
//   - Disables the button during the request to prevent double-submits
//   - Calls our PHP endpoint, reads the JSON response, then re-enables the button
//
// REQUIRED HTML on user_dashboard.php (example):
//
//   <button id="generate-plan-btn"
//           onclick="generateWorkoutProgram()"
//           class="btn btn-primary">
//       <span id="gen-btn-text">🤖 Generate My Weekly Plan</span>
//   </button>
//   <div id="gen-plan-status" class="smart-log-status" hidden></div>
//   <div id="gen-plan-output"></div>   ← rendered plan cards appear here
//
// =============================================================================
function generateWorkoutProgram() {

    // ── Grab DOM references ──────────────────────────────────────────────────
    var btn     = document.getElementById('generate-plan-btn');
    var btnText = document.getElementById('gen-btn-text');
    var status  = document.getElementById('gen-plan-status');
    var output  = document.getElementById('gen-plan-output');

    // ── Set loading state ────────────────────────────────────────────────────
    // TEACHING NOTE: We disable the button immediately to prevent the user from
    // clicking "Generate" multiple times and firing redundant Gemini API calls
    // (each call costs tokens and takes ~5-10 seconds).
    if (btn) btn.disabled = true;
    if (btnText) btnText.innerHTML = '<span class="spinner"></span> Generating…';
    showPlanStatus(status, 'loading', '🤖 AI is building your personalized 7-day plan…');
    if (output) output.innerHTML = ''; // Clear any previously rendered plan

    // ── POST to the PHP backend ───────────────────────────────────────────────
    // TEACHING NOTE: generate_program.php does NOT need a body payload because
    // it reads the user's data directly from the database using $_SESSION['user_id'].
    // We still use POST (not GET) because this action has side-effects: it
    // writes a new row to the user_programs table.
    fetch('generate_program.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({}) // Empty body — server reads session for user data
    })
    .then(function (response) {
        // TEACHING NOTE: We call response.json() to parse the body regardless of
        // the HTTP status code. This lets us read the error message even on a
        // 4xx/5xx response, which the server always encodes as JSON.
        return response.json().then(function (data) {
            // Attach the HTTP status to the data object so we can check it below.
            data._httpStatus = response.status;
            return data;
        });
    })
    .then(function (data) {

        // ── Re-enable button ─────────────────────────────────────────────────
        if (btn) btn.disabled = false;
        if (btnText) btnText.innerHTML = '🤖 Generate My Weekly Plan';

        // ── Handle errors from the server ────────────────────────────────────
        if (data._httpStatus !== 200 || data.status !== 'success') {
            var errMsg = data.message || 'An unknown error occurred. Please try again.';
            showPlanStatus(status, 'error', '❌ ' + errMsg);
            return;
        }

        // ── Success ──────────────────────────────────────────────────────────
        // TEACHING NOTE: The server saved the plan to the DB and returned
        // {"status":"success"}. We now reload the page so the PHP-rendered
        // plan section picks up the new database record.
        //
        // Alternative approach: the server could return the full JSON in the
        // response and we render it here in JS — but reloading keeps a single
        // source of truth (the PHP template) and avoids duplicating render logic.
        showPlanStatus(status, 'ok', '✅ ' + data.message + ' Reloading…');

        // Short delay so the user sees the success message before the reload.
        setTimeout(function () {
            window.location.reload();
        }, 1500);
    })
    .catch(function (err) {
        // ── Network-level failure (no internet, server down, timeout) ────────
        if (btn) btn.disabled = false;
        if (btnText) btnText.innerHTML = '🤖 Generate My Weekly Plan';
        showPlanStatus(status, 'error', '❌ Network error. Check your connection and try again.');
        console.error('generateWorkoutProgram fetch error:', err);
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: show a styled status message in the workout generator section.
// Reuses the same CSS classes as showSmartStatus() — status-loading,
// status-ok, status-error — so no extra CSS is needed.
// ─────────────────────────────────────────────────────────────────────────────
function showPlanStatus(el, type, message) {
    if (!el) return;
    el.hidden      = false;
    el.textContent = message;
    el.className   = 'smart-log-status status-' + type;
}

