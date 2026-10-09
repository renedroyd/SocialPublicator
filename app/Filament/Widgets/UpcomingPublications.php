<?php

namespace App\Filament\Widgets;

use App\Models\Publication;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingPublications extends TableWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Próximas publicaciones';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Publication::query()
                    ->where('active', true)
                    ->whereDate('date', '>=', now()->toDateString())
                    ->orderBy('date')
                    ->orderBy('time')
            )
            ->columns([
                TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('time')
                    ->label('Hora')
                    ->time('H:i')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('text')
                    ->label('Contenido')
                    ->limit(90)
                    ->wrap(),

                TextColumn::make('destination')
                    ->label('Destino')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            'whatsapp' => 'WhatsApp',
                            'facebook' => 'Facebook',
                            'both' => 'WhatsApp + Facebook',
                            default => ucfirst($state),
                        }
                    ),

                TextColumn::make('recurrence_type')
                    ->label('Recurrencia')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state, Publication $record): string => $state === 'hourly'
                            ? 'Cada '.((int) $record->interval_hours).' h'
                            : 'Una vez'
                    ),
            ])
            ->paginated([5, 10]);
    }
}
