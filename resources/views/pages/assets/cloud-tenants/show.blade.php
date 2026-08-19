<?php

use App\Actions\Assets\ClearCloudTenantCredentials;
use App\Actions\Assets\DeleteCloudTenant;
use App\Actions\Assets\DiscoverCloudVirtualMachines;
use App\Actions\Assets\ImportCloudVirtualMachines;
use App\Actions\Assets\UpdateCloudTenant;
use App\Actions\Assets\UpdateCloudTenantCredentials;
use App\Enums\AwsRegion;
use App\Enums\CloudTenantProvider;
use App\Enums\CloudTenantStatus;
use App\Models\CloudTenant;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Cloud Tenant')] class extends Component {
    use AuthorizesRequests;

    public CloudTenant $cloudTenant;

    public string $name = '';

    public string $provider = '';

    public string $external_id = '';

    public string $domain = '';

    public string $status = '';

    public string $notes = '';

    public string $access_key_id = '';

    public string $secret_access_key = '';

    public string $region = 'us-east-1';

    public string $session_token = '';

    public string $tenant_id = '';

    public string $client_id = '';

    public string $client_secret = '';

    public string $subscription_id = '';

    public string $project_id = '';

    public string $service_account_json = '';

    public string $discoveryRegion = 'us-east-1';

    /** @var list<string> */
    public array $selectedExternalIds = [];

    public ?string $discoveryError = null;

    public function mount(CloudTenant $cloudTenant): void
    {
        $this->authorize('view', $cloudTenant);
        abort_unless($cloudTenant->organization_id === CurrentOrganization::require()->id, 404);

        $this->cloudTenant = $cloudTenant->load('virtualwares');
        $this->fillForm();
        $this->fillCredentialForm();
    }

    public function fillForm(): void
    {
        $this->name = $this->cloudTenant->name;
        $this->provider = $this->cloudTenant->provider->value;
        $this->external_id = (string) ($this->cloudTenant->external_id ?? '');
        $this->domain = (string) ($this->cloudTenant->domain ?? '');
        $this->status = $this->cloudTenant->status->value;
        $this->notes = (string) ($this->cloudTenant->notes ?? '');
    }

    public function fillCredentialForm(): void
    {
        foreach ($this->cloudTenant->credentialFormDefaults() as $field => $value) {
            $this->{$field} = $value;
        }

        $this->discoveryRegion = $this->region !== '' ? $this->region : AwsRegion::UsEast1->value;
    }

    public function save(UpdateCloudTenant $updateCloudTenant): void
    {
        $this->authorize('update', $this->cloudTenant);

        $this->cloudTenant = $updateCloudTenant->handle($this->cloudTenant, [
            'name' => $this->name,
            'provider' => $this->provider,
            'external_id' => $this->external_id !== '' ? $this->external_id : null,
            'domain' => $this->domain !== '' ? $this->domain : null,
            'status' => $this->status,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ])->load('virtualwares');

        $this->fillCredentialForm();

        Flux::toast(variant: 'success', text: __('Cloud tenant updated.'));
    }

    public function saveCredentials(UpdateCloudTenantCredentials $updateCloudTenantCredentials): void
    {
        $this->authorize('update', $this->cloudTenant);

        $this->cloudTenant = $updateCloudTenantCredentials->handle(
            $this->cloudTenant,
            $this->credentialInput(),
        )->load('virtualwares');

        $this->fillCredentialForm();
        $this->resetDiscovery();

        Flux::toast(variant: 'success', text: __('Credentials saved.'));
    }

    public function clearCredentials(ClearCloudTenantCredentials $clearCloudTenantCredentials): void
    {
        $this->authorize('update', $this->cloudTenant);

        $this->cloudTenant = $clearCloudTenantCredentials->handle($this->cloudTenant)->load('virtualwares');
        $this->fillCredentialForm();
        $this->resetDiscovery();

        Flux::toast(variant: 'success', text: __('Credentials removed.'));
    }

    public function discoverVirtualMachines(DiscoverCloudVirtualMachines $discoverCloudVirtualMachines): void
    {
        $this->authorize('update', $this->cloudTenant);
        $this->discoveryError = null;

        $this->validate([
            'discoveryRegion' => ['required', 'string', Rule::enum(AwsRegion::class)],
        ]);

        try {
            $discovered = $discoverCloudVirtualMachines->handle($this->cloudTenant, $this->discoveryRegion);
        } catch (\Throwable $exception) {
            $this->resetDiscovery();
            $this->discoveryError = $exception->getMessage();

            return;
        }

        $importedIds = $this->cloudTenant->virtualwares()
            ->whereNotNull('external_id')
            ->pluck('external_id')
            ->all();

        $this->cloudTenant->refresh();
        $machines = collect($discovered)
            ->map(fn ($machine): array => [
                ...Arr::except($machine->toArray(), 'notes'),
                'already_imported' => in_array($machine->externalId, $importedIds, true),
            ])
            ->values()
            ->all();

        Cache::put($this->discoveryCacheKey(), $machines, now()->addMinutes(30));
        unset($this->discoveredMachines);

        $this->selectedExternalIds = collect($machines)
            ->pluck('external_id')
            ->values()
            ->all();

        if ($machines === []) {
            Flux::toast(text: __('No virtual machines were found in :region.', [
                'region' => $this->discoveryRegion,
            ]));
        }
    }

    public function importVirtualMachines(ImportCloudVirtualMachines $importCloudVirtualMachines): void
    {
        $this->authorize('update', $this->cloudTenant);

        $result = $importCloudVirtualMachines->handle(
            $this->cloudTenant,
            $this->selectedExternalIds,
            $this->discoveryRegion,
        );

        $this->cloudTenant = $this->cloudTenant->fresh()->load('virtualwares');
        $this->resetDiscovery();

        Flux::toast(
            variant: 'success',
            text: __('Imported :created new and updated :updated virtual machines.', [
                'created' => $result['created'],
                'updated' => $result['updated'],
            ]),
        );
    }

    public function delete(DeleteCloudTenant $deleteCloudTenant): void
    {
        $this->authorize('delete', $this->cloudTenant);
        $deleteCloudTenant->handle($this->cloudTenant);
        $this->redirect(route('assets.cloud-tenants.index', absolute: false));
    }

    /**
     * @return array<string, mixed>
     */
    protected function credentialInput(): array
    {
        return match ($this->cloudTenant->provider) {
            CloudTenantProvider::Aws => [
                'access_key_id' => $this->access_key_id,
                'secret_access_key' => $this->secret_access_key !== '' ? $this->secret_access_key : null,
                'region' => $this->region,
                'session_token' => $this->session_token !== '' ? $this->session_token : null,
            ],
            CloudTenantProvider::Azure => [
                'tenant_id' => $this->tenant_id,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret !== '' ? $this->client_secret : null,
                'subscription_id' => $this->subscription_id,
            ],
            CloudTenantProvider::Gcp => [
                'project_id' => $this->project_id,
                'service_account_json' => $this->service_account_json !== '' ? $this->service_account_json : null,
            ],
            default => [],
        };
    }

    /**
     * Held in the cache rather than in component state so the list is not
     * serialised into the payload of every Livewire request.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function discoveredMachines(): array
    {
        return Cache::get($this->discoveryCacheKey(), []);
    }

    /** @return list<string> */
    public function discoveredExternalIds(): array
    {
        return collect($this->discoveredMachines)->pluck('external_id')->values()->all();
    }

    protected function discoveryCacheKey(): string
    {
        return sprintf('cloud-tenant:%s:discovery:%s', $this->cloudTenant->id, auth()->id());
    }

    protected function resetDiscovery(): void
    {
        Cache::forget($this->discoveryCacheKey());
        unset($this->discoveredMachines);

        $this->selectedExternalIds = [];
        $this->discoveryError = null;
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex items-center gap-3">
        <flux:button size="sm" :href="route('assets.cloud-tenants.index')" icon="arrow-left">{{ __('Back') }}</flux:button>
        <div>
            <flux:heading size="xl">{{ $cloudTenant->name }}</flux:heading>
            <flux:text>{{ $cloudTenant->provider->label() }} · {{ $cloudTenant->status->label() }}</flux:text>
        </div>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:input wire:model="name" :label="__('Name')" required :disabled="! auth()->user()->can('update', $cloudTenant)" />
        <flux:select wire:model="provider" :label="__('Provider')" :disabled="! auth()->user()->can('update', $cloudTenant)">
            @foreach (CloudTenantProvider::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="external_id" :label="__('External ID')" :disabled="! auth()->user()->can('update', $cloudTenant)" />
        <flux:input wire:model="domain" :label="__('Domain')" :disabled="! auth()->user()->can('update', $cloudTenant)" />
        <flux:select wire:model="status" :label="__('Status')" :disabled="! auth()->user()->can('update', $cloudTenant)">
            @foreach (CloudTenantStatus::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" :disabled="! auth()->user()->can('update', $cloudTenant)" />
        @can('update', $cloudTenant)
            <div class="flex justify-between">
                <flux:button variant="danger" type="button" wire:click="delete" wire:confirm="{{ __('Delete this cloud tenant?') }}">{{ __('Delete') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        @endcan
    </form>

    @if ($cloudTenant->provider->supportsCredentials())
        <form wire:submit="saveCredentials" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div>
                <flux:heading size="lg">{{ __('Credentials') }}</flux:heading>
                <flux:text>
                    {{ __('Store encrypted provider credentials used to discover and import virtual machines.') }}
                    @if ($cloudTenant->hasCredentials())
                        · {{ __('Credentials are saved.') }}
                        @if ($cloudTenant->credentials_verified_at)
                            {{ __('Last verified :time.', ['time' => $cloudTenant->credentials_verified_at->diffForHumans()]) }}
                        @endif
                    @endif
                </flux:text>
            </div>

            @if ($cloudTenant->provider === CloudTenantProvider::Aws)
                <flux:input wire:model="access_key_id" :label="__('Access key ID')" required :disabled="! auth()->user()->can('update', $cloudTenant)" />
                <flux:input
                    wire:model="secret_access_key"
                    type="password"
                    :label="__('Secret access key')"
                    :description="$cloudTenant->hasCredentials() ? __('Leave blank to keep the existing secret.') : null"
                    :required="! $cloudTenant->hasCredentials()"
                    :disabled="! auth()->user()->can('update', $cloudTenant)"
                />
                <flux:select wire:model="region" :label="__('Default region')" required :disabled="! auth()->user()->can('update', $cloudTenant)">
                    @foreach (AwsRegion::cases() as $awsRegion)
                        <option value="{{ $awsRegion->value }}">{{ $awsRegion->label() }} ({{ $awsRegion->value }})</option>
                    @endforeach
                </flux:select>
                <flux:input
                    wire:model="session_token"
                    type="password"
                    :label="__('Session token')"
                    :description="__('Optional. Leave blank unless using temporary credentials.')"
                    :disabled="! auth()->user()->can('update', $cloudTenant)"
                />
            @elseif ($cloudTenant->provider === CloudTenantProvider::Azure)
                <flux:input wire:model="tenant_id" :label="__('Directory (tenant) ID')" required :disabled="! auth()->user()->can('update', $cloudTenant)" />
                <flux:input wire:model="client_id" :label="__('Application (client) ID')" required :disabled="! auth()->user()->can('update', $cloudTenant)" />
                <flux:input
                    wire:model="client_secret"
                    type="password"
                    :label="__('Client secret')"
                    :description="$cloudTenant->hasCredentials() ? __('Leave blank to keep the existing secret.') : null"
                    :required="! $cloudTenant->hasCredentials()"
                    :disabled="! auth()->user()->can('update', $cloudTenant)"
                />
                <flux:input wire:model="subscription_id" :label="__('Subscription ID')" required :disabled="! auth()->user()->can('update', $cloudTenant)" />
            @elseif ($cloudTenant->provider === CloudTenantProvider::Gcp)
                <flux:input wire:model="project_id" :label="__('Project ID')" required :disabled="! auth()->user()->can('update', $cloudTenant)" />
                <flux:textarea
                    wire:model="service_account_json"
                    :label="__('Service account JSON')"
                    rows="6"
                    :description="$cloudTenant->hasCredentials() ? __('Leave blank to keep the existing key.') : null"
                    :required="! $cloudTenant->hasCredentials()"
                    :disabled="! auth()->user()->can('update', $cloudTenant)"
                />
            @endif

            @can('update', $cloudTenant)
                <div class="flex justify-between gap-3">
                    @if ($cloudTenant->hasCredentials())
                        <flux:button
                            variant="danger"
                            type="button"
                            wire:click="clearCredentials"
                            wire:confirm="{{ __('Remove stored credentials for this tenant?') }}"
                        >
                            {{ __('Remove credentials') }}
                        </flux:button>
                    @else
                        <div></div>
                    @endif
                    <flux:button variant="primary" type="submit">{{ __('Save credentials') }}</flux:button>
                </div>
            @endcan
        </form>
    @endif

    @if ($cloudTenant->provider->supportsVmImport())
        <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div>
                <flux:heading size="lg">{{ __('Import VMs') }}</flux:heading>
                <flux:text>{{ __('Discover EC2 instances from this AWS account and import them as virtualware.') }}</flux:text>
            </div>

            @unless ($cloudTenant->hasCredentials())
                <flux:text>{{ __('Add AWS credentials above before discovering instances.') }}</flux:text>
            @endunless

            @can('update', $cloudTenant)
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <flux:select
                            wire:model="discoveryRegion"
                            :label="__('Region')"
                            :disabled="! $cloudTenant->hasCredentials()"
                        >
                            @foreach (AwsRegion::cases() as $awsRegion)
                                <option value="{{ $awsRegion->value }}">{{ $awsRegion->label() }} ({{ $awsRegion->value }})</option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button
                        type="button"
                        wire:click="discoverVirtualMachines"
                        wire:loading.attr="disabled"
                        :disabled="! $cloudTenant->hasCredentials()"
                    >
                        {{ __('Discover EC2 instances') }}
                    </flux:button>
                </div>
            @endcan

            @if ($discoveryError)
                <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/30 dark:text-red-200">
                    {{ $discoveryError }}
                </div>
            @endif

            @if ($this->discoveredMachines !== [])
                <form
                    wire:submit="importVirtualMachines"
                    class="flex flex-col gap-4"
                    x-data="{
                        allIds: @js($this->discoveredExternalIds()),
                        selectAll: @js($selectedExternalIds === $this->discoveredExternalIds()),
                    }"
                    x-init="
                        $watch('selectAll', (value) => $wire.selectedExternalIds = value ? allIds.slice() : []);
                        $watch('$wire.selectedExternalIds', (ids) => selectAll = allIds.length !== 0 && ids.length === allIds.length);
                    "
                >
                    <div class="flex items-center justify-between gap-3">
                        <flux:heading size="sm">{{ __('Select instances to import') }}</flux:heading>
                        <flux:switch x-model="selectAll" :label="__('Select all')" />
                    </div>

                    <flux:checkbox.group wire:model="selectedExternalIds">
                        @foreach ($this->discoveredMachines as $machine)
                            <flux:field variant="inline" wire:key="discovered-{{ $machine['external_id'] }}">
                                <flux:checkbox value="{{ $machine['external_id'] }}" />
                                <div>
                                    <flux:label>{{ $machine['name'] }}</flux:label>
                                    <flux:description>
                                        {{ $machine['external_id'] }}
                                        @if ($machine['instance_type'])
                                            · {{ $machine['instance_type'] }}
                                        @endif
                                        · {{ $machine['region'] }}
                                        @if ($machine['availability_zone'])
                                            · {{ $machine['availability_zone'] }}
                                        @endif
                                        · {{ $machine['status'] }}
                                        @if ($machine['private_ip'])
                                            · {{ $machine['private_ip'] }}
                                        @endif
                                        @if ($machine['public_ip'])
                                            · {{ $machine['public_ip'] }}
                                        @endif
                                        @if (! empty($machine['secondary_ips']))
                                            · {{ trans_choice(':count secondary IP|:count secondary IPs', count($machine['secondary_ips']), ['count' => count($machine['secondary_ips'])]) }}
                                        @endif
                                        @if ($machine['vpc_id'])
                                            · {{ $machine['vpc_id'] }}
                                        @endif
                                        @if ($machine['subnet_id'])
                                            · {{ $machine['subnet_id'] }}
                                        @endif
                                        @if (! empty($machine['disks']))
                                            · {{ trans_choice(':count disk|:count disks', count($machine['disks']), ['count' => count($machine['disks'])]) }}
                                            @php
                                                $diskGb = collect($machine['disks'])->sum(fn ($disk) => (int) ($disk['size_gb'] ?? 0));
                                            @endphp
                                            @if ($diskGb > 0)
                                                ({{ __(':size GB', ['size' => $diskGb]) }})
                                            @endif
                                        @endif
                                        @if ($machine['termination_protection'] !== null)
                                            · {{ $machine['termination_protection'] ? __('TP on') : __('TP off') }}
                                        @endif
                                        @if ($machine['already_imported'])
                                            · {{ __('Already imported') }}
                                        @endif
                                    </flux:description>
                                </div>
                            </flux:field>
                        @endforeach
                    </flux:checkbox.group>

                    <div class="flex items-center justify-between gap-3">
                        <flux:text x-text="`${$wire.selectedExternalIds.length} {{ __('selected') }}`">{{ __(':count selected', ['count' => count($selectedExternalIds)]) }}</flux:text>
                        <flux:button variant="primary" type="submit" x-bind:disabled="$wire.selectedExternalIds.length === 0">
                            {{ __('Import selected') }}
                        </flux:button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading size="lg">{{ __('Linked virtualware') }}</flux:heading>
        <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($cloudTenant->virtualwares as $virtualware)
                <li class="flex items-center justify-between py-3">
                    <div>
                        <a href="{{ route('assets.virtualware.show', $virtualware) }}" class="font-medium text-accent">{{ $virtualware->name }}</a>
                        <flux:text>
                            {{ $virtualware->provider->label() }}
                            · {{ $virtualware->status->label() }}
                            @if ($virtualware->external_id)
                                · {{ $virtualware->external_id }}
                            @endif
                        </flux:text>
                    </div>
                    <flux:button size="sm" :href="route('assets.virtualware.show', $virtualware)">{{ __('View') }}</flux:button>
                </li>
            @empty
                <li class="py-3"><flux:text>{{ __('No virtualware linked to this tenant.') }}</flux:text></li>
            @endforelse
        </ul>
    </div>
</div>
