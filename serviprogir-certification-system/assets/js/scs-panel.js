document.addEventListener('DOMContentLoaded', function () {

    const counter      = document.getElementById('selected-count');
    const table        = document.querySelector('table');
    const selectAllBtn = document.getElementById('select-all');
    const deselectAllBtn = document.getElementById('deselect-all');
    const checkboxes   = document.querySelectorAll('.student-checkbox');

    let currentCount = Array.from(checkboxes).filter(cb => cb.checked).length;

    const updateDisplay = () => {
        if (counter) counter.textContent = currentCount;
    };

    if (table) {
        table.addEventListener('change', function (e) {
            if (e.target.classList.contains('student-checkbox')) {
                currentCount += e.target.checked ? 1 : -1;
                updateDisplay();
                const row = e.target.closest('.student-row');
                if (row) row.style.backgroundColor = e.target.checked ? '#fff9c4' : '';
            }
        });

        // FIX #4 — El listener del botón "Ver PDF" estaba FUERA del DOMContentLoaded
        // y referenciaba 'e' sin ningún addEventListener. Ahora vive aquí correctamente.
        table.addEventListener('click', function (e) {
            if (e.target && e.target.classList.contains('btn-ver-pdf')) {
                const userId   = e.target.getAttribute('data-user');
                const courseId = e.target.getAttribute('data-course');
                // FIX #5 — Usar scs_vars.ajaxurl (registrado con wp_localize_script)
                const url = scs_vars.ajaxurl + '?action=ver_certificado_pdf&user_id=' + userId + '&course_id=' + courseId;
                window.open(url, '_blank');
            }
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            checkboxes.forEach(cb => {
                if (!cb.checked) { cb.checked = true; currentCount++; }
            });
            updateDisplay();
        });
    }

    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function () {
            checkboxes.forEach(cb => {
                if (cb.checked) { cb.checked = false; currentCount--; }
            });
            updateDisplay();
        });
    }

    updateDisplay();
});