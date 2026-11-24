<?php

namespace App\Http\Requests\Api\V1;

use App\Actions\Assets\RecordInventorySyncFailure;
use App\Models\Organization;
use App\Models\OrganizationApiKey;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertInventoryRequest extends FormRequest
{
    private const PROBE_STATUSES = [
        'available',
        'unavailable',
        'unsupported',
        'accessDenied',
        'error',
    ];

    private const TOP_LEVEL_PROPERTIES = [
        'schemaVersion',
        'collectedAtUtc',
        'platform',
        'type',
        'hardwareType',
        'deviceName',
        'serialNumber',
        'manufacturer',
        'model',
        'operatingSystem',
        'cpu',
        'memory',
        'disks',
        'diskEncryption',
        'domainWorkspace',
        'loginProviders',
        'antivirus',
        'updates',
        'sbom',
    ];

    public function authorize(): bool
    {
        return $this->attributes->has('organization');
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        $organization = $this->attributes->get('organization');

        if ($organization instanceof Organization) {
            $apiKey = $this->attributes->get('organizationApiKey');
            $errorCount = $validator->errors()->count();
            $message = (string) $validator->errors()->first();

            if ($errorCount > 1) {
                $remaining = $errorCount - 1;
                $message .= ' (and '.$remaining.' more '.($remaining === 1 ? 'error' : 'errors').')';
            }

            app(RecordInventorySyncFailure::class)->handle(
                $organization,
                $this->all(),
                $message !== '' ? $message : __('Inventory sync failed.'),
                $validator->errors()->toArray(),
                422,
                $apiKey instanceof OrganizationApiKey ? $apiKey : null,
            );
        }

        parent::failedValidation($validator);
    }

    /**
     * Normalize BitLocker volume fields drones sometimes send empty or numeric.
     */
    protected function prepareForValidation(): void
    {
        $diskEncryption = $this->input('diskEncryption');

        if (! is_array($diskEncryption) || ! is_array($diskEncryption['value'] ?? null)) {
            return;
        }

        $volumes = [];

        foreach ($diskEncryption['value'] as $volume) {
            if (! is_array($volume)) {
                $volumes[] = $volume;

                continue;
            }

            $state = $volume['state'] ?? null;

            if (! is_string($state) || trim($state) === '') {
                $volume['state'] = 'Unknown';
            }

            if (array_key_exists('protectionStatus', $volume)) {
                $volume['protectionStatus'] = $this->normalizeProtectionStatus($volume['protectionStatus']);
            }

            $volumes[] = $volume;
        }

        $diskEncryption['value'] = $volumes;

        $this->merge(['diskEncryption' => $diskEncryption]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'schemaVersion' => ['required', 'string', Rule::in(['1.0'])],
            'collectedAtUtc' => ['required', 'date'],
            'platform' => ['required', 'string', Rule::in(['windows', 'linux', 'macos'])],
            'type' => ['required', 'string', Rule::in(['hardware', 'virtualware'])],

            'hardwareType' => ['required', 'array:status,value,detail'],
            'hardwareType.status' => $this->probeStatusRules(),
            'hardwareType.value' => ['nullable', 'string', Rule::in(['laptop', 'desktop', 'server'])],
            'hardwareType.detail' => ['nullable', 'string', 'max:1000'],

            'deviceName' => ['required', 'array:status,value,detail'],
            'deviceName.status' => $this->probeStatusRules(),
            'deviceName.value' => ['nullable', 'string', 'max:255'],
            'deviceName.detail' => ['nullable', 'string', 'max:1000'],

            'serialNumber' => ['required', 'array:status,value,detail'],
            'serialNumber.status' => $this->probeStatusRules(),
            'serialNumber.value' => ['nullable', 'string', 'max:255'],
            'serialNumber.detail' => ['nullable', 'string', 'max:1000'],

            'manufacturer' => ['required', 'array:status,value,detail'],
            'manufacturer.status' => $this->probeStatusRules(),
            'manufacturer.value' => ['nullable', 'string', 'max:255'],
            'manufacturer.detail' => ['nullable', 'string', 'max:1000'],

            'model' => ['required', 'array:status,value,detail'],
            'model.status' => $this->probeStatusRules(),
            'model.value' => ['nullable', 'string', 'max:255'],
            'model.detail' => ['nullable', 'string', 'max:1000'],

            'operatingSystem' => ['required', 'array:status,value,detail'],
            'operatingSystem.status' => $this->probeStatusRules(),
            'operatingSystem.value' => ['nullable', 'array:name,version,displayVersion,build,kernel'],
            'operatingSystem.value.name' => ['required_if:operatingSystem.status,available', 'string', 'max:255'],
            'operatingSystem.value.version' => ['required_if:operatingSystem.status,available', 'string', 'max:255'],
            'operatingSystem.value.displayVersion' => ['nullable', 'string', 'max:255'],
            'operatingSystem.value.build' => ['nullable', 'string', 'max:255'],
            'operatingSystem.value.kernel' => ['nullable', 'string', 'max:255'],
            'operatingSystem.detail' => ['nullable', 'string', 'max:1000'],

            'cpu' => ['required', 'array:status,value,detail'],
            'cpu.status' => $this->probeStatusRules(),
            'cpu.value' => ['nullable', 'array:model,logicalProcessors,physicalCores'],
            'cpu.value.model' => ['required_if:cpu.status,available', 'string', 'max:255'],
            'cpu.value.logicalProcessors' => ['required_if:cpu.status,available', 'integer', 'min:1'],
            'cpu.value.physicalCores' => ['nullable', 'integer', 'min:1'],
            'cpu.detail' => ['nullable', 'string', 'max:1000'],

            'memory' => ['required', 'array:status,value,detail'],
            'memory.status' => $this->probeStatusRules(),
            'memory.value' => ['nullable', 'array:totalBytes,availableBytes'],
            'memory.value.totalBytes' => ['required_if:memory.status,available', 'integer', 'min:0'],
            'memory.value.availableBytes' => ['nullable', 'integer', 'min:0'],
            'memory.detail' => ['nullable', 'string', 'max:1000'],

            'disks' => ['required', 'array:status,value,detail'],
            'disks.status' => $this->probeStatusRules(),
            'disks.value' => ['nullable', 'array', 'max:500'],
            'disks.value.*' => ['array:name,mountPoint,totalBytes,availableBytes,fileSystem'],
            'disks.value.*.name' => ['required', 'string', 'max:1024'],
            'disks.value.*.mountPoint' => ['nullable', 'string', 'max:1024'],
            'disks.value.*.totalBytes' => ['required', 'integer', 'min:0'],
            'disks.value.*.availableBytes' => ['nullable', 'integer', 'min:0'],
            'disks.value.*.fileSystem' => ['nullable', 'string', 'max:100'],
            'disks.detail' => ['nullable', 'string', 'max:1000'],

            'diskEncryption' => ['required', 'array:status,value,detail'],
            'diskEncryption.status' => $this->probeStatusRules(),
            'diskEncryption.value' => ['nullable', 'array', 'max:100'],
            'diskEncryption.value.*' => ['array:volume,technology,state,recoveryKeys,keyProtectors,protectionStatus'],
            'diskEncryption.value.*.volume' => ['required', 'string', 'max:255'],
            'diskEncryption.value.*.technology' => ['required', 'string', 'max:100'],
            'diskEncryption.value.*.state' => ['required', 'string', 'max:100'],
            'diskEncryption.value.*.recoveryKeys' => ['nullable', 'array', 'max:20'],
            'diskEncryption.value.*.recoveryKeys.*' => ['string', 'max:255'],
            'diskEncryption.value.*.keyProtectors' => ['nullable', 'array', 'max:20'],
            'diskEncryption.value.*.keyProtectors.*' => ['array:keyProtectorId,recoveryKey'],
            'diskEncryption.value.*.keyProtectors.*.keyProtectorId' => ['required', 'string', 'max:255'],
            'diskEncryption.value.*.keyProtectors.*.recoveryKey' => ['nullable', 'string', 'max:255'],
            'diskEncryption.value.*.protectionStatus' => ['nullable', 'string', Rule::in(['On', 'Off', 'Unknown'])],
            'diskEncryption.detail' => ['nullable', 'string', 'max:1000'],

            'domainWorkspace' => ['required', 'array:status,value,detail'],
            'domainWorkspace.status' => $this->probeStatusRules(),
            'domainWorkspace.value' => ['nullable', 'array:domain,domainJoined,workspace,workspaceJoined'],
            'domainWorkspace.value.domain' => ['nullable', 'string', 'max:255'],
            'domainWorkspace.value.domainJoined' => ['nullable', 'boolean'],
            'domainWorkspace.value.workspace' => ['nullable', 'string', 'max:255'],
            'domainWorkspace.value.workspaceJoined' => ['nullable', 'boolean'],
            'domainWorkspace.detail' => ['nullable', 'string', 'max:1000'],

            'loginProviders' => ['required', 'array:status,value,detail'],
            'loginProviders.status' => $this->probeStatusRules(),
            'loginProviders.value' => ['nullable', 'array', 'max:100'],
            'loginProviders.value.*' => ['array:name,state,detail'],
            'loginProviders.value.*.name' => ['required', 'string', 'max:255'],
            'loginProviders.value.*.state' => ['required', 'string', 'max:100'],
            'loginProviders.value.*.detail' => ['nullable', 'string', 'max:1000'],
            'loginProviders.detail' => ['nullable', 'string', 'max:1000'],

            'antivirus' => ['required', 'array:status,value,detail'],
            'antivirus.status' => $this->probeStatusRules(),
            'antivirus.value' => ['nullable', 'array', 'max:100'],
            'antivirus.value.*' => ['array:name,state,enabled,upToDate,detail'],
            'antivirus.value.*.name' => ['required', 'string', 'max:255'],
            'antivirus.value.*.state' => ['required', 'string', 'max:100'],
            'antivirus.value.*.enabled' => ['nullable', 'boolean'],
            'antivirus.value.*.upToDate' => ['nullable', 'boolean'],
            'antivirus.value.*.detail' => ['nullable', 'string', 'max:1000'],
            'antivirus.detail' => ['nullable', 'string', 'max:1000'],

            'updates' => ['required', 'array:status,value,detail'],
            'updates.status' => $this->probeStatusRules(),
            'updates.value' => ['nullable', 'array:installed,available'],
            'updates.value.installed' => ['present_if:updates.status,available', 'array', 'max:10000'],
            'updates.value.available' => ['present_if:updates.status,available', 'array', 'max:10000'],
            'updates.value.installed.*' => ['array:id,title,category,installedAtUtc,kbArticle'],
            'updates.value.available.*' => ['array:id,title,category,installedAtUtc,kbArticle'],
            'updates.value.*.*.id' => ['required', 'string', 'max:255'],
            'updates.value.*.*.title' => ['required', 'string', 'max:1000'],
            'updates.value.*.*.category' => ['nullable', 'string', 'max:255'],
            'updates.value.*.*.installedAtUtc' => ['nullable', 'date'],
            'updates.value.*.*.kbArticle' => ['nullable', 'string', 'max:255'],
            'updates.detail' => ['nullable', 'string', 'max:1000'],

            'sbom' => ['required', 'array:status,value,detail'],
            'sbom.status' => $this->probeStatusRules(),
            'sbom.value' => ['nullable', 'array:format,specVersion,generatedAtUtc,targets'],
            'sbom.value.format' => ['required_if:sbom.status,available', 'string', Rule::in(['CycloneDX'])],
            'sbom.value.specVersion' => ['required_if:sbom.status,available', 'string', 'max:50'],
            'sbom.value.generatedAtUtc' => ['nullable', 'date'],
            'sbom.value.targets' => ['required_if:sbom.status,available', 'array', 'max:100'],
            'sbom.value.targets.*' => ['array:bomRef,kind,name,components,image,containerId,detail'],
            'sbom.value.targets.*.bomRef' => ['required', 'string', 'max:255'],
            'sbom.value.targets.*.kind' => ['required', 'string', 'max:100'],
            'sbom.value.targets.*.name' => ['required', 'string', 'max:255'],
            'sbom.value.targets.*.image' => ['nullable', 'string', 'max:500'],
            'sbom.value.targets.*.containerId' => ['nullable', 'string', 'max:255'],
            'sbom.value.targets.*.detail' => ['nullable', 'string', 'max:1000'],
            'sbom.value.targets.*.components' => ['present', 'array', 'max:50000'],
            'sbom.value.targets.*.components.*' => ['array:name,version,type,purl,publisher'],
            'sbom.value.targets.*.components.*.name' => ['required', 'string', 'max:500'],
            'sbom.value.targets.*.components.*.version' => ['nullable', 'string', 'max:255'],
            'sbom.value.targets.*.components.*.type' => ['required', 'string', 'max:100'],
            'sbom.value.targets.*.components.*.purl' => ['nullable', 'string', 'max:2000'],
            'sbom.value.targets.*.components.*.publisher' => ['nullable', 'string', 'max:500'],
            'sbom.detail' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (array_diff(array_keys($this->all()), self::TOP_LEVEL_PROPERTIES) as $property) {
                    $validator->errors()->add($property, "The {$property} property is not allowed.");
                }

                $deviceNameAvailable = $this->input('deviceName.status') === 'available'
                    && trim((string) $this->input('deviceName.value')) !== '';
                $serialNumberAvailable = $this->input('serialNumber.status') === 'available'
                    && trim((string) $this->input('serialNumber.value')) !== '';

                if (! $deviceNameAvailable && ! $serialNumberAvailable) {
                    $validator->errors()->add(
                        'deviceName.status',
                        'Either the deviceName or serialNumber probe must be available to identify the hardware.',
                    );
                }

                if (
                    $this->input('deviceName.status') === 'available'
                    && trim((string) $this->input('deviceName.value')) === ''
                ) {
                    $validator->errors()->add('deviceName.value', 'The deviceName value is required.');
                }

                if (
                    $this->input('serialNumber.status') === 'available'
                    && trim((string) $this->input('serialNumber.value')) === ''
                ) {
                    $validator->errors()->add('serialNumber.value', 'The serialNumber value is required.');
                }

                if (
                    $this->input('hardwareType.status') === 'available'
                    && $this->input('hardwareType.value') === null
                ) {
                    $validator->errors()->add(
                        'hardwareType.value',
                        'The hardwareType value is required when the probe is available.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function probeStatusRules(): array
    {
        return ['required', 'string', Rule::in(self::PROBE_STATUSES)];
    }

    /**
     * Map BitLocker ProtectionStatus enums/numerics onto On/Off/Unknown.
     */
    private function normalizeProtectionStatus(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'On' : 'Off';
        }

        if (is_int($value) || (is_string($value) && is_numeric(trim($value)))) {
            return match ((int) $value) {
                0 => 'Off',
                1 => 'On',
                default => 'Unknown',
            };
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return match (strtolower($trimmed)) {
            'on' => 'On',
            'off' => 'Off',
            'unknown' => 'Unknown',
            default => $trimmed,
        };
    }
}
