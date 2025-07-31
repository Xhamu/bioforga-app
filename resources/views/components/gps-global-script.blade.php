<script>
    document.body.addEventListener('click', function(e) {
        const buttons = [{
                id: 'location-inicio-carga',
                inputIdSuffix: 'gps_inicio_carga'
            },
            {
                id: 'location-fin-carga',
                inputIdSuffix: 'gps_fin_carga'
            },

            {
                id: 'location-descarga',
                inputIdSuffix: 'gps_descarga'
            },

            {
                id: 'location-inicio-trabajo',
                inputIdSuffix: 'gps_inicio_trabajo'
            },
            {
                id: 'location-fin-trabajo',
                inputIdSuffix: 'gps_fin_trabajo'
            },

            {
                id: 'location-inicio-desplazamiento',
                inputIdSuffix: 'gps_inicio_desplazamiento'
            },
            {
                id: 'location-fin-desplazamiento',
                inputIdSuffix: 'gps_fin_desplazamiento'
            },

            {
                id: 'location-inicio-averia',
                inputIdSuffix: 'gps_inicio_averia'
            },
            {
                id: 'location-fin-averia',
                inputIdSuffix: 'gps_fin_averia'
            },

            {
                id: 'location-inicio-otros',
                inputIdSuffix: 'gps_inicio_otros'
            },
            {
                id: 'location-fin-otros',
                inputIdSuffix: 'gps_fin_otros'
            },

            {
                id: 'location-inicio-ayudante',
                inputIdSuffix: 'gps_inicio_ayudante'
            },
            {
                id: 'location-fin-ayudante',
                inputIdSuffix: 'gps_fin_ayudante'
            },
        ];

        for (const btn of buttons) {
            if (e.target.closest(`#${btn.id}`)) {
                const gpsInput = document.querySelector(`input[id$="${btn.inputIdSuffix}"]`);
                if (gpsInput) requestLocationAndFill(gpsInput, true);
                return;
            }
        }
    });

    function requestLocationAndFill(input, force = false) {
        if (!navigator.geolocation) {
            showToast("⚠️ La geolocalización no es compatible con este navegador.");
            return;
        }

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
                            "Has denegado el permiso de ubicación. Activa los permisos de ubicación para esta página en tu navegador."
                        );
                        break;
                    case error.POSITION_UNAVAILABLE:
                        showToast(
                            "No se pudo obtener la ubicación. La señal del GPS no está disponible o es inestable."
                        );
                        break;
                    case error.TIMEOUT:
                        showToast("La solicitud de ubicación tardó demasiado. Intenta nuevamente.");
                        break;
                }
            }
        );
    }

    function showToast(message) {
        const toast = document.getElementById('toast');
        const messageContainer = document.getElementById('toast-message');

        messageContainer.textContent = message;
        toast.classList.remove("toast-hidden");
        toast.classList.add("toast-visible");
    }

    function hideToast() {
        const toast = document.getElementById('toast');
        toast.classList.remove("toast-visible");
        toast.classList.add("toast-hidden");
    }

    const observer = new MutationObserver(() => {
        const inputs = document.querySelectorAll('input[id*="gps_"]');
        inputs.forEach((input) => {
            if (!input.dataset.filled) {
                requestLocationAndFill(input);
                input.dataset.filled = "true";
            }
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
</script>

<div id="toast" class="toast-hidden">
    <div class="toast-content">
        <div class="toast-icon">⚠️</div>
        <div id="toast-message" class="toast-message"></div>
    </div>
    <div class="toast-button-container">
        <button onclick="hideToast()" class="toast-button">Aceptar</button>
    </div>
</div>

<style>
    #toast {
        position: fixed;
        top: 40px;
        left: 50%;
        transform: translateX(-50%) translateY(-30px);
        background-color: #f59e0b;
        /* amarillo warning */
        color: #1f2937;
        /* texto oscuro */
        padding: 18px 24px;
        border-radius: 12px;
        font-size: 15px;
        z-index: 9999;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        opacity: 0;
        transition: opacity 0.4s ease, transform 0.4s ease;
        border-left: 8px solid #d97706;
        width: auto;
        max-width: 900px;
        min-width: 640px;
        text-align: left;
    }

    @media (max-width: 640px) {
        #toast {
            width: 90%;
            min-width: 100px;
        }
    }

    #toast.toast-visible {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }

    #toast.toast-hidden {
        opacity: 0;
        pointer-events: none;
    }

    .toast-content {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .toast-icon {
        font-size: 22px;
        flex-shrink: 0;
    }

    .toast-message {
        flex: 1;
        font-weight: 500;
        line-height: 1.4;
    }

    .toast-button-container {
        margin-top: 16px;
        text-align: center;
    }

    .toast-button {
        background-color: #1f2937;
        border: none;
        padding: 8px 20px;
        color: white;
        border-radius: 8px;
        font-size: 14px;
        cursor: pointer;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }

    .toast-button:hover {
        background-color: #111827;
        transform: scale(1.05);
    }
</style>

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
