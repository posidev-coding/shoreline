<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\Project;
use App\Models\ProjectLineItem;
use App\Support\Usd;
use Brick\Math\BigDecimal;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The working draft of what the job costs, before tax. It becomes the QuickBooks estimate in M6.
 *
 * @property Project $ownerRecord
 */
class LineItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'lineItems';

    protected static ?string $title = 'Line items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Textarea::make('description')
                    ->required()
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('quantity')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->default(1)
                    ->required(),
                TextInput::make('unit_price')
                    ->label('Rate')
                    ->numeric()
                    ->prefix('$')
                    ->step(0.0001)
                    ->required(),
                Toggle::make('is_taxable')
                    ->label('Taxable')
                    ->helperText('Parts usually are. QuickBooks works out the tax.')
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->reorderable('sort_order')
            ->paginated(false)
            ->description(fn (): string => 'Subtotal '.Usd::format($this->ownerRecord->subtotal()).' before tax. QuickBooks adds the tax when the estimate goes out.')
            ->columns([
                TextColumn::make('description')
                    ->wrap(),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->formatStateUsing(fn (string $state): string => (string) BigDecimal::of($state)->strippedOfTrailingZeros()),
                TextColumn::make('unit_price')
                    ->label('Rate')
                    ->formatStateUsing(fn (string $state): string => Usd::rate($state)),
                TextColumn::make('amount')
                    ->state(fn (ProjectLineItem $record): string => Usd::format($record->amount()))
                    ->weight('semibold'),
                TextColumn::make('is_taxable')
                    ->label('Tax')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'TAX' : 'NON')
                    ->color(fn (bool $state): string => $state ? 'gray' : 'warning'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add line')
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'sort_order' => (int) $this->ownerRecord->lineItems()->max('sort_order') + 1,
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No line items yet')
            ->emptyStateDescription('Add the parts and labor as you work out the price.');
    }
}
