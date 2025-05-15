<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;
use Spatie\LaravelSettings\Exceptions\SettingAlreadyExists;

class PWASettings extends SettingsMigration
{
    public function up(): void
    {
        $this->safeAdd('pwa.pwa_app_name', 'TomatoPHP');
        $this->safeAdd('pwa.pwa_short_name', 'Tomato');
        $this->safeAdd('pwa.pwa_start_url', '/');
        $this->safeAdd('pwa.pwa_background_color', '#ffffff');
        $this->safeAdd('pwa.pwa_theme_color', '#000000');
        $this->safeAdd('pwa.pwa_display', 'standalone');
        $this->safeAdd('pwa.pwa_orientation', 'any');
        $this->safeAdd('pwa.pwa_status_bar', '#000000');
        $this->safeAdd('pwa.pwa_icons_72x72', '');
        $this->safeAdd('pwa.pwa_icons_96x96', '');
        $this->safeAdd('pwa.pwa_icons_128x128', '');
        $this->safeAdd('pwa.pwa_icons_144x144', '');
        $this->safeAdd('pwa.pwa_icons_152x152', '');
        $this->safeAdd('pwa.pwa_icons_192x192', '');
        $this->safeAdd('pwa.pwa_icons_384x384', '');
        $this->safeAdd('pwa.pwa_icons_512x512', '');
        $this->safeAdd('pwa.pwa_splash_640x1136', '');
        $this->safeAdd('pwa.pwa_splash_750x1334', '');
        $this->safeAdd('pwa.pwa_splash_828x1792', '');
        $this->safeAdd('pwa.pwa_splash_1125x2436', '');
        $this->safeAdd('pwa.pwa_splash_1242x2208', '');
        $this->safeAdd('pwa.pwa_splash_1242x2688', '');
        $this->safeAdd('pwa.pwa_splash_1536x2048', '');
        $this->safeAdd('pwa.pwa_splash_1668x2224', '');
        $this->safeAdd('pwa.pwa_splash_1668x2388', '');
        $this->safeAdd('pwa.pwa_splash_2048x2732', '');
        $this->safeAdd('pwa.pwa_shortcuts', []);
    }

    protected function safeAdd(string $key, mixed $value): void
    {
        try {
            $this->migrator->add($key, $value);
        } catch (SettingAlreadyExists $e) {
            // El setting ya existe, no hacer nada
        }
    }
}
