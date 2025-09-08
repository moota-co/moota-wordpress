<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BSI;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class BSIGateway extends BaseBankTransfer {
    public $bankCode = 'BSI';
    public $icon;

    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/BSI/BSI.png', MOOTA_FULL_PATH );
    }
}