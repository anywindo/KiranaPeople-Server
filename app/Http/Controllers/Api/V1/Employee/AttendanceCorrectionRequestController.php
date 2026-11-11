<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCorrectionRequest;
use App\Http\Resources\AttendanceCorrectionRequestResource;
use App\Services\AttendanceCorrectionRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceCorrectionRequestController extends Controller
{
    public function __construct(
        protected AttendanceCorrectionRequestService $correctionService
    ) {}

    /**
     * View own correction requests.
     */
    public function index(Request $request)
    {
        $requests = $request->user()->attendanceCorrectionRequests()->latest()->paginate(15);

        return AttendanceCorrectionRequestResource::collection($requests);
    }

    /**
     * Submit a correction request.
     */
    public function store(StoreCorrectionRequest $request): JsonResponse
    {
        $correction = $this->correctionService->submitRequest(
            $request->user(),
            $request->validated('attendance_id'),
            $request->validated('reason'),
            $request->validated('requested_changes')
        );

        return response()->json([
            'message' => 'Correction request submitted successfully.',
            'data' => new AttendanceCorrectionRequestResource($correction),
        ], 201);
    }
}
