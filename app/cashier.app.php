<?php

/**
 *    收银台控制器，其扮演的是收银员的角色，你只需要将你的订单交给收银员，收银员按订单来收银，她专注于这个过程
 *
 *    @author    Garbin
 */
class CashierApp extends ShoppingbaseApp
{
    /**
     *    根据提供的订单信息进行支付
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function index()
    {
        /* 外部提供订单号 */
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if (!$order_id)
        {
            $this->show_warning('no_such_order');

            return;
        }
        /* 内部根据订单号收银,获取收多少钱，使用哪个支付接口 */
        $order_model =& m('order');
        $order_info  = $order_model->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'));
        if (empty($order_info))
        {
            $this->show_warning('no_such_order');

            return;
        }
        /* 订单有效性判断 */
        if ($order_info['payment_code'] != 'cod' && $order_info['status'] != ORDER_PENDING)
        {
            $this->show_warning('no_such_order');
            return;
        }
        $member_model = & m('member');
        $member = $member_model->get('user_id ='.$order_info['buyer_id']);
                //判断如果订单支付金额为0（纯金币兑换订单），就直接改变订单状态
        if($order_info['order_amount'] == 0){
            $order_model->edit($order_id,array('status' => 20, 'payment_name' => '金币兑换' , 'pay_time' => time()));
            //记录支付时间以及操作
            $order_log_model = & m('orderlog');
            $log = array(
                'order_id' => $order_id,
                'operator' => $_SESSION['user_info']['user_name'],
                'order_status' => '已提交',
                'changed_status' => '买家已付款',
                'remark' => '买家金币支付',
                'log_time' => time()-28800,
            );
            $order_log_model->add($log);
            //判断，如果客户是金币抵扣的话（暂时只可以通过订单discount来判断），变成线上支付，直接入K3，因为线下支付入K3不会生成收款单
            if($order_info['discount'] > 0){
                $this->_order_in_k3($member['k3_code'],$member['user_name'],$order_info,'线上汇款');
            }else{
                $this->_order_in_k3($member['k3_code'],$member['user_name'],$order_info,'线下支付');
            }

//            $order_act_model = & m('order_act');
//            $new_act = array(
//                'Order_Id' => $order_info['order_sn'],
//                'Act_State' => 20,
//            );
//            $order_act_model->add($new_act);
            $this->show_message("支付成功！","","index.php?app=buyer_order");
            return;
        }else{
        
        
        
        $payment_model =& m('payment');
        
        if (!$order_info['payment_id'])
        {
            $return_data = $this->_online_pay_discount($order_info['order_amount']); //线上支付优惠
            $this->assign('online_pay_discount',$return_data['discount']);
            /*
 * 金币抵扣现金，计算客户金币可以最高抵扣的金额，高于订单金额取订单金额，不高于区金币总额
 */
            $is_weixin = $this->weixin();
            if($member['coin'] > 0 && !$is_weixin) {
                $coin = $member['coin'] / 80;  //金币低现金
                if ($coin <= $return_data['discount'] && $coin > 0) {
                    $use_coin = $coin * 80;
                } else {
                    $use_coin = $return_data['discount'] * 80;
                }
                $to_price = $use_coin / 80;
                $this->assign('use_coin', $use_coin);
                $this->assign('to_price', $to_price);
            }
            $this->assign('type',$return_data['type']);
            /* 若还没有选择支付方式，则让其选择支付方式 */
            $payments = $payment_model->get_enabled($order_info['seller_id']);
            //查订单里面是否存在促销的产品，如果存在，就不显示余额支付，不存在再查余额是否足够
            $goods_model = & m('goods');
            $order_goods_model = & m('ordergoods');
            $order_goods = $order_goods_model->find('order_id ='.$order_info['order_id']);
            $has_recomended = false;
            foreach($order_goods as $order_good):
                $goods = $goods_model->get('goods_id ='.$order_good['goods_id']);
                if($goods['recommended'] == 1 || $goods['no_advance'] == 1){
                    $has_recomended = true;
                    break;
                }
            endforeach;
            //余额不足或者订单里面有促销产品的，取消余额支付
            $member_model = & m('member');
            $member = $member_model->get('user_id = '.$order_info['buyer_id']);
            if($order_info['order_amount'] > $member['advance'] || $has_recomended){
                foreach($payments as $key=>$payment):
                    if($payment['payment_code'] == 'advance'){
                        unset($payments[$key]);
                    }
                endforeach;
            }
            //循环支付方式，如果是手机网页，就取消pc端的支付宝[payment_code] => alipay，如果是PC网页，就取消手机端网页支付[payment_code] => alipaymobile
            $is_mobile = $this->_get_terminal();
            //pc
            if($is_mobile == 7){
                foreach($payments as $key=>$payment):
                    if($payment['payment_code'] == 'alipaymobile' || $payment['payment_code'] == 'yeepay_onekey'){
                        unset($payments[$key]);
//                        break;
                    }
                endforeach;
            }else{  //移动
                foreach($payments as $key=>$payment):
                    if($payment['payment_code'] == 'alipay' || $payment['payment_code'] == 'yeepay_pc_online'){
                        unset($payments[$key]);
//                        break;
                    }
                endforeach;
            }
            if (empty($payments))
            {
                $this->show_warning('store_no_payment');

                return;
            }
            /* 找出配送方式，判断是否可以使用货到付款 */
            $model_extm =& m('orderextm');
            $consignee_info = $model_extm->get($order_id);
            if (!empty($consignee_info))
            {
                /* 需要配送方式 */
                $model_shipping =& m('shipping');
                $shipping_info = $model_shipping->get($consignee_info['shipping_id']);
                $cod_regions   = unserialize($shipping_info['cod_regions']);
                $cod_usable = true;//默认可用
                if (is_array($cod_regions) && !empty($cod_regions))
                {
                    /* 取得支持货到付款地区的所有下级地区 */
                    $all_regions = array();
                    $model_region =& m('region');
                    foreach ($cod_regions as $region_id => $region_name)
                    {
                        $all_regions = array_merge($all_regions, $model_region->get_descendant($region_id));
                    }

                    /* 查看订单中指定的地区是否在可货到付款的地区列表中，如果不在，则不显示货到付款的付款方式 */
                    if (!in_array($consignee_info['region_id'], $all_regions))
                    {
                        $cod_usable = false;
                    }
                }
                else
                {
                    $cod_usable = false;
                }
                if (!$cod_usable)
                {
                    /* 从列表中去除货到付款的方式 */
                    foreach ($payments as $_id => $_info)
                    {
                        if ($_info['payment_code'] == 'cod')
                        {
                            /* 如果安装并启用了货到付款，则将其从可选列表中去除 */
                            unset($payments[$_id]);
                        }
                    }
                }
            }
            $all_payments = array('online' => array(), 'offline' => array());
            foreach ($payments as $key => $payment)
            {
                if ($payment['is_online'])
                {
                    $all_payments['online'][] = $payment;
                }
                else
                {
                    $all_payments['offline'][] = $payment;
                }
            }
            $this->assign('order', $order_info);
            $this->assign('payments', $all_payments);
            $this->_curlocal(
                LANG::get('cashier')
            );

            $this->_config_seo('title', Lang::get('confirm_payment') . ' - ' . Conf::get('site_title'));
            $this->display('cashier.payment.html');
        }
        else
        {
            /* 否则直接到网关支付 */
            /* 验证支付方式是否可用，若不在白名单中，则不允许使用 */
            if (!$payment_model->in_white_list($order_info['payment_code']))
            {
                $this->show_warning('payment_disabled_by_system');

                return;
            }

            $payment_info  = $payment_model->get("payment_code = '{$order_info['payment_code']}' AND store_id={$order_info['seller_id']}");
            /* 若卖家没有启用，则不允许使用 */
            if (!$payment_info['enabled'])
            {
                $this->show_warning('payment_disabled');

                return;
            }
//            print_r($payment_info);exit;
            //判断是否微信登录，如果是微信登录，并且用的是支付宝支付，就提示跳转
//            $is_weixin = $this->is_weixin();
//            echo $is_weixin;exit;
            if($is_weixin && ($payment_info['payment_code'] == 'alipaymobile' || $payment_info['payment_code'] == 'alipay' )){
                $this->display('pay_in_weixin.html');
                return;
            }
            /* 生成支付URL或表单 */
            $payment    = $this->_get_payment($order_info['payment_code'], $payment_info);
            $payment_form = $payment->get_payform($order_info);

            /* 货到付款，则显示提示页面 */
            if ($payment_info['payment_code'] == 'cod')
            {
                $this->show_message('cod_order_notice',
                    'view_order',   'index.php?app=buyer_order',
                    'close_window', 'javascript:window.close();'
                );

                return;
            }

            /* 线下付款的 */
            if (!$payment_info['online'])
            {
                $this->_curlocal(
                    Lang::get('post_pay_message')
                );
            }

            /* 跳转到真实收银台 */
            $this->_config_seo('title', Lang::get('cashier'));
            $this->assign('payform', $payment_form);
            $this->assign('payment', $payment_info);
            $this->assign('order', $order_info);
            header('Content-Type:text/html;charset=' . CHARSET);
            $this->display('cashier.payform.html');
        }
        }
    }

    /**
     *    确认支付
     *
     *    @author    Garbin
     *    @return    void
     */
    function goto_pay()
    {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        $use_coin = $_POST['use_coin'];
        if (!$order_id)
        {
            $this->show_warning('no_such_order');

            return;
        }
        if (!$payment_id)
        {
            $this->show_warning('no_such_payment');

            return;
        }
        $order_model =& m('order');
        $order_info  = $order_model->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'));
        if (empty($order_info))
        {
            $this->show_warning('no_such_order');

            return;
        }
        
        #可能不合适
        if ($order_info['payment_id'])
        {
            $this->_goto_pay($order_id);
            return;
        }

        /* 验证支付方式 */
        $payment_model =& m('payment');
        $payment_info  = $payment_model->get($payment_id);
        //线上支付优惠
        $order_discount_data = $this->_online_pay_discount($order_info['order_amount']);
        $order_discount = $order_discount_data['discount'];
        //如果使用金币抵扣并且选择线上支付，就修改订单金额，如果使用线下支付，提示不能使用金币抵扣,并且如果线上支付的，直接计算出支付额
        if($use_coin > 0) {
            if ($payment_info['is_online'] == 1) {
                $order_amount = $order_discount-$use_coin; //订单金额等于线上支付折扣后减去抵扣金额
                $order_info['discount'] += $order_info['order_amount'] - $order_amount;
                //扣除客户金币并且记录
                $used_coin = $_POST['use_coin']*80;
                $member_model = & m('member');
                $member = $member_model->get("user_id =".$order_info['buyer_id']);
                $member['coin'] -= ($used_coin);
                $coin_log_model = & m('coin_log');
                $new_log = array(
                    'reason' => "使用".$used_coin."金币抵扣".$_POST['use_coin']."现金",
                    'lanyu_coin' => '-'.$used_coin,
                    'member_id' => $_SESSION['user_info']['user_id'],
                    'add_time' => gmtime(),
                    'order_id' => $order_id,
                );
                $member_model->edit($member['user_id'],$member);
                $coin_log_model->add($new_log);
            } else {
                $this->show_message("金币抵扣只针对线上支付有效！");
                return;
            }
        }else{ //如果没有选择金币抵扣，线上支付价格为优惠后价格，线下支付为原价
            if ($payment_info['is_online'] == 1) {
                $order_amount = $order_discount;
                $order_info['discount'] = $order_info['order_amount'] - $order_amount;
            }else{
                $order_amount = $order_info['order_amount'];
            }
        }
//        print_r($payment_info);exit;
        if (!$payment_info)
        {
            $this->show_warning('no_such_payment');

            return;
        }

        /* 保存支付方式 */
        $edit_data = array(
            'payment_id'    =>  $payment_info['payment_id'],
            'payment_code'  =>  $payment_info['payment_code'],
            'payment_name'  =>  $payment_info['payment_name'],
            'order_amount' => $order_amount,
            'discount' => $order_info['discount'],
        );
        /* 如果是货到付款，则改变订单状态 */
        if ($payment_info['payment_code'] == 'cod')
        {
            $edit_data['status']    =   ORDER_SUBMITTED;
        }

        $order_model->edit($order_id, $edit_data);

        /* 开始支付 */
        $this->_goto_pay($order_id);
    }

    /**
     *    线下支付消息
     *
     *    @author    Garbin
     *    @return    void
     */
    function offline_pay()
    {
        if (!IS_POST)
        {
            return;
        }
        $order_id       = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $pay_message    = isset($_POST['pay_message']) ? trim($_POST['pay_message']) : '';
        if (!$order_id)
        {
            $this->show_warning('no_such_order');
            return;
        }
        if (!$pay_message)
        {
            $this->show_warning('no_pay_message');

            return;
        }
        $order_model =& m('order');
        $order_info  = $order_model->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'));
        if (empty($order_info))
        {
            $this->show_warning('no_such_order');

            return;
        }
        $edit_data = array(
            'pay_message' => $pay_message
        );

        $order_model->edit($order_id, $edit_data);

        /* 线下支付完成并留下pay_message,发送给卖家付款完成提示邮件 */
        $model_member =& m('member');
        $seller_info   = $model_member->get($order_info['seller_id']);
        $mail = get_mail('toseller_offline_pay_notify', array('order' => $order_info, 'pay_message' => $pay_message));
        $this->_mailto($seller_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

        $this->show_message('pay_message_successed',
            'view_order',   'index.php?app=buyer_order',
            'close_window', 'javascript:window.close();');
    }

    function _goto_pay($order_id)
    {
        header('Location:index.php?app=cashier&order_id=' . $order_id);
    }
    
    //获取终端信息7为pc 8为移动
    function _get_terminal() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $mobile_agents = array("240x320", "acer", "acoon", "acs-", "abacho", "ahong", "airness", "alcatel", "amoi",
            "android", "anywhereyougo.com", "applewebkit/525", "applewebkit/532", "asus", "audio",
            "au-mic", "avantogo", "becker", "benq", "bilbo", "bird", "blackberry", "blazer", "bleu",
            "cdm-", "compal", "coolpad", "danger", "dbtel", "dopod", "elaine", "eric", "etouch", "fly ",
            "fly_", "fly-", "go.web", "goodaccess", "gradiente", "grundig", "haier", "hedy", "hitachi",
            "htc", "huawei", "hutchison", "inno", "ipad", "ipaq", "iphone", "ipod", "jbrowser", "kddi",
            "kgt", "kwc", "lenovo", "lg ", "lg2", "lg3", "lg4", "lg5", "lg7", "lg8", "lg9", "lg-", "lge-", "lge9", "longcos", "maemo",
            "mercator", "meridian", "micromax", "midp", "mini", "mitsu", "mmm", "mmp", "mobi", "mot-",
            "moto", "nec-", "netfront", "newgen", "nexian", "nf-browser", "nintendo", "nitro", "nokia",
            "nook", "novarra", "obigo", "palm", "panasonic", "pantech", "philips", "phone", "pg-",
            "playstation", "pocket", "pt-", "qc-", "qtek", "rover", "sagem", "sama", "samu", "sanyo",
            "samsung", "sch-", "scooter", "sec-", "sendo", "sgh-", "sharp", "siemens", "sie-", "softbank",
            "sony", "spice", "sprint", "spv", "symbian", "tablet", "talkabout", "tcl-", "teleca", "telit",
            "tianyu", "tim-", "toshiba", "tsm", "up.browser", "utec", "utstar", "verykool", "virgin",
            "vk-", "voda", "voxtel", "vx", "wap", "wellco", "wig browser", "wii", "windows ce",
            "wireless", "xda", "xde", "zte");
        $is_mobile = 7;
        foreach ($mobile_agents as $device) {
            if (stristr($user_agent, $device)) {
                $is_mobile = 8;
                break;
            }
        }
        return $is_mobile;
    }
    
    function is_weixin()
    { 
        if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
            return true;
        }  
            return false;
    }

    function _online_pay_discount($order_amount){
        $is_weixin = $this->weixin();
        if($is_weixin){
            $grade = $_SESSION['user_info']['sgrade'];
            switch ($grade) {
                case 5:
                    $discount = $order_amount;
                    $type = "VIP会员";
                    break;
                case 2 :
                    $discount = $order_amount;
                    $type = "金卡会员";
                    break;
                case 3 :
                    $discount = $order_amount;
                    $type = "铂金会员";
                    break;
            }
        }else {
            $grade = $_SESSION['user_info']['sgrade'];
            switch ($grade) {
                case 5:
                    $discount = $order_amount * 0.99;
                    $type = "VIP会员";
                    break;
                case 2 :
                    $discount = $order_amount * 0.985;
                    $type = "金卡会员";
                    break;
                case 3 :
                    $discount = $order_amount * 0.98;
                    $type = "铂金会员";
                    break;
            }
        }
        $return_data = array(
            'discount' => $discount,
            'type' => $type,
        );
        return $return_data;
//        print_r($return_data);exit;
    }

    function weixin(){
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        if (strpos($user_agent, 'MicroMessenger') === false) {
            // 非微信浏览器禁止浏览
            return false;
        } else {
            // 微信浏览器，允许访问
            return true;
        }
    }

}

?>
