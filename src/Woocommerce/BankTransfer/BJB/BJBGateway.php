<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BJB;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class BJBGateway extends BaseBankTransfer {
    public $bankCode = 'BJB';
    public $icon;

    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/BJB/BJB.png', MOOTA_FULL_PATH );
    }
}