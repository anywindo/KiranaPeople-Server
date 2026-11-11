<?php

namespace App\Notifications;

use App\Models\AuditLogArchive;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuditPurgeOtpNotification extends Notification
{
    use Queueable;

    public string $otp;

    public AuditLogArchive $archive;

    public function __construct(string $otp, AuditLogArchive $archive)
    {
        $this->otp = $otp;
        $this->archive = $archive;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dateFrom = $this->archive->date_from ? $this->archive->date_from->format('d/m/Y') : '-';
        $dateTo = $this->archive->date_to ? $this->archive->date_to->format('d/m/Y') : '-';

        return (new MailMessage)
            ->subject('Kode OTP Otorisasi Pembersihan Audit Log - Kirana People')
            ->greeting("Halo, {$notifiable->name} (Superadmin)!")
            ->line('Kami mendeteksi permintaan pembersihan (*purge*) riwayat Audit Log di panel Kirana People.')
            ->line("Rentang Data Arsip: **{$dateFrom}** s/d **{$dateTo}**")
            ->line("Jumlah Catatan yang akan Dihapus: **{$this->archive->total_records} data**")
            ->line('Untuk mengonfirmasi penghapusan permanen ini, masukkan kode OTP 6-digit berikut:')
            ->line("# **{$this->otp}**")
            ->line('⚠️ **PENTING:** Kode OTP ini hanya berlaku selama 5 menit. Jangan berikan kode ini kepada siapapun!')
            ->line('Jika Anda tidak melakukan permintaan ini, segera amankan akun Anda.')
            ->salutation("Salam hangat,\nSistem Keamanan Kirana People");
    }
}
