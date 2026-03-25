<?php

namespace App\Filament\Pages;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\MatchStatus;
use App\Filament\Resources\ClubResource;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\Player;
use App\Services\InvitationService;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class QuickClubSetup extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationLabel = 'Nuevo Club';

    protected static string|UnitEnum|null $navigationGroup = 'Onboarding';

    protected static ?string $title = 'Nuevo Club';

    protected string $view = 'filament.pages.quick-club-setup';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'team_a_name' => 'Equipo A',
            'team_b_name' => 'Equipo B',
            'team_a_color' => '#10b981',
            'team_b_color' => '#3b82f6',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Wizard::make([
                        Step::make('Club')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre del club')
                                    ->required()
                                    ->maxLength(255),
                                Textarea::make('description')
                                    ->label('Descripcion')
                                    ->rows(3),
                                TextInput::make('team_a_name')
                                    ->label('Nombre Equipo A')
                                    ->default('Equipo A'),
                                TextInput::make('team_b_name')
                                    ->label('Nombre Equipo B')
                                    ->default('Equipo B'),
                                ColorPicker::make('team_a_color')
                                    ->label('Color Equipo A')
                                    ->default('#10b981'),
                                ColorPicker::make('team_b_color')
                                    ->label('Color Equipo B')
                                    ->default('#3b82f6'),
                            ])
                            ->columns(2),
                        Step::make('Invitaciones')
                            ->icon('heroicon-o-envelope')
                            ->description('Opcional')
                            ->schema([
                                Textarea::make('emails')
                                    ->label('Emails de invitados')
                                    ->helperText('Un email por linea')
                                    ->rows(5),
                            ]),
                        Step::make('Primer Partido')
                            ->icon('heroicon-o-trophy')
                            ->description('Opcional')
                            ->schema([
                                TextInput::make('match_title')
                                    ->label('Titulo del partido'),
                                DatePicker::make('scheduled_date')
                                    ->label('Fecha'),
                                TimePicker::make('scheduled_time')
                                    ->label('Hora'),
                            ]),
                    ])
                        ->skippable()
                        ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                            <x-filament::button
                                type="submit"
                                size="sm"
                            >
                                Crear Club
                            </x-filament::button>
                        BLADE))),
                ])
                    ->livewireSubmitHandler('create'),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        $data = $this->form->getState();

        $user = Auth::user();

        $club = Club::query()->create([
            'name' => $data['name'],
            'slug' => Club::generateUniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'owner_id' => $user->id,
            'invite_token' => Str::random(32),
            'requires_approval' => true,
            'is_invite_active' => true,
        ]);

        ClubMember::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role' => ClubMemberRole::Owner,
            'status' => ClubMemberStatus::Approved,
            'approved_at' => now(),
        ]);

        Player::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'is_active' => true,
        ]);

        $this->sendInvitations($club, $data, $user);
        $this->createMatch($club, $data);

        Notification::make()
            ->success()
            ->title('Club creado exitosamente')
            ->send();

        $this->redirect(ClubResource::getUrl('view', ['record' => $club]));
    }

    private function sendInvitations(Club $club, array $data, $user): void
    {
        $emailsRaw = trim($data['emails'] ?? '');

        if ($emailsRaw === '') {
            return;
        }

        $invitationService = app(InvitationService::class);

        $emails = collect(explode("\n", $emailsRaw))
            ->map(fn (string $email): string => trim($email))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique();

        foreach ($emails as $email) {
            $invitationService->sendInvitation($club, $email, $user);
        }
    }

    private function createMatch(Club $club, array $data): void
    {
        if (empty($data['match_title']) && empty($data['scheduled_date'])) {
            return;
        }

        $scheduledAt = null;

        if (! empty($data['scheduled_date'])) {
            $date = $data['scheduled_date'];
            $time = $data['scheduled_time'] ?? '18:00:00';
            $scheduledAt = "{$date} {$time}";
        }

        FootballMatch::query()->create([
            'club_id' => $club->id,
            'title' => $data['match_title'] ?? 'Partido 1',
            'scheduled_at' => $scheduledAt,
            'status' => MatchStatus::Upcoming,
            'share_token' => Str::random(32),
            'duration_minutes' => 90,
            'arrival_minutes' => 30,
            'max_players' => 22,
            'max_substitutes' => 5,
            'registration_opens_hours' => 48,
            'team_a_name' => $data['team_a_name'] ?? 'Equipo A',
            'team_b_name' => $data['team_b_name'] ?? 'Equipo B',
            'team_a_color' => $data['team_a_color'] ?? '#10b981',
            'team_b_color' => $data['team_b_color'] ?? '#3b82f6',
        ]);
    }
}
