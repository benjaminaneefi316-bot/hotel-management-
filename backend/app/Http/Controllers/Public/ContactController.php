<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Public "contact us" form.
 *
 * There is deliberately no `contact_messages` table in this project, so a
 * submission is validated and logged (without the free-text body, which could
 * contain personal data) and the visitor receives a confirmation.
 */
final class ContactController extends Controller
{
    public function show(): View
    {
        return view('public.contact');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        // Log the fact of the enquiry only — never the message body.
        Log::info('Public contact enquiry received.', [
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'] ?? null,
        ]);

        return back()->with('status', 'Thank you, '.$data['name'].'. Your enquiry has been received.');
    }
}
