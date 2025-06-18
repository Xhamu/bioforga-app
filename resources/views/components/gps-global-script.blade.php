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
            if (gpsInput) requestLocationAndFill(gpsInput, true);
            return;
        }

        // GPS de fin
        if (finBtn) {
            const gpsInput = document.querySelector('input[id$="gps_fin_carga"]');
            if (gpsInput) requestLocationAndFill(gpsInput, true);
            return;
        }

        // OPERACIONES MAQUINA
        // GPS de inicio trabajo
        if (inicioTrabajoBtn) {
            const gpsInput = document.querySelector('input[id$="gps_inicio_trabajo"]');
            if (gpsInput) requestLocationAndFill(gpsInput, true);
            return;
        }

        // GPS de fin trabajo
        if (finTrabajoBtn) {
            const gpsInput = document.querySelector('input[id$="gps_fin_trabajo"]');
            if (gpsInput) requestLocationAndFill(gpsInput, true);
            return;
        }

        // DESPLAZAMIENTOS
        // GPS de inicio trabajo
        if (inicioDesplazamientoBtn) {
            const gpsInput = document.querySelector('input[id$="gps_inicio_desplazamiento"]');
            if (gpsInput) requestLocationAndFill(gpsInput, true);
            return;
        }

        // GPS de fin trabajo
        if (finDesplazamientoBtn) {
            const gpsInput = document.querySelector('input[id$="gps_fin_desplazamiento"]');
            if (gpsInput) requestLocationAndFill(gpsInput, true);
            return;
        }

        // AVERIAS MANTENIMIENTOS
        if (inicioAveriaBtn) {
            const gpsInput = document.querySelector('input[id$="gps_inicio_averia"]');
            if (gpsInput) requestLocationAndFill(gpsInput, true);
            return;
        }

        // GPS de fin trabajo
        if (finAveriaBtn) {
            const gpsInput = document.querySelector('input[id$="gps_fin_averia"]');
            if (gpsInput) requestLocationAndFill(gpsInput, true);
            return;
        }

        // OTROS
        if (inicioOtrosBtn) {
            const gpsInput = document.querySelector('input[id$="gps_inicio_otros"]');
            if (gpsInput) requestLocationAndFill(gpsInput, true);
            return;
        }

        // GPS de fin trabajo
        if (finOtrosBtn) {
            const gpsInput = document.querySelector('input[id$="gps_fin_otros"]');
            if (gpsInput) requestLocationAndFill(gpsInput, true);
            return;
        }

        // AYUDANTE
        if (inicioAyudanteBtn) {
            const gpsInput = document.querySelector('input[id$="gps_inicio_ayudante"]');
            if (gpsInput) requestLocationAndFill(gpsInput, true);
            return;
        }

        // GPS de fin trabajo
        if (finAyudanteBtn) {
            const gpsInput = document.querySelector('input[id$="gps_fin_ayudante"]');
            if (gpsInput) requestLocationAndFill(gpsInput, true);
            return;
        }
    });

    function requestLocationAndFill(input, force = false) {
        if (!navigator.geolocation) {
            alert("⚠️ La geolocalización no es compatible con este navegador.");
            return;
        }

        // Si ya está relleno y no se fuerza, salimos
        if (input.dataset.filled && !force) return;

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const coords = `${position.coords.latitude}, ${position.coords.longitude}`;
                input.value = coords;
                input.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
                input.dataset.filled = "true";
            },
            (error) => {
                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        showToast(
                            "❌ Has denegado el permiso de ubicación. Ve a los permisos del navegador y actívalo para esta página."
                        );
                        break;
                    case error.POSITION_UNAVAILABLE:
                        showToast("⚠️ No se pudo obtener la ubicación. La señal del GPS no está disponible.");
                        break;
                    case error.TIMEOUT:
                        showToast("⚠️ La solicitud de ubicación ha tardado demasiado. Intenta de nuevo.");
                        break;
                }
            }
        );
    }

    function showToast(message) {
        const toast = document.getElementById('toast');
        const messageContainer = document.getElementById('toast-message');

        messageContainer.textContent = message;
        toast.style.display = 'block';
        toast.style.opacity = '1';
    }

    function hideToast() {
        const toast = document.getElementById('toast');
        toast.style.opacity = '0';
        setTimeout(() => {
            toast.style.display = 'none';
        }, 300);
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

<div id="toast"
    style="
    display: none;
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    background-color: #1f2937;
    color: #fff;
    padding: 14px 20px;
    border-radius: 8px;
    font-size: 14px;
    z-index: 9999;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
    max-width: 90%;
    width: auto;
    text-align: center;
">
    <div id="toast-message" style="margin-bottom: 10px;"></div>
    <button onclick="hideToast()"
        style="
        background-color: #3b82f6;
        border: none;
        padding: 6px 14px;
        color: white;
        border-radius: 6px;
        font-size: 13px;
        cursor: pointer;
    ">Aceptar</button>
</div>

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
