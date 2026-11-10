<?php

namespace App\Filament\Resources\WorkspaceMembers;

use App\Filament\Resources\WorkspaceMembers\Pages\CreateWorkspaceMembership;
use App\Filament\Resources\WorkspaceMembers\Pages\EditWorkspaceMembership;
use App\Filament\Resources\WorkspaceMembers\Pages\ListWorkspaceMemberships;
use App\Models\WorkspaceMembership;
use App\Services\Workspaces\WorkspaceContext;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkspaceMembershipResource extends Resource
{
    protected static ?string $model = WorkspaceMembership::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 90;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Espacio de trabajo';
    }

    public static function getNavigationLabel(): string
    {
        return 'Miembros';
    }

    public static function getModelLabel(): string
    {
        return 'miembro';
    }

    public static function getPluralModelLabel(): string
    {
        return 'miembros';
    }

    protected static function canManageWorkspace(): bool
    {
        $user = auth()->user();
        $workspaceId = app(WorkspaceContext::class)->id();

        if (! $user || ! $workspaceId) {
            return false;
        }

        return $user->activeWorkspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->first()?->canManageMembers() ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::canManageWorkspace();
    }

    public static function canCreate(): bool
    {
        return static::canManageWorkspace();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageWorkspace()
            && ! $record->isOwner()
            && ($record->user_id !== auth()->id() || static::currentUserIsOwner());
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManageWorkspace()
            && ! $record->isOwner()
            && $record->user_id !== auth()->id();
    }

    protected static function currentUserIsOwner(): bool
    {
        $user = auth()->user();
        $workspaceId = app(WorkspaceContext::class)->id();

        return $user?->activeWorkspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->first()?->isOwner() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('workspace_id', app(WorkspaceContext::class)->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Acceso al espacio de trabajo')
                ->columns(2)
                ->schema([
                    Select::make('user_id')
                        ->label('Usuario')
                        ->relationship(
                            name: 'user',
                            titleAttribute: 'email',
                            modifyQueryUsing: fn (Builder $query) => $query
                                ->whereDoesntHave('workspaceMemberships', fn (Builder $membershipQuery) => $membershipQuery
                                    ->where('workspace_id', app(WorkspaceContext::class)->id())),
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->visibleOn('create')
                        ->disabledOn('edit'),
                    Select::make('role')
                        ->label('Rol')
                        ->options(fn (): array => static::roleOptions())
                        ->required()
                        ->native(false),
                    Select::make('status')
                        ->label('Estado')
                        ->options([
                            'active' => 'Activo',
                            'suspended' => 'Suspendido',
                        ])
                        ->default('active')
                        ->required()
                        ->native(false),
                    TagsInput::make('permissions')
                        ->label('Permisos personalizados')
                        ->placeholder('Ej.: publications.publish')
                        ->helperText('Si se deja vacío, se aplican los permisos predeterminados del rol. Si se especifican, sustituyen los permisos predeterminados.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    protected static function roleOptions(): array
    {
        $options = [
            WorkspaceMembership::ROLE_EDITOR => 'Editor',
            WorkspaceMembership::ROLE_COLLABORATOR => 'Colaborador',
        ];

        if (static::currentUserIsOwner()) {
            $options = [
                WorkspaceMembership::ROLE_ADMIN => 'Administrador',
                ...$options,
            ];
        }

        return $options;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('user.email')->label('Correo')->searchable()->sortable(),
                TextColumn::make('role')->label('Rol')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'owner' => 'Propietario',
                    'admin' => 'Administrador',
                    'editor' => 'Editor',
                    default => 'Colaborador',
                }),
                TextColumn::make('status')->label('Estado')->badge()->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Activo' : 'Suspendido'),
                TextColumn::make('permissions')
                    ->label('Permisos')
                    ->formatStateUsing(fn ($state): string => is_array($state) && $state !== [] ? count($state).' personalizados' : 'Predeterminados'),
            ])
            ->filters([
                SelectFilter::make('role')->options([
                    'owner' => 'Propietario',
                    'admin' => 'Administrador',
                    'editor' => 'Editor',
                    'collaborator' => 'Colaborador',
                ]),
                SelectFilter::make('status')->options([
                    'active' => 'Activo',
                    'suspended' => 'Suspendido',
                ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->visible(fn (WorkspaceMembership $record): bool => static::canDelete($record)),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->defaultSort('created_at');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkspaceMemberships::route('/'),
            'create' => CreateWorkspaceMembership::route('/create'),
            'edit' => EditWorkspaceMembership::route('/{record}/edit'),
        ];
    }
}
