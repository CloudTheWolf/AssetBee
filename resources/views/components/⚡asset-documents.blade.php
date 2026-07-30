<?php

use App\Actions\Assets\DeleteAssetDocument;
use App\Actions\Assets\UploadAssetDocument;
use App\Enums\AssetDocumentCategory;
use App\Models\AssetDocument;
use App\Models\Hardware;
use App\Models\Software;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component {
    use AuthorizesRequests;
    use WithFileUploads;

    public Hardware|Software $documentable;

    public string $document_name = '';

    public string $document_category = 'invoice';

    public ?TemporaryUploadedFile $document_file = null;

    public function mount(Hardware|Software $documentable): void
    {
        $this->documentable = $documentable;
    }

    public function uploadDocument(UploadAssetDocument $uploadAssetDocument): void
    {
        $this->authorize('create', [AssetDocument::class, $this->documentable]);

        $this->validate([
            'document_name' => ['required', 'string', 'max:255'],
            'document_category' => ['required'],
            'document_file' => ['required', 'file', 'max:10240'],
        ]);

        $uploadAssetDocument->handle(
            $this->documentable,
            auth()->user(),
            $this->document_file,
            [
                'name' => $this->document_name,
                'category' => $this->document_category,
            ],
        );

        $this->reset(['document_name', 'document_file']);
        $this->document_category = AssetDocumentCategory::Invoice->value;
        unset($this->documents);

        Flux::toast(variant: 'success', text: __('Document uploaded.'));
    }

    public function deleteDocument(AssetDocument $document, DeleteAssetDocument $deleteAssetDocument): void
    {
        $this->authorize('delete', $document);
        abort_unless(
            $document->documentable_type === $this->documentable::class
            && $document->documentable_id === $this->documentable->id,
            404,
        );

        $deleteAssetDocument->handle($document);
        unset($this->documents);

        Flux::toast(variant: 'success', text: __('Document deleted.'));
    }

    #[Computed]
    public function documents()
    {
        return $this->documentable->documents()->latest()->get();
    }
}; ?>

<div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
    <div>
        <flux:heading size="lg">{{ __('Documents') }}</flux:heading>
        <flux:text>{{ __('Invoices, contracts, and other attachments.') }}</flux:text>
    </div>

    @can('create', [App\Models\AssetDocument::class, $documentable])
        <form wire:submit="uploadDocument" class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="document_name" :label="__('Name')" required class="sm:col-span-2" />
            <flux:select wire:model="document_category" :label="__('Category')">
                @foreach (App\Enums\AssetDocumentCategory::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input type="file" wire:model="document_file" :label="__('File')" required />
            <div class="sm:col-span-2 flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Upload') }}</flux:button>
            </div>
        </form>
    @endcan

    <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
        @forelse ($this->documents as $document)
            <li class="flex items-center justify-between gap-3 py-3">
                <div class="min-w-0">
                    <div class="truncate font-medium">{{ $document->name }}</div>
                    <flux:text class="truncate">
                        {{ $document->category->label() }} · {{ $document->original_filename }} · {{ $document->humanReadableSize() }}
                    </flux:text>
                </div>
                <div class="flex shrink-0 gap-2">
                    <flux:button size="sm" :href="route('assets.documents.download', $document)">{{ __('Download') }}</flux:button>
                    @can('delete', $document)
                        <flux:button size="sm" variant="danger" wire:click="deleteDocument({{ $document->id }})" wire:confirm="{{ __('Delete this document?') }}">
                            {{ __('Delete') }}
                        </flux:button>
                    @endcan
                </div>
            </li>
        @empty
            <li class="py-3"><flux:text>{{ __('No documents uploaded.') }}</flux:text></li>
        @endforelse
    </ul>
</div>
