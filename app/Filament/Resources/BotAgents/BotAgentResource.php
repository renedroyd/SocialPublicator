<?php

namespace App\Filament\Resources\BotAgents;

use App\Filament\Resources\BotAgents\Pages\CreateBotAgent;
use App\Filament\Resources\BotAgents\Pages\EditBotAgent;
use App\Filament\Resources\BotAgents\Pages\ListBotAgents;
use App\Models\BotAgent;
use App\Models\SocialAccount;
use App\Services\Workspaces\WorkspaceContext;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BotAgentResource extends Resource
{
    protected static ?string $model = BotAgent::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?int $navigationSort = 20;

    protected static function canManageAgents(): bool
    {
        $user = auth()->user();
        $workspaceId = app(WorkspaceContext::class)->id();

        if (! $user || ! $workspaceId) {
            return false;
        }

        $membership = $user->activeWorkspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->first();

        return $membership?->isOwner() || $membership?->hasPermission('accounts.manage') || false;
    }

    public static function canViewAny(): bool
    {
        return static::canManageAgents();
    }

    public static function canCreate(): bool
    {
        return static::canManageAgents();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageAgents();
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
        return 'Cuentas sociales';
    }

    public static function getNavigationLabel(): string
    {
        return 'Agentes WBot';
    }

    public static function getModelLabel(): string
    {
        return 'agente WBot';
    }

    public static function getPluralModelLabel(): string
    {
        return 'agentes WBot';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identidad del agente')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('instance_id')
                        ->label('Identificador de instancia')
                        ->helperText('Debe coincidir exactamente con BOT_INSTANCE_ID en el .env de WBot. Tras guardar, emite el token con php artisan bot-agent:issue-token {workspace_id} {instance_id}; se mostrará una sola vez.')
                        ->required()
                        ->maxLength(191)
                        ->disabledOn('edit'),
                    Select::make('social_account_ids')
                        ->label('Cuentas sociales autorizadas')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => SocialAccount::query()
                            ->orderBy('display_name')
                            ->pluck('display_name', 'id')
                            ->all())
                        ->helperText('El agente solo podrá informar estado y publicar mediante las cuentas seleccionadas.')
                        ->columnSpanFull(),
                    Select::make('is_active')
                        ->label('Estado')
                        ->options([
                            1 => 'Activo',
                            0 => 'Desactivado',
                        ])
                        ->default(1)
                        ->required()
                        ->native(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Agente')->searchable()->sortable(),
                TextColumn::make('instance_id')->label('Instancia')->searchable(),
                TextColumn::make('social_account_ids')
                    ->label('Cuentas autorizadas')
                    ->formatStateUsing(fn (?array $state): string => (string) count($state ?? [])),
                TextColumn::make('is_active')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Activo' : 'Desactivado'),
                TextColumn::make('last_seen_at')->label('Último contacto')->dateTime()->placeholder('Sin conexión'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBotAgents::route('/'),
            'create' => CreateBotAgent::route('/create'),
            'edit' => EditBotAgent::route('/{record}/edit'),
        ];
    }
}
