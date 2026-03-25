<?php

namespace App\Notifications;

use App\Models\VideoServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VideoServiceRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public VideoServiceRequest $videoServiceRequest)
    {
        $this->onQueue('notifications');
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->videoServiceRequest;

        $mail = (new MailMessage)
            ->subject('Nueva solicitud de servicio de video')
            ->greeting('Nueva solicitud de servicio de video')
            ->line("**Nombre:** {$request->name}")
            ->line("**Email:** {$request->email}");

        if ($request->phone) {
            $mail->line("**Teléfono:** {$request->phone}");
        }

        $mail->line("**Club:** {$request->club_name}");

        if ($request->selected_plan) {
            $mail->line("**Plan seleccionado:** {$request->selected_plan}");
        }

        if ($request->preferred_date) {
            $mail->line("**Fecha preferida:** {$request->preferred_date->format('d/m/Y')}");
        }

        if ($request->message) {
            $mail->line("**Mensaje:** {$request->message}");
        }

        $mail->action('Ver solicitudes', url('/admin/video-service-requests'));

        return $mail;
    }
}
