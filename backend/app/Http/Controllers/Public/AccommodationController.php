<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use Illuminate\View\View;

/**
 * Public room/suite listings. The detail route binds by slug
 * ({roomType:slug} — see RoomType::getRouteKeyName()).
 */
final class AccommodationController extends Controller
{
    public function index(): View
    {
        $roomTypes = RoomType::query()
            ->active()
            ->withCount('rooms')
            ->orderBy('base_price')
            ->get();

        return view('public.accommodation.index', [
            'roomTypes' => $roomTypes,
        ]);
    }

    public function show(RoomType $roomType): View
    {
        if (! $roomType->is_active) {
            abort(404);
        }

        $roomType->loadCount('rooms');

        $others = RoomType::query()
            ->active()
            ->whereKeyNot($roomType->getKey())
            ->orderBy('base_price')
            ->limit(3)
            ->get();

        return view('public.accommodation.show', [
            'roomType' => $roomType,
            'others' => $others,
        ]);
    }
}
