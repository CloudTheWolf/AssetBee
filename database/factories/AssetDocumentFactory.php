<?php

namespace Database\Factories;

use App\Enums\AssetDocumentCategory;
use App\Models\AssetDocument;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetDocument>
 */
class AssetDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'documentable_type' => Hardware::class,
            'documentable_id' => Hardware::factory(),
            'name' => fake()->words(3, true),
            'original_filename' => fake()->word().'.pdf',
            'path' => 'documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(10_000, 2_000_000),
            'category' => fake()->randomElement(AssetDocumentCategory::cases()),
            'uploaded_by' => User::factory(),
        ];
    }
}
