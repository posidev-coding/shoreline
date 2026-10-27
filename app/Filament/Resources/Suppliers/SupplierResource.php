<?php

namespace App\Filament\Resources\Suppliers;

use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Filament\Support\ContactFields;
use App\Models\Supplier;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Where parts come from. Small enough to manage in modals on one page.
 */
class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Parts';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('account_number')
                    ->label('Our account number')
                    ->maxLength(255),
                TextInput::make('rep_name')
                    ->label('Sales rep')
                    ->maxLength(255),
                ContactFields::phone('rep_phone', 'Rep’s phone'),
                TextInput::make('rep_email')
                    ->label('Rep’s email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('website')
                    ->url()
                    ->maxLength(255),
                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->withCount('parts'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Supplier $record): ?string => $record->account_number ? "Account {$record->account_number}" : null),
                TextColumn::make('rep_name')
                    ->label('Rep')
                    ->description(fn (Supplier $record): ?string => ContactFields::national($record->rep_phone))
                    ->placeholder('None'),
                TextColumn::make('parts_count')
                    ->label('Parts')
                    ->numeric(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Its parts and orders stay, without a supplier.'),
            ])
            ->emptyStateHeading('No suppliers yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSuppliers::route('/'),
        ];
    }
}
