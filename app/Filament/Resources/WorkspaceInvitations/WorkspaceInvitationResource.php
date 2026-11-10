<?php

namespace App\Filament\Resources\WorkspaceInvitations;

use App\Filament\Resources\WorkspaceInvitations\Pages\CreateWorkspaceInvitation;
use App\Filament\Resources\WorkspaceInvitations\Pages\ListWorkspaceInvitations;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use App\Services\Workspaces\WorkspaceContext;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkspaceInvitationResource extends Resource
{
    protected static ?string $model = WorkspaceInvitation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 91;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Espacio de trabajo';
    }

    public static function getNavigationLabel(): string
    {
        return 'Invitaciones';
    }

    public static function getModelLabel(): string
    {
        return 'invitación';
    }

    public static function getPluralModelLabel(): string
    {
        return 'invitaciones';
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
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('workspace_id', app(WorkspaceContext::class)->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Enviar invitación')
                ->description('El usuario invitado deberá iniciar sesión o registrarse con este mismo correo para incorporarse al espacio de trabajo.')
                ->columns(2)
                ->schema([
                    TextInput::make('email')
                        ->label('Correo electrónico')
                        ->email()
                        ->required()
                        ->maxLength(255),
                    Select::make('role')
                        ->label('Rol inicial')
                        ->options(fn (): array => static::roleOptions())
                        ->default(WorkspaceMembership::ROLE_EDITOR)
                        ->required()
                        ->native(false),
                    TagsInput::make('permissions')
                        ->label('Permisos personalizados')
                        ->placeholder('Ej.: publications.publish')
                        ->helperText('Déjalo vacío para aplicar los permisos predeterminados del rol.')
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

        $user = auth()->user();
        $workspaceId = app(WorkspaceContext::class)->id();
        $isOwner = $user?->activeWorkspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->first()?->isOwner() ?? false;

        if ($isOwner) {
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
                TextColumn::make('email')->label('Correo')->searchable()->sortable(),
                TextColumn::make('role')->label('Rol')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'admin' => 'Administrador',
                    'collaborator' => 'Colaborador',
                    default => 'Editor',
                }),
                TextColumn::make('expires_at')->label('Vence')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->state(fn (WorkspaceInvitation $record): string => match (true) {
                        $record->accepted_at !== null => 'Aceptada',
                        $record->revoked_at !== null => 'Revocada',
                        ! $record->expires_at?->isFuture() => 'Vencida',
                        default => 'Pendiente',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Aceptada' => 'success',
                        'Pendiente' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Action::make('revoke')
                    ->label('Revocar')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (WorkspaceInvitation $record): bool => static::canManageWorkspace() && $record->isUsable())
                    ->action(fn (WorkspaceInvitation $record) => $record->update(['revoked_at' => now()])),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkspaceInvitations::route('/'),
            'create' => CreateWorkspaceInvitation::route('/create'),
        ];
    }
}
