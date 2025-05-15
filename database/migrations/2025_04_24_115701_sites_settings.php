<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;
use Spatie\LaravelSettings\Exceptions\SettingAlreadyExists;

class SitesSettings extends SettingsMigration
{
    public function up(): void
    {
        $this->safeAdd('sites.site_name', '3x1');
        $this->safeAdd('sites.site_description', 'Creative Solutions');
        $this->safeAdd('sites.site_keywords', 'Graphics, Marketing, Programming');
        $this->safeAdd('sites.site_profile', '');
        $this->safeAdd('sites.site_logo', '');
        $this->safeAdd('sites.site_author', 'Fady Mondy');
        $this->safeAdd('sites.site_address', 'Cairo, Egypt');
        $this->safeAdd('sites.site_email', 'info@3x1.io');
        $this->safeAdd('sites.site_phone', '+201207860084');
        $this->safeAdd('sites.site_phone_code', '+2');
        $this->safeAdd('sites.site_location', 'Egypt');
        $this->safeAdd('sites.site_currency', 'EGP');
        $this->safeAdd('sites.site_language', 'English');
        $this->safeAdd('sites.site_social', []);
    }

    protected function safeAdd(string $key, mixed $value): void
    {
        try {
            $this->migrator->add($key, $value);
        } catch (SettingAlreadyExists $e) {
            // Ignorar si ya existe
        }
    }
}
