<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages;
use App\Models\Product;
use App\Models\Producto;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model =
    Product::class;

    protected static string | BackedEnum | null $navigationIcon =
    'heroicon-o-cube';

    protected static ?string $navigationLabel =
    'Productos';

    protected static ?string $modelLabel =
    'producto';

    protected static ?string $pluralModelLabel =
    'productos';

    protected static string | UnitEnum | null $navigationGroup =
    'Inventario';

    protected static ?int $navigationSort =
    1;

    public static function form(
        Schema $schema
    ): Schema {

        return $schema->components([

            Section::make(
                'Información del producto'
            )
                ->schema([

                    TextInput::make('sku')
                        ->label('SKU')
                        ->maxLength(100),

                    TextInput::make('nombre')
                        ->required()
                        ->maxLength(255),

                    Select::make(
                        'categoria_id'
                    )
                        ->label('Categoría')
                        ->relationship(
                            'categoria',
                            'nombre'
                        )
                        ->searchable()
                        ->preload(),

                    TextInput::make('unidad')
                        ->default('unidad')
                        ->required(),

                    Textarea::make(
                        'descripcion'
                    )
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Precios')
                ->schema([

                    TextInput::make(
                        'precio_costo'
                    )
                        ->label('Costo')
                        ->numeric()
                        ->prefix('$')
                        ->default(0),

                    TextInput::make(
                        'precio_mayorista'
                    )
                        ->label('Precio mayorista')
                        ->numeric()
                        ->prefix('$')
                        ->default(0)
                        ->required(),

                    TextInput::make(
                        'precio_minorista'
                    )
                        ->label('Precio minorista')
                        ->numeric()
                        ->prefix('$')
                        ->default(0),
                ])
                ->columns(3),

            Section::make('Inventario')
                ->schema([

                    TextInput::make('stock')
                        ->label('Stock actual')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    TextInput::make(
                        'stock_minimo'
                    )
                        ->label('Stock mínimo')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    TextInput::make(
                        'stock_maximo'
                    )
                        ->label('Stock máximo')
                        ->numeric(),

                    FileUpload::make('imagen')
                        ->label('Imagen')
                        ->image()
                        ->disk('public')
                        ->directory('productos')
                        ->imageEditor(),

                    Toggle::make('activo')
                        ->label('Producto activo')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(
        Table $table
    ): Table {

        return $table
            ->columns([

                TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sku')
                    ->searchable(),

                TextColumn::make(
                    'categoria.nombre'
                )
                    ->label('Categoría')
                    ->sortable(),

                TextColumn::make(
                    'precio_mayorista'
                )
                    ->label('Mayorista')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('stock')
                    ->label('Stock')
                    ->numeric(
                        decimalPlaces: 2
                    )
                    ->sortable()
                    ->color(
                        fn(Product $record) =>
                        $record->stock <= 0
                            ? 'danger'
                            : (
                                $record->stock <=
                                $record->stock_minimo
                                ? 'warning'
                                : 'success'
                            )
                    ),

                IconColumn::make('activo')
                    ->boolean(),
            ])

            ->filters([

                SelectFilter::make(
                    'categoria'
                )
                    ->relationship(
                        'categoria',
                        'nombre'
                    ),

                TernaryFilter::make(
                    'activo'
                ),

                TernaryFilter::make(
                    'stock_bajo'
                )
                    ->label('Stock bajo')
                    ->queries(

                        true: fn($query) =>
                        $query
                            ->where(
                                'stock',
                                '>',
                                0
                            )
                            ->whereColumn(
                                'stock',
                                '<=',
                                'stock_minimo'
                            ),

                        false: fn($query) =>
                        $query->where(
                            function ($q) {

                                $q
                                    ->whereColumn(
                                        'stock',
                                        '>',
                                        'stock_minimo'
                                    )
                                    ->orWhere(
                                        'stock',
                                        '<=',
                                        0
                                    );
                            }
                        ),
                    ),
            ])

            ->defaultSort(
                'nombre'
            );
    }

    public static function getPages(): array
    {
        return [

            'index' =>
            Pages\ListProducts::route('/'),

            'create' =>
            Pages\CreateProduct::route(
                '/create'
            ),

            'edit' =>
            Pages\EditProduct::route(
                '/{record}/edit'
            ),
        ];
    }
}
