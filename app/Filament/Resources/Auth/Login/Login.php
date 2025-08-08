<?php

namespace App\Filament\Resources\Auth\Login;

use App\Models\User;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Component;
use Filament\Pages\Auth\Login as BaseAuth;
use Illuminate\Validation\ValidationException;

class Login extends BaseAuth
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getLoginFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    protected function getLoginFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('NIF')
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    /**
     * Aquí controlamos:
     *  - Detectar si el login es NIF o email
     *  - Bloquear si el usuario existe y está marcado como is_blocked
     *  - Añadir is_blocked = 0 a las credenciales para que Auth::attempt falle si está bloqueado
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        $loginType = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'nif';

        // Si el usuario existe y está bloqueado, mostramos un error específico
        $user = User::query()->where($loginType, $data['login'])->first();
        if ($user?->is_blocked) {
            throw ValidationException::withMessages([
                'data.login' => 'Tu cuenta está bloqueada. Contacta con administración.',
            ]);
        }

        // Credenciales para Auth::attempt: además de login+password,
        // exigimos is_blocked = 0 para que no pueda autenticarse un bloqueado.
        return [
            $loginType => $data['login'],
            'password' => $data['password'],
            'is_blocked' => 0,
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }
}
