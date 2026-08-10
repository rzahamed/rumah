<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['question', 'answer', 'sort_order', 'is_visible'])]
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory, HasTranslations;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question' => 'array',
            'answer' => 'array',
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    /**
     * Publicly visible FAQs in deterministic order: sort_order, then id as
     * the stable tiebreak. Unlike TeamMember::visible(), ordering is baked
     * into this scope — every public call site needs it and the tiebreak
     * must never be forgotten.
     *
     * @param  Builder<Faq>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
