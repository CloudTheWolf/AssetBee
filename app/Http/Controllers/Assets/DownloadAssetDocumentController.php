<?php

namespace App\Http\Controllers\Assets;

use App\Models\AssetDocument;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadAssetDocumentController
{
    use AuthorizesRequests;

    public function __invoke(Request $request, AssetDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);
        abort_unless($document->organization_id === CurrentOrganization::require()->id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download(
            $document->path,
            $document->original_filename,
        );
    }
}
