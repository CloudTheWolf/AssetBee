<?php

namespace App\Actions\Assets;

use App\Models\Hardware;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorContract;

class UpdateHardwareProxmoxCredentials
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Hardware $hardware, array $input): Hardware
    {
        if (! $hardware->is_vm_host) {
            throw ValidationException::withMessages([
                'is_vm_host' => __('Proxmox credentials can only be saved on VM host hardware.'),
            ]);
        }

        $validator = Validator::make($input, [
            'api_url' => ['required', 'string', 'max:255', 'url'],
            'token_id' => ['required', 'string', 'max:255'],
            'token_secret' => ['nullable', 'string', 'max:255'],
            'verify_tls' => ['sometimes', 'boolean'],
            'node' => ['nullable', 'string', 'max:255'],
        ]);

        $this->assertSecretProvidedWhenNeeded($validator, $hardware, $input);
        $validated = $validator->validate();

        $existing = $hardware->proxmox_credentials ?? [];

        $hardware->update([
            'proxmox_credentials' => [
                'api_url' => rtrim((string) $validated['api_url'], '/'),
                'token_id' => $validated['token_id'],
                'token_secret' => filled($validated['token_secret'] ?? null)
                    ? $validated['token_secret']
                    : ($existing['token_secret'] ?? null),
                'verify_tls' => array_key_exists('verify_tls', $validated)
                    ? (bool) $validated['verify_tls']
                    : (bool) ($existing['verify_tls'] ?? true),
                'node' => filled($validated['node'] ?? null)
                    ? trim((string) $validated['node'])
                    : null,
            ],
            'proxmox_credentials_verified_at' => null,
        ]);

        return $hardware->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function assertSecretProvidedWhenNeeded(
        ValidatorContract $validator,
        Hardware $hardware,
        array $input,
    ): void {
        $validator->after(function (ValidatorContract $validator) use ($hardware, $input): void {
            if ($hardware->hasProxmoxCredentials()) {
                return;
            }

            if (blank($input['token_secret'] ?? null)) {
                $validator->errors()->add(
                    'token_secret',
                    __('This field is required when saving credentials for the first time.'),
                );
            }
        });
    }
}
