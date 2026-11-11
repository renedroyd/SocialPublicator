<?php

namespace App\Filament\Resources\WorkspaceDubaiShopIntegrations;

use App\Filament\Resources\Concerns\AuthorizesWorkspaceResources;
use App\Filament\Resources\WorkspaceDubaiShopIntegrations\Pages\CreateWorkspaceDubaiShopIntegration;
use App\Filament\Resources\WorkspaceDubaiShopIntegrations\Pages\EditWorkspaceDubaiShopIntegration;
use App\Filament\Resources\WorkspaceDubaiShopIntegrations\Pages\ListWorkspaceDubaiShopIntegrations;
use App\Models\WorkspaceDubaiShopIntegration;
use App\Services\DubaiShopApiClient;
use App\Services\Workspaces\WorkspaceContext;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class WorkspaceDubaiShopIntegrationResource extends Resource
{
    use AuthorizesWorkspaceResources;

    protected static ?string $model = WorkspaceDubaiShopIntegration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?int $navigationSort = 40;

    protected static function workspacePermissionPrefix(): string
    {
        return 'integrations';
    }

    public static function canCreate(): bool
    {
        return static::currentWorkspaceMembership()?->hasPermission('integrations.manage') === true
            && ! WorkspaceDubaiShopIntegration::query()->exists();
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

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Integraciones';
    }

    public static function getNavigationLabel(): string
    {
        return 'Dubai Shop';
    }

    public static function getModelLabel(): string
    {
        return 'conexión con Dubai Shop';
    }

    public static function getPluralModelLabel(): string
    {
        return 'conexión con Dubai Shop';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Conexión opcional')
                ->description('Sin esta conexión, el espacio de trabajo continúa funcionando de forma independiente con su catálogo local.')
                ->columns(2)
                ->schema([
                    Toggle::make('enabled')
                        ->label('Integración habilitada')
                        ->default(false)
                        ->live(),
                    Toggle::make('campaign_dispatch_enabled')
                        ->label('Permitir campañas automáticas')
                        ->default(false)
                        ->disabled(fn (Get $get): bool => ! $get('enabled'))
                        ->helperText('Mantén esta opción desactivada hasta validar catálogo, almacenes y destinos.'),
                    TextInput::make('api_base_url')
                        ->label('URL base de Dubai Shop')
                        ->url()
                        ->maxLength(2048)
                        ->required(fn (Get $get): bool => $get('enabled'))
                        ->columnSpanFull(),
                    TextInput::make('api_token')
                        ->label('Token de lectura del catálogo')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->required(fn (Get $get, ?WorkspaceDubaiShopIntegration $record): bool => $get('enabled') && blank($record?->api_token))
                        ->helperText('Se cifra al guardar. Déjalo vacío para conservar el token existente.')
                        ->columnSpanFull(),
                ]),
            Section::make('Eventos firmados')
                ->description('Opcional. Permite recibir cambios de catálogo, inventario y precios mediante webhooks HMAC.')
                ->columns(2)
                ->schema([
                    TextInput::make('webhook_key_id')
                        ->label('Identificador de clave HMAC')
                        ->maxLength(120)
                        ->helperText('Debe coincidir con el identificador configurado en el emisor de Dubai Shop.'),
                    TextInput::make('webhook_secret')
                        ->label('Secreto HMAC')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->required(fn (Get $get): bool => filled($get('webhook_key_id')))
                        ->helperText('Se cifra al guardar. Déjalo vacío para conservar el secreto existente.'),
                    TextInput::make('timeout')
                        ->label('Tiempo máximo de espera (segundos)')
                        ->numeric()
                        ->integer()
                        ->minValue(3)
                        ->maxValue(120)
                        ->default(15)
                        ->required(),
                    TextInput::make('webhook_tolerance_seconds')
                        ->label('Ventana de validez del webhook (segundos)')
                        ->numeric()
                        ->integer()
                        ->minValue(30)
                        ->maxValue(1800)
                        ->default(300)
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('enabled')->label('Integración')->boolean(),
                IconColumn::make('campaign_dispatch_enabled')->label('Campañas automáticas')->boolean(),
                TextColumn::make('api_base_url')->label('Servidor')->limit(48),
                TextColumn::make('last_verified_at')->label('Última verificación')->dateTime()->placeholder('Sin verificar'),
                TextColumn::make('last_error_message')->label('Último error')->limit(60)->placeholder('Sin errores'),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label('Probar conexión')
                    ->icon('heroicon-o-signal')
                    ->visible(fn (WorkspaceDubaiShopIntegration $record): bool => $record->isReady())
                    ->action(function (WorkspaceDubaiShopIntegration $record): void {
                        try {
                            $warehouses = app(DubaiShopApiClient::class)->warehouses();
                            $record->update([
                                'last_verified_at' => now(),
                                'last_error_at' => null,
                                'last_error_message' => null,
                            ]);
                            Notification::make()
                                ->title('Conexión correcta')
                                ->body('Dubai Shop respondió correctamente. Almacenes disponibles: '.count($warehouses).'.')
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            $record->update([
                                'last_error_at' => now(),
                                'last_error_message' => 'No fue posible verificar la conexión con Dubai Shop.',
                            ]);
                            Notification::make()
                                ->title('No se pudo verificar la conexión')
                                ->body('Revisa la URL, el token y la conectividad del servidor.')
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->headerActions([
                CreateAction::make()->label('Configurar conexión'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkspaceDubaiShopIntegrations::route('/'),
            'create' => CreateWorkspaceDubaiShopIntegration::route('/create'),
            'edit' => EditWorkspaceDubaiShopIntegration::route('/{record}/edit'),
        ];
    }
}
