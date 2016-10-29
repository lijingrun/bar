<?php

/**
 *    支付网关通知接口
 *
 *    @author    Garbin
 *    @usage    none
 */
class PaynotifyApp extends MallbaseApp
{
    /**
     *    支付完成后返回的URL，在此只进行提示，不对订单进行任何修改操作,这里不严格验证，不改变订单状态
     *
     *    @author    Garbin
     *    @return    void
     */
    function index()
    {
        //这里是支付宝，财付通等当订单状态改变时的通知地址
        $order_id   = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0; //哪个订单
        if (!$order_id)
        {
            /* 无效的通知请求 */
            $this->show_warning('forbidden');

            return;
        }

        /* 获取订单信息 */
        $model_order =& m('order');
        $order_info  = $model_order->get($order_id);
        if (empty($order_info))
        {
            /* 没有该订单 */
            $this->show_warning('forbidden');

            return;
        }

        $model_payment =& m('payment');
        $payment_info  = $model_payment->get("payment_code='{$order_info['payment_code']}' AND store_id={$order_info['seller_id']}");
        if (empty($payment_info))
        {
            /* 没有指定的支付方式 */
            $this->show_warning('no_such_payment');

            return;
        }

        /* 调用相应的支付方式 */
        $payment = $this->_get_payment($order_info['payment_code'], $payment_info);

        /* 获取验证结果 */
        $notify_result = $payment->verify_notify($order_info);
        if ($notify_result === false)
        {
            /* 支付失败 */
            $this->show_warning($payment->get_error());

            return;
        }
        $notify_result['target']=ORDER_ACCEPTED;
        #TODO 临时在此也改变订单状态为方便调试，实际发布时应把此段去掉，订单状态的改变以notify为准
        $this->_change_order_status($order_id, $order_info['extension'], $notify_result);
		//线上支付折扣优惠
//        $order_info['order_amount'] = $this->_online_pay_discount($order_info['order_amount']);

        #TODO 临时在此也改变订单状态为方便调试，实际发布时应把此段去掉，订单状态的改变以notify为准
        //$this->_change_order_status($order_id, $order_info['extension'], $notify_result);
        //增加K3对应表的记录--只针对蓝羽公司,中博的付款了自己入
        $member_model = & m('member');
        $member = $member_model->get('user_id ='.$order_info['buyer_id']);
        $this->send_message($order_info['buyer_id']);
        if($order_info['seller_id'] == 7 || $order_info['seller_id'] == 24480 || $order_info['seller_id'] == 25242){
            if($order_info['seller_id'] == 7){
                $this->_order_in_k3($member['k3_code'],$member['user_name'],$order_info,'线上汇款');
            }else if($order_info['seller_id'] == 25242){
                $this->_order_in_k3($member['k3_code'],$member['user_name'],$order_info,'线上充值');
            }else{
                $order_member = $member_model->get('user_id ='.$order_info['seller_id']);
                $this->_order_in_k3($order_member['k3_code'],$order_member['user_name'],$order_info,'线上汇款');
            }
        }
        $seller = $member_model->get('user_id ='.$order_info['seller_id']);
        //中博发微信信息到玲账号
        if($seller['user_id'] == 24480){
            $this->order_notice($seller['openid'],$order_info['order_sn'],$order_info['buyer_name']);
        }
        //线上支付送金币
//        $this->_get_coin($order_info['order_sn']);


        /* 只有支付时会使用到return_url，所以这里显示的信息是支付成功的提示信息 */
        $this->_curlocal(LANG::get('pay_successed'));
        $this->assign('order', $order_info);
        $this->assign('payment', $payment_info);
        $this->display('paynotify.index.html');
    }

