<?php

namespace App\Http\Requests\Setup;

use App\Support\Installer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class InstallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Installer::active();
    }

    public function rules(): array
    {
        return [
            'app_url' => ['required', 'url:http,https', 'max:255'],
            'company' => ['nullable', 'string', 'max:120'],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:64'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
            'mail_mailer' => ['required', 'in:smtp,sendmail,log'],
            'mail_host' => ['required_if:mail_mailer,smtp', 'nullable', 'string', 'max:255'],
            'mail_port' => ['required_if:mail_mailer,smtp', 'nullable', 'integer', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_from' => ['nullable', 'email', 'max:255'],
            'demo' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['db_database' => 'database name', 'db_username' => 'database user', 'admin_password' => 'password', 'mail_host' => 'SMTP server', 'mail_port' => 'SMTP port'];
    }

    /** The database details must actually connect. */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->hasAny(['db_host', 'db_port', 'db_database', 'db_username'])) {
                return;
            }
            if ($error = $this->databaseError()) {
                $validator->errors()->add('db_host', $error);
            }
        }];
    }

    private function databaseError(): ?string
    {
        try {
            new \PDO("mysql:host={$this->input('db_host')};port={$this->integer('db_port')};dbname={$this->input('db_database')};charset=utf8mb4",
                $this->input('db_username'), (string) $this->input('db_password'), [\PDO::ATTR_TIMEOUT => 5, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

            return null;
        } catch (\Throwable $e) {
            return 'Could not connect to the database: '.$e->getMessage();
        }
    }
}
