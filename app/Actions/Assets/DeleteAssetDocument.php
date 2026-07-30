<?php

namespace App\Actions\Assets;

use App\Models\AssetDocument;
use Illuminate\Support\Facades\Storage;

class DeleteAssetDocument
{
    public function handle(AssetDocument $document): void
    {
        Storage::disk('local')->delete($document->path);
        $document->delete();
    }
}
