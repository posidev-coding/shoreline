<?php

namespace App\Filament\Pages;

use App\Settings\SiteSettings;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Propaganistas\LaravelPhone\PhoneNumber;
use UnitEnum;

/**
 * Business details and hero copy. The name, address, and phone should match the Google Business Profile exactly.
 */
class ManageSite extends SettingsPage
{
    protected static string $settings = SiteSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Business details';

    protected static ?string $title = 'Business details';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Business')
                    ->description('Keep the name, address, and phone exactly as they appear on your Google Business Profile.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('business_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->rule('phone:US')
                            ->helperText('Any format. It’s saved as +1 and ten digits.'),
                        TextInput::make('email')
                            ->email()
                            ->required(),
                        TextInput::make('street_address')
                            ->helperText('Leave empty to show only the town, as many service businesses do.'),
                        Grid::make(3)
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('city')->required(),
                                TextInput::make('region')->label('State')->required()->length(2),
                                TextInput::make('postal_code')->label('ZIP')->required(),
                            ]),
                    ]),
                Section::make('Hours')
                    ->description('Leave empty and the site says to call for a time.')
                    ->schema([
                        Repeater::make('hours')
                            ->hiddenLabel()
                            ->defaultItems(0)
                            ->addActionLabel('Add hours')
                            ->columns(3)
                            ->schema([
                                CheckboxList::make('days')
                                    ->options(array_combine(
                                        ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                                        ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                                    ))
                                    ->columns(7)
                                    ->required()
                                    ->columnSpanFull(),
                                TimePicker::make('opens')->seconds(false)->format('H:i')->required(),
                                TimePicker::make('closes')->seconds(false)->format('H:i')->required()->after('opens'),
                            ]),
                    ]),
                Section::make('Licensing and links')
                    ->columns(2)
                    ->schema([
                        TextInput::make('license_info')
                            ->label('License')
                            ->placeholder('e.g. NC General Contractor License #12345')
                            ->helperText('Shown in the footer and on the About page.'),
                        TextInput::make('insurance_info')
                            ->label('Insurance')
                            ->placeholder('e.g. Fully insured'),
                        TextInput::make('google_review_url')
                            ->label('Google review link')
                            ->url()
                            ->helperText('The “Ask for reviews” link from your Google Business Profile.'),
                        TextInput::make('google_business_profile_url')
                            ->label('Google Business Profile link')
                            ->url(),
                        Repeater::make('social_links')
                            ->label('Social profiles')
                            ->defaultItems(0)
                            ->addActionLabel('Add a profile')
                            ->columns(2)
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('label')->required()->placeholder('Facebook'),
                                TextInput::make('url')->required()->url(),
                            ]),
                    ]),
                Section::make('Home page')
                    ->schema([
                        TextInput::make('hero_headline')
                            ->label('Headline')
                            ->required()
                            ->maxLength(60),
                        Textarea::make('hero_subhead')
                            ->label('Subhead')
                            ->required()
                            ->rows(2)
                            ->maxLength(200)
                            ->helperText('Also the home page’s description in search results.'),
                    ]),
            ]);
    }

    /**
     * Shows the stored E.164 number the way the owner would type it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['phone'] = (new PhoneNumber((string) $data['phone'], 'US'))->formatNational();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['phone'] = (new PhoneNumber((string) $data['phone'], 'US'))->formatE164();
        $data['hours'] = array_values($data['hours'] ?? []);
        $data['social_links'] = array_values($data['social_links'] ?? []);

        return $data;
    }
}
