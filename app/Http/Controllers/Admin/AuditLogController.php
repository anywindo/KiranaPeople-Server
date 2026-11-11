<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Admin\AuditLogsExport;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AuditLogController extends Controller
{
    /**
     * Export filtered audit logs to Excel (.xlsx).
     */
    public function export(Request $request)
    {
        $filters = $request->only(['event', 'model', 'user_id', 'date_from', 'date_to', 'search']);
        $filename = 'Audit_Logs_'.date('Y-m-d_H-i-s').'.xlsx';

        return Excel::download(new AuditLogsExport($filters), $filename);
    }

    /**
     * Display a listing of audit logs (Superadmin only).
     */
    public function index(Request $request)
    {
        $query = AuditLog::with('user')->latest('id');

        // Filter by CRUD event
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        // Filter by Model/Auditable Type
        if ($request->filled('model')) {
            $query->where('auditable_type', $request->model);
        }

        // Filter by Actor (User ID)
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by Date Range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search by keyword (IP address, auditable_id, or actor name)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('auditable_id', $search)
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('employee_id', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = (int) $request->get('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100])) {
            $perPage = 25;
        }

        $logs = $query->paginate($perPage)->withQueryString();

        // Model options for filter dropdown
        $availableModels = [
            'App\Models\User' => 'Karyawan / User',
            'App\Models\Shift' => 'Pengaturan Shift',
            'App\Models\Location' => 'Lokasi Kerja (GPS)',
            'App\Models\Attendance' => 'Presensi',
            'App\Models\Setting' => 'Pengaturan Sistem',
            'App\Models\Role' => 'Hak Akses (Role)',
        ];

        // Unique actors for filter dropdown
        $actors = User::whereIn('id', AuditLog::distinct()->pluck('user_id')->filter())
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id']);

        return view('admin.audit-logs.index', compact('logs', 'availableModels', 'actors'));
    }

    /**
     * Detail API for modal inspection if requested via JSON.
     */
    public function show(AuditLog $auditLog)
    {
        $auditLog->load('user');

        return response()->json([
            'id' => $auditLog->id,
            'event' => $auditLog->event,
            'event_badge' => $auditLog->event_badge,
            'model_label' => $auditLog->model_label,
            'auditable_type' => $auditLog->auditable_type,
            'auditable_id' => $auditLog->auditable_id,
            'actor' => $auditLog->user?->name ?? 'Sistem / Anonim',
            'ip_address' => $auditLog->ip_address ?? '-',
            'user_agent' => $auditLog->user_agent ?? '-',
            'created_at' => $auditLog->created_at?->format('d M Y H:i:s') ?? '-',
            'before' => $auditLog->before ?? [],
            'after' => $auditLog->after ?? [],
        ]);
    }
}
