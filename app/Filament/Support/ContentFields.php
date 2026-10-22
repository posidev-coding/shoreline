<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * Form fields and actions shared by the website content resources.
 */
final class ContentFields
{
    /**
     * Only the formatting the public pages style (prose-site) and their sanitizer keep.
     */
    public static function richText(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->toolbarButtons([
                ['bold', 'italic', 'link'],
                ['h2', 'h3'],
                ['bulletList', 'orderedList'],
                ['undo', 'redo'],
            ])
            ->columnSpanFull();
    }

    /**
     * A name field that fills in the slug, until someone edits the slug by hand.
     */
    public static function name(string $name = 'name', string $label = 'Name'): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->required()
            ->maxLength(255)
            ->live(onBlur: true)
            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                if (($get('slug') ?? '') !== Str::slug((string) $old)) {
                    return;
                }

                $set('slug', Str::slug((string) $state));
            });
    }

    public static function slug(): TextInput
    {
        return TextInput::make('slug')
            ->required()
            ->maxLength(255)
            ->alphaDash()
            ->unique(ignoreRecord: true)
            ->helperText('Part of the page’s web address. Changing it later breaks links people have saved.');
    }

    public static function seoSection(): Section
    {
        return Section::make('Search engines')
            ->description('Optional. Left empty, the page uses its name and summary.')
            ->collapsed()
            ->schema([
                TextInput::make('seo_title')
                    ->label('Title')
                    ->maxLength(60)
                    ->helperText('Shown as the link in search results. Up to 60 characters.'),
                TextInput::make('seo_description')
                    ->label('Description')
                    ->maxLength(155)
                    ->helperText('Shown under the link in search results. Up to 155 characters.'),
            ]);
    }

    /**
     * @param  Closure  $url  Receives the table's record and returns its public URL.
     */
    public static function viewOnSite(Closure $url): Action
    {
        return Action::make('viewOnSite')
            ->label('View on site')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->url($url)
            ->openUrlInNewTab();
    }
}
