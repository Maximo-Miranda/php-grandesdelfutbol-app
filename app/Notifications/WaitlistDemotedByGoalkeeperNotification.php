<?php

namespace App\Notifications;

use App\Enums\AttendanceTeam;
use App\Models\FootballMatch;
use App\Models\Player;
use App\Notifications\Concerns\SendsMailAndWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class WaitlistDemotedByGoalkeeperNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsMailAndWebPush;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    private string $matchTitle;

    private string $matchUrl;

    private string $teamName;

    public function __construct(
        public FootballMatch $match,
        public Player $demotedPlayer,
        public Player $goalkeeper,
        public AttendanceTeam $team,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
        $this->matchTitle = $match->title;
        $this->matchUrl = route('clubs.matches.show', [$match->club, $match]);
        $this->teamName = $match->teamName($team);
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->isDemotedPlayer($notifiable)) {
            return (new MailMessage)
                ->subject("Te movimos a la lista de espera — {$this->matchTitle}")
                ->greeting('Cambio en la convocatoria')
                ->line("El arquero **{$this->goalkeeper->display_name}** confirmó asistencia para **{$this->teamName}**, que no tenía arquero titular.")
                ->line('Para garantizar un arquero en cada equipo, fuiste movido a la lista de espera en **posición #1** — entrarás de nuevo si alguien cancela.')
                ->action('Ver partido', $this->matchUrl)
                ->salutation('Grandes del Futbol');
        }

        return (new MailMessage)
            ->subject("Cascada por arquero — {$this->matchTitle}")
            ->greeting('Movimiento automático por prioridad de arquero')
            ->line("**{$this->demotedPlayer->display_name}** fue movido a la lista de espera para darle el lugar a **{$this->goalkeeper->display_name}** como arquero titular de **{$this->teamName}**.")
            ->action('Ver partido', $this->matchUrl)
            ->salutation('Grandes del Futbol');
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        if ($this->isDemotedPlayer($notifiable)) {
            return (new WebPushMessage)
                ->title('Pasaste a la lista de espera')
                ->body("Un arquero tomó tu lugar en {$this->teamName}. Quedás en posición #1.")
                ->icon('/pwa-192x192.png')
                ->badge('/badge-96x96.png')
                ->tag("match-gk-cascade-{$this->match->id}")
                ->data(['url' => $this->matchUrl]);
        }

        return (new WebPushMessage)
            ->title('Cascada por arquero')
            ->body("{$this->demotedPlayer->display_name} → lista de espera ({$this->goalkeeper->display_name} entra como GK)")
            ->icon('/pwa-192x192.png')
            ->badge('/badge-96x96.png')
            ->tag("match-gk-cascade-{$this->match->id}-admin")
            ->data(['url' => $this->matchUrl]);
    }

    private function isDemotedPlayer(object $notifiable): bool
    {
        return $this->demotedPlayer->user_id !== null
            && $this->demotedPlayer->user_id === $notifiable->id;
    }
}
