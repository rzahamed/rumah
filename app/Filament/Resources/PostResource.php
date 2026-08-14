<?php

namespace App\Filament\Resources;

use App\Enums\PostStatus;
use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Filament\Resources\PostResource\Pages\ListPosts;
use App\Filament\Support\LedgeredUpload;
use App\Filament\Support\TranslatableInputs;
use App\Models\Category;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.groups.content');
    }

    public static function getModelLabel(): string
    {
        return __('content.posts.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('content.posts.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->label(__('content.fields.category'))
                    ->options(fn (): array => Category::query()
                        ->get()
                        ->mapWithKeys(fn (Category $category): array => [
                            $category->getKey() => (string) $category->translate('name'),
                        ])
                        ->all())
                    ->nullable(),

                ...TranslatableInputs::text('title', __('content.fields.title')),

                TextInput::make('slug')
                    ->label(__('content.fields.slug'))
                    ->required()
                    ->maxLength(255)
                    ->regex('/^[a-z0-9-]+$/')
                    ->unique(ignoreRecord: true),

                ...TranslatableInputs::textarea('excerpt', __('content.fields.excerpt'), required: false, rows: 3),

                // Bodies are authored in Markdown and rendered by
                // App\Support\ArticleBody, which escapes any HTML in the
                // stored value rather than interpreting it. The field type
                // is unchanged — only the authoring hint is new.
                ...collect(TranslatableInputs::textarea('body', __('content.fields.body')))
                    ->map(fn (Textarea $field): Textarea => $field->helperText(__('content.hints.body_markdown')))
                    ->all(),

                FileUpload::make('featured_image_path')
                    ->label(__('content.fields.featured_image'))
                    // Env-driven disk selection. CURRENT PHASE: 'public' in
                    // both local and production (VM storage, served through
                    // the public/storage symlink). A GCS disk is a deferred
                    // future migration, switchable via BLOG_FEATURED_DISK
                    // with no code change.
                    ->disk(config('platform.blog_featured_disk'))
                    ->directory(config('platform.blog_featured_dir'))
                    ->visibility('public')
                    ->image()
                    // Raster web formats only — SVG is scriptable and is
                    // deliberately excluded from public asset uploads.
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(4096)
                    ->nullable()
                    ->saveUploadedFileUsing(LedgeredUpload::saveUsing()),

                Select::make('status')
                    ->label(__('content.fields.status'))
                    ->options([
                        PostStatus::Draft->value => PostStatus::Draft->getLabel(),
                        PostStatus::Published->value => PostStatus::Published->getLabel(),
                    ])
                    ->default(PostStatus::Draft->value)
                    ->required(),

                DateTimePicker::make('published_at')
                    ->label(__('content.fields.published_at'))
                    // A "published" post without a publish moment would be
                    // invisible to the public scope — forbid that state.
                    ->requiredIf('status', PostStatus::Published->value)
                    ->nullable()
                    ->seconds(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('content.fields.title'))
                    ->state(fn (Post $record): ?string => $record->translate('title'))
                    ->searchable(query: fn ($query, string $search) => $query->where('slug', 'ilike', "%{$search}%")),

                TextColumn::make('category.name')
                    ->label(__('content.fields.category'))
                    ->state(fn (Post $record): ?string => $record->category?->translate('name'))
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label(__('content.fields.status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('published_at')
                    ->label(__('content.fields.published_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label(__('content.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('content.fields.status'))
                    ->options([
                        PostStatus::Draft->value => PostStatus::Draft->getLabel(),
                        PostStatus::Published->value => PostStatus::Published->getLabel(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}
