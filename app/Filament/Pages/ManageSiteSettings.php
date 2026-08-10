<?php

namespace App\Filament\Pages;

use App\Models\SiteSettings;
use App\Rules\ValidCalUrl;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Super-admin-only site settings. Authorization is enforced server-side in
 * THREE places — canAccess() (navigation + routing), mount(), and save() —
 * so a forged Livewire request can never reach a write. The snippet
 * textareas bind values as escaped plain text: nothing typed here is ever
 * rendered as markup inside the panel.
 */
class ManageSiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracket;

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.manage-site-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.groups.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('settings.navigation_label');
    }

    public function getTitle(): string
    {
        return __('settings.title');
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-site-settings');
    }

    public function mount(): void
    {
        abort_unless(Gate::allows('manage-site-settings'), 403);

        $settings = SiteSettings::instance();

        $this->form->fill([
            'custom_head_start' => $settings->custom_head_start,
            'custom_head_end' => $settings->custom_head_end,
            'custom_body_start' => $settings->custom_body_start,
            'custom_body_end' => $settings->custom_body_end,
            'cal_booking_url' => $settings->cal_booking_url,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('settings.custom_code.section'))
                    ->description(__('settings.custom_code.warning'))
                    ->schema([
                        $this->snippetTextarea('custom_head_start', __('settings.custom_code.head_start')),
                        $this->snippetTextarea('custom_head_end', __('settings.custom_code.head_end')),
                        $this->snippetTextarea('custom_body_start', __('settings.custom_code.body_start')),
                        $this->snippetTextarea('custom_body_end', __('settings.custom_code.body_end')),
                    ]),
                Section::make(__('settings.cal.section'))
                    ->schema([
                        TextInput::make('cal_booking_url')
                            ->label(__('settings.cal.url'))
                            ->helperText(__('settings.cal.helper'))
                            ->maxLength(255)
                            ->nullable()
                            ->rule(new ValidCalUrl, fn ($state): bool => filled($state)),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(Gate::allows('manage-site-settings'), 403);

        $data = $this->form->getState();

        SiteSettings::instance()->fill($data)->save();

        Notification::make()
            ->success()
            ->title(__('settings.saved'))
            ->send();
    }

    private function snippetTextarea(string $name, string $label): Textarea
    {
        return Textarea::make($name)
            ->label($label)
            ->rows(6)
            ->maxLength(SiteSettings::SNIPPET_MAX_LENGTH)
            ->nullable()
            // Monospace: these are code fields, displayed as escaped plain
            // text only.
            ->extraInputAttributes(['class' => 'font-mono', 'spellcheck' => 'false']);
    }
}
