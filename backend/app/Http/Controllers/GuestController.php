<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Guest profiles.
 *
 * `phone`, `national_id` and `address` are stored encrypted (AES-256-GCM) via
 * the EncryptedString cast; the controller only ever sees plaintext.
 */
final class GuestController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $guests = Guest::query()
            ->withCount('bookings')
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('guests.index', ['guests' => $guests]);
    }

    public function create(): View
    {
        return view('guests.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $guest = Guest::query()->create($data);

        $this->audit->success(
            action: 'guest.create',
            eventType: 'data',
            description: sprintf('Created guest profile for %s.', $guest->fullName()),
            subject: $guest,
            newValues: ['email' => $guest->email],
        );

        return redirect()->route('guests.show', $guest)->with('status', 'Guest profile created.');
    }

    public function show(Guest $guest): View
    {
        $guest->load(['bookings' => fn ($q) => $q->with('rooms')->latest('check_in_date')]);

        return view('guests.show', ['guest' => $guest]);
    }

    public function edit(Guest $guest): View
    {
        return view('guests.edit', ['guest' => $guest]);
    }

    public function update(Request $request, Guest $guest): RedirectResponse
    {
        $data = $this->validated($request, $guest);

        $guest->update($data);

        $this->audit->success(
            action: 'guest.update',
            eventType: 'data',
            description: sprintf('Updated guest profile for %s.', $guest->fullName()),
            subject: $guest,
            newValues: ['email' => $guest->email],
        );

        return redirect()->route('guests.show', $guest)->with('status', 'Guest profile updated.');
    }

    public function destroy(Guest $guest): RedirectResponse
    {
        if ($guest->bookings()->exists()) {
            return back()->with('error', 'This guest has bookings on record and cannot be deleted. Deactivate the profile instead.');
        }

        $name = $guest->fullName();
        $guest->delete();

        $this->audit->success(
            action: 'guest.delete',
            eventType: 'data',
            description: sprintf('Deleted guest profile for %s.', $name),
        );

        return redirect()->route('guests.index')->with('status', 'Guest profile deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Guest $guest = null): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => [
                'nullable', 'email', 'max:190',
                Rule::unique('guests', 'email')->ignore($guest?->getKey()),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'national_id' => ['nullable', 'string', 'max:60'],
            'id_type' => ['nullable', 'string', 'max:40'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
