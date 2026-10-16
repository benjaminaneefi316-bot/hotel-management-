<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use Illuminate\View\View;

/**
 * Public marketing pages (no authentication required).
 */
final class HomeController extends Controller
{
    public function index(): View
    {
        $roomTypes = RoomType::query()
            ->active()
            ->withCount('rooms')
            ->orderBy('base_price')
            ->get();

        $featured = $roomTypes->take(3);
        $highlight = $roomTypes->firstWhere('code', 'PRS') ?? $roomTypes->last();

        return view('public.home', [
            'roomTypes' => $roomTypes,
            'featured' => $featured,
            'highlight' => $highlight,
        ]);
    }

    public function gallery(): View
    {
        return view('public.gallery');
    }

    public function about(): View
    {
        return view('public.about');
    }
}
