<?php

namespace App\Filament\Resources\ClubResource\RelationManagers;

use App\Enums\MatchStatus;
use App\Models\Field;
use App\Models\FootballMatch;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'matches';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (MatchStatus $state): string => $state->label())
                    ->color(fn (MatchStatus $state): string => match ($state) {
                        MatchStatus::Upcoming => 'info',
                        MatchStatus::InProgress => 'warning',
                        MatchStatus::Completed => 'success',
                        MatchStatus::Cancelled => 'danger',
                    }),
                TextColumn::make('scheduled_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('video_status')
                    ->label('Video')
                    ->state(function (FootballMatch $record): string {
                        $upload = $record->videoUpload;

                        if ($upload === null) {
                            return "\u{2014}";
                        }

                        if ($upload->youtube_video_id) {
                            return 'YouTube';
                        }

                        return $upload->status->label();
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                $this->getCreateFromTemplateAction(),
            ])
            ->recordActions([
                Action::make('visit_match')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (FootballMatch $record): string => "/clubs/{$record->club->ulid}/matches/{$record->ulid}", shouldOpenInNewTab: true),
            ])
            ->defaultSort('scheduled_at', 'desc');
    }

    protected function getCreateFromTemplateAction(): Action
    {
        return Action::make('create_from_template')
            ->label('Crear desde Plantilla')
            ->icon('heroicon-o-document-duplicate')
            ->slideOver()
            ->mountUsing(function (Action $action): void {
                $club = $this->getOwnerRecord();

                $lastMatch = $club->matches()
                    ->orderByDesc('scheduled_at')
                    ->first();

                if ($lastMatch === null) {
                    return;
                }

                $action->fillForm([
                    'team_a_name' => $lastMatch->team_a_name,
                    'team_b_name' => $lastMatch->team_b_name,
                    'team_a_color' => $lastMatch->team_a_color,
                    'team_b_color' => $lastMatch->team_b_color,
                    'max_players' => $lastMatch->max_players,
                    'max_substitutes' => $lastMatch->max_substitutes,
                    'duration_minutes' => $lastMatch->duration_minutes,
                    'arrival_minutes' => $lastMatch->arrival_minutes,
                    'registration_opens_hours' => $lastMatch->registration_opens_hours,
                    'field_id' => $lastMatch->field_id,
                ]);
            })
            ->schema([
                TextInput::make('title')
                    ->label('Título')
                    ->required(),
                DateTimePicker::make('scheduled_at')
                    ->label('Fecha y hora')
                    ->required(),
                TextInput::make('team_a_name')
                    ->label('Equipo A')
                    ->required(),
                TextInput::make('team_b_name')
                    ->label('Equipo B')
                    ->required(),
                ColorPicker::make('team_a_color')
                    ->label('Color Equipo A')
                    ->required(),
                ColorPicker::make('team_b_color')
                    ->label('Color Equipo B')
                    ->required(),
                TextInput::make('max_players')
                    ->label('Máx. Jugadores')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                TextInput::make('max_substitutes')
                    ->label('Máx. Suplentes')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('duration_minutes')
                    ->label('Duración (min)')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                TextInput::make('arrival_minutes')
                    ->label('Llegada (min antes)')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('registration_opens_hours')
                    ->label('Registro abre (horas antes)')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                Select::make('field_id')
                    ->label('Cancha')
                    ->options(fn (): array => Field::query()
                        ->where('is_active', true)
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->nullable(),
            ])
            ->action(function (array $data): void {
                $club = $this->getOwnerRecord();

                FootballMatch::query()->create([
                    'ulid' => (string) Str::ulid(),
                    'club_id' => $club->id,
                    'title' => $data['title'],
                    'scheduled_at' => $data['scheduled_at'],
                    'team_a_name' => $data['team_a_name'],
                    'team_b_name' => $data['team_b_name'],
                    'team_a_color' => $data['team_a_color'],
                    'team_b_color' => $data['team_b_color'],
                    'max_players' => $data['max_players'],
                    'max_substitutes' => $data['max_substitutes'],
                    'duration_minutes' => $data['duration_minutes'],
                    'arrival_minutes' => $data['arrival_minutes'],
                    'registration_opens_hours' => $data['registration_opens_hours'],
                    'field_id' => $data['field_id'],
                    'status' => MatchStatus::Upcoming,
                    'share_token' => Str::random(16),
                ]);

                Notification::make()
                    ->title('Partido creado exitosamente')
                    ->success()
                    ->send();
            });
    }
}
