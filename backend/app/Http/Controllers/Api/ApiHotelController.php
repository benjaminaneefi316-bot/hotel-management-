<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\RoomType;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * The public marketing website, exposed as JSON so the app can show guests
 * exactly the same hotel story as the website without scraping HTML.
 *
 * Content is static copy (as in the Blade views) except hotel contact details,
 * which come from Settings so changing them in the admin dashboard updates both
 * the website and the app.
 */
final class ApiHotelController extends ApiController
{
    public function __construct(private readonly SettingsService $settings) {}

    public function show(): JsonResponse
    {
        $roomTypes = RoomType::query()
            ->active()
            ->withCount('rooms')
            ->orderBy('base_price')
            ->get();

        return $this->ok([
            'hotel' => [
                'name' => (string) $this->settings->get('hotel_name', 'Azure Bay Hotel'),
                'tagline' => 'Where the ocean sets the pace',
                'established' => 1998,
                'rating' => 4.9,
                'address' => (string) $this->settings->get('hotel_address', '1 Ocean Promenade, Azure Bay'),
                'phone' => (string) $this->settings->get('hotel_phone', '+1 555 0100'),
                'email' => (string) $this->settings->get('hotel_email', 'stay@azurebay.test'),
                'check_in_time' => '15:00',
                'check_out_time' => '11:00',
                'description' => 'Since 1998, Azure Bay has welcomed travellers seeking a quieter kind of luxury. '
                    .'Our 26 rooms and suites are designed to feel like home - only with better views, softer linens '
                    .'and someone else making breakfast.',
                'short_description' => 'A refined seaside retreat where warm hospitality meets effortless luxury, '
                    .'a barefoot walk from the water.',
                'hero_image' => url('/assets/img/hero-lobby.jpg'),
            ],
            'stats' => [
                ['value' => (string) max(26, $roomTypes->sum('rooms_count')), 'label' => 'Rooms & suites'],
                ['value' => '4.9', 'label' => 'Guest rating'],
                ['value' => '24/7', 'label' => 'Front desk'],
                ['value' => '2', 'label' => 'Restaurants'],
            ],
            'amenities' => [
                ['icon' => 'pool', 'title' => 'Rooftop infinity pool', 'description' => 'Heated, open from sunrise to late, with bay views.'],
                ['icon' => 'spa', 'title' => 'Full-service spa', 'description' => 'Massage, facials and a hammam, by appointment.'],
                ['icon' => 'dining', 'title' => 'Two signature restaurants', 'description' => 'Coastal fine dining and an all-day terrace cafe.'],
                ['icon' => 'wifi', 'title' => 'Fibre Wi-Fi everywhere', 'description' => 'Fast, free and password-free in every room.'],
                ['icon' => 'parking', 'title' => 'Secure parking', 'description' => 'Underground parking with EV charging on request.'],
                ['icon' => 'concierge', 'title' => '24-hour concierge', 'description' => 'Transfers, tours, reservations and quiet advice.'],
            ],
            'gallery' => [
                ['url' => url('/assets/img/gallery-exterior.jpg'), 'caption' => 'The seafront facade'],
                ['url' => url('/assets/img/gallery-pool.jpg'), 'caption' => 'Rooftop infinity pool'],
                ['url' => url('/assets/img/gallery-spa.jpg'), 'caption' => 'The spa'],
                ['url' => url('/assets/img/gallery-suite.jpg'), 'caption' => 'A sea-view suite'],
                ['url' => url('/assets/img/gallery-dining.jpg'), 'caption' => 'Coastal dining'],
                ['url' => url('/assets/img/hero-lobby.jpg'), 'caption' => 'The lobby'],
            ],
            'highlights' => [
                [
                    'title' => 'A stay shaped around you',
                    'body' => 'Whether you are here for a single night or a long summer, our team handles the details: '
                        .'airport transfers, restaurant reservations, spa treatments and a personalised welcome for '
                        .'every returning guest.',
                    'image' => url('/assets/img/gallery-pool.jpg'),
                ],
                [
                    'title' => 'Rooms with room to breathe',
                    'body' => 'Six categories, from garden doubles to the presidential suite. Every room has '
                        .'blackout curtains, a rain shower and a balcony or sea view.',
                    'image' => url('/assets/img/room-deluxe.jpg'),
                ],
            ],
            'room_types' => $roomTypes->map(fn (RoomType $type): array => $this->roomTypePayload($type) + [
                'rooms_count' => (int) $type->rooms_count,
                'from_price' => (float) $type->base_price,
            ])->values()->all(),
            'featured_room_types' => $roomTypes->take(3)->map(
                fn (RoomType $type): array => $this->roomTypePayload($type) + ['rooms_count' => (int) $type->rooms_count],
            )->values()->all(),
        ]);
    }

    /**
     * Contact enquiry from the app.
     *
     * Mirrors the website: there is no contact_messages table, so the fact of
     * the enquiry is logged (never the free-text body) and a confirmation is
     * returned. This is deliberately honest - nothing claims an email was sent.
     */
    public function contact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        Log::info('Public contact enquiry received from the app.', [
            'name' => Str::limit($data['name'], 120),
            'email' => $data['email'],
            'subject' => $data['subject'] ?? null,
        ]);

        return $this->ok([
            'status' => 'received',
            'message' => 'Thank you, '.$data['name'].'. Your enquiry has been received.',
        ], 201);
    }
}
