<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BSI;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class BSIVA extends BaseVirtualAccount {
    public $bankCode = 'bsi';
    public $bankName = 'BSI Virtual Account';
    public $icon;
    
    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/BSI/BSI.png', MOOTA_FULL_PATH );
    }
}