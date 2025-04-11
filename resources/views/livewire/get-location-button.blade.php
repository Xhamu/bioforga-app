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

            locationButton.addEventListener('click', function() {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(function(position) {
                        const lat = position.coords.latitude;
                        const lon = position.coords.longitude;
                        const ubicacion = `${lat}, ${lon}`;

                        fetch(
                                `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json&addressdetails=1`
                            )
                            .then(response => response.json())
                            .then(data => {
                                if (data) {
                                    const provincia = data.address.province ||
                                        'Provincia desconocida';
                                    const ayuntamiento = data.address.town ||
                                        'Ayuntamiento desconocido';

                                    if (referenciaInput) {
                                        if (!referenciaInput.value.includes('SU')) {
                                            const provinciaAbrev = provincia.slice(0, 2)
                                                .toUpperCase();
                                            const ayuntamientoAbrev = ayuntamiento.slice(0, 2)
                                                .toUpperCase();

                                            referenciaInput.value = referenciaInput.value +
                                                provinciaAbrev + ayuntamientoAbrev;
                                            referenciaInput.dispatchEvent(new Event('input', {
                                                bubbles: true
                                            }));
                                        }
                                    }

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

                                    if (ubicacionInput) {
                                        ubicacionInput.value = ubicacion;
                                        ubicacionInput.dispatchEvent(new Event('input', {
                                            bubbles: true
                                        }));
                                    }
                                }
                            })
                            .catch(error => {
                                console.error(error);
                            });
                    }, function() {
                        alert("No se ha podido obtener la ubicación.");
                    });
                } else {
                    alert("La geolocalización no es compatible con este navegador.");
                }
            });
        });
    </script>
</div>
