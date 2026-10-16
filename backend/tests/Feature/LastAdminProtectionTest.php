<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\AuthService;
use App\Services\RoleService;
use App\Services\UserArchiveService;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsHotel;
use Tests\TestCase;

/**
 * Business rules that protect the system from locking itself out:
 * the last active administrator can never be demoted or archived.
 */
class LastAdminProtectionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_the_last_active_administrator_cannot_be_demoted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->expectException(BusinessRuleException::class);

        app(RoleService::class)->syncRoles($admin, [RoleName::Receptionist], $admin);
    }

    public function test_an_administrator_can_be_demoted_when_another_active_administrator_remains(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        app(RoleService::class)->syncRoles($admin, [RoleName::Receptionist], $other);

        $this->assertSame(['receptionist'], $admin->fresh()->roleNames());
    }

    public function test_the_last_active_administrator_cannot_be_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->receptionist()->create();

        $this->expectException(BusinessRuleException::class);

        app(UserArchiveService::class)->archive($admin, $staff, 'offboarding');
    }

    public function test_an_administrator_can_be_archived_when_another_remains(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();
        $staff = User::factory()->receptionist()->create();

        $archive = app(UserArchiveService::class)->archive($admin, $staff, 'offboarding');

        $this->assertDatabaseHas('archived_users', [
            'id' => $archive->getKey(),
            'original_user_id' => $admin->getKey(),
        ]);

        $this->assertFalse($admin->fresh()->isActive());
    }

    public function test_an_archived_account_is_removed_from_the_authentication_path(): void
    {
        $user = User::factory()->receptionist()->create();
        $actor = User::factory()->admin()->create();

        app(UserArchiveService::class)->archive($user, $actor, 'offboarding');

        $result = app(AuthService::class)->attemptPassword($user->email, UserFactory::DEFAULT_PASSWORD);

        $this->assertFalse($result['ok']);
        $this->assertSame('account_inactive', $result['reason']);
    }
}
