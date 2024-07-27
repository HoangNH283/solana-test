<?php

namespace Hoangnh\Solana;

use Illuminate\Support\Facades\Facade;

class SolanaFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'solana';
    }
}
