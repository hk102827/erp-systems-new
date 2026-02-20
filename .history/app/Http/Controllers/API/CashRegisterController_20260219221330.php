<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\CashMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CashRegisterController extends Controller
{
    /**
     * Get all cash registers
     */
    public function index(Request $request)
    {
        try {
            $query = CashRegister::with(['branch', 'user']);

            // Filter by branch
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by date
            if ($request->has('date')) {
                $query->whereDate('opened_at', $request->date);
            }

            $registers = $query->latest()->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $registers
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cash registers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Open cash register
     */
    public function open(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'opening_balance' => 'required|numeric|min:0',
            'opening_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Check if user already has an open register
            $existingRegister = CashRegister::where('user_id', auth()->id())
                ->where('status', 'Open')
                ->first();

            if ($existingRegister) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an open cash register',
                    'data' => $existingRegister
                ], 400);
            }

            // Create cash register
            $register = CashRegister::create([
                'branch_id' => $request->branch_id,
                'user_id' => auth()->id(),
                'opening_balance' => $request->opening_balance,
                'status' => 'Open',
                'opened_at' => now(),
                'opening_notes' => $request->opening_notes,
            ]);

            // Record opening movement
            CashMovement::create([
                'cash_register_id' => $register->id,
                'type' => 'Opening',
                'amount' => $request->opening_balance,
                'reason' => 'Cash register opening balance',
                'recorded_by' => auth()->id(),
                'movement_date' => now(),
            ]);

            DB::commit();

            $register->load(['branch', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Cash register opened successfully',
                'data' => $register
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to open cash register',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Close cash register
     */
    public function close(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'closing_balance' => 'required|numeric|min:0',
            'closing_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $register = CashRegister::findOrFail($id);

            if ($register->status === 'Closed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cash register is already closed'
                ], 400);
            }

            // Calculate expected balance
            $expectedBalance = $register->calculateExpectedBalance();
            $difference = $request->closing_balance - $expectedBalance;

            // Close register
            $register->update([
                'closing_balance' => $request->closing_balance,
                'expected_balance' => $expectedBalance,
                'difference' => $difference,
                'status' => 'Closed',
                'closed_at' => now(),
                'closing_notes' => $request->closing_notes,
            ]);

            // Record closing movement
            CashMovement::create([
                'cash_register_id' => $register->id,
                'type' => 'Closing',
                'amount' => $request->closing_balance,
                'reason' => 'Cash register closing balance',
                'recorded_by' => auth()->id(),
                'movement_date' => now(),
            ]);

            DB::commit();

            $register->load(['branch', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Cash register closed successfully',
                'data' => $register
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to close cash register',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add cash in/out
     */
    public function addCashMovement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cash_register_id' => 'required|exists:cash_registers,id',
            'type' => 'required|in:Cash In,Cash Out',
            'amount' => 'required|numeric|min:0.001',
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $register = CashRegister::findOrFail($request->cash_register_id);

            if ($register->status === 'Closed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot add cash movement to a closed register'
                ], 400);
            }

            $movement = CashMovement::create([
                'cash_register_id' => $request->cash_register_id,
                'type' => $request->type,
                'amount' => $request->amount,
                'reason' => $request->reason,
                'recorded_by' => auth()->id(),
                'movement_date' => now(),
            ]);

            $movement->load(['cashRegister', 'recordedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Cash movement recorded successfully',
                'data' => $movement
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record cash movement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cash register details
     */
    public function show($id)
    {
        try {
            $register = CashRegister::with([
                'branch',
                'user',
                'sales',
                'cashMovements.recordedBy'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $register
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cash register not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get current open register for user
     */
    public function getCurrentRegister()
    {
        try {
            $register = CashRegister::with(['branch', 'user'])
                ->where('user_id', auth()->id())
                ->where('status', 'Open')
                ->first();

            if (!$register) {
                return response()->json([
                    'success' => false,
                    'message' => 'No open cash register found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $register
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch current register',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}