    /**
     *    支付完成后，外部网关的通知地址，在此会进行订单状态的改变，这里严格验证，改变订单状态
     *
     *    @author    Garbin
     *    @return    void
     */
    function notify()
    {
        //这里是支付宝，财付通等当订单状态改变时的通知地址
        $order_id   = 0;
        if(isset($_POST['order_id']))
        {
            $order_id = intval($_POST['order_id']);
        }
        else
        {
            $order_id = intval($_GET['order_id']);
        }
        if (!$order_id)
        {
            /* 无效的通知请求 */
            $this->show_warning('no_such_order');
            return;
        }

        /* 获取订单信息 */
        $model_order =& m('order');
        $order_info  = $model_order->get($order_id);
        if (empty($order_info))
        {
            /* 没有该订单 */
            $this->show_warning('no_such_order');
            return;
        }
        //线上支付折扣优惠
//        $order_info['order_amount'] = $this->_online_pay_discount($order_info['order_amount']);
        $model_payment =& m('payment');
        $payment_info  = $model_payment->get("payment_code='{$order_info['payment_code']}' AND store_id={$order_info['seller_id']}");
        if (empty($payment_info))
        {
            /* 没有指定的支付方式 */
            $this->show_warning('no_such_payment');
            return;
        }
        
        /* 调用相应的支付方式 */
        $payment = $this->_get_payment($order_info['payment_code'], $payment_info);
        
        /* 获取验证结果 */
        $notify_result = $payment->verify_notify($order_info, true);
        if ($notify_result === false)
        {
            /* 支付失败 */
            $payment->verify_result(false);
            return;
        }
        
        //改变订单状态
        $this->_change_order_status($order_id, $order_info['extension'], $notify_result);
        $payment->verify_result(true);
        //增加K3对应表的记录--只针对蓝羽公司
        $member_model = & m('member');
        $member = $member_model->get('user_id ='.$order_info['buyer_id']);
        $this->send_message($order_info['buyer_id']);
        if(($order_info['seller_id'] == 7 || $order_info['seller_id'] == 24480 || $order_info['seller_id'] == 25242) && !empty($member['k3_code'])){
            if($order_info['seller_id'] == 7){
                $this->_order_in_k3($member['k3_code'],$member['user_name'],$order_info,'线上汇款');
            }else if($order_info['seller_id'] == 25242){
                $this->_order_in_k3($member['k3_code'],$member['user_name'],$order_info,'线上充值');
            }else{
                $order_member = $member_model->get('user_id ='.$order_info['seller_id']);
                $this->_order_in_k3($order_member['k3_code'],$order_member['user_name'],$order_info,'线上汇款');
            }
        }
        //线上支付送金币
//        $this->_get_coin($order_info['order_sn']);


        if ($notify_result['target'] == ORDER_ACCEPTED)
        {
            /* 发送邮件给卖家，提醒付款成功 */
            $model_member =& m('member');
//            $seller_info  = $model_member->get($order_info['seller_id']);
            //中博发微信信息到玲账号
            $seller = $member_model->get('user_id ='.$order_info['seller_id']);
            if($seller['user_id'] == 24480){
                $this->order_notice($seller['openid'],$order_info['order_sn'],$order_info['buyer_name']);
            }
            /* 同步发送 */
            $this->_sendmail(true);
        }
    }

    
    //银联返回结果验证并跳转,同步传输
    function notify_unionpay()
    {
        //这里是支付宝，财付通等当订单状态改变时的通知地址
        $order_id   = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0; //哪个订单
        if (!$order_id)
        {
            /* 无效的通知请求 */
            $this->show_warning('forbidden');

            return;
        }

        /* 获取订单信息 */
        $model_order =& m('order');
        $order_info  = $model_order->get($order_id);
        if (empty($order_info))
        {
            /* 没有该订单 */
            $this->show_warning('forbidden');

            return;
        }

        $model_payment =& m('payment');
        $payment_info  = $model_payment->get("payment_code='{$order_info['payment_code']}' AND store_id={$order_info['seller_id']}");
        if (empty($payment_info))
        {
            /* 没有指定的支付方式 */
            $this->show_warning('no_such_payment');

            return;
        }

        /* 调用相应的支付方式 */
        $payment = $this->_get_payment($order_info['payment_code'], $payment_info);

        /* 获取验证结果 */
        $notify_result = $payment->verify_notify($order_info);
        if ($notify_result === false)
        {
            /* 支付失败 */
            $this->show_warning($payment->get_error());

            return;
        }

        $notify_result['target']=ORDER_ACCEPTED;
        #TODO 临时在此也改变订单状态为方便调试，实际发布时应把此段去掉，订单状态的改变以notify为准
        $this->_change_order_status($order_id, $order_info['extension'], $notify_result);

        $order_model = & m('order');
        $order_model->edit($order_id,' pay_time = '.gmtime());

        #TODO 临时在此也改变订单状态为方便调试，实际发布时应把此段去掉，订单状态的改变以notify为准
        //$this->_change_order_status($order_id, $order_info['extension'], $notify_result);

        /* 只有支付时会使用到return_url，所以这里显示的信息是支付成功的提示信息 */
//        $this->_curlocal(LANG::get('pay_successed'));
//        $this->assign('order', $order_info);
//        $this->assign('payment', $payment_info);
//        $this->display('paynotify.index.html');
        if($order_info['status'] == 11) {
            //增加K3对应表的记录
            $member_model = &m('member');
            $member = $member_model->get('user_id =' . $order_info['buyer_id']);
            $this->send_message($order_info['buyer_id']);
            $log_model = &m('orderlog');
            $new_log = array(
                'order_id' => $order_info['order_id'],
                'operator' => '银联',
                'order_status' => '等待买家付款',
                'changed_status' => '买家已付款',
                'remark' => '银联支付通知',
                'log_time' => gmtime(),
            );
            //线上支付优惠
//            $this->_get_coin($order_info['order_sn']);
//            $before_amount = $order_info['order_amount'];
//            $order_info['order_amount'] = $this->_online_pay_discount($order_info['order_amount']);
            //记录折后价以及优惠金额
//            $order_info['discount'] += $before_amount-$order_info['order_amount'];
//            $model_order->edit($order_info['order_id'],$order_info);


            $log_model->add($new_log);
            if (($order_info['seller_id'] == 7 || $order_info['seller_id'] == 24480 || $order_info['seller_id'] == 25242)) {
                if ($order_info['seller_id'] == 7) {
                    $this->_order_in_k3($member['k3_code'], $member['user_name'], $order_info, '线上汇款');
                } else if ($order_info['seller_id'] == 25242) {
                    $this->_order_in_k3($member['k3_code'], $member['user_name'], $order_info, '线上充值');
                } else {
                    $order_member = $member_model->get('user_id =' . $order_info['seller_id']);
                    $this->_order_in_k3($order_member['k3_code'], $order_member['user_name'], $order_info, '线上汇款');
                }
            }
        }
        $this->show_message('操作成功！','付款成功！','index.php?app=buyer_order&act=view&order_id='.$order_id);
    }
    
    
    //手机淘宝异步传输（验证有错误，暂时用这个）
    function alipay_mobile(){
        $order_id  = 0;
        $check_no = 0;
        if(isset($_POST['order_id']))
        {
            $order_id = intval($_POST['order_id']);
            $check_no = intval($_POST['check_no']);
        }
        else
        {
            $order_id = intval($_GET['order_id']);
            $check_no = intval($_GET['check_no']);
        }
        if($check_no != 3986966){
            $this->show_warning('认证失败！');
            return;
        }
        if (!$order_id)
        {
            /* 无效的通知请求 */
            $this->show_warning('no_such_order');
            return;
        }

        /* 获取订单信息 */
        $model_order =& m('order');
        $order_info  = $model_order->get($order_id);
        if (empty($order_info))
        {
            /* 没有该订单 */
            $this->show_warning('no_such_order');
            return;
        }

        $model_payment =& m('payment');
        $payment_info  = $model_payment->get("payment_code='{$order_info['payment_code']}' AND store_id={$order_info['seller_id']}");
        if (empty($payment_info))
        {
            /* 没有指定的支付方式 */
            $this->show_warning('no_such_payment');
            return;
        }
        $order = $model_order->get('order_id ='.$order_id);
        if($order['status'] == 11) {
            //线上支付送金币
//            $this->_get_coin($order_info['order_sn']);
//            $order_info['order_amount'] = $this->_online_pay_discount($order_info['order_amount']);

            $order['status'] = 20;
            $order['pay_time'] = gmtime();
            $model_order->edit($order_id, $order);
            $log_model = &m('orderlog');
            $new_log = array(
                'order_id' => $order_id,
                'operator' => 'admin',
                'order_status' => '等待买家付款',
                'changed_status' => '买家已付款',
                'remark' => '支付宝手机支付的异步通知',
                'log_time' => gmtime(),
            );
            $member_model = &m('member');
            $member = $member_model->get('user_id =' . $order_info['buyer_id']);
            $this->send_message($order_info['buyer_id']);
            //中博发微信信息到玲账号
            $seller = $member_model->get('user_id =' . $order_info['seller_id']);
            if ($seller['user_id'] == 24480) {
                $this->order_notice($seller['openid'], $order_info['order_sn'], $order_info['buyer_name']);
            }
            if(($order_info['seller_id'] == 7 || $order_info['seller_id'] == 24480 || $order_info['seller_id'] == 25242) && !empty($member['k3_code'])){
                if($order_info['seller_id'] == 7){
                    $this->_order_in_k3($member['k3_code'],$member['user_name'],$order_info,'线上汇款');
                }else if($order_info['seller_id'] == 25242){
                    $this->_order_in_k3($member['k3_code'],$member['user_name'],$order_info,'线上充值');
                }else{
                    $order_member = $member_model->get('user_id ='.$order_info['seller_id']);
                    $this->_order_in_k3($order_member['k3_code'],$order_member['user_name'],$order_info,'线上汇款');
                }
            }
            $log_model->add($new_log);
        }
        echo 'success';
        exit;
    }

