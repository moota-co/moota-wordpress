<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BNI;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class BNIGateway extends BaseBankTransfer {
    public $bankCode = 'BNI';
    public $icon;

    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/BNI/BNI.png', MOOTA_FULL_PATH );
    }
}