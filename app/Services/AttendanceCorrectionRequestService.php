<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\AttendanceCorrectionRequest;
use App\Models\User;
use App\Repositories\Contracts\AttendanceCorrectionRequestRepositoryInterface;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionRequestService extends BaseService
{
    public function __construct(AttendanceCorrectionRequestRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Submit a correction request.
     */
    public function submitRequest(User $employee, int $attendanceId, string $reason, array $requestedChanges): AttendanceCorrectionRequest
    {
        return $this->repository->create([
            'user_id' => $employee->id,
            'attendance_id' => $attendanceId,
            'reason' => $reason,
            'requested_changes' => $requestedChanges,
            'status' => RequestStatus::PENDING,
        ]);
    }

    /**
     * Approve or reject a request by HR/Manager.
     */
    public function processRequest(AttendanceCorrectionRequest $request, User $manager, RequestStatus $status, ?string $notes = null): AttendanceCorrectionRequest
    {
        if ($request->status !== RequestStatus::PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be processed.',
            ]);
        }

        if (! in_array($status, [RequestStatus::APPROVED, RequestStatus::REJECTED])) {
            throw ValidationException::withMessages([
                'status' => 'Invalid status provided.',
            ]);
        }

        $this->repository->update($request->id, [
            'status' => $status,
            'approved_by' => $manager->id,
            'notes' => $notes,
        ]);

        return $request->fresh();
    }
}
