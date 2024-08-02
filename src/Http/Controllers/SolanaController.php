<?php

namespace Hoangnh\Solana\Http\Controllers;
require 'vendor/autoload.php';
use Hoangnh\Solana\Services\SolanaService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Exception;
use Hoangnh\Solana\Models\SolanaAddress;
use Hoangnh\Solana\Models\SolanaTransaction;
class SolanaController extends Controller
{
    protected $solanaService;
    protected $current_user_id = 1;

    public function __construct(SolanaService $solanaService)
    {
        $this->solanaService = $solanaService;
    }
    public function createAddress(Request $request)
    {
        $userId = $request->input('user_id');
        try {
            $result = $this->solanaService->createAddress();
            $address = SolanaAddress::create([
                'address' => $result['publicKey'],
                'secret_key' => json_encode($result['secretKey']),
                'user_id' => $userId,
            ]);
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

    public function getSignaturesForAddress(Request $request)
    {
        $address = $request->input('address');
        try {
            $result = $this->solanaService->getSignaturesForAddress($address);
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function requestAirdrop(Request $request)
    {
        $address = $request->input('address');
        $amount = $request->input('amount');
        try {
            $result = $this->solanaService->requestAirdrop($address, $amount);
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function transfer(Request $request) {
        $userSolanaInfo = SolanaAddress::where('user_id', $this->current_user_id);
        $fromSecretKey = json_decode($userSolanaInfo->value('secret_key'));
        $toAddress = $request->input('to_address');
        $amount = $request->input('amount');
        // Khởi tạo giao dịch và ghi lại trạng thái ban đầu là "pending"
        $transaction = SolanaTransaction::create([
                'address_id' => $userSolanaInfo->value('id'),
                'to_address' => $toAddress,
                'amount' => $amount,
                'status' => "pending",
                'type' => "transfer"
            ]);
        try {
            $txHash = $this->solanaService->transfer($fromSecretKey, $toAddress, $amount);
            $transaction->status = 'completed';
            $transaction->sol_transaction_id = $txHash;
            $transaction->save();
            return response()->json($transaction);
        } catch (Exception $e) {            
            $transaction->status = 'failed';
            $transaction->save();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function recordTransaction($addressId, $amount, $status, $type, $transactionId = null)
    {
        return SolanaTransaction::create([
            'address_id' => $addressId,
            'sol_transaction_id' => $transactionId,
            'amount' => $amount,
            'status' => $status,
            'type' => $type
        ]);
    }

    
}
