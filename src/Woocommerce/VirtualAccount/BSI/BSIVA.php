<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BSI;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class BSIVA extends BaseVirtualAccount {
    public $bankCode = 'bsi';
    public $bankName = 'BSI Virtual Account';
    
    public function __construct() {
        parent::__construct();
    }
}