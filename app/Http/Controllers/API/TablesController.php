<?php

namespace App\Http\Controllers\API;

use App\Events\OrderUpdated;
use App\Http\Controllers\API\ApiBaseController;
use App\Models\Pos\PosOrder;
use App\Models\Pos\PosTable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TablesController extends ApiBaseController
{
    public function __construct(protected DatabaseManager $database)
    {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        $tables = PosTable::scope($this->account_id)
            ->with(['activeOrder.items' => function ($query) {
                $query->whereNull('voided_at');
            }])
            ->get();

        return response()->json($tables);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'capacity' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $table = new PosTable($data);
        $table->account_id = $this->account_id;
        $table->status = 'available';
        $table->save();

        return response()->json($table, 201);
    }

    public function show(PosTable $table): JsonResponse
    {
        $this->requireScopedTable($table);

        return response()->json($table->load(['activeOrder.items' => function ($query) {
            $query->whereNull('voided_at');
        }]));
    }

    public function update(Request $request, PosTable $table): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'capacity' => 'sometimes|integer|min:0',
            'status' => 'sometimes|string',
            'notes' => 'nullable|string',
        ]);

        $table->fill($data);
        $table->save();

        return response()->json($table);
    }

    public function merge(Request $request, PosTable $table): JsonResponse
    {
        $payload = $request->validate([
            'source_table_ids' => 'required|array|min:1',
            'source_table_ids.*' => 'different:table|integer|exists:pos_tables,id',
        ]);

        $targetTable = $this->requireScopedTable($table);

        $this->database->transaction(function () use ($payload, $targetTable) {
            $targetOrder = $targetTable->activeOrder ?: $this->createOrderForTable($targetTable);

            $sourceTables = PosTable::scope($this->account_id)
                ->whereIn('id', $payload['source_table_ids'])
                ->get();

            foreach ($sourceTables as $sourceTable) {
                $sourceOrder = $sourceTable->activeOrder;
                if (!$sourceOrder) {
                    $sourceTable->status = 'merged';
                    $sourceTable->merged_into_table_id = $targetTable->id;
                    $sourceTable->save();
                    continue;
                }

                foreach ($sourceOrder->items()->get() as $item) {
                    $clone = $item->replicate();
                    $clone->order_id = $targetOrder->id;
                    $clone->save();
                }

                $sourceOrder->status = 'merged';
                $sourceOrder->merged_into_order_id = $targetOrder->id;
                $sourceOrder->save();

                $sourceTable->status = 'merged';
                $sourceTable->merged_into_table_id = $targetTable->id;
                $sourceTable->save();
            }

            $targetTable->status = 'occupied';
            $targetTable->save();

            $targetOrder->refreshTotals();
            event(new OrderUpdated($targetOrder));
        });

        return response()->json(['message' => 'Tables merged']);
    }

    public function transfer(Request $request, PosTable $table): JsonResponse
    {
        $data = $request->validate([
            'target_table_id' => 'required|integer|exists:pos_tables,id|different:table',
        ]);

        $sourceTable = $this->requireScopedTable($table);
        $targetTable = PosTable::scope($this->account_id)->findOrFail($data['target_table_id']);

        $order = $sourceTable->activeOrder;
        if (!$order) {
            return response()->json(['message' => 'Source table has no active order'], 422);
        }

        $this->database->transaction(function () use ($order, $sourceTable, $targetTable) {
            $order->table_id = $targetTable->id;
            $order->save();

            $sourceTable->status = 'available';
            $sourceTable->save();

            $targetTable->status = 'occupied';
            $targetTable->save();

            event(new OrderUpdated($order));
        });

        return response()->json(['message' => 'Order transferred']);
    }

    protected function createOrderForTable(PosTable $table): PosOrder
    {
        $order = new PosOrder([
            'account_id' => $table->account_id,
            'table_id' => $table->id,
            'status' => 'open',
            'reference' => strtoupper(Str::random(8)),
            'opened_by' => Auth::guard('api')->id(),
        ]);
        $order->save();

        return $order->fresh(['items']);
    }

    protected function requireScopedTable(PosTable $table): PosTable
    {
        if ($table->account_id !== $this->account_id) {
            abort(404);
        }

        return $table;
    }
}