    //易宝pc支付回调页面
    function yeepay_pc_online(){

        $r0_Cmd		= $_POST['r0_Cmd'];
        $r1_Code	= $_POST['r1_Code'];
        $r2_TrxId	= $_POST['r2_TrxId'];
        $r3_Amt		= $_POST['r3_Amt'];
        $r4_Cur		= $_POST['r4_Cur'];
        $r5_Pid		= $_POST['r5_Pid'];
        $r6_Order	= $_POST['r6_Order'];
        $r7_Uid		= $_POST['r7_Uid'];
        $r8_MP		= $_POST['r8_MP'];
        $r9_BType	= $_POST['r9_BType'];
        $hmac			= $_POST['hmac'];
        if(empty($r0_Cmd)){
            $r0_Cmd		= $_GET['r0_Cmd'];
            $r1_Code	= $_GET['r1_Code'];
            $r2_TrxId	= $_GET['r2_TrxId'];
            $r3_Amt		= $_GET['r3_Amt'];
            $r4_Cur		= $_GET['r4_Cur'];
            $r5_Pid		= $_GET['r5_Pid'];
            $r6_Order	= $_GET['r6_Order'];
            $r7_Uid		= $_GET['r7_Uid'];
            $r8_MP		= $_GET['r8_MP'];
            $r9_BType	= $_GET['r9_BType'];
            $hmac			= $_GET['hmac'];
        }
//        echo $r0_Cmd."|".$r1_Code."|".$r2_TrxId."|".$r3_Amt."|".$r4_Cur."|".$r5_Pid."|".$r6_Order."|".$r7_Uid."|".$r8_MP."|".$r9_BType."|".$hmac;
        include_once ('./includes/payments/yeepay_pc_online/yeepayCommon.php');
//        $test = getCallbackHmacString($r0_Cmd,$r1_Code,$r2_TrxId,$r3_Amt,$r4_Cur,$r5_Pid,$r6_Order,$r7_Uid,$r8_MP,$r9_BType);
//        echo $test."84738";exit;
        $bRet = CheckHmac($r0_Cmd,$r1_Code,$r2_TrxId,$r3_Amt,$r4_Cur,$r5_Pid,$r6_Order,$r7_Uid,$r8_MP,$r9_BType,$hmac);

        #	校验码正确.
        if($bRet){
            if($r1_Code=="1"){

                #	需要比较返回的金额与商家数据库中订单的金额是否相等，只有相等的情况下才认为是交易成功.
                #	并且需要对返回的处理进行事务控制，进行记录的排它性处理，在接收到支付结果通知后，判断是否进行过业务逻辑处理，不要重复进行业务逻辑处理，防止对同一条交易重复发货的情况发生.


                $order_sn = $r6_Order;
                $order_model = & m('order');
                $order = $order_model->get("status = 11 AND order_sn =".$order_sn);
                if(!empty($order)){
                    if($r3_Amt != $order['order_amount']){
                        $this->show_message('实际支付金额与订单金额对不上！');
                        return;
                    }
//                    if($order['status'] = 11){
                        //线上支付送金币
//                        $this->_get_coin($order['order_sn']);
//                        $before_amount = $order['order_amount'];
//                        $order['order_amount'] = $this->_online_pay_discount($order['order_amount']);
                        //记录折后价以及优惠金额
//                        $order['discount'] += $before_amount-$order['order_amount'];

//                    }
                    $order['status'] = 20;
                    $order['pay_time'] = gmtime();
                    $order_model->edit($order['order_id'],$order);
                    $log_model = & m('orderlog');
                    $new_log = array(
                        'order_id' => $order['order_id'],
                        'operator' => 'admin',
                        'order_status' => '等待买家付款',
                        'changed_status' => '买家已付款',
                        'remark' => '易宝PC端支付通知',
                        'log_time' => gmtime(),
                    );
                    $member_model = & m('member');
                    $member = $member_model->get('user_id ='.$order['buyer_id']);
                    $seller = $member_model->get('user_id ='.$order['seller_id']);
                    $this->send_message($order['buyer_id']);
                    //中博发微信信息到玲账号
                    if($seller['user_id'] == 24480){
                        $this->order_notice($seller['openid'],$order['order_sn'],$order['buyer_name']);
                    }
                    if(($order['seller_id'] == 7 || $order['seller_id'] == 24480 || $order['seller_id'] == 25242) && !empty($member['k3_code'])){
                        if($order['seller_id'] == 7){
                            $this->_order_in_k3($member['k3_code'],$member['user_name'],$order,'线上汇款');
                        }else if($order['seller_id'] == 25242){
                            $this->_order_in_k3($member['k3_code'],$member['user_name'],$order,'线上充值');
                        }else{
                            $order_member = $member_model->get('user_id ='.$order['seller_id']);
                            $this->_order_in_k3($order_member['k3_code'],$order_member['user_name'],$order,'线上汇款');
                        }
                    }
                    $log_model->add($new_log);
                    if($r9_BType=="1"){
    //                    echo "交易成功";
                        $this->show_message('操作成功！','付款成功！','index.php?app=buyer_order&act=view&order_id='.$order['order_id']);

                    }elseif($r9_BType=="2"){
                        #如果需要应答机制则必须回写流,以success开头,大小写不敏感.
                        echo "success";
                        echo "<br />交易成功";
                        echo  "<a href='http://126wallpaper.com'>点击返回店铺</a>";
                    }
                }

            }

        }else{
            echo "交易信息被篡改";exit;
        }
    }

