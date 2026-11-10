<?php

namespace App\Filament\Resources\WorkspaceAccountPermissions;

use App\Filament\Resources\WorkspaceAccountPermissions\Pages\ListWorkspaceAccountPermissions;
use App\Models\SocialAccount;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceMembershipSocialAccountPermission;
use App\Services\Workspaces\WorkspaceContext;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkspaceAccountPermissionResource extends Resource
{
    protected static ?string $model = WorkspaceMembershipSocialAccountPermission::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 20;

    protected static function canManageWorkspace(): bool
    {
        $membership = auth()->user()?->activeWorkspaceMemberships()
            ->where('workspace_id', app(WorkspaceContext::class)->id())
            ->first();

        return $membership?->hasPermission('accounts.manage') ?? false;
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
        return 'Permisos por cuenta';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('workspace_id', app(WorkspaceContext::class)->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Acceso a una cuenta social')
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
                    Select::make('social_account_id')
                        ->label('Cuenta social')
                        ->options(fn (): array => SocialAccount::query()
                            ->orderBy('display_name')
                            ->pluck('display_name', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                    Select::make('permissions')
                        ->label('Permisos concedidos')
                        ->multiple()
                        ->options([
                            'view' => 'Consultar cuenta',
                            'publish' => 'Publicar usando esta cuenta (incluye consulta)',
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
                TextColumn::make('socialAccount.display_name')->label('Cuenta')->searchable(),
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
            'index' => ListWorkspaceAccountPermissions::route('/'),
        ];
    }
}
