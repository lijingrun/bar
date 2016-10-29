<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class PingApp extends MemberbaseApp {

    public function index() {
        $order_info = $_POST;
        if (empty($order_info['order_id']) || empty($order_info['order_sn'])) {
            $this->show_warning('对不起，订单不存在');
        }
        $this->assign('order_info', $order_info);
        $this->display('ping_pay.html');
    }

    public function pay() {
        $order_id = $_GET['order_id'];
        $channel = $_GET['channel'];
        if (empty($order_id)) {
            return $this->show_warning('该订单不存在！', '请返回！', 'index.php');
        }
        $order_model = & m('order');
        $order = $order_model->get('order_id =' . $order_id);
        
        if ($order['status'] != 11) {
            return $this->show_warning('订单已经付款了或者已经取消了', '请返回', 'index.php');
        }
        if (empty($channel)) {
            return $this->show_warning('未选择在线支付类型', '请返回', 'index.php');
        }
        if (empty($order['order_amount']) || $order['order_amount'] <= 0) {
            return $this->show_warning('订单支付金额不存在！', '请返回', 'index.php');
        }
        import('Pingpp');
        Pingpp::setApiKey("sk_test_LyPGCK0uvTKOqXbjfHTeDmn5");

        $extra = array();
        switch ($channel) {
            case 'alipay_wap':
                $extra = array(
                    //index.php?app=paynotify&act=notify&order_id=
                    'success_url' => 'http://www.126wallpaper.com/index.php%3Fapp%3Dpaynotify%26act%3Dnotify_ping%26order_id%3D'.$order_id,
                    'cancel_url' => 'http://www.126wallpaper.com/index.php?app=buyer_order',
                );
                break;
            case 'upmp_wap':
                $extra = array(
                    'result_url' => 'localhost'
                );
                break;
        }

        try {
            $ch = Pingpp_Charge::create(array(
                        'order_no' => $order['order_sn'],
                        'amount' => $order['order_amount']*100,  //人民币以分为单位
                        'app' => array('id' => 'app_8OybbTyDOqP8qbff'),
                        'channel' => $channel,
                        'currency' => 'cny',
                        'client_ip' => '120.24.156.120',
                        'subject' => '蓝羽维涅斯商城产品',
                        'body' => '蓝羽维涅斯商城产品',
                        "extra" => $extra,
            ));
        } catch (Exception $ex) {
            echo($ex);
        }
        json_encode($ch);
        $this->assign('ch', $ch);
        $this->display('ping.html');
    }

}
