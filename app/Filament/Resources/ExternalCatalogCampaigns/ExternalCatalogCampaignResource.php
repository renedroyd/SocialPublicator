<?php

namespace App\Filament\Resources\ExternalCatalogCampaigns;

use App\Filament\Resources\Concerns\AuthorizesWorkspaceResources;
use App\Filament\Resources\ExternalCatalogCampaigns\Pages\CreateExternalCatalogCampaign;
use App\Filament\Resources\ExternalCatalogCampaigns\Pages\EditExternalCatalogCampaign;
use App\Filament\Resources\ExternalCatalogCampaigns\Pages\ListExternalCatalogCampaigns;
use App\Models\ExternalCatalogCampaign;
use App\Models\ExternalCatalogItem;
use App\Models\WorkspaceMembershipDestinationPermission;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ExternalCatalogCampaignResource extends Resource
{
    use AuthorizesWorkspaceResources;

    protected static function workspacePermissionPrefix(): string
    {
        return 'campaigns';
    }

    protected static ?string $model = ExternalCatalogCampaign::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 35;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Integraciones');
    }

    public static function getNavigationLabel(): string
    {
        return __('Campañas sociales');
    }

    public static function getModelLabel(): string
    {
        return __('campaña social');
    }

    public static function getPluralModelLabel(): string
    {
        return __('campañas sociales');
    }

    private static function authorizedDestinations(Builder $query, string $destinationType): Builder
    {
        $membership = auth()->user()?->activeWorkspaceMemberships()
            ->where('workspace_id', app(WorkspaceContext::class)->id())
            ->first();

        if ($membership === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($membership->isOwner()) {
            return $query;
        }

        if (
            ! $membership->hasPermission('destinations.view')
            || ! $membership->hasPermission('publications.publish')
        ) {
            return $query->whereRaw('1 = 0');
        }

        if (! $membership->hasPermission('destinations.manage')) {
            $destinationIds = $membership->destinationPermissions()
                ->where('destination_type', $destinationType)
                ->whereJsonContains('permissions', 'publish')
                ->select('destination_id');

            $query->whereIn('id', $destinationIds);
        }

        if (! $membership->hasPermission('accounts.manage')) {
            $accountIds = $membership->socialAccountPermissions()
                ->whereJsonContains('permissions', 'publish')
                ->select('social_account_id');

            $query->where(function (Builder $query) use ($accountIds): void {
                $query->whereNull('social_account_id')
                    ->orWhereIn('social_account_id', $accountIds);
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Datos de la campaña'))->columns(2)->schema([
                TextInput::make('name')->label(__('Nombre'))->required()->maxLength(255),
                Toggle::make('is_active')->label(__('Activa'))->default(true),
                Textarea::make('description')->label(__('Descripción'))->rows(3)->columnSpanFull(),
                Select::make('selection_mode')->label(__('Selección de productos'))->options([
                    'manual' => __('Manual'),
                    'automatic' => __('Automática (todo el catálogo elegible)'),
                ])->default('manual')->required()->native(false),
                Select::make('publication_mode')->label(__('Formato de publicación'))->options([
                    'grouped' => __('Agrupada'),
                    'individual' => __('Individual'),
                ])->default('grouped')->required()->native(false),
                TextInput::make('max_publications_per_day')->label(__('Máximo de publicaciones diarias'))->numeric()->integer()->minValue(1)->maxValue(100)->default(3)->required(),
                TextInput::make('minimum_interval_minutes')->label(__('Intervalo mínimo entre publicaciones (minutos)'))->numeric()->integer()->minValue(1)->maxValue(10080)->default(240)->required(),
            ]),
            Section::make(__('Rotación y horario'))->description(__('El planificador crea grupos de artículos dentro de estas reglas. No envía publicaciones a redes sociales.'))->columns(2)->schema([
                Select::make('rotation_strategy')->label(__('Estrategia de rotación'))->options([
                    'least_recent' => __('Menos publicado recientemente'),
                    'round_robin' => __('Turno rotativo'),
                    'random' => __('Aleatoria'),
                ])->default('least_recent')->required()->native(false),
                TextInput::make('group_size')->label(__('Artículos por publicación agrupada'))->numeric()->integer()->minValue(1)->maxValue(20)->default(4)->required(),
                TimePicker::make('posting_window_start')->label(__('Hora inicial'))->seconds(false)->format('H:i')->default('09:00')->required(),
                TimePicker::make('posting_window_end')->label(__('Hora final'))->seconds(false)->format('H:i')->default('21:00')->after('posting_window_start')->required(),
                Select::make('timezone')->label(__('Zona horaria'))->options(collect(timezone_identifiers_list())->mapWithKeys(fn (string $zone): array => [$zone => $zone])->all())->searchable()->native(false)->default(config('app.timezone', 'UTC'))->required(),
                CheckboxList::make('posting_weekdays')->label(__('Días permitidos'))->options([
                    1 => __('Lunes'),
                    2 => __('Martes'),
                    3 => __('Miércoles'),
                    4 => __('Jueves'),
                    5 => __('Viernes'),
                    6 => __('Sábado'),
                    7 => __('Domingo'),
                ])->columns(7)->default([1, 2, 3, 4, 5, 6, 7])->required()->columnSpanFull(),
            ]),
            Section::make(__('Catálogo de Dubai Shop'))->description(__('Solo aparecen artículos externos activos y visibles sincronizados desde Dubai Shop.'))->schema([
                Select::make('items')
                    ->label(__('Productos y variantes'))
                    ->relationship(
                        name: 'items',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query
                            ->where('source', ExternalCatalogItem::SOURCE_DUBAI_SHOP)
                            ->where('active', true)
                            ->where('visible', true)
                            ->orderBy('name'),
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->helperText(__('Selecciona los artículos que esta campaña podrá publicar.'))
                    ->visible(fn (Get $get): bool => $get('selection_mode') === 'manual'),
                Select::make('warehouses')
                    ->label(__('Almacenes habilitados'))
                    ->relationship(
                        name: 'warehouses',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query
                            ->where('active', true)
                            ->where('online_fulfillment_enabled', true)
                            ->orderBy('name'),
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required()
                    ->helperText(__('La disponibilidad se calculará únicamente en los almacenes seleccionados.')),
            ]),
            Section::make(__('Reglas comerciales'))->columns(2)->schema([
                Select::make('out_of_stock_behavior')->label(__('Cuando se agote'))->options([
                    'pause' => __('Pausar publicaciones del artículo'),
                    'publish' => __('Publicar aviso de agotado'),
                ])->default('pause')->required()->native(false),
                Select::make('restock_behavior')->label(__('Cuando se reponga'))->options([
                    'publish' => __('Publicar aviso de reposición'),
                    'ignore' => __('No publicar aviso'),
                ])->default('publish')->required()->native(false),
                Select::make('price_change_behavior')->label(__('Cuando cambie el precio'))->options([
                    'immediate' => __('Publicar actualización inmediatamente'),
                    'ignore' => __('No publicar actualización'),
                ])->default('immediate')->required()->native(false),
            ]),
            Section::make(__('Destinos sociales'))->description(__('Las publicaciones automáticas utilizarán estos grupos activos y los bots ya configurados.'))->schema([
                Select::make('destination')->label(__('Canales'))->options([
                    'whatsapp' => 'WhatsApp',
                    'facebook' => 'Facebook',
                    'both' => 'WhatsApp + Facebook',
                ])->default('whatsapp')->required()->native(false)->live(),
                Select::make('whatsappGroups')
                    ->label(__('Grupos de WhatsApp'))
                    ->relationship(
                        name: 'whatsappGroups',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => static::authorizedDestinations(
                            $query->where('active', true),
                            WorkspaceMembershipDestinationPermission::DESTINATION_WHATSAPP_GROUP,
                        )->orderBy('name'),
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(fn (Get $get): bool => in_array($get('destination'), ['whatsapp', 'both'], true))
                    ->visible(fn (Get $get): bool => in_array($get('destination'), ['whatsapp', 'both'], true)),
                Select::make('facebookGroups')
                    ->label(__('Grupos de Facebook'))
                    ->relationship(
                        name: 'facebookGroups',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => static::authorizedDestinations(
                            $query->where('active', true),
                            WorkspaceMembershipDestinationPermission::DESTINATION_FACEBOOK_GROUP,
                        )->orderBy('name'),
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(fn (Get $get): bool => in_array($get('destination'), ['facebook', 'both'], true))
                    ->visible(fn (Get $get): bool => in_array($get('destination'), ['facebook', 'both'], true)),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('Campaña'))->searchable()->sortable(),
            TextColumn::make('selection_mode')->label(__('Selección'))->badge(),
            TextColumn::make('publication_mode')->label(__('Formato'))->badge(),
            TextColumn::make('items_count')->counts('items')->label(__('Artículos')),
            TextColumn::make('warehouses_count')->counts('warehouses')->label(__('Almacenes')),
            TextColumn::make('destination')->label(__('Canales'))->badge(),
            TextColumn::make('triggers_count')->counts('triggers')->label(__('Avisos pendientes')),
            IconColumn::make('is_active')->label(__('Activa'))->boolean(),
        ])->filters([
            TernaryFilter::make('is_active')->label(__('Estado')),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make()->requiresConfirmation(),
        ])->headerActions([
            CreateAction::make()->label(__('Nueva campaña')),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExternalCatalogCampaigns::route('/'),
            'create' => CreateExternalCatalogCampaign::route('/create'),
            'edit' => EditExternalCatalogCampaign::route('/{record}/edit'),
        ];
    }
}
