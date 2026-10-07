<?php

namespace App\Filament\Resources\Publicaciones\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PublicacionesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('hora', 'asc')
            ->striped()
            ->paginated([10, 25, 50])
            ->searchPlaceholder('Buscar publicaciones...')
            ->emptyStateHeading('No hay publicaciones')
            ->emptyStateDescription(
                'Crea una publicación para comenzar a configurar tu cronograma.'
            )
            ->emptyStateIcon('heroicon-o-megaphone')

            ->columns([

                /*
                 * HORA
                 */
                TextColumn::make('hora')
                    ->label('Hora')
                    ->time('H:i')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->icon('heroicon-o-clock')
                    ->weight('bold')
                    ->size('lg')
                    ->alignCenter()
                    ->width('120px')
                    ->extraAttributes([
                        'class' => 'whitespace-nowrap',
                    ]),

                /*
                 * IMAGEN
                 */
                ImageColumn::make('imagen')
                    ->label('Imagen')
                    ->disk('public')
                    ->square()
                    ->size(70)
                    ->extraImgAttributes([
                        'class' => 'rounded-xl object-cover ring-1 ring-gray-200 shadow-sm',
                    ])
                    ->alignCenter()
                    ->width('110px'),

                /*
                 * CONTENIDO
                 */
                TextColumn::make('texto')
                    ->label('Contenido')
                    ->searchable()
                    ->wrap()
                    ->limit(180)
                    ->lineClamp(3)
                    ->weight('medium')
                    ->color('gray')
                    ->tooltip(
                        fn(TextColumn $column): ?string =>
                        filled($column->getState())
                            ? $column->getState()
                            : null
                    )
                    ->grow(),

                /*
                 * DESTINO
                 */
                TextColumn::make('destino')
                    ->label('Destino')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'whatsapp' => 'WhatsApp',
                        'facebook' => 'Facebook',
                        'ambos' => 'WhatsApp + Facebook',
                        default => ucfirst($state),
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'whatsapp' => 'success',
                        'facebook' => 'info',
                        'ambos' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),

                /*
                 * ESTADO
                 */
                IconColumn::make('activo')
                    ->label('Estado')
                    ->boolean()
                    ->sortable()
                    ->alignCenter()
                    ->width('90px'),

                /*
                 * FECHA DE CREACIÓN
                 */
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->color('gray')
                    ->icon('heroicon-o-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),

            ])

            /*
             * ACCIONES POR REGISTRO
             */
            ->recordActions([

                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary'),

                DeleteAction::make()
                    ->label('Eliminar')
                    ->icon('heroicon-o-trash')
                    ->color('danger'),

            ])

            /*
             * ACCIONES MASIVAS
             */
            ->toolbarActions([

                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas')
                        ->icon('heroicon-o-trash'),
                ]),

            ]);
    }
}
