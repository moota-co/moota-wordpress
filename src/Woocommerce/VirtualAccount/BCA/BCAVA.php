<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BCA;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class BCAVA extends BaseVirtualAccount {
    public $bankCode = 'bca';
    public $bankName = 'BCA Virtual Account';
    public $icon;
    
    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/BCA/BCA.png', MOOTA_FULL_PATH );
    }
}