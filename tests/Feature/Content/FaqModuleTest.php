<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\FaqResource\Pages\CreateFaq;
use App\Filament\Resources\FaqResource\Pages\EditFaq;
use App\Models\Faq;
use Livewire\Livewire;
use Tests\Feature\Auth\AdminTestCase;

/**
 * The FAQ content module: editor-level CRUD through the resource,
 * validation, and the public visible() scope's deterministic ordering
 * contract. Homepage rendering is asserted with the Home checkpoint.
 */
class FaqModuleTest extends AdminTestCase
{
    public function test_editor_can_create_a_faq(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreateFaq::class)
            ->fillForm([
                'question' => ['en' => 'What services do you provide?', 'ar' => 'ما الخدمات التي تقدمونها؟'],
                'answer' => ['en' => 'We offer a broad range of services.', 'ar' => 'نقدم مجموعة واسعة من الخدمات.'],
                'sort_order' => 3,
                'is_visible' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $faq = Faq::query()->sole();

        $this->assertSame('What services do you provide?', $faq->translate('question', 'en'));
        $this->assertSame('ما الخدمات التي تقدمونها؟', $faq->translate('question', 'ar'));
        $this->assertSame('We offer a broad range of services.', $faq->translate('answer', 'en'));
        $this->assertSame('نقدم مجموعة واسعة من الخدمات.', $faq->translate('answer', 'ar'));
        $this->assertSame(3, $faq->sort_order);
        $this->assertTrue($faq->is_visible);
    }

    public function test_editor_can_update_and_delete_a_faq(): void
    {
        $faq = Faq::factory()->create();

        $this->actingAs($this->editor());

        Livewire::test(EditFaq::class, ['record' => $faq->getRouteKey()])
            ->fillForm(['question' => ['en' => 'Updated question?']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated question?', $faq->fresh()->translate('question', 'en'));

        Livewire::test(EditFaq::class, ['record' => $faq->getRouteKey()])
            ->callAction('delete');

        $this->assertNull(Faq::query()->find($faq->getKey()));
    }

    public function test_default_locale_question_is_required(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreateFaq::class)
            ->fillForm([
                'question' => ['ar' => 'بدون إنجليزية'],
                'answer' => ['en' => 'Answer.'],
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['question.en']);

        $this->assertSame(0, Faq::query()->count());
    }

    public function test_visible_scope_filters_and_orders_deterministically(): void
    {
        // Created deliberately out of display order, with duplicate
        // sort_order values so the id tiebreak is exercised.
        $late = Faq::factory()->create(['sort_order' => 2]);
        Faq::factory()->hidden()->create(['sort_order' => 0]);
        $firstOfPair = Faq::factory()->create(['sort_order' => 1]);
        $secondOfPair = Faq::factory()->create(['sort_order' => 1]);
        $first = Faq::factory()->create(['sort_order' => 0]);

        $this->assertSame(
            [$first->getKey(), $firstOfPair->getKey(), $secondOfPair->getKey(), $late->getKey()],
            Faq::visible()->pluck('id')->all(),
        );
    }
}
