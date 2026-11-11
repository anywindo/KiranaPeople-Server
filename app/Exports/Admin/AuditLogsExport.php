<?php

namespace App\Exports\Admin;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AuditLogsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    protected array $filters;

    protected int $rowNumber = 0;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query(): Builder
    {
        $query = AuditLog::with('user')->latest('id');

        if (! empty($this->filters['event'])) {
            $query->where('event', $this->filters['event']);
        }

        if (! empty($this->filters['model'])) {
            $query->where('auditable_type', $this->filters['model']);
        }

        if (! empty($this->filters['user_id'])) {
            $query->where('user_id', $this->filters['user_id']);
        }

        if (! empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }

        if (! empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        if (! empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
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

        return $query;
    }

    public function headings(): array
    {
        return [
            'No.',
            'Waktu Kejadian',
            'Pelaku (User)',
            'ID / Email Pelaku',
            'Aksi (Event)',
            'Modul Data',
            'ID Data',
            'Alamat IP',
            'User Agent',
            'Data Sebelum (Before)',
            'Data Sesudah (After)',
        ];
    }

    public function map($log): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $log->created_at?->format('Y-m-d H:i:s') ?? '-',
            $log->user?->name ?? 'Sistem / Otomatis',
            $log->user?->employee_id ?? $log->user?->email ?? '-',
            strtoupper($log->event),
            $log->model_label,
            '#'.$log->auditable_id,
            $log->ip_address ?? '-',
            $log->user_agent ?? '-',
            ! empty($log->before) ? json_encode($log->before, JSON_UNESCAPED_UNICODE) : '-',
            ! empty($log->after) ? json_encode($log->after, JSON_UNESCAPED_UNICODE) : '-',
        ];
    }
}
