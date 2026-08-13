@if ($this->inventory)
    @php
        $operatingSystem = $this->inventoryProbe('operatingSystem');
        $cpu = $this->inventoryProbe('cpu');
        $memory = $this->inventoryProbe('memory');
        $domainWorkspace = $this->inventoryProbe('domainWorkspace');
        $updates = $this->inventoryProbe('updates');
        $disks = $this->inventoryList('disks');
        $encryption = $this->inventoryList('diskEncryption');
        $loginProviders = $this->inventoryList('loginProviders');
        $antivirus = $this->inventoryList('antivirus');
        $installedUpdates = is_array(data_get($updates, 'value.installed')) ? data_get($updates, 'value.installed') : [];
        $availableUpdates = is_array(data_get($updates, 'value.available')) ? data_get($updates, 'value.available') : [];
        $sbom = $this->inventoryProbe('sbom');
        $sbomComponentCount = \App\Support\SbomListing::componentCount($sbom);
    @endphp

    <div class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <div>
            <flux:heading size="lg">{{ __('Collected inventory') }}</flux:heading>
            <flux:text>
                {{ __('Last collected :when', ['when' => $collectedAt?->timezone('UTC')->toDayDateTimeString().' UTC']) }}
                · {{ strtoupper((string) data_get($this->inventory, 'platform')) }}
                · {{ __('Schema :version', ['version' => data_get($this->inventory, 'schemaVersion')]) }}
            </flux:text>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <flux:text class="font-medium">{{ __('Operating system') }}</flux:text>
                @if (($operatingSystem['status'] ?? null) === 'available')
                    <div>{{ data_get($operatingSystem, 'value.name') }}</div>
                    <flux:text>
                        {{ collect([
                            data_get($operatingSystem, 'value.version'),
                            data_get($operatingSystem, 'value.displayVersion'),
                            data_get($operatingSystem, 'value.build') ? __('Build :build', ['build' => data_get($operatingSystem, 'value.build')]) : null,
                        ])->filter()->implode(' · ') }}
                    </flux:text>
                @else
                    <flux:text>{{ data_get($operatingSystem, 'status', '—') }}</flux:text>
                @endif
            </div>
            <div>
                <flux:text class="font-medium">{{ __('CPU') }}</flux:text>
                @if (($cpu['status'] ?? null) === 'available')
                    <div>{{ data_get($cpu, 'value.model') }}</div>
                    <flux:text>
                        {{ __(':logical logical / :physical physical', [
                            'logical' => data_get($cpu, 'value.logicalProcessors', '—'),
                            'physical' => data_get($cpu, 'value.physicalCores') ?? '—',
                        ]) }}
                    </flux:text>
                @else
                    <flux:text>{{ data_get($cpu, 'status', '—') }}</flux:text>
                @endif
            </div>
            <div>
                <flux:text class="font-medium">{{ __('Memory') }}</flux:text>
                @if (($memory['status'] ?? null) === 'available')
                    <div>{{ $this->formatBytes(data_get($memory, 'value.totalBytes')) }}</div>
                    @if (data_get($memory, 'value.availableBytes') !== null)
                        <flux:text>{{ __(':available available', ['available' => $this->formatBytes(data_get($memory, 'value.availableBytes'))]) }}</flux:text>
                    @endif
                @else
                    <flux:text>{{ data_get($memory, 'status', '—') }}</flux:text>
                @endif
            </div>
            <div>
                <flux:text class="font-medium">{{ __('Domain / workspace') }}</flux:text>
                @if (($domainWorkspace['status'] ?? null) === 'available')
                    <div>{{ data_get($domainWorkspace, 'value.domain') ?: '—' }}</div>
                    <flux:text>
                        {{ collect([
                            data_get($domainWorkspace, 'value.domainJoined') ? __('Domain joined') : null,
                            data_get($domainWorkspace, 'value.workspace'),
                            data_get($domainWorkspace, 'value.workspaceJoined') ? __('Workspace joined') : null,
                        ])->filter()->implode(' · ') ?: '—' }}
                    </flux:text>
                @else
                    <flux:text>{{ data_get($domainWorkspace, 'status', '—') }}</flux:text>
                @endif
            </div>
        </div>

        <div class="space-y-3 border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <div class="font-medium">{{ __('Disks') }}</div>
            @forelse ($disks as $disk)
                <div class="flex items-start justify-between gap-4 py-2" wire:key="disk-{{ $loop->index }}">
                    <div>
                        <div class="font-medium">{{ $disk['name'] ?? '—' }}</div>
                        <flux:text>
                            {{ collect([
                                $disk['mountPoint'] ?? null,
                                $disk['fileSystem'] ?? null,
                            ])->filter()->implode(' · ') }}
                        </flux:text>
                    </div>
                    <flux:text class="whitespace-nowrap text-right">
                        {{ $this->formatBytes($disk['totalBytes'] ?? null) }}
                        @if (($disk['availableBytes'] ?? null) !== null)
                            <br>{{ __(':available free', ['available' => $this->formatBytes($disk['availableBytes'])]) }}
                        @endif
                    </flux:text>
                </div>
            @empty
                <flux:text>{{ __('No disk details collected.') }}</flux:text>
            @endforelse
        </div>

        <div class="space-y-3 border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <div class="font-medium">{{ __('Disk encryption') }}</div>
            @forelse ($encryption as $volume)
                <div class="flex items-start justify-between gap-4 py-2" wire:key="encryption-{{ $loop->index }}">
                    <div>
                        <div class="font-medium">{{ $volume['volume'] ?? '—' }}</div>
                        <flux:text>{{ ($volume['technology'] ?? '—').' · '.($volume['state'] ?? '—') }}</flux:text>
                    </div>
                    @php
                        $hasRecoveryKey = collect($volume['recoveryKeys'] ?? [])->filter()->isNotEmpty()
                            || collect($volume['keyProtectors'] ?? [])->contains(fn ($protector) => filled(data_get($protector, 'recoveryKey')));
                    @endphp
                    @if ($hasRecoveryKey)
                        <flux:badge size="sm" color="green">{{ __('Recovery key stored') }}</flux:badge>
                    @endif
                </div>
            @empty
                <flux:text>{{ __('No encryption details collected.') }}</flux:text>
            @endforelse
        </div>

        <div class="space-y-3 border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <div class="font-medium">{{ __('Login providers') }}</div>
            @forelse ($loginProviders as $provider)
                <div class="py-2" wire:key="login-{{ $loop->index }}">
                    <div class="font-medium">{{ $provider['name'] ?? '—' }}</div>
                    <flux:text>{{ collect([$provider['state'] ?? null, $provider['detail'] ?? null])->filter()->implode(' · ') }}</flux:text>
                </div>
            @empty
                <flux:text>{{ __('No login providers collected.') }}</flux:text>
            @endforelse
        </div>

        <div class="space-y-3 border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <div class="font-medium">{{ __('Antivirus') }}</div>
            @forelse ($antivirus as $product)
                <div class="flex items-start justify-between gap-4 py-2" wire:key="av-{{ $loop->index }}">
                    <div>
                        <div class="font-medium">{{ $product['name'] ?? '—' }}</div>
                        <flux:text>{{ collect([$product['state'] ?? null, $product['detail'] ?? null])->filter()->implode(' · ') }}</flux:text>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        @if (($product['enabled'] ?? null) === true)
                            <flux:badge size="sm" color="green">{{ __('Enabled') }}</flux:badge>
                        @elseif (($product['enabled'] ?? null) === false)
                            <flux:badge size="sm" color="zinc">{{ __('Disabled') }}</flux:badge>
                        @endif
                        @if (($product['upToDate'] ?? null) === true)
                            <flux:badge size="sm" color="green">{{ __('Up to date') }}</flux:badge>
                        @elseif (($product['upToDate'] ?? null) === false)
                            <flux:badge size="sm" color="amber">{{ __('Out of date') }}</flux:badge>
                        @endif
                    </div>
                </div>
            @empty
                <flux:text>{{ __('No antivirus details collected.') }}</flux:text>
            @endforelse
        </div>

        <div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <div>
                <div class="font-medium">{{ __('Updates') }}</div>
                @if (($updates['status'] ?? null) === 'available')
                    <flux:text>
                        {{ __(':installed installed · :available available', [
                            'installed' => count($installedUpdates),
                            'available' => count($availableUpdates),
                        ]) }}
                    </flux:text>
                @else
                    <flux:text>{{ data_get($updates, 'status', '—') }}</flux:text>
                @endif
            </div>

            @if ($availableUpdates !== [])
                <div class="space-y-2">
                    <flux:text class="font-medium">{{ __('Available') }}</flux:text>
                    <div class="max-h-96 space-y-2 overflow-y-auto rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        @foreach ($availableUpdates as $update)
                            <div class="py-1" wire:key="available-update-{{ $loop->index }}">
                                <div>{{ $update['title'] ?? $update['id'] ?? '—' }}</div>
                                <flux:text>{{ collect([$update['id'] ?? null, $update['category'] ?? null, $update['kbArticle'] ?? null])->filter()->implode(' · ') }}</flux:text>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($installedUpdates !== [])
                <div class="space-y-2">
                    <flux:text class="font-medium">{{ __('Installed') }}</flux:text>
                    <div class="max-h-96 space-y-2 overflow-y-auto rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        @foreach ($installedUpdates as $update)
                            <div class="py-1" wire:key="installed-update-{{ $loop->index }}">
                                <div>{{ $update['title'] ?? $update['id'] ?? '—' }}</div>
                                <flux:text>
                                    {{ collect([
                                        $update['id'] ?? null,
                                        $update['kbArticle'] ?? null,
                                        filled($update['installedAtUtc'] ?? null)
                                            ? \Illuminate\Support\Carbon::parse($update['installedAtUtc'])->timezone('UTC')->toFormattedDateString()
                                            : null,
                                    ])->filter()->implode(' · ') }}
                                </flux:text>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <div>
                <div class="font-medium">{{ __('Software bill of materials') }}</div>
                @if (($sbom['status'] ?? null) === 'available')
                    <flux:text>
                        {{ collect([
                            trim((string) data_get($sbom, 'value.format').' '.(string) data_get($sbom, 'value.specVersion')),
                            __(':count components across :targets targets', [
                                'count' => $sbomComponentCount,
                                'targets' => count(data_get($sbom, 'value.targets', [])),
                            ]),
                        ])->filter()->implode(' · ') }}
                    </flux:text>
                    <flux:text>
                        {{ filled(data_get($sbom, 'value.generatedAtUtc'))
                            ? __('Generated :when', ['when' => \Illuminate\Support\Carbon::parse(data_get($sbom, 'value.generatedAtUtc'))->timezone('UTC')->toDayDateTimeString().' UTC'])
                            : '—' }}
                    </flux:text>
                @else
                    <flux:text>{{ data_get($sbom, 'status', '—') }}</flux:text>
                @endif
            </div>

            @if (($sbom['status'] ?? null) === 'available')
                <flux:input
                    wire:model.live.debounce.300ms="sbomSearch"
                    :placeholder="__('Search SBOM components…')"
                />

                <div class="max-h-96 space-y-4 overflow-y-auto rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    @forelse ($this->filteredSbomTargets as $sbomTarget)
                        @php
                            $target = $sbomTarget['target'];
                            $sbomComponents = $sbomTarget['components'];
                            $matchingCount = $sbomTarget['matchingCount'];
                        @endphp
                        <div class="space-y-2" wire:key="sbom-target-{{ $loop->index }}">
                            <div>
                                <flux:text class="font-medium">{{ $target['name'] ?? $target['bomRef'] ?? __('Target') }}</flux:text>
                                <flux:text>
                                    {{ collect([
                                        $target['kind'] ?? null,
                                        filled($target['image'] ?? null) ? $target['image'] : null,
                                        __(':count components', ['count' => $matchingCount]),
                                    ])->filter()->implode(' · ') }}
                                </flux:text>
                            </div>

                            @foreach ($sbomComponents as $component)
                                <div class="py-1" wire:key="sbom-component-{{ $loop->parent->index }}-{{ $loop->index }}">
                                    <div>
                                        {{ $component['name'] ?? '—' }}
                                        @if (filled($component['version'] ?? null))
                                            <span class="text-zinc-500 dark:text-zinc-400">{{ $component['version'] }}</span>
                                        @endif
                                    </div>
                                    <flux:text>
                                        {{ collect([
                                            $component['type'] ?? null,
                                            $component['publisher'] ?? null,
                                            $component['purl'] ?? null,
                                        ])->filter()->implode(' · ') }}
                                    </flux:text>
                                </div>
                            @endforeach

                            @if ($matchingCount > count($sbomComponents))
                                <flux:text>{{ __('Showing first :shown of :count components…', ['shown' => count($sbomComponents), 'count' => $matchingCount]) }}</flux:text>
                            @endif
                        </div>
                    @empty
                        <flux:text>
                            {{ filled(trim($sbomSearch))
                                ? __('No components match your search.')
                                : __('No components collected.') }}
                        </flux:text>
                    @endforelse
                </div>
            @endif
        </div>
    </div>
@endif
