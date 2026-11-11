<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessCorrectionRequest;
use App\Http\Resources\AttendanceCorrectionRequestResource;
use App\Models\AttendanceCorrectionRequest;
use App\Repositories\Contracts\AttendanceCorrectionRequestRepositoryInterface;
use App\Services\AttendanceCorrectionRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceCorrectionRequestController extends Controller
{
    public function __construct(
        protected AttendanceCorrectionRequestRepositoryInterface $repository,
        protected AttendanceCorrectionRequestService $service
    ) {}

    /**
     * View all correction requests (for HR/Admin).
     */
    public function index(Request $request)
    {
        $requests = $this->repository->paginate($request->input('per_page', 15));

        return AttendanceCorrectionRequestResource::collection($requests);
    }

    /**
     * Approve or reject a request.
     */
    public function process(ProcessCorrectionRequest $request, int $id): JsonResponse
    {
        /** @var AttendanceCorrectionRequest $correctionRequest */
        $correctionRequest = $this->repository->findOrFail($id);

        $status = RequestStatus::from($request->validated('status'));

        $processed = $this->service->processRequest(
            $correctionRequest,
            $request->user(),
            $status,
            $request->validated('notes')
        );

        return response()->json([
            'message' => 'Correction request processed successfully.',
            'data' => new AttendanceCorrectionRequestResource($processed),
        ]);
    }
}
