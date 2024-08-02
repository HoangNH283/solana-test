<?php

namespace Hoangnh\Solana\Services;
require 'vendor/autoload.php';

// use GuzzleHttp\Client;
use Hoangnh\Solana\Models\SolanaAddress;
use Hoangnh\Solana\Models\SolanaTransaction;
use Hoangnh\Solana\Models\SolanaDeposit;
use Hoangnh\Solana\Models\SolanaWithdraw;
use Exception;
use Illuminate\Support\Facades\Log;

use Tighten\SolanaPhpSdk\Connection;
use Tighten\SolanaPhpSdk\SolanaRpcClient;
use Tighten\SolanaPhpSdk\KeyPair;
use Tighten\SolanaPhpSdk\PublicKey;
use Tighten\SolanaPhpSdk\Programs\SystemProgram;
use Tighten\SolanaPhpSdk\Transaction;

class SolanaService
{
    protected $lamports = 1000000000;
    protected $url = 'https://api.devnet.solana.com';
    public function createAddress()
    {
        // Tạo Keypair mới
        $keypair = Keypair::generate();

        // Lấy địa chỉ công khai và khóa riêng
        $publicKey = $keypair->getPublicKey()->toBase58();
        $secretKey = $keypair->getSecretKey();
        return [
            'publicKey'=> $publicKey,
            'secretKey'=> $secretKey->toArray(),
        ];
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

    public function transfer($fromSecretKey, $toAddress, $amount)
    {
        $client = new SolanaRpcClient(SolanaRpcClient::DEVNET_ENDPOINT);
        $connection = new Connection($client);
        $fromKeyPair = KeyPair::fromSecretKey($fromSecretKey);
        $toPublicKey = new PublicKey($toAddress);

        // Lấy recentBlockhash
        $recentBlockhashResponse = $connection->getRecentBlockhash();
        
        if (!isset($recentBlockhashResponse['blockhash'])) {
            throw new Exception('Failed to retrieve recent blockhash');
        }
        $recentBlockhash = $recentBlockhashResponse['blockhash'];

        $instruction = SystemProgram::transfer(
            $fromKeyPair->getPublicKey(),
            $toPublicKey,
            $amount * $this->lamports
        );
        
        // $transaction = new Transaction(null, null, $fromKeyPair->getPublicKey()); 
        $transaction = new Transaction();
        $transaction->recentBlockhash = $recentBlockhash;
        $transaction->feePayer = $fromKeyPair->getPublicKey();
        $transaction->add($instruction);
        // $transaction->sign($fromKeyPair);
        $txHash = $connection->sendTransaction($transaction, [$fromKeyPair]);
        return $txHash;
    }

    protected function isValidSolanaAddress($address)
    {
        $address = trim($address);
        $result = preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address);
        return $result;
        
    }

    public function getBalance($address)
    {
        $postData = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getBalance',
            'params' => [
                $address
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
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

    public function getSignaturesForAddress($address)
    {
        $postData = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getSignaturesForAddress',
            'params' => [
                $address,
                ['limit' => 1]
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
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
            throw new Exception('Failed to retrieve signature: ' . curl_error($ch));
        }
        curl_close($ch);
        $responseData = json_decode($response, true);
        if (isset($responseData['result'][0]['signature'])) {
            return $responseData;
        } else {
            Log::error('Failed to retrieve signature', ['response' => $response]);
            throw new Exception('Failed to retrieve signature');
        }
    }

    public function requestAirdrop($address, $amount){
        $postData = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'requestAirdrop',
            'params' => [
                $address,
                $amount * $this->lamports
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
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
            throw new Exception('Failed to retrieve airdrop: ' . curl_error($ch));
        }
        curl_close($ch);
        $responseData = json_decode($response, true);
        if (isset($responseData['result'])) {
            return $responseData;
        } else {
            Log::error('Failed to retrieve airdrop', ['response' => $response]);
            throw new Exception($responseData['error']['message']);
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
