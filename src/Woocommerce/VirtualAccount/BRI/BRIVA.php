<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BRI;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class BRIVA extends BaseVirtualAccount {
    public $bankCode = 'bri';
    public $bankName = 'BRI Virtual Account';
    public $icon;
    
    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/BRI/BRI.png', MOOTA_FULL_PATH );
    }
}