<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\CIMB;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class CIMBVA extends BaseVirtualAccount {
    public $bankCode = 'CIMBVA';
    public $bankName = 'CIMB Virtual Account';
    
    public function __construct() {
        parent::__construct();
    }
}