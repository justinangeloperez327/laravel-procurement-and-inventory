<?php

namespace App\Filament\Resources\BacCommittees\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ResolutionsRelationManager extends RelationManager
{
    protected static string $relationship = 'resolutions';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['procurementProject', 'recordedBy']))
            ->columns([
                TextColumn::make('resolution_no')->label('No.')->sortable(),
                TextColumn::make('title')->wrap(),
                TextColumn::make('resolution_type')->label('Type')->formatStateUsing(fn (string $state) => MeetingsRelationManager::RESOLUTION_TYPES[$state] ?? $state),
                TextColumn::make('procurementProject.reference_no')->label('Project')->placeholder('—'),
                TextColumn::make('adopted_on')->date(),
                TextColumn::make('recordedBy.name')->label('Recorded by'),
            ])
            ->defaultSort('resolution_no', 'desc');
    }
}
