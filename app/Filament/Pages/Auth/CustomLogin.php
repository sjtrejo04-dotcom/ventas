<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseAuth;
use Illuminate\Contracts\Support\Htmlable;

class CustomLogin extends BaseAuth
{
    protected string $view = 'filament.pages.auth.custom-login';

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return '';
    }

    public function hasLogo(): bool
    {
        return false;
    }

    public function mount(): void
    {
        parent::mount();

        /*
         * Puedes personalizar los valores por defecto del formulario de login.
         * Por ejemplo, rellenar el email para el entorno de desarrollo:
         * $this->form->fill([
         *     'email' => 'admin@example.com',
         *     'password' => 'password',
         *     'remember' => true,
         * ]);
         */
    }

    // Aquí puedes sobreescribir la vista si necesitas personalización extrema,
    // o puedes depender de la configuración del panel (colores y logo)
    // y simplemente tener esta clase lista para futuras extensiones.
}
