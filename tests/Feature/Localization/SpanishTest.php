<?php

namespace Tests\Feature\Localization;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SpanishTest extends TestCase
{
    public function test_the_application_runs_in_spanish(): void
    {
        $this->assertSame('es', app()->getLocale());
        $this->assertSame('es_PE', config('app.faker_locale'));
    }

    public function test_validation_messages_use_spanish_field_names(): void
    {
        $errors = Validator::make(
            ['email' => 'no-es-un-correo', 'password' => '123'],
            ['email' => 'required|email', 'password' => 'required|min:8', 'name' => 'required'],
        )->errors();

        $this->assertSame('El campo correo electrónico debe ser un correo electrónico válido.', $errors->first('email'));
        $this->assertSame('El campo contraseña debe tener al menos 8 caracteres.', $errors->first('password'));
        $this->assertSame('El campo nombre es obligatorio.', $errors->first('name'));
    }

    public function test_auth_and_password_messages_are_in_spanish(): void
    {
        $this->assertSame('Estas credenciales no coinciden con nuestros registros.', trans('auth.failed'));
        $this->assertSame('Te hemos enviado por correo el enlace para restablecer tu contraseña.', trans('passwords.sent'));
        $this->assertSame('Siguiente &raquo;', trans('pagination.next'));
    }

    public function test_every_breeze_string_has_a_spanish_translation(): void
    {
        $translations = json_decode(file_get_contents(lang_path('es.json')), true, flags: JSON_THROW_ON_ERROR);
        $missing = [];

        $files = array_merge(
            glob(resource_path('views/livewire/**/*.blade.php')),
            glob(resource_path('views/livewire/*/*/*.blade.php')),
            glob(resource_path('views/layouts/*.blade.php')),
            glob(resource_path('views/components/*.blade.php')),
            [resource_path('views/profile.blade.php'), resource_path('views/dashboard.blade.php')],
        );

        foreach ($files as $file) {
            preg_match_all('/__\(\s*(["\'])((?:\\\\.|(?!\1).)*)\1/s', file_get_contents($file), $matches);

            foreach ($matches[2] as $key) {
                $key = stripcslashes($key);

                // Keys such as auth.failed live in lang/es/*.php, not in the JSON file.
                if (preg_match('/^[a-z_]+(\.[a-z_]+)+$/', $key)) {
                    continue;
                }

                if (! array_key_exists($key, $translations)) {
                    $missing[$key] = basename($file);
                }
            }
        }

        $this->assertSame([], $missing, 'Strings without a Spanish translation in lang/es.json');
    }
}
