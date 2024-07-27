<?php

namespace Hoangnh\Solana\Services;

// use GuzzleHttp\Client;
use Hoangnh\Solana\Models\SolanaAddress;
use Hoangnh\Solana\Models\SolanaTransaction;
use Hoangnh\Solana\Models\SolanaDeposit;
use Hoangnh\Solana\Models\SolanaWithdraw;
use Exception;
use Illuminate\Support\Facades\Log;
class SolanaService
{
    public function createAddress($userId)
    {
        // Logic to generate Solana address
        $address = 'Generated_Solana_Address'; // Replace with actual address generation logic
        $address = $this->generateRandomHex();

        return SolanaAddress::create([
            'address' => $address,
            'user_id' => $userId,
        ]);
    }

    public function deposit($addressId, $amount)
    {
        // $recentBlockhash = $this->getRecentBlockhash();
        // Ghi lại giao dịch vào bảng transactions
        $solTransactionId = uniqid(true); // Tạo ID giao dịch giả lập id giao dịch trên mạng sol
        $status = 'completed'; // Hoặc 'pending', 'failed' tùy theo tình trạng

        $transaction = $this->recordTransaction($addressId, $amount, $status, 'deposit', $solTransactionId);

        // Tạo bản ghi nạp tiền
        $deposit = SolanaDeposit::create([
            'address_id' => $addressId,
            'amount' => $amount,
            'transaction_id' => $transaction->id
        ]);

        return $deposit;
    }

    public function withdraw($addressId, $amount)
    {

        // Ghi lại giao dịch vào bảng transactions
        $solTransactionId = uniqid(true);
        $status = 'completed';

        $transaction =$this->recordTransaction($addressId, $amount, $status, 'withdraw', $solTransactionId);

        // Tạo bản ghi rút tiền
        $withdraw = SolanaWithdraw::create([
            'address_id' => $addressId,
            'amount' => $amount,
            'transaction_id' => $transaction->id
        ]);
        return $withdraw;
    }

    public function transfer($fromAddressId, $toAddress, $amount)
    {
        if (!$this->isValidSolanaAddress($toAddress)) {
            throw new Exception('Invalid Solana address');
        }

        // Ghi lại giao dịch vào bảng transactions
        $fromAddress = SolanaAddress::findOrFail($fromAddressId);
        $balance = $this->getBalance($fromAddress->address);
        // Kiểm tra số dư của ví gửi
        if ($balance < $amount) {
            throw new Exception('Insufficient balance');
        }

        // Khởi tạo giao dịch và ghi lại trạng thái ban đầu là "pending"
        $transaction = $this->recordTransaction($fromAddressId, -$amount, 'pending','transfer');

        try {
            // Thực hiện giao dịch trên blockchain Solana
            $transactionResult = $this->executeSolanaTransfer($fromAddress->address, $toAddress, $amount);

            // Cập nhật trạng thái giao dịch dựa trên kết quả thực hiện
            if ($transactionResult['status'] === 'success') {
                $transaction->status = 'completed';
                $transaction->sol_transaction_id = $transactionResult['sol_transaction_id'];
                $transaction->save();

                // Cập nhật số dư của ví gửi và ghi lại chi tiết giao dịch cho cả ví gửi và ví nhận
                $balance -= $amount;
                $fromAddress->save();

                // Nếu ví nhận là địa chỉ ví bên ngoài, ghi lại thông tin giao dịch ngoài hệ thống
                if (!$this->isInternalAddress($toAddress)) {
                    // $this->recordTransactionExternal($toAddress, $amount, 'completed', $transactionResult['transaction_id']);
                } else {
                    // Nếu ví nhận là địa chỉ nội bộ, cập nhật số dư và ghi lại chi tiết giao dịch
                    $toAddressModel = SolanaAddress::where('address', $toAddress)->first();
                    // $toAddressModel->balance += $amount;
                    // $toAddressModel->save();
                    $this->recordTransaction($toAddressModel->id, $amount,'completed', 'transfer', $transactionResult['sol_transaction_id']);
                }
            } else {
                $transaction->status = 'failed';
                $transaction->save();
                throw new Exception('Transaction failed on Solana blockchain');
            }
        } catch (Exception $e) {
            $transaction->status = 'failed';
            $transaction->save();
            throw $e;
        }

        return $transaction;
    }

    protected function isValidSolanaAddress($address)
    {
        $address = trim($address);
        $result = preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address);
        return $result;
        
    }
    public function getBalance($address)
    {
        $url = 'https://api.mainnet-beta.solana.com';

        $postData = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getBalance',
            'params' => [
                $address
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            Log::error('cURL error: ' . curl_error($ch));
            throw new Exception('Failed to retrieve balance: ' . curl_error($ch));
        }

        curl_close($ch);

        $responseData = json_decode($response, true);

        if (isset($responseData['result']['value'])) {
            $balance = $responseData['result']['value'] / 1000000000; // Số dư được trả về trong đơn vị lamports, chia cho 1 tỷ để chuyển sang SOL
            Log::info('Balance retrieved successfully', ['balance' => $balance]);
            return $balance;
        } else {
            Log::error('Failed to retrieve balance', ['response' => $response]);
            throw new Exception('Failed to retrieve balance');
        }
    }

    protected function executeSolanaTransfer($fromAddress, $toAddress, $amount)
    {
        // Trả về một mảng với kết quả giao dịch bao gồm 'status' và 'transaction_id'
        return [
            'status' => 'success',
            'sol_transaction_id' => uniqid(true),
        ];
    }
    protected function isInternalAddress($address)
    {
        return SolanaAddress::where('address', $address)->exists();
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
    function generateRandomHex($length = 42) {
        // Đảm bảo rằng chiều dài của chuỗi là số chẵn
        $length = ($length % 2 == 0) ? $length : $length + 1;

        $randomBytes = random_bytes($length / 2);
        $hex = bin2hex($randomBytes);

        return substr($hex, 0, $length);
    }
}
