<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RoomType;
use Illuminate\Database\Seeder;

/**
 * The six room categories offered by the hotel.
 *
 * `image_url` values are ROOT-RELATIVE paths under public/assets/img, so they
 * resolve through asset() on any host and work offline (no CDN dependency).
 */
class RoomTypeSeeder extends Seeder
{
    /** @var array<int, array<string, mixed>> */
    private const TYPES = [
        [
            'code' => 'CLS',
            'name' => 'Classic Queen',
            'slug' => 'classic-queen',
            'bed_type' => '1 Queen bed',
            'capacity' => 2,
            'base_price' => 145.00,
            'image' => '/assets/img/room-classic.jpg',
            'description' => 'A calm, light-filled room with a queen bed, a work desk and a rain shower — ideal for short city stays.',
            'amenities' => ['Wi-Fi', 'Air conditioning', 'Smart TV', 'In-room safe', 'Tea & coffee station'],
        ],
        [
            'code' => 'DLX',
            'name' => 'Deluxe King',
            'slug' => 'deluxe-king',
            'bed_type' => '1 King bed',
            'capacity' => 2,
            'base_price' => 210.00,
            'image' => '/assets/img/room-deluxe.jpg',
            'description' => 'Extra space, a king bed with premium linens, a lounge chair and a marble bathroom with a walk-in shower.',
            'amenities' => ['Wi-Fi', 'Air conditioning', '55" Smart TV', 'Minibar', 'Nespresso machine', 'Bathrobes'],
        ],
        [
            'code' => 'EXE',
            'name' => 'Executive Suite',
            'slug' => 'executive-suite',
            'bed_type' => '1 King bed + sofa bed',
            'capacity' => 3,
            'base_price' => 320.00,
            'image' => '/assets/img/room-executive.jpg',
            'description' => 'A separate living area, a dining table for four and access to the Executive Lounge with complimentary evening canapés.',
            'amenities' => ['Wi-Fi', 'Air conditioning', 'Lounge access', 'Minibar', 'Bathrobes', 'Turndown service'],
        ],
        [
            'code' => 'FAM',
            'name' => 'Family Room',
            'slug' => 'family-room',
            'bed_type' => '2 Queen beds',
            'capacity' => 4,
            'base_price' => 260.00,
            'image' => '/assets/img/room-family.jpg',
            'description' => 'Two queen beds and a convertible sofa, with a connecting door available — designed for families travelling together.',
            'amenities' => ['Wi-Fi', 'Air conditioning', 'Smart TV', 'Kids welcome pack', 'Bathtub', 'Mini fridge'],
        ],
        [
            'code' => 'GDN',
            'name' => 'Garden Twin',
            'slug' => 'garden-twin',
            'bed_type' => '2 Twin beds',
            'capacity' => 2,
            'base_price' => 130.00,
            'image' => '/assets/img/room-garden.jpg',
            'description' => 'A ground-floor twin room opening onto the landscaped courtyard garden. Quiet, green and budget-friendly.',
            'amenities' => ['Wi-Fi', 'Air conditioning', 'Smart TV', 'Garden terrace', 'Tea & coffee station'],
        ],
        [
            'code' => 'PRS',
            'name' => 'Presidential Suite',
            'slug' => 'presidential-suite',
            'bed_type' => '1 King bed + guest room',
            'capacity' => 4,
            'base_price' => 640.00,
            'image' => '/assets/img/room-presidential.jpg',
            'description' => 'Our flagship suite: panoramic views, a private dining room, a butler pantry and a spa bathroom with a soaking tub.',
            'amenities' => ['Wi-Fi', 'Air conditioning', 'Butler service', 'Private dining', 'Spa bathroom', 'Panoramic view', 'Airport transfer'],
        ],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $type) {
            RoomType::query()->updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'slug' => $type['slug'],
                    'description' => $type['description'],
                    'bed_type' => $type['bed_type'],
                    'capacity' => $type['capacity'],
                    'base_price' => $type['base_price'],
                    'amenities' => $type['amenities'],
                    'image_url' => $type['image'],
                    'is_active' => true,
                ],
            );
        }

        $this->command->info('Room types seeded: '.count(self::TYPES));
    }
}
