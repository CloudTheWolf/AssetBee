<?php

use App\Actions\Assets\DeleteAssetDocument;
use App\Actions\Assets\UploadAssetDocument;
use App\Enums\AssetDocumentCategory;
use App\Models\AssetDocument;
use App\Models\Hardware;
use App\Models\Software;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('owners can upload documents to hardware', function () {
    Storage::fake('local');
    [$user, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->create(['organization_id' => $organization->id]);
    $file = UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf');

    $document = app(UploadAssetDocument::class)->handle($hardware, $user, $file, [
        'name' => 'Purchase invoice',
        'category' => AssetDocumentCategory::Invoice->value,
    ]);

    expect($document->documentable_id)->toBe($hardware->id)
        ->and($document->documentable_type)->toBe(Hardware::class)
        ->and($document->category)->toBe(AssetDocumentCategory::Invoice);

    Storage::disk('local')->assertExists($document->path);
});

test('owners can upload documents to software via the documents component', function () {
    Storage::fake('local');
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->create(['organization_id' => $organization->id]);
    $file = UploadedFile::fake()->create('contract.pdf', 80, 'application/pdf');

    Livewire::test('asset-documents', ['documentable' => $software])
        ->set('document_name', 'Annual contract')
        ->set('document_category', 'contract')
        ->set('document_file', $file)
        ->call('uploadDocument')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('asset_documents', [
        'organization_id' => $organization->id,
        'documentable_type' => Software::class,
        'documentable_id' => $software->id,
        'name' => 'Annual contract',
        'category' => 'contract',
    ]);
});

test('authorized users can download asset documents', function () {
    Storage::fake('local');
    [$user, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->create(['organization_id' => $organization->id]);
    $path = 'asset-documents/'.$organization->id.'/invoice.pdf';
    Storage::disk('local')->put($path, 'invoice-content');

    $document = AssetDocument::factory()->create([
        'organization_id' => $organization->id,
        'documentable_type' => Hardware::class,
        'documentable_id' => $hardware->id,
        'path' => $path,
        'original_filename' => 'invoice.pdf',
        'uploaded_by' => $user->id,
    ]);

    $this->get(route('assets.documents.download', $document))
        ->assertOk();
});

test('deleting a document removes the stored file', function () {
    Storage::fake('local');
    [$user, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->create(['organization_id' => $organization->id]);
    $file = UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf');

    $document = app(UploadAssetDocument::class)->handle($hardware, $user, $file, [
        'name' => 'Receipt',
        'category' => AssetDocumentCategory::Receipt->value,
    ]);

    $path = $document->path;
    app(DeleteAssetDocument::class)->handle($document);

    Storage::disk('local')->assertMissing($path);
    $this->assertDatabaseMissing('asset_documents', ['id' => $document->id]);
});