    //易宝一键支付回调页面
    function yeepay_onekey(){
        import('yeepay/yeepayMPay');
        // 商户编号
        $merchantaccount = '10013227109';
// 商户私钥
        $merchantPrivateKey = 'MIICdQIBADANBgkqhkiG9w0BAQEFAASCAl8wggJbAgEAAoGBAIkKyO+L62my2ZKMTbv+fww6bVWlUWSAVy3/XIM6B9TcqMOOlrzemiUXKD+JuJb7s8yVQfvncfnyxnB0fYgZOGwWRsHRmfMzA2IERMHmPkxu1A64ZHdmN2hNO1fwd9PmKlN1nCgr1c91bnNRwWZ0jKgM2CuDUG5BqRzLPX2fY31hAgMBAAECgYASuB5qWjp13bBKjE+x9jl0eialJEfR6pX9+nuwkSSwttN2ouuEMQPtPRSKWU2VkhwlPd4dgqfW9IqWodLj3E4Qgj729jZEXeyn5lBXnCTjuV+k91ZhOEsz8Ues4dHHpX1zoLne3LxU4orJqs3YY1X3vJ5YKjk4MnCWBEsdkUmAAQJBAMwG542zUDQx978TCV65vZJXxYUZIgKD14JMHfkfyvGcoWmrTIBRIH2bX/6ouf+HoFcJq7zb74ZvJUGH+HCgpuECQQCr86LA5EZzPFDKkEO5uEPfXP27xxXT52BkkXsf4B1WUt5AoXwU5H5/9JXLrNQ69SgfU70SI+WTZaZpa1f86iaBAkBQdzylhx3PqBFUm3ZrlIeuis1Mw+/E3CiHq+t6UE6i4apLWZLPXK+aukeu0O6iV+Qlz5ua3YbnFzizUqPqD4IhAkBT75b337aAE+ZAKxHUO51uEB+PpQwDp4NHNDjNA4Jum/7/v5QpQqx5W3QvuwrSSM+wExlNHJa5T7pe5VZLECWBAkBqt/nNOa992dUl1c6TkY3WF6bIuyd7wu1X9K+sWV+KnIbHtRBJepFbpmwANtttzRRX9NAmcIlGAUvVcXegb2vb';

// 商户公钥
        $merchantPublicKey = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCJCsjvi+tpstmSjE27/n8MOm1VpVFkgFct/1yDOgfU3KjDjpa83polFyg/ibiW+7PMlUH753H58sZwdH2IGThsFkbB0ZnzMwNiBETB5j5MbtQOuGR3ZjdoTTtX8HfT5ipTdZwoK9XPdW5zUcFmdIyoDNgrg1BuQakcyz19n2N9YQIDAQAB';
// 易宝公钥
        $yeepayPublicKey = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCCuQAdaXu2il0WpXwhXC/AYreTXr1cKnFRNyGUrS+I7buOaMkl7DPTG2Nn5kB8TCHP2YeiainK+jJWdZpohsJskHDkQZNTvpvvmT4DTPKqdqlE8hwPiotmwwEGREGxWOvSvj0XhXeOYAD7m4WIKWAHPR+pImSAEpgJ2O2XA5eA7wIDAQAB';
        $yeepay = new yeepayMPay($merchantaccount,$merchantPublicKey,$merchantPrivateKey,$yeepayPublicKey);

        try {
            $data = $_POST['data'];
            $encryptkey = $_POST['encryptkey'];
            if(empty($data)){
                $data = $_GET['data'];
                $encryptkey = $_GET['encryptkey'];
            }
            $return = $yeepay->callback($data, $encryptkey);
            if($return['status'] == 1) {
                // TODO:添加订单处理逻辑代码

                $order_model = &m('order');
                $order = $order_model->get('order_sn =' . $return['orderid']);
                $member_model = & m('member');
                $member = $member_model->get('user_id ='.$order['buyer_id']);
                if (!empty($order['order_id']) && $return['status'] == 1) {
                    if($order['status'] == 11) {
                        //线上支付送金币
//                        $this->_get_coin($order['order_sn']);
//                        $before_amount = $order['order_amount'];
//                        $order['order_amount'] = $this->_online_pay_discount($order['order_amount']);
                        //记录折后价以及优惠金额
//                        $order['discount'] += $before_amount-$order['order_amount'];

                        $order['status'] = 20;
                        $order_model->edit($order['order_id'], $order);
                        //增加修改记录
                        $log_model = & m('orderlog');
                        $new_log = array(
                            'order_id' => $order['order_id'],
                            'operator' => 'admin',
                            'order_status' => '等待买家付款',
                            'changed_status' => '买家已付款',
                            'remark' => '易宝一键支付通知',
                            'log_time' => gmtime(),
                        );
                        $log_model->add($new_log);
                        //如果是蓝羽的订单，直接入K3
                        if(($order['seller_id'] == 7 || $order['seller_id'] == 24480 || $order['seller_id'] == 25242) && !empty($member['k3_code'])){
                            if($order['seller_id'] == 7){
                                $this->_order_in_k3($member['k3_code'],$member['user_name'],$order,'线上汇款');
                            }else if($order['seller_id'] == 25242){
                                $this->_order_in_k3($member['k3_code'],$member['user_name'],$order,'线上充值');
                            }else{
                                $order_member = $member_model->get('user_id ='.$order['seller_id']);
                                $this->_order_in_k3($order_member['k3_code'],$order_member['user_name'],$order,'线上汇款');
                            }
                        }
                    }
                    $this->send_message($order['buyer_id']);
                    echo 'SUCCESS';
                    $this->show_message('操作成功！','付款成功！','index.php?app=buyer_order&act=view&order_id='.$order['order_id']);
                }
            }else{
                echo "订单返回失败，请联系相关技术人员！";
            }
            exit;
        }catch (yeepayMPayException $e) {
            // TODO：添加订单支付异常逻辑代码
            echo "订单返回失败，请联系相关技术人员！";
            exit;
        }
    }

