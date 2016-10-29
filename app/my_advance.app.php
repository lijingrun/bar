<?php
/**
 * Created by PhpStorm.
 * User: lijingrun
 * Date: 2016/2/5
 * Time: 11:18
 * 我的预付款
 */

class My_advanceApp extends MemberbaseApp {

    function index(){
        $member_model = & m('member');
        $member = $member_model->get(array(
            'conditions' =>  'user_id ='.$_SESSION['user_info']['user_id'],
            'fields'     => 'advance',
        ));
        $this->assign('member',$member);

        $this->display('my_advance.html');
    }

    //查看消费记录
    function orders(){
        $user_id = $_SESSION['user_info']['user_id'];
        $order_model = & m('order');
        $orders = $order_model->find("payment_code = 'advance' AND buyer_id =".$user_id);
        $this->assign('orders', $orders);
        $this->display('my_advance_orders.html');
    }

    //查看充值记录
    function advance_logs(){
        $user_id = $_SESSION['user_info']['user_id'];
        $advance_log_model = & m('advance_log');
        $logs = $advance_log_model->find("user_id =".$user_id." order by create_time desc");
        $order_model = & m('order');
        foreach($logs as $key=>$log):
            $order = $order_model->get('order_sn ='.$log['order_sn']);
            $logs[$key]['order_id'] = $order['order_id'];
        endforeach;
        $this->assign('logs', $logs);
        $this->display('my_advance_log.html');
    }

}