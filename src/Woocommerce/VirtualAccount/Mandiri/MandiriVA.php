<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\Mandiri;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class MandiriVA extends BaseVirtualAccount {
    public $bankCode = 'Mandiri';
    public $bankName = 'Mandiri Virtual Account';
    public $icon;
    
    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/Mandiri/Mandiri.png', MOOTA_FULL_PATH );
    }
}