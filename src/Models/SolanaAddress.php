<?php

namespace Hoangnh\Solana\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolanaAddress extends Model
{
    use HasFactory;

    protected $table = 'wallets_solana_address';

    protected $fillable = ['address', 'user_id'];

    public function transactions()
    {
        return $this->hasMany(SolanaTransaction::class);
    }

    public function deposits()
    {
        return $this->hasMany(SolanaTransaction::class);
    }

    public function withdraws()
    {
        return $this->hasMany(SolanaWithdraw::class);
    }
}