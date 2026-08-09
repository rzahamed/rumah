<?php

namespace App\Http\Controllers;

use App\Actions\AcceptInvitation;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use SensitiveParameter;

/**
 * Thin public (pre-auth) endpoints for invitation acceptance on the admin
 * host. Everything security-relevant — the indistinguishable 404 for any
 * invalid token, row locking, post-lock revalidation, password rules —
 * lives in AcceptInvitation and is neither duplicated nor caught here.
 * The post-acceptance redirect resolves the admin panel explicitly by its
 * ID, never relying on a current panel existing on this route. Route-layer
 * protections (admin-domain binding, throttle:invitation, token regex,
 * CSRF, noindex) are applied where the routes are declared.
 */
class InvitationController extends Controller
{
    public function show(#[SensitiveParameter] string $token, AcceptInvitation $acceptInvitation): View
    {
        $user = $acceptInvitation->findInvitedUser($token);

        return view('invitation.accept', [
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function store(Request $request, #[SensitiveParameter] string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $acceptInvitation->accept(
            $token,
            $request->only(['password', 'password_confirmation']),
        );

        Notification::make()
            ->title(__('invitations.accepted'))
            ->success()
            ->send();

        return redirect()->to(
            Filament::getPanel('admin')->getLoginUrl(),
        );
    }
}
