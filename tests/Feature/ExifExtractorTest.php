<?php

namespace Tests\Feature;

use App\Services\ExifExtractorService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ExifExtractorTest extends TestCase
{
    public function test_valid_image_can_be_analyzed_without_exif(): void
    {
        $response = $this->post('/extract', ['image' => UploadedFile::fake()->image('sample.jpg', 640, 480)]);

        $response->assertOk()->assertViewIs('exif.result')->assertViewHas('metadata', function (array $metadata) {
            return $metadata['image']['width'] === '640 px' && $metadata['gps']['available'] === false;
        });
    }

    public function test_invalid_file_is_rejected(): void
    {
        $response = $this->post('/extract', ['image' => UploadedFile::fake()->create('script.php', 10, 'application/x-php')]);

        $response->assertSessionHasErrors('image');
    }

    public function test_service_returns_stable_json_shape(): void
    {
        $metadata = app(ExifExtractorService::class)->extract(UploadedFile::fake()->image('shape.png', 100, 50));

        $this->assertSame(['file', 'image', 'camera', 'photo', 'gps', 'other'], array_keys($metadata));
        $this->assertArrayHasKey('available', $metadata['gps']);
        $this->assertJson(json_encode($metadata));
        $this->assertStringContainsString('"camera"', app(ExifExtractorService::class)->toJson($metadata));
    }

    public function test_gps_dms_conversion_handles_south_and_west(): void
    {
        $service = app(ExifExtractorService::class);
        $method = new \ReflectionMethod($service, 'dms');
        $method->setAccessible(true);

        $this->assertEqualsWithDelta(-6.2, $method->invoke($service, ['6/1', '12/1', '0/1'], 'S'), 0.000001);
        $this->assertEqualsWithDelta(-106.816666, $method->invoke($service, ['106/1', '48/1', '60/1'], 'W'), 0.000001);
    }
}
