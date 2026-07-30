<?php

namespace App\Actions\Assets;

use App\Enums\AssetDocumentCategory;
use App\Models\AssetDocument;
use App\Models\Hardware;
use App\Models\Software;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UploadAssetDocument
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Hardware|Software $documentable, User $uploader, UploadedFile $file, array $input): AssetDocument
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(AssetDocumentCategory::class)],
        ])->validate();

        Validator::make(['file' => $file], [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,webp,doc,docx,xls,xlsx,csv,txt'],
        ])->validate();

        $path = $file->store(
            'asset-documents/'.$documentable->organization_id,
            'local',
        );

        return AssetDocument::query()->create([
            'organization_id' => $documentable->organization_id,
            'documentable_type' => $documentable::class,
            'documentable_id' => $documentable->id,
            'name' => $validated['name'],
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'category' => $validated['category'],
            'uploaded_by' => $uploader->id,
        ]);
    }
}
