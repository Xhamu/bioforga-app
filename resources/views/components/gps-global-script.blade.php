<script>
    document.body.addEventListener('click', function(e) {
        const inicioBtn = e.target.closest('#location-inicio-carga');
        const finBtn = e.target.closest('#location-fin-carga');

        const inicioTrabajoBtn = e.target.closest('#location-inicio-trabajo');
        const finTrabajoBtn = e.target.closest('#location-fin-trabajo');

        const inicioDesplazamientoBtn = e.target.closest('#location-inicio-desplazamiento');
        const finDesplazamientoBtn = e.target.closest('#location-fin-desplazamiento');

        const inicioAveriaBtn = e.target.closest('#location-inicio-averia');
        const finAveriaBtn = e.target.closest('#location-fin-averia');

        const inicioOtrosBtn = e.target.closest('#location-inicio-otros');
        const finOtrosBtn = e.target.closest('#location-fin-otros');

        const inicioAyudanteBtn = e.target.closest('#location-inicio-ayudante');
        const finAyudanteBtn = e.target.closest('#location-fin-ayudante');

        // SUMINISTROS DEL TRANSPORTISTA
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

        // OPERACIONES MAQUINA
        // GPS de inicio trabajo
        if (inicioTrabajoBtn) {
            const gpsInput = document.querySelector('input[id$="gps_inicio_trabajo"]');
            if (gpsInput) requestLocationAndFill(gpsInput);
            return;
        }

        // GPS de fin trabajo
        if (finTrabajoBtn) {
            const gpsInput = document.querySelector('input[id$="gps_fin_trabajo"]');
            if (gpsInput) requestLocationAndFill(gpsInput);
            return;
        }

        // DESPLAZAMIENTOS
        // GPS de inicio trabajo
        if (inicioDesplazamientoBtn) {
            const gpsInput = document.querySelector('input[id$="gps_inicio_desplazamiento"]');
            if (gpsInput) requestLocationAndFill(gpsInput);
            return;
        }

        // GPS de fin trabajo
        if (finDesplazamientoBtn) {
            const gpsInput = document.querySelector('input[id$="gps_fin_desplazamiento"]');
            if (gpsInput) requestLocationAndFill(gpsInput);
            return;
        }

        // AVERIAS MANTENIMIENTOS
        if (inicioAveriaBtn) {
            const gpsInput = document.querySelector('input[id$="gps_inicio_averia"]');
            if (gpsInput) requestLocationAndFill(gpsInput);
            return;
        }

        // GPS de fin trabajo
        if (finAveriaBtn) {
            const gpsInput = document.querySelector('input[id$="gps_fin_averia"]');
            if (gpsInput) requestLocationAndFill(gpsInput);
            return;
        }

        // OTROS
        if (inicioOtrosBtn) {
            const gpsInput = document.querySelector('input[id$="gps_inicio_otros"]');
            if (gpsInput) requestLocationAndFill(gpsInput);
            return;
        }

        // GPS de fin trabajo
        if (finOtrosBtn) {
            const gpsInput = document.querySelector('input[id$="gps_fin_otros"]');
            if (gpsInput) requestLocationAndFill(gpsInput);
            return;
        }

        // AYUDANTE
        if (inicioAyudanteBtn) {
            const gpsInput = document.querySelector('input[id$="gps_inicio_ayudante"]');
            if (gpsInput) requestLocationAndFill(gpsInput);
            return;
        }

        // GPS de fin trabajo
        if (finAyudanteBtn) {
            const gpsInput = document.querySelector('input[id$="gps_fin_ayudante"]');
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
            },
        );
    }

    const observer = new MutationObserver(() => {
        const inputs = document.querySelectorAll('input[id*="gps_"]');

        inputs.forEach((input) => {
            if (!input.dataset.filled) {
                requestLocationAndFill(input);
                input.dataset.filled = "true"; // Evita múltiples ejecuciones
            }
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
</script>

@php
    $version = trim(file_get_contents(base_path('.version')));
@endphp

<div class="flex justify-center mt-10 mb-6">
    <div class="flex flex-col items-center text-sm text-gray-500 dark:text-gray-400 space-y-1 text-center">
        <a href="https://www.quadralia.com/" target="_blank" rel="noopener noreferrer"
            class="hover:opacity-80 transition-opacity duration-150">
            <img src="{{ asset('images/powered-by-quadralia.svg') }}" alt="Powered by Quadralia" class="h-6">
        </a>
        <span class="text-xs leading-tight">Versión {{ $version }}</span>
    </div>
</div>
