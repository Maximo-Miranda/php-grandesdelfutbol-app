<?php

namespace App\Notifications;

use App\Enums\AttendanceRole;
use App\Enums\AttendanceTeam;
use App\Models\FootballMatch;
use App\Models\Player;
use App\Notifications\Concerns\SendsMailAndWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class WaitlistPromotedNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsMailAndWebPush;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    private string $matchTitle;

    private string $matchUrl;

    private string $roleLabel;

    private ?string $replacedName;

    private ?string $assignedTeamName;

    private ?string $preferredTeamName;

    private bool $teamSwitched;

    public function __construct(
        public FootballMatch $match,
        public Player $promotedPlayer,
        public AttendanceRole $newRole,
        public ?Player $replacedPlayer = null,
        public ?AttendanceTeam $preferredTeam = null,
        public ?AttendanceTeam $assignedTeam = null,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
        $this->matchTitle = $match->title;
        $this->matchUrl = route('clubs.matches.show', [$match->club, $match]);
        $this->roleLabel = $newRole->label();
        $this->replacedName = $replacedPlayer?->display_name;

        $this->assignedTeamName = $assignedTeam ? $match->teamName($assignedTeam) : null;
        $this->preferredTeamName = $preferredTeam ? $match->teamName($preferredTeam) : null;
        $this->teamSwitched = $preferredTeam !== null
            && $assignedTeam !== null
            && $preferredTeam !== $assignedTeam;
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->isPromotedPlayer($notifiable)) {
            return (new MailMessage)
                ->subject("¡Entraste al partido! — {$this->matchTitle}")
                ->greeting('Pasaste de la lista de espera')
                ->line("Quedaste como **{$this->roleLabel}** en **{$this->matchTitle}**.")
                ->when($this->replacedName, fn ($m) => $m->line("Entras en reemplazo de **{$this->replacedName}**."))
                ->when(
                    $this->teamSwitched,
                    fn ($m) => $m->line("Tu preferencia era **{$this->preferredTeamName}**, pero ese equipo ya estaba lleno. Quedas en **{$this->assignedTeamName}**."),
                )
                ->action('Ver partido', $this->matchUrl)
                ->salutation('Grandes del Futbol');
        }

        return (new MailMessage)
            ->subject("Promoción desde lista de espera — {$this->matchTitle}")
            ->greeting('Movimiento en la convocatoria')
            ->line("**{$this->promotedPlayer->display_name}** fue promovido desde la lista de espera a **{$this->roleLabel}** en **{$this->matchTitle}**.")
            ->when($this->replacedName, fn ($m) => $m->line("Reemplaza a **{$this->replacedName}**."))
            ->when(
                $this->teamSwitched,
                fn ($m) => $m->line("Preferia **{$this->preferredTeamName}** pero se asignó a **{$this->assignedTeamName}** por disponibilidad."),
            )
            ->action('Ver partido', $this->matchUrl)
            ->salutation('Grandes del Futbol');
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        if ($this->isPromotedPlayer($notifiable)) {
            $body = $this->buildPlayerPushBody();

            return (new WebPushMessage)
                ->title('¡Entraste al partido!')
                ->body($body)
                ->icon('/pwa-192x192.png')
                ->badge('/badge-96x96.png')
                ->tag("match-waitlist-promoted-{$this->match->id}")
                ->data(['url' => $this->matchUrl]);
        }

        return (new WebPushMessage)
            ->title('Promoción desde lista de espera')
            ->body("{$this->promotedPlayer->display_name} → {$this->roleLabel} en {$this->matchTitle}")
            ->icon('/pwa-192x192.png')
            ->badge('/badge-96x96.png')
            ->tag("match-waitlist-promoted-{$this->match->id}-admin")
            ->data(['url' => $this->matchUrl]);
    }

    private function buildPlayerPushBody(): string
    {
        $base = $this->replacedName
            ? "Reemplazas a {$this->replacedName} como {$this->roleLabel}"
            : "Quedaste como {$this->roleLabel}";

        if ($this->teamSwitched) {
            return "{$base} en {$this->assignedTeamName} (tu preferencia era {$this->preferredTeamName})";
        }

        return $base;
    }

    private function isPromotedPlayer(object $notifiable): bool
    {
        return $this->promotedPlayer->user_id !== null
            && $this->promotedPlayer->user_id === $notifiable->id;
    }
}
