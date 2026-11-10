<?php

namespace App\Filament\Resources\WorkspaceDestinationPermissions;

use App\Filament\Resources\WorkspaceDestinationPermissions\Pages\ListWorkspaceDestinationPermissions;
use App\Models\FacebookGroup;
use App\Models\PublicationDestination;
use App\Models\WhatsAppGroup;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceMembershipDestinationPermission;
use App\Services\Workspaces\WorkspaceContext;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkspaceDestinationPermissionResource extends Resource
{
    protected static ?string $model = WorkspaceMembershipDestinationPermission::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?int $navigationSort = 21;

    protected static function canManageWorkspace(): bool
    {
        $membership = auth()->user()?->activeWorkspaceMemberships()
            ->where('workspace_id', app(WorkspaceContext::class)->id())
            ->first();

        return $membership?->hasPermission('destinations.manage') ?? false;
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
        return static::canManageWorkspace();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManageWorkspace();
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Cuentas sociales';
    }

    public static function getNavigationLabel(): string
    {
        return 'Permisos por destino';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('workspace_id', app(WorkspaceContext::class)->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Acceso a un destino')
                ->columns(2)
                ->schema([
                    Select::make('membership_id')
                        ->label('Miembro')
                        ->options(fn (): array => WorkspaceMembership::query()
                            ->where('workspace_id', app(WorkspaceContext::class)->id())
                            ->where('status', 'active')
                            ->with('user')
                            ->get()
                            ->mapWithKeys(fn (WorkspaceMembership $membership): array => [
                                $membership->id => ($membership->user?->name ?: $membership->user?->email).' — '.$membership->role,
                            ])
                            ->all())
                        ->searchable()
                        ->required(),
                    Select::make('destination_type')
                        ->label('Tipo de destino')
                        ->options([
                            WorkspaceMembershipDestinationPermission::DESTINATION_FACEBOOK_GROUP => 'Grupo de Facebook',
                            WorkspaceMembershipDestinationPermission::DESTINATION_WHATSAPP_GROUP => 'Grupo de WhatsApp',
                            WorkspaceMembershipDestinationPermission::DESTINATION_PUBLICATION => 'Página/destino de publicación',
                        ])
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('destination_id', null))
                        ->required(),
                    Select::make('destination_id')
                        ->label('Destino')
                        ->options(fn (Get $get): array => match ($get('destination_type')) {
                            WorkspaceMembershipDestinationPermission::DESTINATION_FACEBOOK_GROUP => FacebookGroup::query()->orderBy('name')->pluck('name', 'id')->all(),
                            WorkspaceMembershipDestinationPermission::DESTINATION_WHATSAPP_GROUP => WhatsAppGroup::query()->orderBy('name')->pluck('name', 'id')->all(),
                            WorkspaceMembershipDestinationPermission::DESTINATION_PUBLICATION => PublicationDestination::query()->orderBy('name')->pluck('name', 'id')->all(),
                            default => [],
                        })
                        ->searchable()
                        ->required(),
                    Select::make('permissions')
                        ->label('Permisos concedidos')
                        ->multiple()
                        ->options([
                            'view' => 'Consultar destino',
                            'publish' => 'Publicar en este destino (incluye consulta)',
                        ])
                        ->required()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('membership.user.name')->label('Miembro')->searchable(),
                TextColumn::make('destination_type')->label('Tipo')->formatStateUsing(fn (string $state): string => match ($state) {
                    'facebook_group' => 'Grupo de Facebook',
                    'whatsapp_group' => 'Grupo de WhatsApp',
                    default => 'Página/destino',
                }),
                TextColumn::make('destination_id')->label('ID destino'),
                TextColumn::make('permissions')->label('Permisos')->formatStateUsing(fn ($state): string => implode(', ', is_array($state) ? $state : [])),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkspaceDestinationPermissions::route('/'),
        ];
    }
}
