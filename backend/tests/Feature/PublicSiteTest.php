<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsHotel;
use Tests\TestCase;

/**
 * The public marketing site must render for anonymous visitors.
 */
class PublicSiteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedSettings();
    }

    public function test_home_page_renders(): void
    {
        RoomType::factory()->count(3)->create();

        $this->get('/')
            ->assertOk()
            ->assertSee('Azure Bay Hotel');
    }

    public function test_accommodation_index_and_detail_pages_render(): void
    {
        $type = RoomType::factory()->create(['name' => 'Deluxe King']);

        $this->get('/accommodation')->assertOk();
        $this->get('/accommodation/'.$type->slug)->assertOk()->assertSee('Deluxe King');
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk();
    }
}
