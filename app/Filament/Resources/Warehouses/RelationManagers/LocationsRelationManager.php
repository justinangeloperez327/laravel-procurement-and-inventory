<?php

namespace App\Filament\Resources\Warehouses\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'locations';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(32)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('warehouse_id', $this->getOwnerRecord()->id)),
            TextInput::make('name')->maxLength(255),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->placeholder('—'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('code')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()]);
    }
}
