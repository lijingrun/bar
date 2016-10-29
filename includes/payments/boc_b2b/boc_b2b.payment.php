<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Boc_b2bPayment extends BasePayment {

//    var $_gateway = 'https://domainName/PGWPortal/RecvOrder.do';  //响应接口
    var $_gateway = 'http://180.168.146.79/PGWPortal/B2BRecvOrder.do';
    var $_code = 'boc_b2b';

    //跳转到支付
    function get_payform($order_info) {
        require_once(dirname(__FILE__) . '/boc.class.php');
        $pay = new boc("1111111a");
        $pay->cert = 'cert/cert1.pem';
        $pay->privateKey = 'cert/key1.pem';
        $orderNo = $order_info['order_id'];
        if (!empty($orderNo)) {
            //商户号
            $merchantNo = "6794";

            //转换下单时间格式 yyyymmddhhmmmi
            $ordertime = date('YmdHis', $order_info['add_time']);

            //签名数据格式
            //订单号|订单时间|币种|金额|商户号
            $unsignData = $orderNo . "|" . $ordertime . "|001|" . $order_info['order_amount'] . "|" . $merchantNo;
            $signData = $pay->signFromStr($unsignData); //加密后的签名数据
            
            $parmas = array(
                "bocNo" => $merchantNo,
                "payType" => "1",
                'orderNo' => $order_info['order_id'],
                "curCode" => "001",
                "orderAmount" => $order_info['order_amount'],
                "orderTime" => $ordertime,
                "orderNote" => $order_info['buyer_name'] . '从蓝羽维涅斯商城购买商品',
                "orderUrl" => $this->_create_BOC_return_url($order_info['order_id']), //返回数据处理
                "signData" => $signData,
            );
            return $this->_create_payform("POST", $parmas);
        } else {
            show_warning("证书验证失败！", "请联系管理员", "index.php?app=buyer_order");
            return;
        }
    }

}
