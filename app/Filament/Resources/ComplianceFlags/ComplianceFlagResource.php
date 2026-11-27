<?php

namespace App\Filament\Resources\ComplianceFlags;

use App\Domain\Compliance\ComplianceFlags;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Filament\Concerns\ScopedToUserAgency;
use App\Filament\Resources\ComplianceFlags\Pages\ListComplianceFlags;
use App\Filament\Resources\ComplianceFlags\Pages\ViewComplianceFlag;
use App\Models\ComplianceFlag;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** The compliance review queue: splitting, sanctions, expired documents, conflicts of interest. */
class ComplianceFlagResource extends Resource
{
    use ReportsDomainViolations, ScopedToUserAgency;

    protected static ?string $model = ComplianceFlag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Compliance';

    protected static ?int $navigationSort = 10;

    protected static bool $isGloballySearchable = false;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->whereIn('status', ComplianceFlags::OPEN_STATUSES)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('flag_type')->label('Type')->formatStateUsing(fn (string $state) => static::typeLabel($state)),
            TextEntry::make('severity')->badge()->color(fn (string $state) => static::severityColor($state)),
            TextEntry::make('status')->badge(),
            TextEntry::make('subject')->label('Concerns')->state(fn (ComplianceFlag $record) => static::subjectLabel($record)),
            TextEntry::make('raised_at')->dateTime(),
            TextEntry::make('raisedBy.name')->label('Raised by')->placeholder('System'),
            TextEntry::make('description')->columnSpanFull(),
            TextEntry::make('reviewedBy.name')->label('Reviewed by')->placeholder('—'),
            TextEntry::make('reviewed_at')->dateTime()->placeholder('—'),
            TextEntry::make('review_notes')->columnSpanFull()->placeholder('—'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('subject'))
            ->columns([
                TextColumn::make('raised_at')->dateTime()->sortable(),
                TextColumn::make('severity')->badge()->color(fn (string $state) => static::severityColor($state)),
                TextColumn::make('flag_type')->label('Type')->formatStateUsing(fn (string $state) => static::typeLabel($state)),
                TextColumn::make('subject')->label('Concerns')->state(fn (ComplianceFlag $record) => static::subjectLabel($record))->wrap()
                    ->tooltip(fn (ComplianceFlag $record) => $record->description),
                TextColumn::make('status')->badge()->color(fn (string $state) => in_array($state, ComplianceFlags::OPEN_STATUSES, true) ? 'warning' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['open' => 'Open', 'escalated' => 'Escalated', 'reviewed' => 'Reviewed', 'dismissed' => 'Dismissed'])
                    ->default('open'),
                SelectFilter::make('severity')->options(['blocking' => 'Blocking', 'warning' => 'Warning', 'info' => 'Info']),
            ])
            ->defaultSort('raised_at', 'desc')
            ->recordActions([ViewAction::make(), static::reviewAction()]);
    }

    public static function reviewAction(): Action
    {
        return Action::make('review')
            ->icon('heroicon-o-clipboard-document-check')
            ->authorize(fn (ComplianceFlag $record) => Filament::auth()->user()?->can('review', $record) ?? false)
            ->schema([
                Select::make('decision')->required()->options([
                    'reviewed' => 'Reviewed — resolved',
                    'dismissed' => 'Dismissed — not an issue',
                    'escalated' => 'Escalated — needs higher review',
                ]),
                Textarea::make('notes')->required()->maxLength(5000),
            ])
            ->action(function (ComplianceFlag $record, array $data, Action $action) {
                static::guarded($action, fn () => app(ComplianceFlags::class)->review(Filament::auth()->user(), $record, $data['decision'], $data['notes']), 'Review recorded');
                $record->refresh();
            });
    }

    public static function typeLabel(string $type): string
    {
        [$base, $detail] = array_pad(explode(':', $type, 2), 2, null);

        return ucfirst(str_replace('_', ' ', $base)).($detail ? ' ('.str_replace('_', ' ', $detail).')' : '');
    }

    public static function severityColor(string $severity): string
    {
        return match ($severity) {
            'blocking' => 'danger',
            'warning' => 'warning',
            default => 'info',
        };
    }

    public static function subjectLabel(ComplianceFlag $flag): string
    {
        $subject = $flag->subject;
        if ($subject === null) {
            return class_basename($flag->subject_type)." #{$flag->subject_id}";
        }

        return class_basename($subject).': '.($subject->name ?? $subject->reference_no ?? "#{$subject->getKey()}");
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComplianceFlags::route('/'),
            'view' => ViewComplianceFlag::route('/{record}'),
        ];
    }
}
