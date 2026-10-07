<?php

namespace App\Filament\Resources\Publications\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PublicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Programación')
                ->description('Define cuándo y con qué frecuencia se realizará esta publicación.')
                ->icon('heroicon-o-calendar-days')
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])->schema([
                        DatePicker::make('date')
                            ->label('Fecha de inicio')
                            ->placeholder('Seleccionar fecha')
                            ->helperText('Día a partir del cual comenzará la publicación.')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->closeOnDateSelection()
                            ->required()
                            ->prefixIcon('heroicon-o-calendar'),

                        Select::make('recurrence_type')
                            ->label('Recurrencia')
                            ->options([
                                'none' => 'Sin recurrencia',
                                'hourly' => 'Cada X horas',
                            ])
                            ->default('none')
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('Selecciona si la publicación se realizará una sola vez o periódicamente.')
                            ->prefixIcon('heroicon-o-arrow-path'),

                        TimePicker::make('recurrence_start_time')
                            ->label('Hora de inicio')
                            ->placeholder('Ej.: 09:00')
                            ->helperText('Primera hora en la que se realizará la publicación recurrente.')
                            ->seconds(false)
                            ->native(false)
                            ->displayFormat('H:i')
                            ->format('H:i')
                            ->required(fn(Get $get): bool => $get('recurrence_type') === 'hourly')
                            ->visible(fn(Get $get): bool => $get('recurrence_type') === 'hourly')
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (filled($state)) {
                                    $set('time', $state);
                                }
                            })
                            ->prefixIcon('heroicon-o-play'),

                        TextInput::make('interval_hours')
                            ->label('Intervalo')
                            ->placeholder('Ej.: 2')
                            ->helperText('Cantidad de horas entre cada publicación.')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(24)
                            ->step(1)
                            ->required(fn(Get $get): bool => $get('recurrence_type') === 'hourly')
                            ->visible(fn(Get $get): bool => $get('recurrence_type') === 'hourly')
                            ->suffix('horas')
                            ->prefixIcon('heroicon-o-clock'),

                        TimePicker::make('recurrence_end_time')
                            ->label('Hora límite diaria')
                            ->placeholder('Ej.: 18:00')
                            ->helperText('La última publicación del día no superará esta hora.')
                            ->seconds(false)
                            ->native(false)
                            ->displayFormat('H:i')
                            ->format('H:i')
                            ->required(fn(Get $get): bool => $get('recurrence_type') === 'hourly')
                            ->visible(fn(Get $get): bool => $get('recurrence_type') === 'hourly')
                            ->prefixIcon('heroicon-o-stop'),

                        DatePicker::make('end_date')
                            ->label('Fecha de finalización')
                            ->placeholder('Sin fecha de finalización')
                            ->helperText('Último día en que se permitirá la recurrencia.')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->minDate(fn(Get $get) => $get('date'))
                            ->visible(fn(Get $get): bool => $get('recurrence_type') === 'hourly')
                            ->prefixIcon('heroicon-o-calendar'),

                        Select::make('destination')
                            ->label('Destino')
                            ->options([
                                'whatsapp' => 'WhatsApp',
                                'facebook' => 'Facebook',
                                'both' => 'WhatsApp + Facebook',
                            ])
                            ->default('whatsapp')
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('Selecciona dónde se realizará la publicación.')
                            ->prefixIcon('heroicon-o-paper-airplane'),

                        Select::make('facebookGroups')
                            ->label('Grupos de Facebook')
                            ->relationship(name: 'facebookGroups', titleAttribute: 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->optionsLimit(50)
                            ->native(false)
                            ->required(fn(Get $get): bool => in_array($get('destination'), ['facebook', 'both'], true))
                            ->visible(fn(Get $get): bool => in_array($get('destination'), ['facebook', 'both'], true))
                            ->helperText('Selecciona los grupos donde se compartirá esta publicación.')
                            ->prefixIcon('heroicon-o-user-group')
                            ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 3]),

                        Toggle::make('active')
                            ->label('Publicación activa')
                            ->helperText('El bot solo procesará publicaciones activas.')
                            ->default(true)
                            ->onColor('success')
                            ->offColor('gray')
                            ->onIcon('heroicon-o-check')
                            ->offIcon('heroicon-o-x-mark'),
                    ]),
                ])
                ->columnSpanFull(),

            Section::make('Imagen')
                ->description('Sube la imagen que acompañará el mensaje.')
                ->icon('heroicon-o-photo')
                ->schema([
                    FileUpload::make('image')
                        ->label('Imagen de la publicación')
                        ->placeholder('Arrastra una imagen aquí o haz clic para seleccionarla')
                        ->helperText('Formatos recomendados: JPG, JPEG, PNG o WEBP.')
                        ->image()
                        ->disk('public')
                        ->directory('publications')
                        ->imageEditor()
                        ->openable()
                        ->downloadable()
                        ->required()
                        ->imagePreviewHeight('320')
                        ->panelLayout('integrated')
                        ->removeUploadedFileButtonPosition('top')
                        ->uploadButtonPosition('center')
                        ->uploadProgressIndicatorPosition('center')
                        ->columnSpanFull(),
                ])
                ->compact()
                ->columnSpanFull(),

            Section::make('Mensaje')
                ->description('Escribe el texto que recibirán los clientes junto con la imagen.')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    Textarea::make('text')
                        ->label('Texto de la publicación')
                        ->placeholder("🍺 ¡Realiza tu pedido ahora!\n\n💰 $15.000,00 CUP por caja")
                        ->helperText('Puedes utilizar emojis, saltos de línea y hasta 5000 caracteres.')
                        ->rows(12)
                        ->autosize()
                        ->maxLength(5000)
                        ->required()
                        ->columnSpanFull(),
                ])
                ->compact()
                ->columnSpanFull(),
        ])->columns(1);
    }
}
