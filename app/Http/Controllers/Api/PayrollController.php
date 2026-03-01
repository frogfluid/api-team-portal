<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\Payroll;
use App\Models\User;
use App\Notifications\PayrollPublished;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PayrollController extends Controller
{
    // ── Employee Payroll ─────────────────────────────────────────────

    /**
     * Get current user's payroll for a given month.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $yearMonth = $request->query('month', Carbon::now()->format('Y-m'));

        $payroll = Payroll::where('user_id', $user->id)
            ->where('year_month', $yearMonth)
            ->whereIn('status', ['published', 'paid'])
            ->first();

        $availableMonths = Payroll::where('user_id', $user->id)
            ->whereIn('status', ['published', 'paid'])
            ->orderByDesc('year_month')
            ->pluck('year_month')
            ->toArray();

        return response()->json([
            'payroll' => $payroll ? $this->transformPayroll($payroll) : null,
            'available_months' => $availableMonths,
            'year_month' => $yearMonth,
        ]);
    }

    // ── Payment Methods ─────────────────────────────────────────────

    public function paymentMethods(Request $request): JsonResponse
    {
        $methods = $request->user()
            ->paymentMethods()
            ->orderByDesc('is_default')
            ->get()
            ->map(fn($pm) => $this->transformPaymentMethod($pm));

        return response()->json($methods);
    }

    public function storePaymentMethod(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate($this->paymentMethodRules());

        $data['user_id'] = $user->id;

        if (!empty($data['is_default'])) {
            $user->paymentMethods()->update(['is_default' => false]);
        }
        if ($user->paymentMethods()->count() === 0) {
            $data['is_default'] = true;
        }

        $method = PaymentMethod::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Payment method added successfully.',
            'payment_method' => $this->transformPaymentMethod($method),
        ], 201);
    }

    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $user = $request->user();
        if ($paymentMethod->user_id !== $user->id) {
            abort(403);
        }

        $data = $request->validate($this->paymentMethodRules());

        if (!empty($data['is_default'])) {
            $user->paymentMethods()->where('id', '!=', $paymentMethod->id)->update(['is_default' => false]);
        }

        $paymentMethod->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Payment method updated successfully.',
            'payment_method' => $this->transformPaymentMethod($paymentMethod->fresh()),
        ]);
    }

    public function destroyPaymentMethod(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $user = $request->user();
        if ($paymentMethod->user_id !== $user->id) {
            abort(403);
        }

        $wasDefault = $paymentMethod->is_default;
        $paymentMethod->delete();

        if ($wasDefault) {
            $first = $user->paymentMethods()->first();
            $first?->update(['is_default' => true]);
        }

        return response()->json(['success' => true, 'message' => 'Payment method deleted successfully.']);
    }

    // ── Admin Payroll Management ────────────────────────────────────

    public function adminIndex(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->canAccessAdmin()) {
            abort(403);
        }

        $yearMonth = $request->query('month', Carbon::now()->format('Y-m'));

        $users = User::where('is_active', true)
            ->where('role', '!=', 'admin')
            ->with(['paymentMethods' => fn($q) => $q->orderByDesc('is_default')])
            ->orderBy('name')
            ->get();

        $payrolls = Payroll::where('year_month', $yearMonth)
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $result = $users->map(function (User $u) use ($yearMonth, $payrolls) {
            $payroll = $payrolls->get($u->id);

            return [
                'id' => $u->id,
                'name' => $u->name,
                'avatar_url' => $u->avatar_url,
                'role' => $u->role->value ?? $u->role,
                'payroll' => $payroll ? $this->transformPayroll($payroll) : null,
                'payment_methods' => $u->paymentMethods->map(fn($pm) => $this->transformPaymentMethod($pm)),
            ];
        });

        return response()->json([
            'users' => $result,
            'year_month' => $yearMonth,
        ]);
    }

    public function adminStore(Request $request, User $user): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->canAccessAdmin()) {
            abort(403);
        }

        $data = $request->validate([
            'year_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'bonus' => ['required', 'numeric', 'min:0'],
            'allowance' => ['required', 'numeric', 'min:0'],
            'deduction' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'max:10'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $data['net_amount'] = round(
            (float) $data['base_salary'] + (float) $data['bonus'] + (float) $data['allowance'] - (float) $data['deduction'],
            2
        );

        $payroll = Payroll::updateOrCreate(
            ['user_id' => $user->id, 'year_month' => $data['year_month']],
            $data
        );

        return response()->json([
            'success' => true,
            'message' => "Payroll for {$user->name} saved successfully.",
            'payroll' => $this->transformPayroll($payroll),
        ]);
    }

    public function adminPublish(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->canAccessAdmin()) {
            abort(403);
        }

        $yearMonth = $request->input('year_month', Carbon::now()->format('Y-m'));

        $payrolls = Payroll::where('year_month', $yearMonth)
            ->where('status', 'draft')
            ->where('net_amount', '>', 0)
            ->get();

        if ($payrolls->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No draft payrolls available to publish.']);
        }

        $count = 0;
        foreach ($payrolls as $payroll) {
            $payroll->update(['status' => 'published', 'notified_at' => now()]);
            $payroll->user->notify(new PayrollPublished($payroll));
            $count++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} payroll(s) published successfully.",
            'count' => $count,
        ]);
    }

    /**
     * Copy previous month's payroll data to the current month as drafts.
     */
    public function adminCopyPrevious(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->canAccessAdmin()) {
            abort(403);
        }

        $yearMonth = $request->input('year_month', Carbon::now()->format('Y-m'));
        $prevDate = Carbon::createFromFormat('Y-m', $yearMonth)->subMonth();
        $prevYearMonth = $prevDate->format('Y-m');

        $prevPayrolls = Payroll::where('year_month', $prevYearMonth)->get();
        if ($prevPayrolls->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No payroll records found for previous month.']);
        }

        $created = 0;
        foreach ($prevPayrolls as $prev) {
            $exists = Payroll::where('user_id', $prev->user_id)
                ->where('year_month', $yearMonth)
                ->exists();
            if ($exists) {
                continue;
            }

            Payroll::create([
                'user_id' => $prev->user_id,
                'year_month' => $yearMonth,
                'base_salary' => $prev->base_salary,
                'bonus' => $prev->bonus,
                'allowance' => $prev->allowance,
                'deduction' => $prev->deduction,
                'net_amount' => $prev->net_amount,
                'currency' => $prev->currency,
                'status' => 'draft',
            ]);
            $created++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$created} payroll(s) copied from {$prevYearMonth}.",
            'count' => $created,
        ]);
    }

    public function adminSummary(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->canAccessAdmin()) {
            abort(403);
        }

        $yearMonth = $request->query('month', Carbon::now()->format('Y-m'));

        $summary = Payroll::where('year_month', $yearMonth)
            ->selectRaw('
                SUM(base_salary) as total_base_salary,
                SUM(bonus) as total_bonus,
                SUM(allowance) as total_allowance,
                SUM(deduction) as total_deduction,
                SUM(net_amount) as total_net_amount,
                COUNT(CASE WHEN status = "draft" THEN 1 END) as draft_count,
                COUNT(CASE WHEN status = "published" THEN 1 END) as published_count,
                COUNT(CASE WHEN status = "paid" THEN 1 END) as paid_count,
                COUNT(id) as total_payrolls
            ')
            ->first();

        return response()->json([
            'year_month' => $yearMonth,
            'summary' => [
                'total_base_salary' => (float) $summary->total_base_salary,
                'total_bonus' => (float) $summary->total_bonus,
                'total_allowance' => (float) $summary->total_allowance,
                'total_deduction' => (float) $summary->total_deduction,
                'total_net_amount' => (float) $summary->total_net_amount,
                'draft_count' => (int) $summary->draft_count,
                'published_count' => (int) $summary->published_count,
                'paid_count' => (int) $summary->paid_count,
                'total_payrolls' => (int) $summary->total_payrolls,
            ],
        ]);
    }

    // ── Transformers ────────────────────────────────────────────────

    private function transformPayroll(Payroll $p): array
    {
        return [
            'id' => $p->id,
            'user_id' => $p->user_id,
            'year_month' => $p->year_month,
            'base_salary' => (float) $p->base_salary,
            'bonus' => (float) $p->bonus,
            'allowance' => (float) $p->allowance,
            'deduction' => (float) $p->deduction,
            'net_amount' => (float) $p->net_amount,
            'payment_date' => $p->payment_date?->format('Y-m-d'),
            'currency' => $p->currency ?? 'JPY',
            'note' => $p->note,
            'status' => $p->status,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }

    private function transformPaymentMethod(PaymentMethod $pm): array
    {
        return [
            'id' => $pm->id,
            'method_type' => $pm->method_type,
            'method_type_label' => $pm->method_type_label,
            'bank_name' => $pm->bank_name,
            'branch_name' => $pm->branch_name,
            'account_type' => $pm->account_type,
            'account_number' => $pm->account_number,
            'account_holder' => $pm->account_holder,
            'masked_account_number' => $pm->masked_account_number,
            'swift_bic' => $pm->swift_bic,
            'email' => $pm->email,
            'is_default' => (bool) $pm->is_default,
        ];
    }

    private function paymentMethodRules(): array
    {
        return [
            'method_type' => ['required', 'string', 'in:bank_transfer,tng'],
            'bank_name' => ['required_if:method_type,bank_transfer', 'nullable', 'string', 'max:100'],
            'branch_name' => ['required_if:method_type,bank_transfer', 'nullable', 'string', 'max:100'],
            'account_type' => ['nullable', 'string', 'max:30'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_holder' => ['required', 'string', 'max:100'],
            'swift_bic' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
