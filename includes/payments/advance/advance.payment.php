<?php
/**
 * Created by PhpStorm.
 * User: lijingrun
 * Date: 2016/2/5
 * Time: 17:40
 */

class AdvancePayment extends BasePayment {
    function get_payform($order_info) {
        $member_model = & m('member');
        $member = $member_model->get("user_id =".$order_info['buyer_id']);
        $order_model = & m('order');
        $order = $order_model->get('order_id ='.$order_info['order_id']);
        //余额不足，清空支付方式
        if($member['advance'] < $order_info['order_amount']){
            $order['payment_id'] = null;
            $order['payment_name'] = null;
            $order['payment_code'] = null;
            $order_model->edit($order['order_id'],$order);
            echo "对不起，余额不足以支付所有款项！";
            exit;
        }
        $before_advance = $member['advance'];
        //够，就直接扣除余额
        $member['advance'] -= $order['order_amount'];
        $member_model->edit($member['user_id'],$member);
        //改变订单状态并且记录
        $order['status'] = 20;
        $order['postscript'] = '客户商城余额支付';
        $order['pay_time'] = gmtime();
        $order_model->edit($order['order_id'],$order);
        $order_log_model = & m('orderlog');
        $new_log = array(
            'order_id' => $order['order_id'],
            'operator' => $order['buyer_id'],
            'order_status' => '等待买家付款',
            'changed_status' => '买家已付款',
            'remark' => '余额支付自动扣款',
            'log_time' => gmtime(),
        );
        $order_log_model->add($new_log);
        //消费扣除记录
        $advance_model = & m('advance_log');
        $new_advance_log = array(
            'admin_id' => $_SESSION['user_info']['user_id'],
            'order_sn' => $order_info['order_sn'],
            'create_time' => gmtime(),
            'user_id' => $_SESSION['user_info']['user_id'],
            'before' => $before_advance,
            'after' => $member['advance'],
            'amount' => -$order['order_amount'],
        );
        $advance_model->add($new_advance_log);
    }
}