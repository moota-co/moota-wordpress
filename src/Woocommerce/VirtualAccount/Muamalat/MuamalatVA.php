<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\Muamalat;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class MuamalatVA extends BaseVirtualAccount {
    public $bankCode = 'Muamalat';
    public $bankName = 'Muamalat Virtual Account';
    
    public function __construct() {
        parent::__construct();
    }
}