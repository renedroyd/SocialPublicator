<?php

namespace App\Filament\Resources\Publicaciones\Schemas;

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

class PublicacionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                /*
                |--------------------------------------------------------------------------
                | PROGRAMACIÓN
                |--------------------------------------------------------------------------
                */

                Section::make('Programación')
                    ->description(
                        'Define cuándo y con qué frecuencia se realizará esta publicación.'
                    )
                    ->icon('heroicon-o-calendar-days')
                    ->collapsible(false)
                    ->schema([

                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([

                                /*
                                |--------------------------------------------------------------------------
                                | FECHA DE INICIO
                                |--------------------------------------------------------------------------
                                */

                                DatePicker::make('fecha')
                                    ->label('Fecha de inicio')
                                    ->placeholder('Seleccionar fecha')
                                    ->helperText(
                                        'Día a partir del cual comenzará la publicación.'
                                    )
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->format('Y-m-d')
                                    ->closeOnDateSelection()
                                    ->required()
                                    ->prefixIcon('heroicon-o-calendar')
                                    ->columnSpan(1),

                                /*
                                |--------------------------------------------------------------------------
                                | TIPO DE RECURRENCIA
                                |--------------------------------------------------------------------------
                                */

                                Select::make('tipo_recurrencia')
                                    ->label('Recurrencia')
                                    ->options([
                                        'ninguna' => 'Sin recurrencia',
                                        'cada_horas' => 'Cada X horas',
                                    ])
                                    ->default('ninguna')
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->helperText(
                                        'Selecciona si la publicación se realizará una sola vez o periódicamente.'
                                    )
                                    ->prefixIcon('heroicon-o-arrow-path')
                                    ->columnSpan(1),

                                /*
                                |--------------------------------------------------------------------------
                                | HORA DE INICIO DE RECURRENCIA
                                |--------------------------------------------------------------------------
                                */

                                TimePicker::make('hora_inicio_recurrencia')
                                    ->label('Hora de inicio')
                                    ->placeholder('Ej.: 09:00')
                                    ->helperText(
                                        'Primera hora en la que se realizará la publicación recurrente.'
                                    )
                                    ->seconds(false)
                                    ->native(false)
                                    ->displayFormat('H:i')
                                    ->format('H:i')
                                    ->required(
                                        fn(Get $get): bool =>
                                        $get('tipo_recurrencia') === 'cada_horas'
                                    )
                                    ->visible(
                                        fn(Get $get): bool =>
                                        $get('tipo_recurrencia') === 'cada_horas'
                                    )
                                    ->live()
                                    ->afterStateUpdated(function (
                                        ?string $state,
                                        Set $set
                                    ): void {
                                        if (filled($state)) {
                                            $set('hora', $state);
                                        }
                                    })
                                    ->prefixIcon('heroicon-o-play')
                                    ->columnSpan(1),

                                /*
                                |--------------------------------------------------------------------------
                                | INTERVALO
                                |--------------------------------------------------------------------------
                                */

                                TextInput::make('intervalo_horas')
                                    ->label('Intervalo')
                                    ->placeholder('Ej.: 2')
                                    ->helperText(
                                        'Cantidad de horas entre cada publicación.'
                                    )
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(24)
                                    ->step(1)
                                    ->required(
                                        fn(Get $get): bool =>
                                        $get('tipo_recurrencia') === 'cada_horas'
                                    )
                                    ->visible(
                                        fn(Get $get): bool =>
                                        $get('tipo_recurrencia') === 'cada_horas'
                                    )
                                    ->suffix('horas')
                                    ->prefixIcon('heroicon-o-clock')
                                    ->columnSpan(1),

                                /*
                                |--------------------------------------------------------------------------
                                | HORA LÍMITE DIARIA
                                |--------------------------------------------------------------------------
                                */

                                TimePicker::make('hora_fin_recurrencia')
                                    ->label('Hora límite diaria')
                                    ->placeholder('Ej.: 18:00')
                                    ->helperText(
                                        'La última publicación del día no superará esta hora.'
                                    )
                                    ->seconds(false)
                                    ->native(false)
                                    ->displayFormat('H:i')
                                    ->format('H:i')
                                    ->required(
                                        fn(Get $get): bool =>
                                        $get('tipo_recurrencia') === 'cada_horas'
                                    )
                                    ->visible(
                                        fn(Get $get): bool =>
                                        $get('tipo_recurrencia') === 'cada_horas'
                                    )
                                    ->prefixIcon('heroicon-o-stop')
                                    ->columnSpan(1),

                                /*
                                |--------------------------------------------------------------------------
                                | FECHA FIN
                                |--------------------------------------------------------------------------
                                */

                                DatePicker::make('fecha_fin')
                                    ->label('Fecha de finalización')
                                    ->placeholder('Sin fecha de finalización')
                                    ->helperText(
                                        'Último día en que se permitirá la recurrencia.'
                                    )
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->format('Y-m-d')
                                    ->minDate(
                                        fn(Get $get) => $get('fecha')
                                    )
                                    ->visible(
                                        fn(Get $get): bool =>
                                        $get('tipo_recurrencia') === 'cada_horas'
                                    )
                                    ->prefixIcon('heroicon-o-calendar')
                                    ->columnSpan(1),

                                /*
                                |--------------------------------------------------------------------------
                                | DESTINO
                                |--------------------------------------------------------------------------
                                */

                                Select::make('destino')
                                    ->label('Destino')
                                    ->options([
                                        'whatsapp' => 'WhatsApp',
                                        'facebook' => 'Facebook',
                                        'ambos' => 'WhatsApp + Facebook',
                                    ])
                                    ->default('whatsapp')
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->helperText(
                                        'Selecciona dónde se realizará la publicación.'
                                    )
                                    ->prefixIcon('heroicon-o-paper-airplane')
                                    ->columnSpan(1),

                                /*
                                |--------------------------------------------------------------------------
                                | GRUPOS DE FACEBOOK
                                |--------------------------------------------------------------------------
                                */

                                Select::make('facebook_groups')
                                    ->label('Grupos de Facebook')
                                    ->relationship(
                                        name: 'facebookGroups',
                                        titleAttribute: 'name'
                                    )
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->optionsLimit(50)
                                    ->native(false)
                                    ->required(
                                        fn(Get $get): bool =>
                                        in_array(
                                            $get('destino'),
                                            ['facebook', 'ambos'],
                                            true
                                        )
                                    )
                                    ->visible(
                                        fn(Get $get): bool =>
                                        in_array(
                                            $get('destino'),
                                            ['facebook', 'ambos'],
                                            true
                                        )
                                    )
                                    ->helperText(
                                        'Selecciona los grupos donde se compartirá esta publicación.'
                                    )
                                    ->prefixIcon('heroicon-o-user-group')
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 2,
                                        'lg' => 3,
                                    ]),

                                /*
                                |--------------------------------------------------------------------------
                                | ESTADO
                                |--------------------------------------------------------------------------
                                */

                                Toggle::make('activo')
                                    ->label('Publicación activa')
                                    ->helperText(
                                        'El bot solo procesará publicaciones activas.'
                                    )
                                    ->default(true)
                                    ->onColor('success')
                                    ->offColor('gray')
                                    ->onIcon('heroicon-o-check')
                                    ->offIcon('heroicon-o-x-mark')
                                    ->inline(false)
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->columnSpanFull(),

                /*
                |--------------------------------------------------------------------------
                | IMAGEN
                |--------------------------------------------------------------------------
                */

                Section::make('Imagen')
                    ->description(
                        'Sube la imagen que acompañará el mensaje.'
                    )
                    ->icon('heroicon-o-photo')
                    ->schema([

                        FileUpload::make('imagen')
                            ->label('Imagen de la publicación')
                            ->placeholder(
                                'Arrastra una imagen aquí o haz clic para seleccionarla'
                            )
                            ->helperText(
                                'Formatos recomendados: JPG, JPEG, PNG o WEBP.'
                            )
                            ->image()
                            ->disk('public')
                            ->directory('publicaciones')
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

                /*
                |--------------------------------------------------------------------------
                | MENSAJE
                |--------------------------------------------------------------------------
                */

                Section::make('Mensaje')
                    ->description(
                        'Escribe el texto que recibirán los clientes junto con la imagen.'
                    )
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->schema([

                        Textarea::make('texto')
                            ->label('Texto de la publicación')
                            ->placeholder(
                                "🍺 ¡Realiza tu pedido ahora!\n\n" .
                                    "💰 $15.000,00 CUP por caja"
                            )
                            ->helperText(
                                'Puedes utilizar emojis, saltos de línea y hasta 5000 caracteres.'
                            )
                            ->rows(12)
                            ->autosize()
                            ->maxLength(5000)
                            ->required()
                            ->columnSpanFull()
                            ->extraInputAttributes([
                                'class' =>
                                'text-base leading-relaxed font-normal',
                                'spellcheck' => 'true',
                            ]),
                    ])
                    ->compact()
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }
}

