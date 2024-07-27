<?php

namespace Hoangnh\Solana\Http\Controllers;

use Hoangnh\Solana\Services\SolanaService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Exception;
class SolanaController extends Controller
{
    protected $solanaService;

    public function __construct(SolanaService $solanaService)
    {
        $this->solanaService = $solanaService;
    }

    public function createAddress(Request $request)
    {
        $userId = $request->input('user_id');
        try {
            $address = $this->solanaService->createAddress($userId);
            return response()->json($address);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deposit(Request $request)
    {
        $addressId = $request->input('address_id');
        $amount = $request->input('amount');

        try {
            $deposit = $this->solanaService->deposit($addressId, $amount);
            return response()->json($deposit);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function withdraw(Request $request)
    {
        $addressId = $request->input('address_id');
        $amount = $request->input('amount');

        try {
            $withdraw = $this->solanaService->withdraw($addressId, $amount);
            return response()->json($withdraw);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function transfer(Request $request)
    {
        $fromAddressId = $request->input('from_address_id');
        $toAddress = $request->input('to_address');
        $amount = $request->input('amount');

        try {
            $transfer = $this->solanaService->transfer($fromAddressId, $toAddress, $amount);
            return response()->json($transfer);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