    /**
     * 客户使用线上支付的话，每日送一张10元现金卷
     *
     */

	/* audo general coupon while the newest coupon in database's 
	 * start time is not equal current time in day period
	 */
	function general_coupon( 
			$store_id = 7, 
			$coupon_name = '10元代金卷', 
			$coupon_value = 10,
			$use_times = 1,
			$min_amount = 100,
			$if_issue = 1,
			$img = '/data/indeximages/coupon_images/10'
		) {
		$coupon_model = & m('coupon');
//		$coupon = $coupon_model->get(array(
//			'fields' => 'MAX(start_time) as start_time',
//		));
//
//		$coupon = $coupon_model->get( 'start_time = '. $coupon['start_time'] );
        //获取开始时间是今天凌晨的coupon
        $start_time = strtotime(date("Y-m-d",gmtime()));
        $the_coupon = $coupon_model->get('start_time ='.$start_time);
		if(empty($the_coupon)) {
			$coupon_id = $coupon_model->add(
				array(
					'store_id' => $store_id,
					'coupon_name' => $coupon_name,
					'coupon_value' => $coupon_value,
					'use_times' => $use_times ,
					'min_amount' => $min_amount ,
					'if_issue' => $if_issue ,
					'img' => $img ,
					'start_time' => $start_time,
					'end_time' => $start_time + 3600*24*30,
				)
			);

            $the_coupon = $coupon_model->get( 'coupon_id ='. $coupon_id );
		}

		return $the_coupon;
	}

