<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\ArchivedUser;
use App\Services\UserArchiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Archived (soft-deleted) user accounts.
 *
 * Accounts are archived into a dedicated `archived_users` table so they can
 * never authenticate and can be restored later without losing their history.
 */
final class ArchivedUserController extends Controller
{
    public function __construct(private readonly UserArchiveService $archive) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $archives = ArchivedUser::query()
            ->with(['archivedBy', 'restoredBy'])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('archived_at')
            ->paginate(20)
            ->withQueryString();

        return view('archived-users.index', ['archives' => $archives]);
    }

    public function restore(Request $request, ArchivedUser $archivedUser): RedirectResponse
    {
        try {
            $user = $this->archive->restore($archivedUser, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('users.show', $user)
            ->with('status', 'Account restored and re-activated.');
    }
}
