<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Services\CheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Front-desk check-in / check-out (admin + receptionist only).
 */
final class ApiStayController extends ApiController
{
    public function __construct(private readonly CheckInService $stays) {}

    public function checkIn(Request $request, Booking $booking): JsonResponse
    {
        $roomId = $request->integer('room_id') ?: null;

        try {
            $stay = $this->stays->checkIn($booking, $request->user(), $roomId);
        } catch (BusinessRuleException $e) {
            return $this->fail($e->getMessage(), 409, 'check_in_failed');
        }

        return $this->ok(['data' => $this->stayPayload($stay)], 201);
    }

    public function checkOut(Request $request, CheckIn $stay): JsonResponse
    {
        try {
            $stay = $this->stays->checkOut($stay, $request->user());
        } catch (BusinessRuleException $e) {
            return $this->fail($e->getMessage(), 409, 'check_out_failed');
        }

        return $this->ok(['data' => $this->stayPayload($stay)]);
    }
}
