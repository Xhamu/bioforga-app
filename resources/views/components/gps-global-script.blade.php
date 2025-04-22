<script>
    document.body.addEventListener('click', function(e) {
        const inicioBtn = e.target.closest('#location-inicio-carga');
        const finBtn = e.target.closest('#location-fin-carga');

        // GPS de inicio
        if (inicioBtn) {
            const gpsInput = document.querySelector('input[id$="gps_inicio_carga"]');
            if (gpsInput) requestLocationAndFill(gpsInput);
            return;
        }

        // GPS de fin
        if (finBtn) {
            const gpsInput = document.querySelector('input[id$="gps_fin_carga"]');
            if (gpsInput) requestLocationAndFill(gpsInput);
            return;
        }
    });

    function requestLocationAndFill(input) {
        if (!navigator.geolocation) {
            alert("⚠️ La geolocalización no es compatible con este navegador.");
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const coords = `${position.coords.latitude}, ${position.coords.longitude}`;
                input.value = coords;
                input.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
                console.log('📍 Coordenadas insertadas:', coords);
            },
            (error) => {
                alert("❌ Error al obtener ubicación: " + error.message);
            }
        );
    }
</script>
