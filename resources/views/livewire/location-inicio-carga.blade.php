<div>
    <button type="button" class="custom-btn" id="location-inicio-carga">
        Obtener ubicación inicio
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
            const locationButton = document.getElementById('location-inicio-carga');
            const ubicacionInicioCargaInput = document.querySelector('input[id$="gps_inicio_carga"]');
            const fechaHoraInicioCargaInput = document.querySelector('input[id$="fecha_hora_inicio_carga"]')

            locationButton.addEventListener('click', function() {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(function(position) {
                        const lat = position.coords.latitude;
                        const lon = position.coords.longitude;
                        const ubicacion = `${lat}, ${lon}`;

                        if (ubicacionInicioCargaInput) {
                            ubicacionInicioCargaInput.value = ubicacion;
                            ubicacionInicioCargaInput.dispatchEvent(new Event(
                                'input', {
                                    bubbles: true
                                }));
                        }

                        if (fechaHoraInicioCargaInput) {
                            const now = new Date();
                            const formattedDate = now.getFullYear() + '-' +
                                String(now.getMonth() + 1).padStart(2, '0') + '-' +
                                String(now.getDate()).padStart(2, '0') + ' ' +
                                String(now.getHours()).padStart(2, '0') + ':' +
                                String(now.getMinutes()).padStart(2, '0') + ':' +
                                String(now.getSeconds()).padStart(2, '0');

                            fechaHoraInicioCargaInput.value = formattedDate;
                        }

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
