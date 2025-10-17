// Funcionalidades adicionales con JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Resaltar pedidos vencidos
    const hoy = new Date().toISOString().split('T')[0];
    document.querySelectorAll('tbody tr').forEach(row => {
        const fechaLlegada = row.cells[7].textContent.split('/').reverse().join('-');
        const estado = row.cells[10].textContent.trim();
        
        if (fechaLlegada < hoy && estado !== 'Recibido' && estado !== 'Cancelado') {
            row.classList.add('vencido');
        }
    });
    
    // Tooltips de Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});



document.addEventListener('DOMContentLoaded', function() {
    const odsInput = document.getElementById('ods');
    const odsIdInput = document.getElementById('ods_id');
    const sugerenciasOds = document.getElementById('sugerencias-ods');
    const linkOds = document.getElementById('link-ods');
    let timeoutId = null;

    // Función para buscar ODS
    function buscarODS(termino) {
        if (termino.length < 2) {
            sugerenciasOds.style.display = 'none';
            return;
        }

        fetch(`buscar_ods.php?term=${encodeURIComponent(termino)}`)
            .then(response => response.json())
            .then(data => {
                // Verificar si hay error
                if (data.error) {
                    console.error('Error al buscar ODS:', data.error);
                    return;
                }
                
                sugerenciasOds.innerHTML = '';
                
                if (data.length > 0) {
                    data.forEach(ods => {
                        const item = document.createElement('a');
                        item.classList.add('list-group-item', 'list-group-item-action');
                        item.href = '#';
                        
                        // Mostrar información relevante de la ODS
                        let info = `<strong>${ods.Idods}</strong>`;
                        if (ods.numero_ods) info += ` - Número: ${ods.numero_ods}`;
                        if (ods.cliente) info += ` - Cliente: ${ods.cliente}`;
                        if (ods.equipo) info += ` - Equipo: ${ods.equipo}`;
                        if (ods.descripcion) info += ` - Descripción: ${ods.descripcion.substring(0, 50)}...`;
                        
                        item.innerHTML = info;
                        
                        item.addEventListener('click', function(e) {
                            e.preventDefault();
                            odsInput.value = ods.Idods; // Usar Idods como valor principal
                            odsIdInput.value = ods.Idods;
                            sugerenciasOds.style.display = 'none';
                            
                            // Actualizar el enlace al sistema externo
                            const urlBase = 'https://sistema-externo.com/ods/'; // Cambiar por la URL real
                            linkOds.href = urlBase + ods.Idods;
                            linkOds.style.display = 'inline-block';
                        });
                        
                        sugerenciasOds.appendChild(item);
                    });
                    sugerenciasOds.style.display = 'block';
                } else {
                    sugerenciasOds.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                sugerenciasOds.style.display = 'none';
            });
    }

    // Event listener para el input de ODS
    odsInput.addEventListener('input', function() {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            buscarODS(this.value);
        }, 300);
    });

    // Ocultar sugerencias al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!odsInput.contains(e.target) && !sugerenciasOds.contains(e.target)) {
            sugerenciasOds.style.display = 'none';
        }
    });

    // Si estamos editando, configurar el enlace existente
    if (odsIdInput.value) {
        const urlBase = 'https://sistema-externo.com/ods/'; // Cambiar por la URL real
        linkOds.href = urlBase + odsIdInput.value;
        linkOds.style.display = 'inline-block';
    } else {
        linkOds.style.display = 'none';
    }

    // También permitir búsqueda al hacer clic en el botón de lupa
    const btnBuscarOds = document.getElementById('btn-buscar-ods');
    if (btnBuscarOds) {
        btnBuscarOds.addEventListener('click', function() {
            buscarODS(odsInput.value);
        });
    }
});