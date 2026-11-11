<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class EmployeeLateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $employee;

    public $latenessMinutes;

    /**
     * Create a new notification instance.
     */
    public function __construct($employee, $latenessMinutes)
    {
        $this->employee = $employee;
        $this->latenessMinutes = $latenessMinutes;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => "{$this->employee->name} terlambat {$this->latenessMinutes} menit pada ".now()->format('d M Y'),
            'employee_id' => $this->employee->id,
            'lateness_minutes' => $this->latenessMinutes,
        ];
    }
}
