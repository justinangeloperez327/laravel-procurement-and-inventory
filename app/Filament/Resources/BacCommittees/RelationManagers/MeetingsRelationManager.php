<?php

namespace App\Filament\Resources\BacCommittees\RelationManagers;

use App\Domain\Bac\BacComposition;
use App\Domain\Bac\BacMeetings;
use App\Filament\Concerns\ReportsDomainViolations;
use App\Models\BacCommittee;
use App\Models\Meeting;
use App\Models\ProcurementProject;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class MeetingsRelationManager extends RelationManager
{
    use ReportsDomainViolations;

    public const TYPES = ['regular' => 'Regular', 'special' => 'Special', 'pre_procurement' => 'Pre-procurement conference', 'bid_opening' => 'Bid opening', 'evaluation' => 'Evaluation'];

    public const RESOLUTION_TYPES = [
        'recommend_mode' => 'Recommend procurement mode',
        'recommend_award' => 'Recommend award',
        'declare_failure' => 'Declare failure of procurement',
        'post_qualification' => 'Post-qualification result',
        'other' => 'Other',
    ];

    protected static string $relationship = 'meetings';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var BacCommittee $committee */
        $committee = $this->getOwnerRecord();
        $user = fn () => Filament::auth()->user();
        $projects = fn () => ProcurementProject::where('agency_id', $committee->agency_id)->orderByDesc('id')->limit(200)->get()
            ->mapWithKeys(fn ($p) => [$p->id => "{$p->reference_no} — {$p->title}"]);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('procurementProject')->withCount(['attendees as present_count' => fn ($q) => $q->where('present', true)]))
            ->columns([
                TextColumn::make('scheduled_at')->dateTime()->sortable(),
                TextColumn::make('meeting_type')->label('Type')->formatStateUsing(fn (string $state) => self::TYPES[$state] ?? $state),
                TextColumn::make('procurementProject.reference_no')->label('Project')->placeholder('—'),
                TextColumn::make('held_at')->dateTime()->placeholder('Not yet held'),
                TextColumn::make('present_count')->label('Present'),
                IconColumn::make('quorum_met')->label('Quorum')->boolean(),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->headerActions([
                Action::make('schedule')
                    ->label('Schedule meeting')
                    ->visible(fn () => $user()?->hasPermission('bac.meeting') ?? false)
                    ->schema([
                        Select::make('type')->options(self::TYPES)->required(),
                        DateTimePicker::make('scheduled_at')->required(),
                        Select::make('project_id')->label('Procurement project')->searchable()->options($projects),
                        TextInput::make('venue')->maxLength(255),
                    ])
                    ->action(fn (array $data, Action $action) => static::guarded($action, fn () => app(BacMeetings::class)->schedule(
                        $user(), $committee, $data['type'], $data['scheduled_at'],
                        filled($data['project_id'] ?? null) ? ProcurementProject::find($data['project_id']) : null, $data['venue'] ?? null), 'Meeting scheduled')),
            ])
            ->recordActions([
                Action::make('attendance')
                    ->label('Record attendance')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->visible(fn (Meeting $record) => $record->held_at === null && ($user()?->hasPermission('bac.meeting') ?? false))
                    ->schema(fn (Meeting $record) => [
                        DateTimePicker::make('held_at')->required()->default($record->scheduled_at),
                        CheckboxList::make('present')->label('Present')
                            ->options(fn () => $committee->members()->with('user')->get()
                                ->filter(fn ($m) => $m->ends_on === null || $m->ends_on->gte(Carbon::parse($record->scheduled_at)->startOfDay()))
                                ->mapWithKeys(fn ($m) => [$m->user_id => $m->user->name.' ('.BacComposition::MEMBER_ROLES[$m->member_role]['label'].')'])
                                ->union($committee->twgMembers()->with('user')->get()->mapWithKeys(fn ($t) => [$t->user_id => $t->user->name.' (TWG)'])))
                            ->helperText('Quorum: a majority of voting members, with the chair or vice-chair presiding.'),
                        TagsInput::make('observers')->placeholder('Add observer name'),
                    ])
                    ->action(fn (Meeting $record, array $data, Action $action) => static::guarded($action, fn () => app(BacMeetings::class)->recordHeld(
                        $user(), $record, array_map('intval', $data['present'] ?? []), $data['observers'] ?? [], $data['held_at']), 'Attendance recorded')),
                Action::make('resolution')
                    ->label('Adopt resolution')
                    ->icon('heroicon-o-document-check')->color('success')
                    ->visible(fn (Meeting $record) => $record->quorum_met === true && ($user()?->hasPermission('bac.recommend') ?? false))
                    ->schema(fn (Meeting $record) => [
                        Select::make('type')->options(self::RESOLUTION_TYPES)->required(),
                        TextInput::make('title')->required()->maxLength(255),
                        Select::make('project_id')->label('Procurement project')->searchable()->options($projects)->default($record->procurement_project_id),
                        Textarea::make('body')->label('Resolution text')->rows(6),
                    ])
                    ->action(fn (Meeting $record, array $data, Action $action) => static::guarded($action, fn () => app(BacMeetings::class)->adoptResolution(
                        $user(), $record, $data['type'], $data['title'], $data['body'] ?? null,
                        filled($data['project_id'] ?? null) ? ProcurementProject::find($data['project_id']) : null), 'Resolution adopted')),
            ]);
    }
}