    //下单通知商户
    function order_notice($openid,$order_sn,$buyer_name){
        $notice_model = & m('order_notice');
        $first_notice = $notice_model->get('order_sn ='.$order_sn);
        if(empty($first_notice)){
            $new_notice = array(
                'order_sn' => $order_sn,
            );
            $notice_model->add($new_notice);
            $data = array('first'=>array(value=>'中博装饰下单通知','topcolor'=> '#0F12D'),
                'keyword1'=>array(value=> date("Y-m-d",time()) ,'topcolor'=> '#0F12D'),
                'keyword2'=>array(value=>$order_sn ,'topcolor'=> '#0F12D'),
                'keyword3'=>array(value=>'蓝羽维涅斯商城订单' ,'topcolor'=> '#0F12D'),
                'keyword4'=>array(value=>$buyer_name ,'topcolor'=> '#0F12D'),
                'remark' => array(value=>'请及时发货','topcolor'=> '#0F12D'),
            );
            $this->_send_message_to_weixin($openid,'5MyGYUHa5c2eHg5ykSInJg9-pA8zCeWfFRLz6TyMiQo',$data);
        }
    }

    function give_coupon($order,$member){

        //先查是否今天第一张单
        $order_model = & m('order');
        //查本账号今天的所有线上付款的订单
        $order_day = strtotime(date("Y-m-d",$order['add_time']));
        $end_time = $order_day + 86400;
        $check_order = $order_model->find("status = 20 AND pay_time > ".$order_day." AND pay_time < ".$end_time." AND buyer_id =".$order['buyer_id']." and order_id !=".$order['order_id']." AND seller_id = 7");
        //没有就发coupon
        if(empty($check_order)){
            $the_coupon = $this->general_coupon();
            $coupon_model = & m('coupon');
            $coupon = $coupon_model->get('coupon_id = 10');
            $coupon_sn_model = & m('couponsn');
            $user_coupon_model = & m('user_coupon');
            $coupon_sn = mt_rand(10000, 99999);
            $coupon_sn = '000000'.$coupon_sn;
            $new_coupon_sn = array(
                'coupon_sn' => $coupon_sn,
                'coupon_id' => $the_coupon['coupon_id'],
                'remain_times' => 1,
            );
            $new_user_coupon = array(
                'coupon_sn' => $coupon_sn,
                'user_id' => $order['buyer_id'],
            );
            $coupon_sn_model->add($new_coupon_sn);
            $user_coupon_model->add($new_user_coupon);
            //微信用户，发送微信信息
            if(!empty($member['openid'])){
                $data = array('first'=>array(value=>'优惠券已经成功发放到你账号上！','topcolor'=> '#0F12D'),
                    'keyword1'=>array(value=>'蓝羽维涅斯商城蓝羽辅料'.$coupon['coupon_name'] ,'topcolor'=> '#0F12D'),
                    'keyword2'=>array(value=>$coupon_sn ,'topcolor'=> '#0F12D'),
                    'keyword3'=>array(value=>$coupon['use_times'] ,'topcolor'=> '#0F12D'),
                    'remark' => array(value=>'感谢你支持我们的线上支付','topcolor'=> '#0F12D'),
                );
                $this->_send_message_to_weixin($member['openid'], 'mjYYFdHlN91zCtFmsqWKG5wqRIgSlQLRHfCX6srchJc', $data);
            }
        }
    }

