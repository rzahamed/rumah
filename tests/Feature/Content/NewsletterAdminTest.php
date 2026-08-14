<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\NewsletterSubscriberResource;
use App\Filament\Resources\NewsletterSubscriberResource\Pages\ListNewsletterSubscribers;
use App\Models\NewsletterSubscriber;
use App\Support\NewsletterCsvExport;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Feature\Auth\AdminTestCase;

/**
 * The protected admin surface for subscriber data: list + search + export +
 * single delete only, and a spreadsheet-safe streamed export.
 */
class NewsletterAdminTest extends AdminTestCase
{
    /** Capture a streamed response's body without sending it to the client. */
    private function streamedBody(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    public function test_the_resource_surface_is_structurally_read_only(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        // Enforced in the resource itself, not only in the policy: an active
        // super admin bypasses policies via Gate::before.
        $this->assertFalse(NewsletterSubscriberResource::canCreate());
        $this->assertFalse(NewsletterSubscriberResource::canEdit($subscriber));
        $this->assertFalse(NewsletterSubscriberResource::canDeleteAny());

        // No create/edit/view routes are registered at all.
        $this->assertSame(['index'], array_keys(NewsletterSubscriberResource::getPages()));
    }

    public function test_an_admin_can_list_subscribers_but_an_editor_cannot(): void
    {
        NewsletterSubscriber::factory()->create(['email' => 'listed@example.com']);

        $this->actingAs($this->admin());
        Livewire::test(ListNewsletterSubscribers::class)
            ->assertOk()
            ->assertSee('listed@example.com');

        $this->assertFalse($this->editor()->can('viewAny', NewsletterSubscriber::class));
    }

    public function test_the_list_can_be_searched_by_email(): void
    {
        $wanted = NewsletterSubscriber::factory()->create(['email' => 'wanted@example.com']);
        $other = NewsletterSubscriber::factory()->create(['email' => 'someone-else@example.org']);

        $this->actingAs($this->admin());

        Livewire::test(ListNewsletterSubscribers::class)
            ->assertCanSeeTableRecords([$wanted, $other])
            ->searchTable('wanted@example.com')
            ->assertCanSeeTableRecords([$wanted])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_export_is_gated_on_its_own_ability(): void
    {
        $admin = $this->admin();
        $editor = $this->editor();

        $this->assertTrue($admin->can('export', NewsletterSubscriber::class));
        $this->assertFalse($editor->can('export', NewsletterSubscriber::class));
        // Reading the list and carrying it off are separate privileges.
        $this->assertTrue($admin->can('viewAny', NewsletterSubscriber::class));
    }

    public function test_the_export_streams_a_header_row_and_every_subscriber(): void
    {
        NewsletterSubscriber::factory()->create([
            'email' => 'first@example.com',
            'locale' => 'en',
        ]);
        NewsletterSubscriber::factory()->create([
            'email' => 'second@example.com',
            'locale' => 'ar',
        ]);

        $response = NewsletterCsvExport::stream();

        $this->assertInstanceOf(StreamedResponse::class, $response);

        $body = $this->streamedBody($response);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'a UTF-8 BOM keeps Excel from mangling Arabic');
        $this->assertStringContainsString('email,locale,consented_at,created_at', $body);
        $this->assertStringContainsString('first@example.com', $body);
        $this->assertStringContainsString('second@example.com', $body);
        $this->assertStringContainsString('ar', $body);
    }

    public function test_the_export_neutralizes_every_spreadsheet_injection_prefix(): void
    {
        // "+" and "=" are legal in an email local part, so this is a
        // realistic attack path into an administrator's spreadsheet. The
        // control-character cases are defensive: the factory writes them
        // directly, standing in for legacy or imported rows that never
        // passed the public endpoint's validation.
        $payloads = [
            '=cmd|calc!a1@example.com',
            '+attack@example.com',
            '-minus@example.com',
            '@at@example.com',
            "\ttab@example.com",
            "\rcarriage@example.com",
            "\nnewline@example.com",
        ];

        foreach ($payloads as $email) {
            NewsletterSubscriber::factory()->create(['email' => $email]);
        }

        NewsletterSubscriber::factory()->create(['email' => 'safe@example.com']);

        $body = $this->streamedBody(NewsletterCsvExport::stream());

        foreach ($payloads as $email) {
            $this->assertStringContainsString("'".$email, $body, 'unescaped: '.json_encode($email));
        }

        // An ordinary address is left exactly as stored.
        $this->assertStringNotContainsString("'safe@example.com", $body);
        $this->assertStringContainsString('safe@example.com', $body);
    }

    public function test_the_export_carries_no_requester_metadata(): void
    {
        NewsletterSubscriber::factory()->create(['email' => 'private@example.com']);

        $body = $this->streamedBody(NewsletterCsvExport::stream());
        $header = strtok($body, "\n");

        $this->assertStringNotContainsStringIgnoringCase('ip', (string) $header);
        $this->assertStringNotContainsStringIgnoringCase('agent', (string) $header);
    }

    public function test_the_filename_is_dated(): void
    {
        $this->assertSame(
            'newsletter-subscribers-'.now()->format('Y-m-d').'.csv',
            NewsletterCsvExport::filename(),
        );
    }

    public function test_a_subscriber_can_be_deleted_individually_but_never_in_bulk(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();
        $survivor = NewsletterSubscriber::factory()->create();

        $this->actingAs($this->admin());

        Livewire::test(ListNewsletterSubscribers::class)
            ->callTableAction(DeleteAction::class, $subscriber)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('newsletter_subscribers', ['id' => $subscriber->getKey()]);
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $survivor->getKey()]);

        // Bulk destruction of collected data stays denied outright.
        $this->assertFalse(NewsletterSubscriberResource::canDeleteAny());
        $this->assertFalse($this->admin()->can('deleteAny', NewsletterSubscriber::class));
        $this->assertFalse($this->admin()->can('update', $survivor));
        $this->assertFalse($this->admin()->can('create', NewsletterSubscriber::class));
    }
}
