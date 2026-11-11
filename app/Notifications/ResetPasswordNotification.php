<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public string $token;

    public string $otp;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token, string $otp)
    {
        $this->token = $token;
        $this->otp = $otp;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable->getEmailForPasswordReset() ?? $notifiable->email;
        $resetUrl = url("/reset-password/{$this->token}?email=".urlencode((string) $email));

        return (new MailMessage)
            ->subject('Permintaan Reset Kata Sandi - Kirana People')
            ->greeting("Halo, {$notifiable->name}!")
            ->line('Kami menerima permintaan untuk mereset kata sandi akun Anda di Kirana People.')
            ->line('Jika Anda menggunakan peramban web, klik tombol di bawah ini untuk mengatur kata sandi baru:')
            ->action('Reset Kata Sandi (Web)', $resetUrl)
            ->line('---')
            ->line('Jika Anda menggunakan aplikasi mobile Kirana People, masukkan kode verifikasi OTP berikut:')
            ->line("# **{$this->otp}**")
            ->line('Tautan dan kode OTP ini hanya berlaku selama 15 menit.')
            ->line('Jika Anda tidak melakukan permintaan ini, tidak ada tindakan lebih lanjut yang diperlukan.')
            ->salutation("Salam hangat,\nTim Kirana People");
    }
}
