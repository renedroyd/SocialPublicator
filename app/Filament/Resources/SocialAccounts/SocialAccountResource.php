<?php

namespace App\Filament\Resources\SocialAccounts;

use App\Filament\Resources\Concerns\AuthorizesWorkspaceResources;
use App\Filament\Resources\SocialAccounts\Pages\ListSocialAccounts;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Services\SocialAccountConnectionService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SocialAccountResource extends Resource
{
    use AuthorizesWorkspaceResources;

    protected static ?string $model = SocialAccount::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-share';

    protected static ?int $navigationSort = 10;

    protected static function workspacePermissionPrefix(): string
    {
        return 'accounts';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Cuentas sociales';
    }

    public static function getNavigationLabel(): string
    {
        return 'Cuentas';
    }

    public static function getModelLabel(): string
    {
        return 'cuenta social';
    }

    public static function getPluralModelLabel(): string
    {
        return 'cuentas sociales';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $membership = static::currentWorkspaceMembership();

        if (! $membership || $membership->isOwner() || $membership->hasPermission('accounts.manage')) {
            return $query;
        }

        return $query->whereIn(
            'id',
            $membership->socialAccountPermissions()
                ->whereJsonContains('permissions', 'view')
                ->select('social_account_id'),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identidad de la cuenta')
                ->columns(2)
                ->schema([
                    Select::make('platform')
                        ->label('Plataforma')
                        ->disabledOn('edit')
                        ->options([
                            SocialAccount::PLATFORM_WHATSAPP => 'WhatsApp',
                            SocialAccount::PLATFORM_FACEBOOK => 'Facebook',
                        ])
                        ->required()
                        ->native(false),
                    Select::make('account_type')
                        ->label('Tipo de cuenta')
                        ->disabledOn('edit')
                        ->options([
                            SocialAccount::TYPE_WHATSAPP => 'Cuenta WhatsApp',
                            SocialAccount::TYPE_FACEBOOK_PROFILE => 'Perfil de Facebook',
                            SocialAccount::TYPE_FACEBOOK_PAGE => 'Página de Facebook',
                        ])
                        ->required()
                        ->native(false),
                    TextInput::make('display_name')
                        ->label('Nombre visible')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('external_account_id')
                        ->label('ID externo')
                        ->maxLength(191),
                    TextInput::make('username')
                        ->label('Usuario')
                        ->maxLength(191),
                    Select::make('parent_account_id')
                        ->label('Perfil de Facebook asociado')
                        ->disabledOn('edit')
                        ->options(fn (): array => SocialAccount::query()
                            ->where('platform', SocialAccount::PLATFORM_FACEBOOK)
                            ->where('account_type', SocialAccount::TYPE_FACEBOOK_PROFILE)
                            ->orderBy('display_name')
                            ->pluck('display_name', 'id')
                            ->all())
                        ->searchable()
                        ->visible(fn (Get $get): bool => $get('account_type') === SocialAccount::TYPE_FACEBOOK_PAGE)
                        ->required(fn (callable $get): bool => $get('account_type') === SocialAccount::TYPE_FACEBOOK_PAGE),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')->label('Cuenta')->searchable()->sortable(),
                TextColumn::make('platform')->label('Plataforma')->badge()->formatStateUsing(fn (string $state): string => $state === 'whatsapp' ? 'WhatsApp' : 'Facebook'),
                TextColumn::make('account_type')->label('Tipo')->formatStateUsing(fn (string $state): string => match ($state) {
                    SocialAccount::TYPE_WHATSAPP => 'Cuenta WhatsApp',
                    SocialAccount::TYPE_FACEBOOK_PROFILE => 'Perfil de Facebook',
                    default => 'Página de Facebook',
                }),
                TextColumn::make('external_account_id')->label('ID externo')->toggleable(),
                TextColumn::make('status')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'connected' => 'Conectada',
                    'disconnected' => 'Desconectada',
                    'error' => 'Error',
                    default => 'Pendiente',
                }),
                TextColumn::make('connections_count')->counts('connections')->label('Conexiones'),
            ])
            ->actions([
                Action::make('facebookBrowserSession')
                    ->label('Sesión Facebook')
                    ->icon('heroicon-o-globe-alt')
                    ->url(fn (SocialAccount $record): string => route('social-accounts.facebook.session', $record))
                    ->visible(fn (SocialAccount $record): bool => $record->account_type === SocialAccount::TYPE_FACEBOOK_PROFILE),
                Action::make('connectWhatsApp')
                    ->label('Conectar WhatsApp')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn (SocialAccount $record): string => route('social-accounts.whatsapp.qr', $record))
                    ->visible(fn (SocialAccount $record): bool => $record->account_type === SocialAccount::TYPE_WHATSAPP),
                Action::make('connectMeta')
                    ->label('Conectar con Meta')
                    ->icon('heroicon-o-link')
                    ->url(fn (SocialAccount $record): string => route('social-accounts.meta.connect', $record))
                    ->visible(fn (SocialAccount $record): bool => $record->account_type === SocialAccount::TYPE_FACEBOOK_PROFILE),

                Action::make('disconnectWhatsApp')
                    ->label('Desconectar WhatsApp')
                    ->icon('heroicon-o-link-slash')
                    ->color('danger')
                    ->visible(fn (SocialAccount $record): bool => static::canManageConnections()
                        && $record->account_type === SocialAccount::TYPE_WHATSAPP
                        && static::hasConnection($record, SocialAccountConnection::TYPE_WPP_CONNECT_QR, 'connected'))
                    ->requiresConfirmation()
                    ->modalDescription('Revoca el acceso de SocialPublicator. La sesión local y los tokens de WBot se conservan; no se eliminan archivos.')
                    ->action(function (SocialAccount $record): void {
                        abort_unless(static::canManageConnections(), 403);
                        app(SocialAccountConnectionService::class)->revoke(
                            $record,
                            SocialAccountConnection::TYPE_WPP_CONNECT_QR,
                        );
                        Notification::make()->title('Acceso de WhatsApp revocado')->success()->send();
                    }),

                Action::make('reactivateWhatsApp')
                    ->label('Reactivar WhatsApp')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn (SocialAccount $record): bool => static::canManageConnections()
                        && $record->account_type === SocialAccount::TYPE_WHATSAPP
                        && static::hasConnection($record, SocialAccountConnection::TYPE_WPP_CONNECT_QR, 'revoked'))
                    ->requiresConfirmation()
                    ->action(function (SocialAccount $record): void {
                        abort_unless(static::canManageConnections(), 403);
                        app(SocialAccountConnectionService::class)->reactivate(
                            $record,
                            SocialAccountConnection::TYPE_WPP_CONNECT_QR,
                        );
                        Notification::make()
                            ->title('WhatsApp habilitado')
                            ->body('La sesión local se conservó. Abre Conectar WhatsApp o reinicia WBot para que informe el estado actual.')
                            ->success()
                            ->send();
                    }),

                Action::make('disconnectFacebookBrowser')
                    ->label('Desconectar sesión Facebook')
                    ->icon('heroicon-o-link-slash')
                    ->color('danger')
                    ->visible(fn (SocialAccount $record): bool => static::canManageConnections()
                        && $record->account_type === SocialAccount::TYPE_FACEBOOK_PROFILE
                        && static::hasConnection($record, SocialAccountConnection::TYPE_BROWSER_SESSION, 'connected'))
                    ->requiresConfirmation()
                    ->modalDescription('Revoca el acceso de SocialPublicator a la sesión de navegador. El perfil Chromium y sus cookies no se eliminan.')
                    ->action(function (SocialAccount $record): void {
                        abort_unless(static::canManageConnections(), 403);
                        app(SocialAccountConnectionService::class)->revoke(
                            $record,
                            SocialAccountConnection::TYPE_BROWSER_SESSION,
                        );
                        Notification::make()->title('Acceso de la sesión Facebook revocado')->success()->send();
                    }),

                Action::make('reactivateFacebookBrowser')
                    ->label('Reactivar sesión Facebook')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn (SocialAccount $record): bool => static::canManageConnections()
                        && $record->account_type === SocialAccount::TYPE_FACEBOOK_PROFILE
                        && static::hasConnection($record, SocialAccountConnection::TYPE_BROWSER_SESSION, 'revoked'))
                    ->requiresConfirmation()
                    ->action(function (SocialAccount $record): void {
                        abort_unless(static::canManageConnections(), 403);
                        app(SocialAccountConnectionService::class)->reactivate(
                            $record,
                            SocialAccountConnection::TYPE_BROWSER_SESSION,
                        );
                        Notification::make()
                            ->title('Sesión Facebook habilitada')
                            ->body('El perfil y las cookies locales se conservaron. Reinicia WBot si la sesión no vuelve a informar estado.')
                            ->success()
                            ->send();
                    }),

                Action::make('disconnectMeta')
                    ->label('Desconectar Meta OAuth')
                    ->icon('heroicon-o-link-slash')
                    ->color('danger')
                    ->visible(fn (SocialAccount $record): bool => static::canManageConnections()
                        && $record->account_type === SocialAccount::TYPE_FACEBOOK_PROFILE
                        && static::hasConnection($record, SocialAccountConnection::TYPE_META_OAUTH, 'connected'))
                    ->requiresConfirmation()
                    ->modalDescription('Bloquea el uso de Meta OAuth desde SocialPublicator y desactiva las páginas hijas como destinos. No cierra sesión en Meta ni elimina los tokens cifrados.')
                    ->action(function (SocialAccount $record): void {
                        abort_unless(static::canManageConnections(), 403);
                        app(SocialAccountConnectionService::class)->revoke(
                            $record,
                            SocialAccountConnection::TYPE_META_OAUTH,
                        );
                        Notification::make()->title('Acceso Meta OAuth revocado')->success()->send();
                    }),

                EditAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function canManageConnections(): bool
    {
        return static::currentWorkspaceMembership()?->hasPermission('accounts.manage') ?? false;
    }

    private static function hasConnection(
        SocialAccount $account,
        string $connectionType,
        ?string $status = null,
    ): bool {
        return $account->connections()
            ->where('connection_type', $connectionType)
            ->when($status !== null, fn (Builder $query): Builder => $query->where('status', $status))
            ->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocialAccounts::route('/'),
        ];
    }
}
