<?php

namespace App\Filament\Resources\SocialAccounts;

use App\Filament\Resources\Concerns\AuthorizesWorkspaceResources;
use App\Filament\Resources\SocialAccounts\Pages\ListSocialAccounts;
use App\Models\SocialAccount;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SocialAccountResource extends Resource
{
    use AuthorizesWorkspaceResources;

    protected static ?string $model = SocialAccount::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-share';

    protected static ?int $navigationSort = 10;

    protected static function workspacePermissionPrefix(): string
    {
        return 'accounts';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Cuentas sociales';
    }

    public static function getNavigationLabel(): string
    {
        return 'Cuentas';
    }

    public static function getModelLabel(): string
    {
        return 'cuenta social';
    }

    public static function getPluralModelLabel(): string
    {
        return 'cuentas sociales';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $membership = static::currentWorkspaceMembership();

        if (! $membership || $membership->isOwner() || $membership->hasPermission('accounts.manage')) {
            return $query;
        }

        return $query->whereIn(
            'id',
            $membership->socialAccountPermissions()
                ->whereJsonContains('permissions', 'view')
                ->select('social_account_id'),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identidad de la cuenta')
                ->columns(2)
                ->schema([
                    Select::make('platform')
                        ->label('Plataforma')
                        ->disabledOn('edit')
                        ->options([
                            SocialAccount::PLATFORM_WHATSAPP => 'WhatsApp',
                            SocialAccount::PLATFORM_FACEBOOK => 'Facebook',
                        ])
                        ->required()
                        ->native(false),
                    Select::make('account_type')
                        ->label('Tipo de cuenta')
                        ->disabledOn('edit')
                        ->options([
                            SocialAccount::TYPE_WHATSAPP => 'Cuenta WhatsApp',
                            SocialAccount::TYPE_FACEBOOK_PROFILE => 'Perfil de Facebook',
                            SocialAccount::TYPE_FACEBOOK_PAGE => 'Página de Facebook',
                        ])
                        ->required()
                        ->native(false),
                    TextInput::make('display_name')
                        ->label('Nombre visible')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('external_account_id')
                        ->label('ID externo')
                        ->maxLength(191),
                    TextInput::make('username')
                        ->label('Usuario')
                        ->maxLength(191),
                    Select::make('parent_account_id')
                        ->label('Perfil de Facebook asociado')
                        ->disabledOn('edit')
                        ->options(fn (): array => SocialAccount::query()
                            ->where('platform', SocialAccount::PLATFORM_FACEBOOK)
                            ->where('account_type', SocialAccount::TYPE_FACEBOOK_PROFILE)
                            ->orderBy('display_name')
                            ->pluck('display_name', 'id')
                            ->all())
                        ->searchable()
                        ->visible(fn (Get $get): bool => $get('account_type') === SocialAccount::TYPE_FACEBOOK_PAGE)
                        ->required(fn (callable $get): bool => $get('account_type') === SocialAccount::TYPE_FACEBOOK_PAGE),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')->label('Cuenta')->searchable()->sortable(),
                TextColumn::make('platform')->label('Plataforma')->badge()->formatStateUsing(fn (string $state): string => $state === 'whatsapp' ? 'WhatsApp' : 'Facebook'),
                TextColumn::make('account_type')->label('Tipo')->formatStateUsing(fn (string $state): string => match ($state) {
                    SocialAccount::TYPE_WHATSAPP => 'Cuenta WhatsApp',
                    SocialAccount::TYPE_FACEBOOK_PROFILE => 'Perfil de Facebook',
                    default => 'Página de Facebook',
                }),
                TextColumn::make('external_account_id')->label('ID externo')->toggleable(),
                TextColumn::make('status')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'connected' => 'Conectada',
                    'disconnected' => 'Desconectada',
                    'error' => 'Error',
                    default => 'Pendiente',
                }),
                TextColumn::make('connections_count')->counts('connections')->label('Conexiones'),
            ])
            ->actions([
                Action::make('connectWhatsApp')
                    ->label('Conectar WhatsApp')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn (SocialAccount $record): string => route('social-accounts.whatsapp.qr', $record))
                    ->visible(fn (SocialAccount $record): bool => $record->account_type === SocialAccount::TYPE_WHATSAPP),
                Action::make('connectMeta')
                    ->label('Conectar con Meta')
                    ->icon('heroicon-o-link')
                    ->url(fn (SocialAccount $record): string => route('social-accounts.meta.connect', $record))
                    ->visible(fn (SocialAccount $record): bool => $record->account_type === SocialAccount::TYPE_FACEBOOK_PROFILE),
                EditAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocialAccounts::route('/'),
        ];
    }
}
