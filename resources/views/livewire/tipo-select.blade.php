<div>
    <select id="referencia-select"
        class="w-full rounded-md border-gray-300 bg-white text-sm shadow-sm
           px-3 py-2
           focus:border-primary-500 focus:ring-primary-500 focus:outline-none">
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
                    const formattedDate =
                        today.getDate().toString().padStart(2, '0') + // DD
                        (today.getMonth() + 1).toString().padStart(2, '0') + // MM
                        today.getFullYear().toString().slice(-2); // YY

                    let base = '';
                    if (referenciaSelect.value === 'suministro') {
                        base = 'SU' + formattedDate;
                    } else if (referenciaSelect.value === 'servicio') {
                        base = formattedDate;
                    } else {
                        referenciaInput.value = '';
                        return;
                    }

                    fetch('/contador-referencias-hoy')
                        .then(res => res.json())
                        .then(data => {
                            const contador = String(data.total + 1).padStart(2, '0');
                            referenciaInput.value = base + contador;
                            referenciaInput.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        })
                        .catch(error => {
                            console.error('Error al obtener el contador de referencias:', error);
                            referenciaInput.value = base + '01';
                            referenciaInput.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        });
                });
            } else {
                console.error('No se encontraron el select o el input de referencia.');
            }
        });
    </script>
</div>
