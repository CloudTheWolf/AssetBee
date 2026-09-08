<flux:modal name="google-workspace-setup" class="max-w-2xl">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">{{ __('Set up Google Workspace access') }}</flux:heading>
            <flux:text>
                {{ __('AssetBee uses a Google Cloud service account with domain-wide delegation to read licensed seats. Subscription spend is taken from the billing amount saved on this asset.') }}
            </flux:text>
        </div>

        <ol class="list-decimal space-y-3 ps-5 text-sm text-zinc-700 dark:text-zinc-300">
            <li>
                {{ __('In Google Cloud Console, create or choose a project and enable the Admin SDK API and Enterprise License Manager API.') }}
            </li>
            <li>
                {{ __('Create a service account, download a JSON key, and copy the service account email and numeric Client ID (under domain-wide delegation).') }}
            </li>
            <li>
                {{ __('In Google Admin Console → Security → Access and data control → API controls → Domain-wide delegation, add that Client ID and authorize this scope:') }}
                <code class="mt-2 block break-all rounded-md bg-zinc-100 px-2 py-1 text-xs dark:bg-zinc-800">https://www.googleapis.com/auth/apps.licensing</code>
            </li>
            <li>
                {{ __('Copy the Customer ID from Admin Console → Account → Account settings (usually starts with C).') }}
            </li>
            <li>
                {{ __('Use a Workspace super admin email for Admin email, then paste the JSON key below and save.') }}
            </li>
        </ol>

        <flux:text class="text-sm">
            {{ __('Tip: set the asset billing amount to your monthly Workspace spend so cost sync can update totals after seat discovery.') }}
        </flux:text>

        <div class="flex justify-end">
            <flux:modal.close>
                <flux:button variant="primary">{{ __('Got it') }}</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
