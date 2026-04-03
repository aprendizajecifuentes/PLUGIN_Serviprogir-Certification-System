document.addEventListener('DOMContentLoaded', function(){

    const counter = document.getElementById('selected-count');
    const table = document.querySelector('table'); // Para delegación de eventos
    const selectAllBtn = document.getElementById('select-all');
    const deselectAllBtn = document.getElementById('deselect-all');
    const checkboxes = document.querySelectorAll('.student-checkbox');
    
    // 1. Estado inicial del contador (basado en lo que viene del servidor)
    let currentCount = Array.from(checkboxes).filter(cb => cb.checked).length;

    const updateDisplay = () => {
        if(counter) counter.textContent = currentCount;
    };

    // 2. Delegación de Eventos: Un solo listener para todos los checkboxes
    // Esto es mucho más eficiente que 500 listeners individuales
    if(table) {
        table.addEventListener('change', function(e) {
            if(e.target.classList.contains('student-checkbox')) {
                if(e.target.checked) {
                    currentCount++;
                } else {
                    currentCount--;
                }
                updateDisplay();
                
                // Opcional: Resaltar la fila seleccionada visualmente
                const row = e.target.closest('.student-row');
                if(row) row.style.backgroundColor = e.target.checked ? '#fff9c4' : '';
            }
        });
    }

    // 3. Botones Masivos (Ajustan el contador de golpe)
    if(selectAllBtn){
        selectAllBtn.addEventListener('click', function(){
            checkboxes.forEach(cb => {
                if(!cb.checked) {
                    cb.checked = true;
                    currentCount++;
                }
            });
            updateDisplay();
        });
    }

    if(deselectAllBtn){
        deselectAllBtn.addEventListener('click', function(){
            checkboxes.forEach(cb => {
                if(cb.checked) {
                    cb.checked = false;
                    currentCount--;
                }
            });
            updateDisplay();
        });
    }

    // Inicializar visualización
    updateDisplay();

    // ... (Mantener aquí el resto de tu lógica de filtros y confirmación de desaprobación)
});