    //发送微信信息
    function _send_message_to_weixin($openid,$template_id,$data)
    {
        $access_token_model = & m('access_token');
        //先获取服务器上面的access_token;
        $effective_time = time()-7200;  //有效时间2小时
        $access_token_record = $access_token_model->get("create_time > ".$effective_time);
        //如果access_token失效，则重新获取
        if (empty($access_token_record)) {
            $access_token = $this->_get_access_token();
        }else{
            $access_token = $access_token_record['access_token'];
        }
        $url = 'http://open.weixin.qq.com/connect/oauth2/authorize?appid=wx7ccf7c8a377ea11b&redirect_uri=http%3A%2F%2Fwww.126wallpaper.com&response_type=code&scope=snsapi_base&state=123#wechat_redirect';
        $touser = $openid;
        $template = array(
            'touser' => $touser,
            'template_id' => $template_id,
            'url' => $url,
            'data' => $data
        );
        $json_template = json_encode($template);
        $url = "http://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $access_token;
        $dataRes = $this->request_post($url, urldecode($json_template));
//        return $dataRes['errcode'];
        if ($dataRes['errcode'] == 0) {
            return true;
        } else {
            return false;
        }


    }

    /**
     * 发送post请求
     * @param string $url
     * @param string $param
     * @return bool|mixed
     */
    function request_post($url = '', $param = '')
    {
        if (empty($url) || empty($param)) {
            return false;
        }
        $postUrl = $url;
        $curlPost = $param;
        $ch = curl_init(); //初始化curl
        curl_setopt($ch, CURLOPT_URL, $postUrl); //抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0); //设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1); //post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
//        echo 123;exit;
        $data = curl_exec($ch); //运行curl
        $data =curl_multi_getcontent($ch);
        curl_close($ch);

        return $data;
    }

