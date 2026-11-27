<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Read-only, append-only audit trail. */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Access control';

    protected static ?int $navigationSort = 90;

    protected static bool $isGloballySearchable = false;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('event')->badge(),
            TextEntry::make('user.name')->label('Actor')->placeholder('System'),
            TextEntry::make('auditable_type')->label('Record type')->placeholder('—'),
            TextEntry::make('auditable_id')->label('Record ID')->placeholder('—'),
            TextEntry::make('ip_address')->placeholder('—'),
            TextEntry::make('user_agent')->placeholder('—')->columnSpanFull(),
            KeyValueEntry::make('old_values')->state(fn (AuditLog $r) => self::flatten($r->old_values))->columnSpanFull(),
            KeyValueEntry::make('new_values')->state(fn (AuditLog $r) => self::flatten($r->new_values))->columnSpanFull(),
            KeyValueEntry::make('context')->state(fn (AuditLog $r) => self::flatten($r->context))->columnSpanFull(),
        ]);
    }

    /** @return array<string, string> */
    private static function flatten(?array $values): array
    {
        return collect($values ?? [])
            ->map(fn ($v) => is_scalar($v) || $v === null ? (string) json_encode($v) : (string) json_encode($v, JSON_UNESCAPED_SLASHES))
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('event')->badge()->searchable(),
                TextColumn::make('user.name')->label('Actor')->placeholder('System'),
                TextColumn::make('auditable_type')->label('Record')
                    ->formatStateUsing(fn (?string $state, AuditLog $r) => $state ? class_basename($state).' #'.$r->auditable_id : '—'),
                TextColumn::make('ip_address')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')->options(fn () => AuditLog::query()->distinct()->orderBy('event')->pluck('event', 'event')),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }
}
