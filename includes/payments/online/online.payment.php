<?php

/**
 *    货到付款支付方式
 *
 *    @author    Garbin
 *    @usage    none
 */
class OnlinePayment extends BasePayment
{
    var $_code = 'online'; //唯一标识
    var $_gateway = '/index.php?app=ping';
    
    //跳转到支付
    function get_payform($order_info) {
        return $this->_create_payform("POST", $order_info);
    }
}

?>