<?php

namespace App\Filament\Resources\Workspaces;

use App\Filament\Resources\Workspaces\Pages\CreateWorkspace;
use App\Filament\Resources\Workspaces\Pages\EditWorkspace;
use App\Filament\Resources\Workspaces\Pages\ListWorkspaces;
use App\Models\Workspace;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkspaceResource extends Resource
{
    protected static ?string $model = Workspace::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 89;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Espacio de trabajo';
    }

    public static function getNavigationLabel(): string
    {
        return 'Mis espacios';
    }

    public static function getModelLabel(): string
    {
        return 'espacio de trabajo';
    }

    public static function getPluralModelLabel(): string
    {
        return 'espacios de trabajo';
    }

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canCreate(): bool
    {
        return auth()->check();
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->workspaceMemberships()
            ->where('workspace_id', $record->getKey())
            ->where('status', 'active')
            ->exists() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->workspaceMemberships()
            ->where('workspace_id', $record->getKey())
            ->where('role', 'owner')
            ->where('status', 'active')
            ->exists() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('memberships', fn (Builder $query) => $query
                ->where('user_id', auth()->id())
                ->where('status', 'active'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del espacio')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true),
                    TextInput::make('slug')
                        ->label('Identificador')
                        ->helperText('Se genera a partir del nombre si lo dejas vacío. Puedes modificarlo manualmente.')
                        ->maxLength(100)
                        ->unique(ignoreRecord: true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Espacio')->searchable()->sortable(),
                TextColumn::make('slug')->label('Identificador')->searchable(),
                TextColumn::make('memberships_count')->counts('memberships')->label('Miembros'),
                TextColumn::make('created_at')->label('Creado')->dateTime('d/m/Y')->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->defaultSort('created_at');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkspaces::route('/'),
            'create' => CreateWorkspace::route('/create'),
            'edit' => EditWorkspace::route('/{record}/edit'),
        ];
    }
}
