<?php

namespace App\Filament\Resources\Publications\Schemas;

use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\WorkspaceMembershipDestinationPermission;
use App\Services\Workspaces\WorkspaceContext;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PublicationForm
{
    private static function authorizedDestinations(Builder $query, string $destinationType): Builder
    {
        $workspaceId = app(WorkspaceContext::class)->id();

        if ($workspaceId && SocialAccount::query()->where('workspace_id', $workspaceId)->exists()) {
            $query->whereNotNull('social_account_id');
        }
        if ($destinationType === WorkspaceMembershipDestinationPermission::DESTINATION_WHATSAPP_GROUP) {
            $query->where('is_admin', true);
        }

        $membership = auth()->user()?->activeWorkspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->first();

        if (! $membership) {
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

            $query->whereIn('social_account_id', $accountIds);
        }

        return $query;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                /*
                 * Scheduling
                 */
                Section::make('Programación')
                    ->description(
                        'Define cuándo se publicará el contenido y, si lo deseas, establece una programación recurrente.'
                    )
                    ->icon('heroicon-o-calendar-days')
                    ->collapsible()
                    ->persistCollapsed(false)
                    ->schema([
                        Grid::make()
                            ->columns([
                                'default' => 1,
                                'sm' => 2,
                                'lg' => 4,
                            ])
                            ->schema([
                                DatePicker::make('date')
                                    ->label('Fecha de inicio')
                                    ->placeholder('Seleccionar fecha')
                                    ->helperText('Día en que comenzará la publicación.')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->format('Y-m-d')
                                    ->closeOnDateSelection()
                                    ->required()
                                    ->prefixIcon('heroicon-o-calendar')
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 1,
                                        'lg' => 1,
                                    ]),

                                TimePicker::make('time')
                                    ->label('Hora')
                                    ->placeholder('Seleccionar hora')
                                    ->helperText('Hora de la primera publicación.')
                                    ->seconds(false)
                                    ->displayFormat('H:i')
                                    ->format('H:i')
                                    ->required()
                                    ->prefixIcon('heroicon-o-clock')
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 1,
                                        'lg' => 1,
                                    ]),

                                Select::make('recurrence_type')
                                    ->label('Recurrencia')
                                    ->options([
                                        'none' => 'Sin recurrencia',
                                        'daily' => 'Diaria',
                                        'weekly' => 'Semanal',
                                        'hourly' => 'Cada X minutos',
                                    ])
                                    ->default('none')
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->searchable(false)
                                    ->helperText('Elige si se publicará una sola vez, diariamente, semanalmente o cada X minutos.')
                                    ->prefixIcon('heroicon-o-arrow-path')
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 2,
                                        'lg' => 2,
                                    ]),
                            ]),

                        CheckboxList::make('recurrence_days')
                            ->label('Días de publicación')
                            ->options([
                                '1' => 'Lunes',
                                '2' => 'Martes',
                                '3' => 'Miércoles',
                                '4' => 'Jueves',
                                '5' => 'Viernes',
                                '6' => 'Sábado',
                                '7' => 'Domingo',
                            ])
                            ->columns(4)
                            ->gridDirection('row')
                            ->required(fn (Get $get): bool => $get('recurrence_type') === 'weekly')
                            ->visible(fn (Get $get): bool => $get('recurrence_type') === 'weekly')
                            ->helperText('Selecciona uno o varios días de la semana.')
                            ->live()
                            ->columnSpanFull(),

                        Grid::make()
                            ->columns([
                                'default' => 1,
                                'sm' => 2,
                                'lg' => 4,
                            ])
                            ->visible(
                                fn (Get $get): bool => $get('recurrence_type') === 'hourly'
                            )
                            ->schema([
                                TimePicker::make('recurrence_start_time')
                                    ->label('Hora de inicio')
                                    ->placeholder('Ej.: 09:00')
                                    ->helperText('Primera hora de ejecución.')
                                    ->seconds(false)
                                    ->native(false)
                                    ->displayFormat('H:i')
                                    ->format('H:i')
                                    ->required(fn (Get $get): bool => $get('recurrence_type') === 'hourly')
                                    ->live()
                                    ->afterStateUpdated(
                                        function (?string $state, Set $set): void {
                                            if (filled($state)) {
                                                $set('time', $state);
                                            }
                                        }
                                    )
                                    ->prefixIcon('heroicon-o-play')
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 1,
                                        'lg' => 1,
                                    ]),

                                TextInput::make('interval_minutes')
                                    ->label('Intervalo')
                                    ->placeholder('Ej.: 15')
                                    ->helperText('Minutos entre publicaciones. Mínimo: 1 minuto.')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(1440)
                                    ->step(1)
                                    ->required(fn (Get $get): bool => $get('recurrence_type') === 'hourly')
                                    ->suffix('minutos')
                                    ->prefixIcon('heroicon-o-clock')
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 1,
                                        'lg' => 1,
                                    ]),

                                TimePicker::make('recurrence_end_time')
                                    ->label('Hora límite diaria')
                                    ->placeholder('Ej.: 18:00')
                                    ->helperText('Última hora permitida cada día.')
                                    ->seconds(false)
                                    ->native(false)
                                    ->displayFormat('H:i')
                                    ->format('H:i')
                                    ->required(fn (Get $get): bool => $get('recurrence_type') === 'hourly')
                                    ->prefixIcon('heroicon-o-stop')
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 1,
                                        'lg' => 1,
                                    ]),
                            ]),

                        Grid::make()
                            ->columns([
                                'default' => 1,
                                'sm' => 2,
                                'lg' => 4,
                            ])
                            ->visible(
                                fn (Get $get): bool => in_array(
                                    $get('recurrence_type'),
                                    ['daily', 'weekly', 'hourly'],
                                    true
                                )
                            )
                            ->schema([
                                DatePicker::make('end_date')
                                    ->label('Fecha de finalización')
                                    ->placeholder('Sin fecha límite')
                                    ->helperText(
                                        fn (Get $get): string => match ($get('recurrence_type')) {
                                            'daily' => 'Último día en que se realizará la publicación diaria.',
                                            'weekly' => 'Último día en que se realizará la publicación semanal.',
                                            default => 'Último día de la recurrencia.',
                                        }
                                    )
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->format('Y-m-d')
                                    ->minDate(fn (Get $get) => $get('date'))
                                    ->prefixIcon('heroicon-o-calendar')
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 2,
                                        'lg' => 1,
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),

                /*
                 * Destination
                 */
                Section::make('Destino')
                    ->description(
                        'Selecciona las plataformas donde se publicará automáticamente el contenido.'
                    )
                    ->icon('heroicon-o-paper-airplane')
                    ->schema([
                        Grid::make()
                            ->columns([
                                'default' => 1,
                                'sm' => 2,
                                'lg' => 4,
                            ])
                            ->schema([
                                Select::make('destination')
                                    ->label('Canal de publicación')
                                    ->options([
                                        'whatsapp' => 'WhatsApp',
                                        'facebook' => 'Facebook',
                                        'both' => 'WhatsApp + Facebook',
                                    ])
                                    ->default('whatsapp')
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->searchable(false)
                                    ->helperText(
                                        'Elige dónde quieres enviar esta publicación.'
                                    )
                                    ->prefixIcon('heroicon-o-paper-airplane')
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 2,
                                        'lg' => 2,
                                    ]),

                                Toggle::make('active')
                                    ->label('Publicación activa')
                                    ->helperText(
                                        'El bot procesará únicamente publicaciones activas.'
                                    )
                                    ->default(true)
                                    ->onColor('success')
                                    ->offColor('gray')
                                    ->onIcon('heroicon-o-check')
                                    ->offIcon('heroicon-o-x-mark')
                                    ->inline(false)
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 2,
                                        'lg' => 2,
                                    ]),
                            ]),

                        Select::make('whatsappGroups')
                            ->label('Grupos de WhatsApp')
                            ->relationship(
                                name: 'whatsappGroups',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => static::authorizedDestinations(
                                    $query,
                                    WorkspaceMembershipDestinationPermission::DESTINATION_WHATSAPP_GROUP,
                                ),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->optionsLimit(100)
                            ->native(false)
                            ->required(fn (Get $get): bool => in_array($get('destination'), ['whatsapp', 'both'], true))
                            ->visible(fn (Get $get): bool => in_array(
                                $get('destination'),
                                ['whatsapp', 'both'],
                                true
                            ))
                            ->helperText(
                                'Selecciona uno o varios grupos de WhatsApp donde se enviará la publicación.'
                            )
                            ->prefixIcon('heroicon-o-chat-bubble-left-right')
                            ->columnSpanFull(),

                        Select::make('destinations')
                            ->label('Páginas de Facebook (API oficial)')
                            ->relationship(
                                name: 'destinations',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => static::authorizedDestinations(
                                    $query
                                        ->where('platform', 'facebook')
                                        ->where('active', true)
                                        ->whereHas('socialAccount', fn (Builder $account): Builder => $account
                                            ->where('account_type', SocialAccount::TYPE_FACEBOOK_PAGE)
                                            ->where('status', SocialAccount::STATUS_CONNECTED)
                                            ->whereHas('connections', fn (Builder $connection): Builder => $connection
                                                ->where('connection_type', SocialAccountConnection::TYPE_META_OAUTH)
                                                ->where('status', 'connected')
                                                ->where(fn (Builder $expiry): Builder => $expiry
                                                    ->whereNull('expires_at')
                                                    ->orWhere('expires_at', '>', now())))),
                                    WorkspaceMembershipDestinationPermission::DESTINATION_PUBLICATION,
                                ),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->optionsLimit(50)
                            ->native(false)
                            ->live()
                            ->required(fn (Get $get): bool => in_array($get('destination'), ['facebook', 'both'], true)
                                && blank($get('facebookGroups')))
                            ->visible(fn (Get $get): bool => in_array($get('destination'), ['facebook', 'both'], true))
                            ->helperText('Páginas conectadas con OAuth de Meta. Se publicará directamente mediante Graph API, sin utilizar el navegador.')
                            ->prefixIcon('heroicon-o-building-office-2')
                            ->columnSpanFull(),

                        Select::make('facebookGroups')
                            ->label('Grupos de Facebook')
                            ->relationship(
                                name: 'facebookGroups',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => static::authorizedDestinations(
                                    $query,
                                    WorkspaceMembershipDestinationPermission::DESTINATION_FACEBOOK_GROUP,
                                ),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->optionsLimit(50)
                            ->native(false)
                            ->live()
                            ->required(fn (Get $get): bool => in_array($get('destination'), ['facebook', 'both'], true)
                                && blank($get('destinations')))
                            ->visible(fn (Get $get): bool => in_array(
                                $get('destination'),
                                ['facebook', 'both'],
                                true
                            ))
                            ->helperText(
                                'Selecciona grupos de Facebook. También puedes publicar en páginas oficiales mediante la API de Meta.'
                            )
                            ->prefixIcon('heroicon-o-user-group')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                /*
                 * Image
                 */
                Section::make('Imagen')
                    ->description(
                        'Añade una imagen atractiva para acompañar el contenido de la publicación.'
                    )
                    ->icon('heroicon-o-photo')
                    ->collapsible()
                    ->schema([
                        FileUpload::make('image')
                            ->label('Imagen de la publicación')
                            ->placeholder(
                                'Arrastra una imagen aquí o haz clic para seleccionarla'
                            )
                            ->helperText(
                                'JPG, JPEG, PNG o WEBP. Se recomienda una imagen optimizada para redes sociales.'
                            )
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

                /*
                 * Message
                 */
                Section::make('Mensaje')
                    ->description(
                        'Escribe el contenido que acompañará a la imagen en WhatsApp y Facebook.'
                    )
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->schema([
                        Textarea::make('text')
                            ->label('Texto de la publicación')
                            ->placeholder(
                                "🍺 ¡Realiza tu pedido ahora!\n\n💰 $15.000,00 CUP por caja"
                            )
                            ->helperText(
                                'Puedes utilizar emojis, saltos de línea y hasta 5000 caracteres.'
                            )
                            ->rows(12)
                            ->autosize()
                            ->maxLength(5000)
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                    ])
                    ->compact()
                    ->columnSpanFull(),

            ])
            ->columns(1);
    }
}
