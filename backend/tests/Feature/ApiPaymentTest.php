<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsHotel;
use Tests\TestCase;

/**
 * Payments in the mobile app: list, summary, record and refund.
 */
class ApiPaymentTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function token(User $user): string
    {
        return $user->createToken('test-device', ['*'])->plainTextToken;
    }

    public function test_staff_can_list_payments_with_booking_details(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $booking = Booking::factory()->create();
        $payment = Payment::factory()->create(['booking_id' => $booking->getKey(), 'amount' => 420.50]);

        $response = $this->withToken($this->token($receptionist))->getJson('/api/payments')->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $payment->getKey());

        $this->assertNotNull($row);
        $this->assertSame($booking->reference, $row['booking_reference']);
        $this->assertEqualsWithDelta(420.50, $row['amount'], 0.001);
        $this->assertArrayHasKey('guest_name', $row);
    }

    public function test_payments_can_be_filtered_by_booking(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = Booking::factory()->create();
        $mine = Payment::factory()->create(['booking_id' => $booking->getKey()]);
        Payment::factory()->create();

        $response = $this->withToken($this->token($admin))
            ->getJson('/api/payments?booking_id='.$booking->getKey())
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$mine->getKey()], $ids);
    }

    public function test_the_summary_totals_paid_and_pending_amounts(): void
    {
        $admin = User::factory()->admin()->create();
        Payment::factory()->create(['amount' => 100, 'status' => PaymentStatus::Paid]);
        Payment::factory()->create(['amount' => 50, 'status' => PaymentStatus::Paid]);
        Payment::factory()->pending()->create(['amount' => 25]);

        $this->withToken($this->token($admin))
            ->getJson('/api/payments/summary')
            ->assertOk()
            ->assertJsonPath('paid_total', 150)
            ->assertJsonPath('pending_total', 25)
            ->assertJsonPath('counts.paid', 2);
    }

    public function test_a_paid_payment_can_be_refunded(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = Payment::factory()->create(['amount' => 300]);

        $this->withToken($this->token($admin))
            ->postJson('/api/payments/'.$payment->getKey().'/refund', ['reason' => 'guest cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded');

        $fresh = $payment->fresh();
        $this->assertSame(PaymentStatus::Refunded, $fresh->status);
        $this->assertNotNull($fresh->refunded_at);
        $this->assertStringContainsString('guest cancelled', (string) $fresh->notes);
    }

    public function test_a_pending_payment_cannot_be_refunded(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = Payment::factory()->pending()->create();

        $this->withToken($this->token($admin))
            ->postJson('/api/payments/'.$payment->getKey().'/refund')
            ->assertStatus(422)
            ->assertJsonPath('reason', 'not_refundable');
    }

    public function test_a_guest_cannot_see_or_refund_payments(): void
    {
        $guest = User::factory()->guest()->create();
        $payment = Payment::factory()->create();

        $this->withToken($this->token($guest))->getJson('/api/payments')->assertForbidden();
        $this->withToken($this->token($guest))
            ->postJson('/api/payments/'.$payment->getKey().'/refund')
            ->assertForbidden();
    }
}
