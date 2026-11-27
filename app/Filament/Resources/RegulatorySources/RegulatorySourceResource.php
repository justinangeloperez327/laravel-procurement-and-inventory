<?php

namespace App\Filament\Resources\RegulatorySources;

use App\Filament\Resources\RegulatorySources\Pages\ManageRegulatorySources;
use App\Models\RegulatorySource;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class RegulatorySourceResource extends Resource
{
    protected static ?string $model = RegulatorySource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Regulatory';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(64)->unique(ignoreRecord: true),
            TextInput::make('reference')->required()->maxLength(128)->placeholder('RA 12009'),
            TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('issuer')->required()->maxLength(128)->placeholder('Congress, GPPB, COA, DBM'),
            TextInput::make('url')->url()->maxLength(2048),
            DatePicker::make('issued_on'),
            DatePicker::make('effective_from'),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('issuer'),
                TextColumn::make('effective_from')->date()->placeholder('—'),
                TextColumn::make('rule_versions_count')->counts('ruleVersions')->label('Rule versions'),
            ])
            ->defaultSort('reference')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRegulatorySources::route('/')];
    }
}
