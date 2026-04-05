<?php

namespace App\Filament\Resources\NewsDictionaryEntries;

use App\Enums\NewsDictionaryType;
use App\Filament\Resources\NewsDictionaryEntries\Pages\ManageNewsDictionaryEntries;
use App\Models\NewsDictionaryEntry;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class NewsDictionaryEntryResource extends Resource
{
    protected static ?string $model = NewsDictionaryEntry::class;

    protected static bool $shouldSkipAuthorization = true;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Diccionario';

    protected static ?string $modelLabel = 'Entrada';

    protected static ?string $pluralModelLabel = 'Diccionario de Noticias';

    protected static string|UnitEnum|null $navigationGroup = 'Noticias';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Tipo')
                    ->options(NewsDictionaryType::options())
                    ->required(),
                TextInput::make('key')
                    ->label('Clave')
                    ->helperText('Identificador único (ej: real_madrid, champions_league)')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->alphaDash(),
                TextInput::make('label')
                    ->label('Nombre')
                    ->helperText('Nombre visible (ej: Real Madrid, Champions League)')
                    ->required(),
                TagsInput::make('aliases')
                    ->label('Aliases')
                    ->helperText('Palabras clave para detectar en noticias. Presiona Enter después de cada alias.')
                    ->placeholder('Agregar alias...')
                    ->splitKeys(['Tab', ','])
                    ->reorderable()
                    ->required(),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (NewsDictionaryType $state): string => $state->label())
                    ->color(fn (NewsDictionaryType $state): string => match ($state) {
                        NewsDictionaryType::Team => 'info',
                        NewsDictionaryType::Competition => 'success',
                        NewsDictionaryType::Topic => 'warning',
                        NewsDictionaryType::BreakingKeyword => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('label')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('key')
                    ->label('Clave')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('aliases')
                    ->label('Aliases')
                    ->badge()
                    ->limitList(4)
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(NewsDictionaryType::options()),
            ])
            ->defaultSort('type')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageNewsDictionaryEntries::route('/'),
        ];
    }
}
