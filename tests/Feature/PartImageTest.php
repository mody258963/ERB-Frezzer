<?php

namespace Tests\Feature;

use App\Models\Part;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_upload_part_image_returns_image_url(): void
    {
        $this->seed(DatabaseSeeder::class);
        $token = $this->adminToken();
        $partId = $this->createPartId($token);

        $response = $this->multipartPost($token, $partId, $this->fixtureUpload('part.png', 'image/png'));

        $response->assertOk();
        $imageUrl = $response->json('image_url');
        $this->assertNotNull($imageUrl);
        $this->assertStringContainsString('/storage/parts/', $imageUrl);

        $part = Part::query()->findOrFail($partId);
        $this->assertNotNull($part->image_path);
        Storage::disk('public')->assertExists($part->image_path);

        $this->withToken($token)->getJson("/api/v1/parts/{$partId}")
            ->assertOk()
            ->assertJsonPath('image_url', $imageUrl);
    }

    public function test_upload_rejects_file_over_two_megabytes(): void
    {
        $this->seed(DatabaseSeeder::class);
        $token = $this->adminToken();
        $partId = $this->createPartId($token);

        $file = UploadedFile::fake()->create('large.png', 3000, 'image/png');

        $this->multipartPost($token, $partId, $file)->assertStatus(422);
    }

    public function test_upload_rejects_invalid_mime(): void
    {
        $this->seed(DatabaseSeeder::class);
        $token = $this->adminToken();
        $partId = $this->createPartId($token);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->multipartPost($token, $partId, $file)->assertStatus(422);
    }

    public function test_delete_part_image_clears_url_and_file(): void
    {
        $this->seed(DatabaseSeeder::class);
        $token = $this->adminToken();
        $partId = $this->createPartId($token);

        $this->multipartPost($token, $partId, $this->fixtureUpload('part.png', 'image/png'))->assertOk();

        $path = Part::query()->findOrFail($partId)->image_path;
        $this->assertNotNull($path);

        $this->withToken($token)->deleteJson("/api/v1/parts/{$partId}/image")
            ->assertOk()
            ->assertJsonPath('image_url', null);

        $part = Part::query()->findOrFail($partId);
        $this->assertNull($part->image_path);
        Storage::disk('public')->assertMissing($path);
    }

    private function fixtureUpload(string $filename, string $mime): UploadedFile
    {
        $path = base_path('tests/Fixtures/'.$filename);

        return new UploadedFile($path, $filename, $mime, null, true);
    }

    private function multipartPost(string $token, string $partId, UploadedFile $file): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/api/v1/parts/{$partId}/image", ['image' => $file]);
    }

    private function adminToken(): string
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        return (string) $login->json('token');
    }

    private function createPartId(string $token): string
    {
        $response = $this->withToken($token)->postJson('/api/v1/parts', [
            'code' => 'IMG-'.uniqid(),
            'name' => 'Image Part',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 10,
            'cost_price' => 5,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        $response->assertCreated();

        return (string) $response->json('id');
    }
}
