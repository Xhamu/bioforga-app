<div>
    <select id="referencia-select" class="referencia-select"
        style="width: 100%; padding: 12px 20px; font-size: 16px; font-weight: bold; border: 2px solid #218838; border-radius: 10px; background-color: #28a745; color: white;">
        <option value="">Selecciona una opción</option>
        <option value="suministro">SUMINISTRO</option>
        <option value="servicio">SERVICIO</option>
    </select>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const referenciaSelect = document.getElementById('referencia-select');
            const referenciaInput = document.querySelector('input[id="data.referencia"]');

            if (referenciaSelect && referenciaInput) {
                referenciaSelect.addEventListener('change', function() {
                    const today = new Date();
                    const formattedDate = today.getFullYear().toString().slice(-2) +
                        (today.getMonth() + 1).toString().padStart(2, '0') +
                        today.getDate().toString().padStart(2, '0');

                    if (referenciaSelect.value === 'suministro') {
                        referenciaInput.value = 'SU' + formattedDate;
                    } else if (referenciaSelect.value === 'servicio') {
                        referenciaInput.value = formattedDate;
                    } else {
                        referenciaInput.value = '';
                    }

                    referenciaInput.dispatchEvent(new Event('input', {
                        bubbles: true
                    }));
                });
            } else {
                console.error('El campo de referencia o el select no se encontraron.');
            }
        });
    </script>
</div>
