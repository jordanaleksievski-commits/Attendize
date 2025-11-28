<?php

namespace App\Http\Controllers\API;

use App\Events\OrderUpdated;
use App\Http\Controllers\API\ApiBaseController;
use App\Models\Pos\PosOrder;
use App\Models\Pos\PosOrderAdjustment;
use App\Models\Pos\PosOrderItem;
use App\Models\Pos\PosTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OrdersController extends ApiBaseController
{
    public function index(): JsonResponse
    {
        $orders = PosOrder::scope($this->account_id)
            ->with(['items', 'table'])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($orders);
    }

    public function show(PosOrder $order): JsonResponse
    {
        $this->guardAccount($order);

        return response()->json($order->load(['items', 'table']));
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'table_id' => 'required|integer|exists:pos_tables,id',
            'shift_id' => 'nullable|integer|exists:pos_shifts,id',
            'covers' => 'nullable|integer|min:0',
            'items' => 'array',
            'items.*.name' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        $table = PosTable::scope($this->account_id)->findOrFail($payload['table_id']);

        $order = new PosOrder([
            'account_id' => $table->account_id,
            'table_id' => $table->id,
            'shift_id' => $payload['shift_id'] ?? null,
            'covers' => $payload['covers'] ?? 0,
            'status' => 'open',
            'reference' => strtoupper(Str::random(8)),
            'opened_by' => Auth::guard('api')->id(),
        ]);
        $order->save();

        $this->syncItems($order, $payload['items'] ?? []);

        $table->status = 'occupied';
        $table->save();

        $order->refreshTotals();
        event(new OrderUpdated($order));

        return response()->json($order->load(['items', 'table']), 201);
    }

    public function update(Request $request, PosOrder $order): JsonResponse
    {
        $this->guardAccount($order);

        $payload = $request->validate([
            'status' => 'sometimes|string',
            'covers' => 'sometimes|integer|min:0',
            'items' => 'array',
            'items.*.name' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        $order->fill($payload);
        $order->save();

        if (isset($payload['items'])) {
            $this->syncItems($order, $payload['items']);
        }

        $order->refreshTotals();
        event(new OrderUpdated($order));

        return response()->json($order->load(['items', 'table']));
    }

    public function close(PosOrder $order): JsonResponse
    {
        $this->guardAccount($order);

        $order->status = 'closed';
        $order->closed_by = Auth::guard('api')->id();
        $order->closed_at = now();
        $order->save();

        if ($order->table) {
            $order->table->status = 'available';
            $order->table->save();
        }

        event(new OrderUpdated($order));

        return response()->json($order->load(['items', 'table']));
    }

    public function voidItem(Request $request, PosOrder $order, PosOrderItem $item): JsonResponse
    {
        $this->guardAccount($order);
        $this->guardItem($item, $order);

        $data = $request->validate([
            'reason' => 'nullable|string',
        ]);

        $item->voided_at = now();
        $item->voided_by = Auth::guard('api')->id();
        $item->save();

        $this->logAdjustment($order, $item, 'void', $item->total, $data['reason'] ?? null);

        $order->refreshTotals();
        event(new OrderUpdated($order));

        return response()->json($order->load('items'));
    }

    public function applyDiscount(Request $request, PosOrder $order, PosOrderItem $item): JsonResponse
    {
        $this->guardAccount($order);
        $this->guardItem($item, $order);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
            'reason' => 'nullable|string',
        ]);

        $item->discount_amount = $data['amount'];
        $item->save();

        $this->logAdjustment($order, $item, 'discount', $data['amount'], $data['reason'] ?? null);

        $order->refreshTotals();
        event(new OrderUpdated($order));

        return response()->json($order->load('items'));
    }

    protected function syncItems(PosOrder $order, array $items): void
    {
        foreach ($items as $itemData) {
            $item = new PosOrderItem([
                'name' => $itemData['name'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'total' => $itemData['quantity'] * $itemData['unit_price'],
                'notes' => $itemData['notes'] ?? null,
            ]);
            $order->items()->save($item);
        }
    }

    protected function guardAccount(PosOrder $order): void
    {
        if ($order->account_id !== $this->account_id) {
            abort(404);
        }
    }

    protected function guardItem(PosOrderItem $item, PosOrder $order): void
    {
        if ($item->order_id !== $order->id) {
            abort(404);
        }
    }

    protected function logAdjustment(PosOrder $order, PosOrderItem $item, string $type, float $amount, ?string $reason = null): void
    {
        $adjustment = new PosOrderAdjustment([
            'account_id' => $order->account_id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
            'user_id' => Auth::guard('api')->id(),
            'metadata' => [
                'table_id' => $order->table_id,
                'status' => $order->status,
            ],
        ]);

        $adjustment->save();
    }
}
