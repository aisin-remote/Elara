<?php

namespace App\Http\Controllers\App;

use App\Actions\Workspace\AcceptInvitation;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function show(Request $request, string $token, AcceptInvitation $accept): View
    {
        $invitation = $accept->find($token);

        if (! hash_equals(Str::lower($request->user()->email), Str::lower($invitation->email))) {
            throw new AuthorizationException('This invitation belongs to another email address.');
        }

        return view('app.invitations.show', compact('invitation', 'token'));
    }
}
