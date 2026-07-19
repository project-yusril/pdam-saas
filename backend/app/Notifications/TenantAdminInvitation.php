<?php

namespace App\Notifications;

use App\Models\PdamOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * TenantAdminInvitation — email undangan untuk admin PDAM baru (PRD 16.1).
 * Dikirim saat Super-Admin selesai provisioning tenant. Berisi identitas login
 * (kode PDAM + email) dan tautan untuk masuk / mengatur ulang kata sandi.
 */
class TenantAdminInvitation extends Notification
{
    use Queueable;

    public function __construct(
        private PdamOrganization $organization,
        private string $adminName,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/').'/login';

        return (new MailMessage)
            ->subject('Akun Admin PDAM Anda Telah Dibuat — '.$this->organization->name)
            ->greeting('Halo '.$this->adminName.',')
            ->line('Akun administrator untuk **'.$this->organization->name.'** telah dibuat pada platform.')
            ->line('Gunakan detail berikut untuk masuk:')
            ->line('• Kode PDAM: **'.$this->organization->code.'**')
            ->line('• Email: **'.$notifiable->email.'**')
            ->line('Demi keamanan, segera ganti kata sandi Anda setelah login pertama.')
            ->action('Masuk ke Aplikasi', $loginUrl)
            ->line('Jika Anda tidak merasa mendaftar, abaikan email ini.');
    }
}
