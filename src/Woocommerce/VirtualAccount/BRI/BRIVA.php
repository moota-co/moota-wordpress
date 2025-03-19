<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BRI;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class BRIVA extends BaseVirtualAccount {
    public $bankCode = 'bri';
    public $bankName = 'BRI Virtual Account';
    
    public function __construct() {
        parent::__construct();
    }
}