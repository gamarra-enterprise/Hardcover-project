<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\OrderNotCancellable;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Orders in the admin panel: list with filters, detail, and the status change.
 * Confirming a payment and refunding are not done by hand here: they belong to the payment flow.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Order::class);

        $status = OrderStatus::tryFrom((string) $request->query('estado'));
        $search = trim((string) $request->query('q'));

        $orders = Order::query()
            ->withCount('items')
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where('tracking_code', 'ilike', $like)->orWhere('email', 'ilike', $like);
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', ['orders' => $orders, 'status' => $status, 'search' => $search]);
    }

    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['items', 'payments', 'user', 'statusHistories.author']);

        return view('admin.orders.show', ['order' => $order, 'targets' => $this->targets($order)]);
    }

    public function update(Request $request, Order $order, OrderService $service): RedirectResponse
    {
        Gate::authorize('update', $order);

        // An empty list would make `only()` accept every status, so it is refused up front.
        $targets = $this->targets($order);
        abort_if($targets === [], 422, 'Este pedido no admite cambios de estado manuales.');

        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)->only($targets)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $to = OrderStatus::from($data['status']);

        try {
            if ($to === OrderStatus::CANCELLED) {
                $service->cancel($order, $request->user(), $data['note'] ?? 'Cancelado por el personal');
            } else {
                $order->transitionTo($to, $request->user(), $data['note'] ?? null);
            }
        } catch (InvalidOrderTransition|OrderNotCancellable) {
            return back()->with('error', 'El pedido cambió mientras tanto y ya no puede pasar a ese estado.');
        }

        ActivityLog::record('order.status_changed', "Pedido {$order->tracking_code} → {$to->label()}", ['order_id' => $order->id]);

        return back()->with('notice', 'Pedido actualizado: '.$to->label().'. Se avisó al cliente por correo.');
    }

    /** @return list<OrderStatus> where staff can move this order by hand */
    private function targets(Order $order): array
    {
        return array_values(array_filter(
            $order->status->next(),
            fn (OrderStatus $s) => ! in_array($s, [OrderStatus::CONFIRMED, OrderStatus::REFUNDED], true),
        ));
    }
}
