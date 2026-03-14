<?php

// database/factories/Tenant/MediaItemFactory.php

namespace Database\Factories\Tenant;

use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaItemFactory extends Factory
{
    protected $model = MediaItem::class;

    public function definition(): array
    {
        return [
            'album_id' => MediaAlbum::factory(),
            'uploaded_by' => User::factory(),
            'file_name' => fake()->slug(2).'.jpg',
            'file_path' => 'albums/1/2026/04/'.fake()->slug(2).'-'.fake()->lexify('????????').'.jpg',
            'thumb_path' => null,
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => fake()->numberBetween(100_000, 5_000_000),
            'width_px' => 1920,
            'height_px' => 1080,
            'exif_data' => null,
            'caption' => null,
            'sha256_hash' => fake()->sha256(),
        ];
    }

    public function jpeg(): static
    {
        return $this->state([
            'mime_type' => 'image/jpeg',
            'file_name' => fake()->slug(2).'.jpg',
        ]);
    }

    public function png(): static
    {
        return $this->state([
            'mime_type' => 'image/png',
            'file_name' => fake()->slug(2).'.png',
        ]);
    }

    public function video(): static
    {
        return $this->state([
            'mime_type' => 'video/mp4',
            'file_name' => fake()->slug(2).'.mp4',
            'width_px' => 1920,
            'height_px' => 1080,
        ]);
    }

    public function withExif(): static
    {
        return $this->state([
            'exif_data' => [
                'DateTimeOriginal' => '2024:06:15 14:30:00',
                'Make' => 'Canon',
                'Model' => 'EOS R6',
                'ExposureTime' => 0.005,
                'FNumber' => 2.8,
                'ISOSpeedRatings' => 800,
                'FocalLength' => 50.0,
                'GPSLatitude' => [46.0, 48.0, 41.0],
                'GPSLatitudeRef' => 'N',
                'GPSLongitude' => [1.0, 53.0, 57.0],
                'GPSLongitudeRef' => 'E',
            ],
        ]);
    }
}
