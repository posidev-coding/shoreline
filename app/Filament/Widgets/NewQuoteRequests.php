<?php

namespace App\Filament\Widgets;

use App\Enums\LeadStatus;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Quote requests nobody has answered yet, newest first: the sooner a request gets a call, the
 * likelier the job. The inbox has any older ones.
 */
class NewQuoteRequests extends TableWidget
{
    /**
     * Rows shown; the inbox has the rest.
     */
    public const int LIMIT = 5;

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Lead::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Waiting for a reply')
            ->query(Lead::query()->with('service')->where('status', LeadStatus::New)->latest()->limit(self::LIMIT))
            ->paginated(false)
            ->recordUrl(fn (Lead $record): string => LeadResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->weight('semibold')
                    ->wrap()
                    ->description(fn (Lead $record): string => collect([$record->service->name ?? 'Something else', $record->town])->filter()->implode(' · ')),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->dateTimeTooltip(),
            ])
            ->headerActions([
                Action::make('inbox')
                    ->label('All requests')
                    ->link()
                    ->url(LeadResource::getUrl()),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->emptyStateHeading('All caught up')
            ->emptyStateDescription(null);
    }
}
