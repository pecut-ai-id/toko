<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\AuditLogRequest;
use App\Models\AuditLog;
use App\Support\CurrentBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/**
 * Riwayat aktivitas (jejak audit).
 *
 * Baca-saja: tidak ada endpoint untuk menambah, mengubah, atau menghapus
 * barisnya. Barisnya hanya lahir dari `App\Services\AuditLogger` di dalam
 * operasi yang dicatat.
 */
class AuditLogController extends Controller
{
    public function index(AuditLogRequest $request, CurrentBusiness $current): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $business = $current->require();
        $period = $request->period();
        $action = $request->auditAction();
        $userId = $request->userId();

        $logs = AuditLog::query()
            ->forBusiness($business)
            ->with(['user', 'subject'])
            ->whereBetween('created_at', [$period->from->startOfDay(), $period->to->endOfDay()])
            ->when($action !== null, fn (Builder $query) => $query->where('action', $action))
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('audit-logs.index', [
            'logs' => $logs,
            'period' => $period,
            'actionGroups' => AuditAction::grouped(),
            'selectedAction' => $action,
            'selectedUserId' => $userId,
            'users' => $business->users()->orderBy('name')->get(),
        ]);
    }
}
