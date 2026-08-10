<?php

namespace App\Http\Controllers\InternalApi;

use App\Enums\SupportTicketStatus;
use App\Http\Requests\Support\StoreSupportTicketRequest;
use App\Models\ActivityLog;
use App\Models\SupportTicket;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SupportTicketController extends Controller
{
    public function store(StoreSupportTicketRequest $request, Workspace $workspace): JsonResponse|RedirectResponse
    {
        $ticket = DB::transaction(function () use ($request, $workspace): SupportTicket {
            $ticket = $workspace->supportTickets()->create([
                'requester_id' => $request->user()->id,
                'subject' => $request->string('subject')->toString(),
                'body' => $request->string('body')->toString(),
                'status' => SupportTicketStatus::OPEN,
            ]);
            ActivityLog::record($workspace, $ticket, 'support.ticket_created', $request->user(), ipAddress: $request->ip());

            return $ticket;
        });

        return $this->success($request, ['public_id' => $ticket->public_id, 'status' => $ticket->status->value], 'Support request submitted.', route('help'), 201);
    }
}
