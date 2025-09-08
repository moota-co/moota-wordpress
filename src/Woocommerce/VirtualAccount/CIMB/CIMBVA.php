<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\CIMB;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class CIMBVA extends BaseVirtualAccount {
    public $bankCode = 'CIMBVA';
    public $bankName = 'CIMB Virtual Account';
    public $icon;
    
    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/CIMB/CIMB.png', MOOTA_FULL_PATH );
    }
}