<?php

namespace App\Filament\Resources\ExternalCatalogCampaigns;

use App\Filament\Resources\ExternalCatalogCampaigns\Pages\CreateExternalCatalogCampaign;
use App\Filament\Resources\ExternalCatalogCampaigns\Pages\EditExternalCatalogCampaign;
use App\Filament\Resources\ExternalCatalogCampaigns\Pages\ListExternalCatalogCampaigns;
use App\Models\ExternalCatalogCampaign;
use App\Models\ExternalCatalogItem;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ExternalCatalogCampaignResource extends Resource
{
    protected static ?string $model = ExternalCatalogCampaign::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 35;

    public static function getNavigationGroup(): string | \UnitEnum | null
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Datos de la campaña'))->columns(2)->schema([
                TextInput::make('name')->label(__('Nombre'))->required()->maxLength(255),
                Toggle::make('is_active')->label(__('Activa'))->default(true),
                Textarea::make('description')->label(__('Descripción'))->rows(3)->columnSpanFull(),
                Select::make('selection_mode')->label(__('Selección de productos'))->options([
                    'manual' => __('Manual'),
                    'automatic' => __('Automática (se configurará en la siguiente fase)'),
                ])->default('manual')->required()->native(false),
                Select::make('publication_mode')->label(__('Formato de publicación'))->options([
                    'grouped' => __('Agrupada'),
                    'individual' => __('Individual'),
                ])->default('grouped')->required()->native(false),
                TextInput::make('max_publications_per_day')->label(__('Máximo de publicaciones diarias'))->numeric()->integer()->minValue(1)->maxValue(100)->default(3)->required(),
                TextInput::make('minimum_interval_minutes')->label(__('Intervalo mínimo entre publicaciones (minutos)'))->numeric()->integer()->minValue(1)->maxValue(10080)->default(240)->required(),
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
                    ->helperText(__('Selecciona los artículos que esta campaña podrá publicar.')),
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
