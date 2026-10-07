<?php

namespace App\Filament\Resources\Publicaciones;

use App\Filament\Resources\Publicaciones\Pages\CreatePublicacion;
use App\Filament\Resources\Publicaciones\Pages\EditPublicacion;
use App\Filament\Resources\Publicaciones\Pages\ListPublicaciones;
use App\Filament\Resources\Publicaciones\Schemas\PublicacionForm;
use App\Filament\Resources\Publicaciones\Tables\PublicacionesTable;
use App\Models\Publication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PublicacionResource extends Resource
{
    protected static ?string $model = Publication::class;

    protected static ?string $modelLabel = 'publicación';

    protected static ?string $pluralModelLabel = 'publicaciones';

    protected static ?string $navigationLabel = 'Publicaciones';

    protected static ?string $slug = 'publicaciones';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';

    public static function form(Schema $schema): Schema
    {
        return PublicacionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublicacionesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPublicaciones::route('/'),
            'create' => CreatePublicacion::route('/create'),
            'edit' => EditPublicacion::route('/{record}/edit'),
        ];
    }
}
