<?php

namespace App\Filament\Resources\ApprovalWorkflows\RelationManagers;

use App\Domain\Identity\RoleCatalog;
use App\Models\ApprovalWorkflow;
use App\Policies\ApprovalWorkflowPolicy;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    public function isReadOnly(): bool
    {
        /** @var ApprovalWorkflow $workflow */
        $workflow = $this->getOwnerRecord();

        return ApprovalWorkflowPolicy::inUse($workflow) || ! (auth()->user()?->can('update', $workflow) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sequence')->integer()->required()->minValue(1)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('approval_workflow_id', $this->getOwnerRecord()->id)),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('permission_code')->label('Required permission')->required()->searchable()
                ->options(collect(RoleCatalog::PERMISSIONS)->mapWithKeys(fn ($desc, $code) => [$code => "{$code} — {$desc}"])),
            Toggle::make('department_scoped')->default(true)
                ->helperText('If on, the approver must hold the permission for the record\'s department.'),
            Toggle::make('is_required')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sequence')->sortable(),
                TextColumn::make('name'),
                TextColumn::make('permission_code')->badge(),
                IconColumn::make('department_scoped')->boolean(),
                IconColumn::make('is_required')->boolean(),
            ])
            ->defaultSort('sequence')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
