<?php

return [
    'title' => 'Configuración',
    'group' => 'Ajustes generales',
    'back' => 'Volver',

    'settings' => [
        'site' => [
            'title' => 'Sitio web',
            'description' => 'Configura los ajustes básicos del sitio',
            'site-map' => 'Mapa del sitio',

            'form' => [
                'site_name' => 'Nombre del sitio',
                'site_description' => 'Descripción del sitio',
                'site_keywords' => 'Palabras clave',
                'site_phone' => 'Teléfono de contacto',
                'site_profile' => 'Imagen de perfil',
                'site_logo' => 'Logo del sitio',
                'site_author' => 'Autor del sitio',
                'site_email' => 'Correo electrónico',
            ],
        ],

        'social' => [
            'title' => 'Redes sociales',
            'description' => 'Enlaza tus redes sociales',
            'form' => [
                'site_social' => 'Enlaces a redes sociales',
                'vendor' => 'Proveedor',
                'link' => 'Enlace',
            ],
        ],

        'location' => [
            'title' => 'Ubicación',
            'description' => 'Establece la ubicación del negocio',
            'form' => [
                'site_address' => 'Dirección',
                'site_phone_code' => 'Código de teléfono',
                'site_location' => 'País o región',
                'site_currency' => 'Moneda',
                'site_language' => 'Idioma del sitio',
            ],
        ],
    ],
];
