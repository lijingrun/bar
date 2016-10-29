<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class AlipaymobilePayment extends BasePayment {

    var $_gateway = 'https://mapi.alipay.com/gateway.do';  //响应接口
//    https://mapi.alipay.com/gateway.do
    var $_code = 'alipaymobile';

    /**
     *    获取支付表单
     *
     *    @author    Garbin
     *    @param     array $order_info  待支付的订单信息，必须包含总费用及唯一外部交易号
     *    @return    array
     */
    function get_payform($order_info) {
        header("Content-type:text/html;charset=utf-8");
        require_once("alipay.config.php");
        require_once("lib/alipay_submit.class.php");

        //支付类型
        $payment_type = "1";
        //必填，不能修改
        //服务器异步通知页面路径
        $notify_url = "http://126wallpaper.com/paynotify/alipay_mobile/".$order_info['order_id']."/3986966";
        //需http://格式的完整路径，不能加?id=123这类自定义参数
        //页面跳转同步通知页面路径
//        $return_url = $this->_create_return_url($order_info['order_id']);
        $return_url = "http://126wallpaper.com/index.php?app=paynotify&act=notify_unionpay&order_id=".$order_info['order_id'];
//        $notify_url = $return_url;
        //需http://格式的完整路径，不能加?id=123这类自定义参数，不能写成http://localhost/
        //商户订单号
        $out_trade_no = $order_info['order_sn'];
        //商户网站订单系统中唯一订单号，必填
        //订单名称
        $subject = '蓝羽维涅斯订单' . $order_info['order_sn'];
        //必填
        //付款金额
        $total_fee = $order_info['order_amount'];
        //必填
        //商品展示地址
        $show_url = 'http://www.126wallpaper.com/index.php?app=buyer_order';
        //订单描述
        $body = '客户从蓝羽维涅斯商城下单';
        $params = array(
            "service" => "alipay.wap.create.direct.pay.by.user",
            "partner" => trim($alipay_config['partner']),
            "seller_id" => trim($alipay_config['seller_id']),
            "payment_type" => $payment_type,
            "notify_url" => $notify_url,
            "return_url" => $return_url,
            "out_trade_no" => $out_trade_no,
            "subject" => $subject,
            "total_fee" => $total_fee,
            "show_url" => $show_url,
            "body" => $body,
            "_input_charset" => trim(strtolower($alipay_config['input_charset']))
        );
        $alipaySubmit = new AlipaySubmit($alipay_config);
        $html_text = $alipaySubmit->buildRequestForm($params, "get", "确认");
        echo $html_text;
    }
    
}
