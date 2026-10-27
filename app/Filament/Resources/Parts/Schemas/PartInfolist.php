<?php

namespace App\Filament\Resources\Parts\Schemas;

use App\Models\Part;
use App\Support\PartSpecs;
use App\Support\Usd;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PartInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(2)
                    ->schema([
                        Section::make('Part')
                            ->columns(3)
                            ->schema([
                                TextEntry::make('category'),
                                TextEntry::make('maker')
                                    ->label('Make and model')
                                    ->state(fn (Part $record): ?string => trim($record->manufacturer.' '.$record->model) ?: null)
                                    ->placeholder('None'),
                                TextEntry::make('sku')
                                    ->label('Part number')
                                    ->copyable()
                                    ->placeholder('None'),
                                TextEntry::make('specs')
                                    ->label('Details')
                                    ->state(fn (Part $record): array => PartSpecs::lines($record->category, $record->specs ?? []))
                                    ->listWithLineBreaks()
                                    ->placeholder('None recorded')
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Buying and selling')
                            ->columns(3)
                            ->schema([
                                TextEntry::make('supplier.name')
                                    ->label('Supplier')
                                    ->placeholder('None'),
                                TextEntry::make('cost')
                                    ->label('Our cost')
                                    ->formatStateUsing(fn (string $state, Part $record): string => Usd::rate($state).' / '.$record->unit->value)
                                    ->placeholder('Not set'),
                                TextEntry::make('price')
                                    ->label('Price')
                                    ->formatStateUsing(fn (string $state, Part $record): string => Usd::rate($state).' / '.$record->unit->value)
                                    ->helperText(fn (Part $record): ?string => self::margin($record))
                                    ->placeholder('Not set'),
                                TextEntry::make('warranty_months')
                                    ->label('Warranty')
                                    ->formatStateUsing(fn (int $state): string => $state % 12 === 0 ? ($state / 12).' '.str('year')->plural($state / 12) : "{$state} months")
                                    ->placeholder('None'),
                                TextEntry::make('is_active')
                                    ->label('Still sold')
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                            ]),
                    ]),
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Stock')
                            ->schema([
                                TextEntry::make('on_hand')
                                    ->label('On hand')
                                    ->state(fn (Part $record): string => $record->quantityLabel($record->on_hand))
                                    ->size('lg')
                                    ->weight('bold')
                                    ->color(fn (Part $record): ?string => $record->isLowStock() ? 'danger' : null)
                                    ->helperText(fn (Part $record): ?string => $record->isLowStock() ? 'Low: at or below the reorder point.' : null),
                                TextEntry::make('reorder_point')
                                    ->label('Reorder at')
                                    ->state(fn (Part $record): ?string => $record->reorder_point === null ? null : $record->quantityLabel($record->reorder_point))
                                    ->placeholder('Not set'),
                            ])
                            ->visible(fn (Part $record): bool => $record->track_stock),
                        Section::make('Stock')
                            ->schema([
                                TextEntry::make('untracked')
                                    ->hiddenLabel()
                                    ->state('Not counted. Turn on "Keep count of stock" with Edit to track it.'),
                            ])
                            ->visible(fn (Part $record): bool => ! $record->track_stock),
                        Section::make('Photo')
                            ->schema([
                                SpatieMediaLibraryImageEntry::make('photo')
                                    ->hiddenLabel()
                                    ->collection('photo')
                                    ->visibility('private'),
                            ])
                            ->visible(fn (Part $record): bool => $record->hasMedia('photo')),
                        Section::make('Spec sheet')
                            ->schema([
                                TextEntry::make('spec_sheet')
                                    ->hiddenLabel()
                                    ->state(fn (Part $record): ?string => $record->getFirstMedia('spec_sheet')?->file_name)
                                    ->url(fn (Part $record): ?string => $record->getFirstMedia('spec_sheet')?->getTemporaryUrl(now()->addMinutes(30)), shouldOpenInNewTab: true)
                                    ->icon('heroicon-o-document-text'),
                            ])
                            ->visible(fn (Part $record): bool => $record->hasMedia('spec_sheet')),
                    ]),
            ]);
    }

    /**
     * "41% margin", from cost and price.
     */
    private static function margin(Part $record): ?string
    {
        if ($record->cost === null || $record->price === null || (float) $record->price <= 0) {
            return null;
        }

        $price = BigDecimal::of($record->price);
        $percent = $price->minus($record->cost)->multipliedBy(100)->dividedBy($price, 0, RoundingMode::HalfUp);

        return "{$percent}% margin";
    }
}