    //获取access_token
    function _get_access_token(){
        $appid = "wx7ccf7c8a377ea11b";
        $secret = "4cf617291ca6d73b022c13d085dc6da1";
        $token_access_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $secret;
        $res = file_get_contents($token_access_url); //获取文件内容或获取网络请求的内容
        $result = json_decode($res, true); //接受一个 JSON 格式的字符串并且把它转换为 PHP 变量
        $access_token = $result['access_token'];
        $access_token_model = & m('access_token');
        $new_access_token = array(
            'access_token' => $access_token,
            'create_time' => time(),
        );
        $access_token_model->edit(1, $new_access_token);
        return $result['access_token'];
    }

    function test_k3(){
        $order_sn = $_GET['order_sn'];
        $order_model = & m('order');
        $order = $order_model->get('order_sn ='.$order_sn);
        $member_model = & m('member');
        $member = $member_model->get('user_id ='.$order['buyer_id']);
        if(($order['seller_id'] == 7 || $order['seller_id'] == 24480 || $order['seller_id'] == 25242) && !empty($member['k3_code'])){
            if($order['seller_id'] == 7){
                $this->_order_in_k3($member['k3_code'],$member['user_name'],$order,'线上汇款');
            }else if($order['seller_id'] == 25242){
                $this->_order_in_k3($member['k3_code'],$member['user_name'],$order,'线上充值');
            }else{
                $order_member = $member_model->get('user_id ='.$order['seller_id']);
                $this->_order_in_k3($order_member['k3_code'],$order_member['user_name'],$order,'线上汇款');
            }
        }
    }

    function send_message($user_id){
        $device_model = & m('devices');
        $device = $device_model->get("user_id =".$user_id);
        if(!empty($device['serial_id']) && $device['device_type'] == 'mqtt'){
            $mqtt = new Mqtt();
            $mqtt->send($device['serial_id'],'支付成功通知：您的工单已经支付成功！');
        }else if(!empty($device['serial_id']) && $device['device_type'] == 'apns'){
            $apns = new Apns();
            $apns->send($device['serial_id'],'支付成功通知：您的工单已经支付成功！');
        }
    }
    /**
     *    改变订单状态
     *
     *    @author    Garbin
     *    @param     int $order_id
     *    @param     string $order_type
     *    @param     array  $notify_result
     *    @return    void
     */
    function _change_order_status($order_id, $order_type, $notify_result)
    {
        /* 将验证结果传递给订单类型处理 */
        $order_type  =& ot($order_type);
        $order_type->respond_notify($order_id, $notify_result);    //响应通知
    }

    //获取订单对应的金币并且标识订单
    function _get_coin($order_sn){
        $order_model = & m("order");
        $coin_log_model = & m('coin_log');
        $member_model = & m('member');
        $store_model = & m('store');
        $order = $order_model->get("order_sn =".$order_sn);
        $coin = $order['amount'];
        $store = $store_model->get("store_id =".$order['buyer_id']);
        if(empty($store)){
            $grade = 1;
        }else{
            $grade = $store['sgrade'];
        }
        switch($grade){
            case 2 : $coin = $coin*1.5; //金卡
                break;
            case 3 : $coin = $coin*1.8; //铂金
                break;
            case 5 : $coin = $coin*1.3; //vip
                break;
        }
        $coin_log = array(
            'reason' => "商城下单并且线上支付",
            'lanyu_coin' => $coin,
            'member_id' => $order['buyer_id'],
            'add_time' => time(),
            'order_sn' => $order_sn,
            'order_id' => $order['order_id'],
        );
        $member = $member_model->get("user_id =".$order['buyer_id']);
        $member['coin'] += $coin;
        $order['pay_message'] = 'online_pay';
        $coin_log_model->add($coin_log);
        $order_model->edit($order['order_id'],$order);
        $member_model->edit($member['user_id'],$member);
    }


    //线上支付优惠
    function _online_pay_discount($order_amount){
//        $order_amount*100;
//        $grade = $_SESSION['user_info']['sgrade'];
//        switch($grade){
//            case 5: $discount = round($order_amount*0.99);
//                break;
//            case 2 : $discount = round($order_amount*0.985);
//                break;
//            case 3 : $discount = round($order_amount*0.98);
//                break;
//        }
//        return $discount/100;
        return $order_amount;
    }

}

?>
