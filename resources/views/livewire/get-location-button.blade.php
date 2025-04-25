<div>
    <button type="button" class="custom-btn" id="get-location-button">
        Obtener ubicación actual
    </button>

    <style>
        .custom-btn {
            width: 100%;
            justify-items: center;
            align-items: center;
            background-color: #28a745;
            color: white;
            border: 2px solid #218838;
            border-radius: 10px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .custom-btn:hover {
            background-color: #218838;
            border-color: #1e7e34;
            cursor: pointer;
        }

        .custom-btn:focus {
            outline: none;
        }

        .custom-btn:active {
            background-color: #1e7e34;
            border-color: #155d27;
        }
    </style>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const locationButton = document.getElementById('get-location-button');
            const ubicacionInput = document.querySelector('input[id="data.ubicacion_gps"]');
            const provinciaInput = document.querySelector('input[id="data.provincia"]');
            const ayuntamientoInput = document.querySelector('input[id="data.ayuntamiento"]');
            const referenciaInput = document.querySelector('input[id="data.referencia"]');
            const tipoSelect = document.getElementById('referencia-select'); // SUMINISTRO o SERVICIO

            locationButton.addEventListener('click', function() {
                if (!navigator.geolocation) {
                    alert("La geolocalización no es compatible con este navegador.");
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lon = position.coords.longitude;
                        const ubicacion = `${lat}, ${lon}`;

                        if (ubicacionInput) {
                            ubicacionInput.value = ubicacion;
                            ubicacionInput.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        }

                        fetch(
                                `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json&addressdetails=1`)
                            .then(response => response.json())
                            .then(data => {
                                const provincia = data?.address?.province ??
                                'Provincia desconocida';
                                const ayuntamiento = data?.address?.town ??
                                    'Ayuntamiento desconocido';

                                if (provinciaInput) {
                                    provinciaInput.value = provincia;
                                    provinciaInput.dispatchEvent(new Event('input', {
                                        bubbles: true
                                    }));
                                }

                                if (ayuntamientoInput) {
                                    ayuntamientoInput.value = ayuntamiento;
                                    ayuntamientoInput.dispatchEvent(new Event('input', {
                                        bubbles: true
                                    }));
                                }

                                if (referenciaInput && tipoSelect?.value === 'servicio') {
                                    const fecha = new Date();
                                    const formatted = fecha.getFullYear().toString().slice(-2) +
                                        (fecha.getMonth() + 1).toString().padStart(2, '0') +
                                        fecha.getDate().toString().padStart(2, '0');

                                    const provinciaAbrev = provincia.slice(0, 2).toUpperCase();
                                    const ayuntamientoAbrev = ayuntamiento.slice(0, 2)
                                .toUpperCase();

                                    referenciaInput.value =
                                        `${ayuntamientoAbrev}${provinciaAbrev}${formatted}`;
                                    referenciaInput.dispatchEvent(new Event('input', {
                                        bubbles: true
                                    }));
                                }
                            })
                            .catch(error => {
                                console.error("Error al obtener datos de ubicación:", error);
                            });
                    },
                    function() {
                        alert("No se ha podido obtener la ubicación.");
                    }
                );
            });
        });
    </script>
</div>
