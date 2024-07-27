<?php

namespace Hoangnh\Solana\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolanaTransaction extends Model
{
    use HasFactory;

    protected $table = 'wallets_solana_transactions';

    protected $fillable = ['address_id', 'to_address','type', 'sol_transaction_id', 'amount', 'status'];

    public function address()
    {
        return $this->belongsTo(SolanaAddress::class);
    }
    public function deposit()
    {
        return $this->hasOne(SolanaDeposit::class, 'transaction_id');
    }

    public function withdraw()
    {
        return $this->hasOne(SolanaWithdraw::class, 'transaction_id');
    }
}
