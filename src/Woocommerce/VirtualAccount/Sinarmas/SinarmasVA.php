<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\Sinarmas;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class SinarmasVA extends BaseVirtualAccount {
    public $bankCode = 'Sinarmas';
    public $bankName = 'Sinarmas Virtual Account';
    
    public function __construct() {
        parent::__construct();
    }
}