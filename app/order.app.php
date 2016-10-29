<?php

/**
 *    售货员控制器，其扮演实际交易中柜台售货员的角色，你可以这么理解她：你告诉我（售货员）要买什么东西，我会询问你你要的收货地址是什么之类的问题
 *        并根据你的回答来生成一张单子，这张单子就是“订单”
 *
 *    @author    Garbin
 *    @param    none
 *    @return    void
 */
class OrderApp extends ShoppingbaseApp {

    /**
     *    填写收货人信息，选择配送，支付方式。
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function index() {
        $goods_info = $this->_get_goods_info();
        if(empty($goods_info)){
            show_message('您的购物车没有任何商品','','index.php');
            return;
        }
        //判断账号积分是否足够花费，不足够的话，就不能生成订单
        $user_id = $_SESSION['user_info']['user_id'];
        $member_model = & m('member');
        $member = $member_model->get('user_id =' . $user_id);
        //由于有些客户积分小于0就不能下任何订单，所以增加一个判断，客户积分大于0而且小于花费需要积分，就不能下单
        $point_total = 0;
            foreach($goods_info['items'] as $good):
                if($good['point_price'] != 0){
                    $point_total += $good['point_price']*$good['quantity'];
                }
            endforeach;
        if ($member['coin'] < $point_total && $member['coin'] > 0) {
            $this->show_warning('您账号金币不足，不能生成订单');
            return;
        }

        if ($goods_info === false) {
            /* 购物车是空的 */
            $this->show_warning('goods_empty');

            return;
        }

        /*  检查库存 */
        $goods_beyond = $this->_check_beyond_stock($goods_info['items']);
        if ($goods_beyond) {
            $str_tmp = '';
            foreach ($goods_beyond as $goods) {
                $str_tmp .= '<br /><br />' . $goods['goods_name'] . '&nbsp;&nbsp;' . $goods['specification'] . '&nbsp;&nbsp;' . Lang::get('stock') . ':' . $goods['stock'];
            }
            $this->show_warning(sprintf(Lang::get('quantity_beyond_stock'), $str_tmp));
            return;
        }
        $sgrade = $_SESSION['user_info']['sgrade'];
        if (empty($sgrade)) {
            $sgrade = 1;
        }
        $policy_type_model = & m("policy_type");
        $mount_policy_model = & m('mount_policy');
        if (!IS_POST) {
            /* 根据商品类型获取对应订单类型 */
            $goods_type = & gt($goods_info['type']);
            $order_type = & ot($goods_info['otype']);

            /* 显示订单表单 */
            $form = $order_type->get_order_form($goods_info['store_id']);
            if ($form === false) {
                $this->show_warning($order_type->get_error());

                return;
            }
            $this->_curlocal(
                    LANG::get('create_order')
            );
            $this->_config_seo('title', Lang::get('confirm_order') . ' - ' . Conf::get('site_title'));

            import('init.lib');
            $this->assign('coupon_list', Init_OrderApp::get_available_coupon($goods_info['store_id']));
//			print_r(Init_OrderApp::get_available_coupon($goods_info['store_id']));exit;
            /*
             * 20160620 查配送方式
             */
            $shipping_model = & m('shipping');
            $store_shippings = $shipping_model->find("store_id =".$goods_info['store_id']);
            $this->assign('store_shippings', $store_shippings);
            //如果总价为0，则计算总蓝币
            foreach($goods_info['items'] as $key=>$goods):
                if($goods['subtotal'] == 0){
                    $goods_info['items'][$key]['subtotal'] = $goods['point_price']*$goods['quantity'];
                }
            endforeach;

            //begin for 大姨妈
            //查等级对应的大姨妈政策，并且查这个月是否已经用了
            $mount_policy = $mount_policy_model->find("sgrade_id =".$sgrade." AND store_id =".$_GET['store_id']);
            $member_mount_policy = array();
            if(!empty($mount_policy)){
                foreach($mount_policy as $val):
                    $member_mount_model = & m("member_mount_policy");
                    $year = date("Y",gmtime());
                    $mount = date("m",gmtime());
                    $member_mount = $member_mount_model->get("store_id =".$_SESSION['user_info']['user_id']." AND mount_policy =".$val['id']." AND year=".$year." AND mount =".$mount);
                    if(empty($member_mount)){
                        $member_mount_policy[] = $policy_type_model->get("id =".$val['policy_id']);
                    }
                endforeach;
            }
            $this->assign('member_mount_policy',$member_mount_policy);
            //end for 大姨妈
            /*
             * 金币抵扣现金，计算客户金币可以最高抵扣的金额，高于订单金额取订单金额，不高于区金币总额
             */
            if($member['coin'] > 0) {
                $coin = $member['coin'] / 80;  //金币低现金
                if ($coin <= $goods_info['amount'] && $coin > 0) {
                    $use_coin = $coin * 80;
                } else {
                    $use_coin = $goods_info['amount'] * 80;
                }
                $to_price = $use_coin / 80;
                $this->assign('use_coin', $use_coin);
                $this->assign('to_price', $to_price);
            }

            $this->assign('goods_info', $goods_info);
            $this->assign($form['data']);
            $this->display($form['template']);  //order.form.html
        } else {
            /* 在此获取生成订单的两个基本要素：用户提交的数据（POST），商品信息（包含商品列表，商品总价，商品总数量，类型），所属店铺 */
            $store_id = isset($_GET['store_id']) ? intval($_GET['store_id']) : 0;
            if ($goods_info === false) {
                /* 购物车是空的 */
                $this->show_warning('goods_empty');

                return;
            }
            //查看是否有大姨妈政策，有的话再做处理
            $policy = $_POST['policy'];
            if(!empty($policy)){
                /*
                 * 清订单原来赠送的商品（price=0&point_price=0）
                 */
//                print_r($goods_info);exit;
                $goods_quantity = 0;
                foreach($goods_info['items'] as $key=>$val){
                    if($val['price'] == 0 && $val['point_price'] == 0){
                        unset($goods_info['items'][$key]);
                    }else{
                        $goods_quantity += $val['quantity'];
                    }
                }
                echo $goods_quantity;
                //查对应的赠送规则，将赠送的商品添加到订单里面
                $policy_type = $policy_type_model->get("id =".$policy);
                //判断商品数量是否满足赠送条件，不满足，就返回提示
                if($policy_type['coe'] > $goods_quantity){
                    $this->show_message('对不起，您购买的数量不满足赠送条件','请返回重新下单！','index.php?app=cart');
                    return;
                }
                //计算赠送数量
                $give = floor($goods_quantity/$policy_type['coe'])*$policy_type['give'];
                $goods_model = & m('goods');
                $give_goods_id = 2578;
                $give_goods = $goods_model->get("goods_id =".$give_goods_id);
                $goods_spec_model = & m('goodsspec');
                $give_goods_spec = $goods_spec_model->get("goods_id =".$give_goods_id);
                $goods_info['items'][] = array(
                    'goods_id' => $give_goods['goods_id'],
                    'goods_name' => $give_goods['goods_name'],
                    'spec_id' => $give_goods_spec['spec_id'],
                    'specification' => '箱',
                    'price' => 0,
                    'quantity' => $give,
                    'goods_image' => $give_goods['default_image'],
//                    'subtotal' => $subtotal,
//                    'stock' => $goodsspec[$spec_id]['stock'],
                );
                //记录用户本月来了大姨妈
                $mount_policy = $mount_policy_model->get("sgrade_id =".$sgrade." AND store_id =".$store_id." AND policy_id =".$policy_type['id']);
                $member_mount_policy_model = & m("member_mount_policy");
                $new_mount_log = array(
                    'mount_policy' => $mount_policy['id'],
                    'year' => date("Y",gmtime()),
                    'mount' => date("m",gmtime()),
                    'store_id' => $_SESSION['user_info']['user_id'],
                    'add_time' => gmtime(),
                );
                $member_mount_policy_model->add($new_mount_log);
            }
            /* 优惠券数据处理 */
            if ($goods_info['allow_coupon'] && isset($_POST['coupon_sn']) && !empty($_POST['coupon_sn'])) {
                $coupon_sn = trim($_POST['coupon_sn']); //现金卷号码
                $use_nums = $_POST['use_nums']; //使用张数
                $coupon_mod = & m('couponsn');
                $coupon = $coupon_mod->get(array(
                    'fields' => 'coupon.*,couponsn.remain_times',
                    'conditions' => "coupon_sn.coupon_sn = '{$coupon_sn}' AND coupon.store_id = " . $store_id,
                    'join' => 'belongs_to_coupon'));
                if (empty($coupon)) {
                    $this->show_warning('involid_couponsn');
                    exit;
                }
                if ($coupon['remain_times'] < 1) {
                    $this->show_warning("times_full");
                    exit;
                }
                $time = gmtime();
                if ($coupon['start_time'] > $time) {
                    $this->show_warning("coupon_time");
                    exit;
                }

                if ($coupon['end_time'] < $time) {
                    $this->show_warning("coupon_expired");
                    exit;
                }
                if ($coupon['min_amount'] > $goods_info['amount']) {
                    $this->show_warning("amount_short");
                    exit;
                }
                unset($time);
                //完全查不到coupon的数量在哪里减，由于现在新家一个coupon可以使用多张，只能现在这里减去使用张数-1的数量
                $nums = $coupon['remain_times'] - $use_nums + 1;
                $coupon_mod->edit($coupon['coupon_sn'], "remain_times =" . $nums);
                $goods_info['discount'] += $coupon['coupon_value'] * $use_nums;  //订单中的优惠
            }
            /* 根据商品类型获取对应的订单类型 */
            $goods_type = & gt($goods_info['type']);
            $order_type = & ot($goods_info['otype']);
            $first_order = $this->_first_order($_SESSION['user_info']['user_id']);
            /* 将这些信息传递给订单类型处理类生成订单(你根据我提供的信息生成一张订单) */
            $order_id = $order_type->submit_order(array(
                'goods_info' => $goods_info, //商品信息（包括列表，总价，总量，所属店铺，类型）,可靠的!
                'post' => $_POST, //用户填写的订单信息
            ));


            if (!$order_id) {
                $this->show_warning($order_type->get_error());

                return;
            }

            //            echo $order_id;exit;
            //获取到$order_id,根据order_id查找订单下面的所有商品，
            $order_goods_model = & m('ordergoods');
            $goods_model = & m('goods');
            $goods_for_order = $order_goods_model->find('order_id =' . $order_id);
            $total_point_price = 0;  //总花费的积分
            //遍历这些涉及的商品，然后计算出积分总和
            foreach ($goods_for_order as $goods):
                $good = $goods_model->get("goods_id =" . $goods['goods_id']);
                //如果该产品需要花费积分，则叠加
                if ($good['point_price'] > 0) {
                    $total_point_price += $good['point_price'] * $goods['quantity'];
                }
            endforeach;
            $order_model = & m('order');
            //判断客户是微信下单还系非微信下单，k3_state字段没有用，直接拿来用，不另外增加字段
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            if (strpos($user_agent, 'MicroMessenger') === false) {
                $order_model->edit($order_id, array('k3_state' => 12));  //非微信下单
            }else{
                $order_model->edit($order_id, array('k3_state' => 13));  //微信下单
            }
            //如果客户选择了金币抵扣现金，直接将抵扣部分扣除
            if($_POST['use_coin'] > 0){
                $order = $order_model->get("order_id =".$order_id);
                $order['order_amount'] -= $_POST['use_coin'];
                $order['discount'] = $_POST['use_coin'];
                //扣除客户金币并且记录
                $used_coin = $_POST['use_coin']*80;
                $member['coin'] -= ($used_coin);
                $coin_log_model = & m('coin_log');
                $new_log = array(
                    'reason' => "使用".$used_coin."金币抵扣".$_POST['use_coin']."现金",
                    'lanyu_coin' => $used_coin,
                    'member_id' => $_SESSION['user_info']['user_id'],
                    'add_time' => gmtime(),
                    'order_id' => $order_id,
                );
                $member_model->edit($member['user_id'],$member);
                $coin_log_model->add($new_log);
                $order_model->edit($order_id,$order);
            }
            //如果花费积分不为0，则将积分添加到订单里面,并且直接扣除相应的积分
            if ($total_point_price != 0) {
                $use_coin = $member['coin'] - $total_point_price;
                $member_model->edit($user_id, array('coin' => $use_coin));
                $order_model->edit($order_id, array('point_price' => $total_point_price));
//                //记录扣分记录
//                $point_record = array(
//                    'user_id' => $user_id,
//                    'user_name' => $member['user_name'],
//                    'created' => time(),
//                    'status' => 1,
//                    'operator' => '系统',
//                    'event' => '购买了积分兑换商品,订单号：' . $order_id,
//                    'point' => $total_point_price,
//                );
//                $remember_point_model = & m('redeem_points');
//                $remember_point_model->add($point_record);
                //记录金币扣除记录
                $order = $order_model->get("order_id =".$order_id);
                $coin_log = array(
                    'reason' => '用金币购买了商城礼品，订单号为'.$order['order_sn'],
                    'lanyu_coin' => '-'.$total_point_price,
                    'member_id' => $_SESSION['user_info']['user_id'],
                    'add_time' => gmtime(),
                    'order_id' => $order['order_id'],
                );
                $coin_log_model = & m('coin_log');
                $coin_log_model->add($coin_log);
            }


            /*  检查是否添加收货人地址  */
            if (isset($_POST['save_address']) && (intval(trim($_POST['save_address'])) == 1)) {
                $data = array(
                    'user_id' => $this->visitor->get('user_id'),
                    'consignee' => trim($_POST['consignee']),
                    'region_id' => $_POST['region_id'],
                    'region_name' => $_POST['region_name'],
                    'address' => trim($_POST['address']),
                    'zipcode' => trim($_POST['zipcode']),
                    'phone_tel' => trim($_POST['phone_tel']),
                    'phone_mob' => trim($_POST['phone_mob']),
                );
                $model_address = & m('address');
                $model_address->add($data);
            }
            /* 下单完成后清理商品，如清空购物车，或将团购拍卖的状态转为已下单之类的 */
            $this->_clear_goods($order_id);

            /* 发送邮件 */
            $model_order = & m('order');

            /* 减去商品库存 */
            $model_order->change_stock('-', $order_id);

            /* 获取订单信息 */
            $order_info = $model_order->get($order_id);

            /* 发送事件 */
            $feed_images = array();
            foreach ($goods_info['items'] as $_gi) {
                $feed_images[] = array(
                    'url' => SITE_URL . '/' . $_gi['goods_image'],
                    'link' => SITE_URL . '/' . url('app=goods&id=' . $_gi['goods_id']),
                );
            }
            $this->send_feed('order_created', array(
                'user_id' => $this->visitor->get('user_id'),
                'user_name' => addslashes($this->visitor->get('user_name')),
                'seller_id' => $order_info['seller_id'],
                'seller_name' => $order_info['seller_name'],
                'store_url' => SITE_URL . '/' . url('app=store&id=' . $order_info['seller_id']),
                'images' => $feed_images,
            ));

            $buyer_address = $this->visitor->get('email');
            $model_member = & m('member');
            $member_info = $model_member->get($goods_info['store_id']);
            $seller_address = $member_info['email'];

            /* 发送给买家下单通知 */
//            $buyer_mail = get_mail('tobuyer_new_order_notify', array('order' => $order_info));
            //$this->_mailto($buyer_address, addslashes($buyer_mail['subject']), addslashes($buyer_mail['message']));
            $device_model = & m('devices');
            $device = $device_model->get("user_id =".$order_info['buyer_id']);
            if(!empty($device['serial_id']) && $device['device_type'] == 'mqtt'){
                $mqtt = new Mqtt();
                $mqtt->send($device['serial_id'],'您已经成功下单，请进行支付！');
            }else if(!empty($device['serial_id']) && $device['device_type'] == 'apns'){
                $apns = new Apns();
                $apns->send($device['serial_id'],'您已经成功下单，请进行支付！');
            }
//            $mqtt_massage = new Mqtt();
//            $device_model = & m('devices');
//            $device = $device_model->get("device_type = 'mqtt' AND user_id =".$_SESSION['user_info']['user_id']." order by 'last_login desc'");
//            if(!empty($device['serial_id']))
//            $mqtt_massage->send($device['serial_id'],'您已经成功下单，请进行支付！');
            /* 发送给卖家新订单通知 */
//            $seller_mail = get_mail('toseller_new_order_notify', array('order' => $order_info));
//            $this->_mailto($seller_address, addslashes($seller_mail['subject']), addslashes($seller_mail['message']));
            //增加K3对应表的记录--只针对蓝羽公司
            $member_model = & m('member');
            $member = $member_model->get('user_id ='.$order_info['buyer_id']);
            if($order_info['seller_id'] == 7){
                $this->_order_in_k3($member['k3_code'],$member['user_name'],$order_info,'未支付');
                //查客户是否首次下单，是的话，就发短信给当区经理
                if($first_order){
                    $qq_message_model = & m('qq_service');
                    $r_id = $member['region_id'];
                    if(empty($r_id)){
                        $r_id = 1;
                    }
                    $qq_message = $qq_message_model->get("store_id =".$order_info['seller_id']." AND region_id =".$r_id);
                    $tel = $qq_message['telephone'];

                    //主帐号,对应开官网发者主账号下的 ACCOUNT SID
                    $accountSid = '8a48b5514f73ea32014f87f34700281b';
                    //主帐号令牌,对应官网开发者主账号下的 AUTH TOKEN
                    $accountToken = '0f1b1347b19141bc8ccf20de6da3ad51';
                    //应用Id，在官网应用列表中点击应用，对应应用详情中的APP ID
                    //在开发调试的时候，可以使用官网自动为您分配的测试Demo的APP ID
                    $appId = '8a48b5514f73ea32014f87f68dd62838';
                    //请求地址
                    //沙盒环境（用于应用开发调试）：sandboxapp.cloopen.com
                    //生产环境（用户应用上线使用）：app.cloopen.com
                    $serverIP = 'app.cloopen.com';
                    //请求端口，生产环境和沙盒环境一致
                    $serverPort = '8883';
                    //REST版本号，在官网文档REST介绍中获得。
                    $softVersion = '2013-12-26';
//            global $accountSid, $accountToken, $appId, $serverIP, $serverPort, $softVersion;
                    $rest = new REST($serverIP, $serverPort, $softVersion);
                    $rest->setAccount($accountSid, $accountToken);
                    $rest->setAppId($appId);


                    $to = $tel;
                    $data = array($member['user_name'],$member['k3_code']);
                    $tempId = 111061;
                    $rest->sendTemplateSMS($to, $data, $tempId);
                }
//                $order_act_model = & m('order_act');
//                $new_act = array(
//                    'Order_id' => $order_info['order_sn'],
//                    'Act_State' => 11,
//                    'Act_Time' => date("Y-m-d h:i:s", time()),
//                );
//                $order_act_model->add($new_act);
//                $this->_order_in_k3($member['ke_code'],);
            }
            /* 更新下单次数 */
            $model_goodsstatistics = & m('goodsstatistics');
            $goods_ids = array();
            foreach ($goods_info['items'] as $goods) {
                $goods_ids[] = $goods['goods_id'];
            }
            $model_goodsstatistics->edit($goods_ids, 'orders=orders+1');

			// order rule logs.
			$order_rule_log_model = & m('order_rule_log');
			$store_id = $goods_info['store_id']; 
			$user_id  = $order_info['buyer_id'];

			if ( $goods_info['rule_log'] )
			foreach ( $goods_info['rule_log'] as $k => $v ) {
				$log = array(
					'order_id' => $order_id,
					'order_rule_id' => $k,
					'store_id' => $goods_info['store_id'],
					'user_id'  => $order_info['buyer_id'],
					'affect_lines' => $v,
				);
				$order_rule_log_model->add($log);
			};
            
            /* 到收银台付款 */
            header('Location:index.php?app=cashier&order_id=' . $order_id);
        }
    }

    /**
     *    获取外部传递过来的商品
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function _get_goods_info() {
        $return = array(
            'items' => array(), //商品列表
            'quantity' => 0, //商品总量
            'amount' => 0, //商品总价
            'total_point' => 0, //商品积分
            'store_id' => 0, //所属店铺
            'store_name' => '', //店铺名称
            'type' => null, //商品类型
            'otype' => 'normal', //订单类型
            'allow_coupon' => true, //是否允许使用优惠券
            'point_price' => 0.00, //所要花费的积分
        );
        switch ($_GET['goods']) {
            case 'groupbuy':
                /* 团购的商品 */
                $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
                $user_id = $this->visitor->get('user_id');
                if (!$group_id || !$user_id) {
                    return false;
                }
                /* 获取团购记录详细信息 */
                $model_groupbuy = & m('groupbuy');
                $groupbuy_info = $model_groupbuy->get(array(
                    'join' => 'be_join, belong_store, belong_goods',
                    'conditions' => $model_groupbuy->getRealFields("groupbuy_log.user_id={$user_id} AND groupbuy_log.group_id={$group_id} AND groupbuy_log.order_id=0 AND this.state=" . GROUP_FINISHED),
                    'fields' => 'store.store_id, store.store_name, goods.goods_id, goods.goods_name, goods.default_image, groupbuy_log.quantity, groupbuy_log.spec_quantity, this.spec_price',
                ));

                if (empty($groupbuy_info)) {
                    return false;
                }

                /* 库存信息 */
                $model_goodsspec = &m('goodsspec');
                $goodsspec = $model_goodsspec->find('goods_id=' . $groupbuy_info['goods_id']);

                /* 获取商品信息 */
                $spec_quantity = unserialize($groupbuy_info['spec_quantity']);
                $spec_price = unserialize($groupbuy_info['spec_price']);
                $amount = 0;
                $groupbuy_items = array();
                $goods_image = empty($groupbuy_info['default_image']) ? Conf::get('default_goods_image') : $groupbuy_info['default_image'];
                foreach ($spec_quantity as $spec_id => $spec_info) {
                    $the_price = $spec_price[$spec_id]['price'];
                    $subtotal = $spec_info['qty'] * $the_price;
                    $groupbuy_items[] = array(
                        'goods_id' => $groupbuy_info['goods_id'],
                        'goods_name' => $groupbuy_info['goods_name'],
                        'spec_id' => $spec_id,
                        'specification' => $spec_info['spec'],
                        'price' => $the_price,
                        'quantity' => $spec_info['qty'],
                        'goods_image' => $goods_image,
                        'subtotal' => $subtotal,
                        'stock' => $goodsspec[$spec_id]['stock'],
                    );
                    $amount += $subtotal;
                }

                $return['items'] = $groupbuy_items;
                $return['quantity'] = $groupbuy_info['quantity'];
                $return['amount'] = $amount;
                $return['store_id'] = $groupbuy_info['store_id'];
                $return['store_name'] = $groupbuy_info['store_name'];
                $return['type'] = 'material';
                $return['otype'] = 'groupbuy';
                $return['allow_coupon'] = false;
                break;
            default:
                /* 从购物车中取商品 */
                $_GET['store_id'] = isset($_GET['store_id']) ? intval($_GET['store_id']) : 0;
                $store_id = $_GET['store_id'];
                if (!$store_id) {
                    return false;
                }


                $cart_model = & m('cart');

                $cart_items = $cart_model->find(array(
                    'conditions' => "user_id = " . $this->visitor->get('user_id') . " AND store_id = {$store_id} AND session_id='" . SESS_ID . "'",
                    'join' => 'belongs_to_goodsspec',
                ));
                if (empty($cart_items)) {
                    return false;
                }


                $store_model = & m('store');
                $store_info = $store_model->get($store_id);

                foreach ($cart_items as $rec_id => $goods) {

                    //查是否有活动，是的话直接使用活动价替换了当前价格
                    $special_model = & m('special');
                    $special = $special_model->get('start <' . gmtime() . " AND end >" . gmtime());
                    if (!empty($special)) {
                        $special_goods_model = & m('special_goods');
                        $special_goods = $special_goods_model->get('special_id =' . $special['special_id'] . " AND spec_id =" . $goods['spec_id']);
                        if (!empty($special_goods['price'])) {
                            $goods['price'] = $special_goods['price'];
                            $cart_items[$rec_id]['price'] =  $special_goods['price'];
                        }
                    }

                    $return['point_price'] += $goods['point_price'];       //商品所需要的总积分
                    $return['quantity'] += $goods['quantity'];                      //商品总量
                    $return['amount'] += $goods['quantity'] * $goods['price'];    //商品总价
                    $return['total_point'] += $goods['point'] * $goods['quantity'];
                    $cart_items[$rec_id]['subtotal'] = $goods['quantity'] * $goods['price'];   //小计
                    empty($goods['goods_image']) && $cart_items[$rec_id]['goods_image'] = Conf::get('default_goods_image');

					////////////////////////////////////////////////////////////////////////////
					// Rin @ 12/24/2015
					// Item cycle - Load rules.
					$order_rule_store = & m('order_rule_store');
					$order_rule_store = & db();

					// Fu*k, fu*k, fu*k!!!!!!!!
					// 
					//$order_sub_rules = $order_rule_store->find( array(
					//	'conditions' => 
					//		'order_rule_products.spec_id = '.$goods['spec_id'] /*.' AND '. 'start <' . gmtime() . " AND end >" . gmtime()*/ .
					//		' AND ( user_id = 0 OR user_id is NULL OR user_id = '.$this->visitor->get('user_id').' )'
					//		,
					//	'join' => 'has_rule_products',
					//	'fields' => 'order_rule_products.*, this.*',
					//	'order' => 'priority DESC',
					//));	

					$order_sub_rules = $order_rule_store->getall(
						"Select rp.sub_rule_id as rp_sub_rule_id, rp.goods_id as rp_goods_id, rp.spec_id as rp_spec_id, rp.spec_name as rp_spec_name, rs.* ".
						"From ecm_order_rule_store As rs, ecm_order_rule_products As rp ".
						"Where rp.sub_rule_id = rs.id ".
						" AND enabled = 1".
						" AND rp.spec_id = ".$goods['spec_id'].
						" AND ( rs.user_id = 0 OR rs.user_id is NULL OR rs.user_id = ".$this->visitor->get('user_id')." )".
                                                " AND rs.enabled = 1".
						" Order By priority DESC"
					);

					$order_rule = & m('order_rule');
					foreach( $order_sub_rules as $osrs ) {
						$or = $order_rule->get( 'id='.$osrs['order_rule_id'] );
						if($or) {
							$arguments = json_decode( $osrs['arguments'], true );

							// priority
							if( !$procedured_goods[$osrs[order_rule_id]][$osrs[rp_spec_id]] ) {
								// execute
								@eval( $or[item_cycle] );	
							}
						}
					}
                }

                $return['items'] = $cart_items;
                $return['store_id'] = $store_id;
                $return['store_name'] = $store_info['store_name'];
                $return['store_im_qq'] = $store_info['im_qq']; // tyioocom 
                $return['type'] = 'material';
                $return['otype'] = 'normal';


				/**********************************************************************************
				 * Rin: Order rules start
				 *
				 * Input Sample:
				 *
				 * (1)Arguments List:
				 *		{"rules_amount":"数量下限", "rules_discount": "折扣", 
				 *		 "rules_discount_mode": "折扣方式", "rules_gift_quantity": "赠送数量"}
				 *
				 * (2)Item Cycle:
				 *		if( intval($arguments["rules_amount"]) <= $goods['quantity'] ) {
				 *			// present
				 *			$present_good = $this->default_cart_item($return['items'], $goods['goods_id']);
				 *			$present_good['price'] = $goods['price'];
				 *			$present_good['spec_id'] = $goods['spec_id'];
				 *			$present_good['specification'] = $goods['specification'];
				 *
				 *			// addition.
				 *			$present_good['goods_name'] .= '(赠送产品)';
				 *			$present_good['quantity'] = intval($arguments["rules_gift_quantity"]);
				 *
				 *			// add present, here use $cart_items
				 *			array_push($cart_items, $present_good );
				 *
				 *			// set priority
				 *			$procedured_goods[$osrs[goods_id]] = 1;
				 *		}
				 *
				 * (3)Main Condition:
				 *		[{"logic": "AND", "model": "order_rule", "conditions": "{$RETURN_AMOUNT}<0", "fields": "id", "join": ""}]
				 *
				 * (4)Operation:
				 *		// discount
				 *		$return['amount'] -= 100;
				 *		$return['amount'] *= .8; // 20% off
				 *
				 *		// present
				 *		$present_good = $this->default_cart_item($return['items'],104);
				 *		$present_good['goods_name'] .= '(赠送)';
				 *		$present_good['quantity'] = 1;
				 *
				 *		// add present, here use $return['items']
				 *		array_push($return['items'], $present_good );
				 */
				/*
				// present orignal
				array_push($return['items'],
					Array ( 
						'rec_id' => 0, 
						'user_id' => 12,
						'session_id' => '500d3be4f054d0773a0d6d06d3646e12',
						'store_id' => 7, 
						'goods_id' => 104, 
						'goods_name' => '蓝羽第一品牌中性免胶粉300g(赠送)', 
						'spec_id' => 9252, 
						'specification' => '产品规格（型号）:1*60/箱', 
						'price' => 1680.0, 
						'quantity' => 2, 
						'point' => 0.01 ,
						'goods_image' => 'data/files/store_7/goods_32/small_201501201447124762.JPG',
						'additional_info' => '',
						'point_price' => 10.00, 
						'spec_1' => '1*60/箱',
						'spec_2' => 5, 
						'color_rgb' => '', 
						'original_price' => 0.00, 
						'stock' => 999840, 
						'sku' => '5.12.02.20.122.22', 
						'subtotal' => 0, 
					)
				);
				*/

				// get rules from database and apply it.
				$order_rule  = & m('order_rule');
				$order_rules = $order_rule->findAll();
				$db = &db();

				// pre-order variable define here.
				$RETURN_AMOUNT = $return['amount'];

				foreach ( $order_rules as $im ) {

					// condition sample.
					// [ {"logic": "AND", "model": "order_rule", "conditions": "{$RETURN_AMOUNT}>1000", "fields": "id", "join": ""}, 
					//   {"logic": "OR", "model": "order_rule", "conditions": "id=1", "fields": "*", "join": ""} ]
					$result = false;
					$conditions = json_decode( $im['condition'], true );
					foreach( $conditions as $key => $condition ) {
						// $variable transformer
						$match = array();  
						preg_match_all('/{\$(.*?)}/', $condition['conditions'], $match);  
						foreach($match[1] as $key => $value) {  
							if(isset($$value)) {  
								$condition['conditions'] = 
									str_replace($match[0][$key], $$value, $condition['conditions']);  
							}  
						}  

						// start match.
						$model = & m( $condition['model'] );
						$r = $model->find( array(
							'conditions' => $condition['conditions'],
							'fields' => $condition['fields'],
							'join' => $condition['join'],
						));

						// define first logic-value.
						if(!$key) $result = ($condition['logic'] == 'AND') ? true : false;
						switch( $condition['logic'] ) {
							default: case 'AND': 
								$result = $result && count($r);
								break;
							case 'OR': 
								$result = $result || count($r);
								break;
						}

						// debug
						//print_r( count($r) );
						//print_r( $r);
						//if ($result) print_r(empty(current($r)));
					}

					// execute operation commands.
					if($result) @eval( $im['operation'] );
				}

				// log to rule_log.
				$return['rule_log'] = $rule_log;
				//print_r($rule_log)

                break;
        }
        return $return;
    }

	// Rin: mall order rule.
	// get default cart item for modify.
	// example:
	//	$this->default_cart_item( $return['items'], $item_id); // get default item.
	//	array_push($return['items'], $new_items_array );       // write to order.
	function default_cart_item( $last_item, $goods_id ) {
		// get first items
		$first_cart_item = current($last_item);

		// get goods information.
		$sgrade = $_SESSION['user_info']['sgrade'];
		if (empty($sgrade)) $sgrade = 1;
		$goods_model = & m('goods');
		$found_good = $goods_model->get('goods_id ='.$goods_id." AND display_sgrade like '%,$sgrade,%'");
		if (empty($found_good['goods_id'])) return null;

		// return default cart arrays.
		return Array(
			'rec_id' => $first_cart_item['rec_id'], 
			'user_id' => $first_cart_item['user_id'],
			'session_id' => $first_cart_item['session_id'],
			'store_id' => $found_good['store_id'], 
			'goods_id' => $goods_id, 
			'goods_name' => $found_good['goods_name'], 
			'price' => $found_good['price'], 
			'point' => 0.01,
			'goods_image' => $found_good['default_image'],
			'additional_info' => '',
			'point_price' => $found_good['point_price'], 
			'color_rgb' => '', 
			'original_price' => 0.00, 
			'stock' => 999999, 
			//'spec_id' => 9252, 
			//'specification' => '', 
			//'spec_1' => '1*60/箱',
			//'spec_2' => 5, 
			//'sku' => '5.12.02.20.122.22', 
			'quantity' => 0, // user defined. 
			'subtotal' => 0, // user defined. 
		);
	}

    /**
     *    下单完成后清理商品
     *
     *    @author    Garbin
     *    @return    void
     */
    function _clear_goods($order_id) {
        switch ($_GET['goods']) {
            case 'groupbuy':
                /* 团购的商品 */
                $model_groupbuy = & m('groupbuy');
                $model_groupbuy->updateRelation('be_join', intval($_GET['group_id']), $this->visitor->get('user_id'), array(
                    'order_id' => $order_id,
                ));
                break;
            default://购物车中的商品
                /* 订单下完后清空指定购物车 */
                $_GET['store_id'] = isset($_GET['store_id']) ? intval($_GET['store_id']) : 0;
                $store_id = $_GET['store_id'];
                if (!$store_id) {
                    return false;
                }
                $model_cart = & m('cart');
                $model_cart->drop("store_id = {$store_id} AND session_id='" . SESS_ID . "'");
                //优惠券信息处理
                if (isset($_POST['coupon_sn']) && !empty($_POST['coupon_sn'])) {
                    $sn = trim($_POST['coupon_sn']);
                    $couponsn_mod = & m('couponsn');
                    $couponsn = $couponsn_mod->get("coupon_sn = '{$sn}'");
                    if ($couponsn['remain_times'] > 0) {
                        $couponsn_mod->edit("coupon_sn = '{$sn}'", "remain_times= remain_times - 1");
                    }
                }
                break;
        }
    }

    /**
     * 检查优惠券有效性
     */
    function check_coupon() {
        $coupon_sn = $_GET['coupon_sn'];
        $store_id = is_numeric($_GET['store_id']) ? $_GET['store_id'] : 0;
        if (empty($coupon_sn)) {
            $this->js_result(false);
        }
        $coupon_mod = & m('couponsn');
        $coupon = $coupon_mod->get(array(
            'fields' => 'coupon.*,couponsn.remain_times',
            'conditions' => "coupon_sn.coupon_sn = '{$coupon_sn}' AND coupon.store_id = " . $store_id,
            'join' => 'belongs_to_coupon'));
        if (empty($coupon)) {
            $this->json_result(false);
            exit;
        }
        if ($coupon['remain_times'] < 1) {
            $this->json_result(false);
            exit;
        }
        $time = gmtime();
        if ($coupon['start_time'] > $time) {
            $this->json_result(false);
            exit;
        }


        if ($coupon['end_time'] < $time) {
            $this->json_result(false);
            exit;
        }

        // 检查商品价格与优惠券要求的价格

        $model_cart = & m('cart');
        $item_info = $model_cart->find("store_id={$store_id} AND session_id='" . SESS_ID . "'");
        $price = 0;
        foreach ($item_info as $val) {
            $price = $price + $val['price'] * $val['quantity'];
        }
        if ($price < $coupon['min_amount']) {
            $this->json_result(false);
            exit;
        }
        $this->json_result(array('res' => true, 'price' => $coupon['coupon_value']));
        exit;
    }

    function _check_beyond_stock($goods_items) {
        $goods_beyond_stock = array();
        foreach ($goods_items as $rec_id => $goods) {
            if ($goods['quantity'] > $goods['stock']) {
                $goods_beyond_stock[$goods['spec_id']] = $goods;
            }
        }
        return $goods_beyond_stock;
    }

    //页面传coupon_sn过来，然后检查下最多可以用几多张
    function user_coupons() {
        $coupon_model = & m('coupon');
        $coupon_sn_model = & m('couponsn');
        $coupon_no = $_POST['coupon_sn'];
        $total_price = $_POST['total_price']; //总价
        //查coupon_sn后通过coupon_id查coupon，然后找到抵消，用总价除以抵消取整，就是这次可以使用的coupon上限，当然，还需要判断coupon剩余使用次数，2者区小的
        $coupon_sn = $coupon_sn_model->get("coupon_sn =" . $coupon_no);
        //如果查不到优惠券，就不存在
        if (empty($coupon_sn['coupon_sn'])) {
            echo 111;
            exit;
        }
        $now_time = time() - 28800;
        $coupon = $coupon_model->get("coupon_id =" . $coupon_sn["coupon_id"] . " AND start_time <=" . $now_time . " AND end_time >=" . $now_time);
        if (empty($coupon['coupon_id'])) {
            echo 111;
            exit;
        }
        $max_use = floor($total_price / $coupon['min_amount']);
        //判断可用次数和总价/可用金额 的大小，取小的
        if ($coupon_sn['remain_times'] < $max_use) {
            $can_use = $coupon_sn['remain_times'];
        } else {
            $can_use = $max_use;
        }
        echo "<select id='use_nums' name='use_nums' >";
        for ($i = 1; $i <= $can_use; $i++):
            echo "<option value='" . $i . "'>使用" . $i . "张</option>";
        endfor;
        echo "</select>";
        exit;
    }

    function _first_order($buyer_id){
        $order_model = & m("order");
        $order = $order_model->get("buyer_id =".$buyer_id );
        if(empty($order)){
            return true;
        }else{
            return false;
        }
    }

}


class REST {
    private $AccountSid;
    private $AccountToken;
    private $AppId;
    private $ServerIP;
    private $ServerPort;
    private $SoftVersion;
    private $Batch;  //时间戳
    private $BodyType = "xml";//包体格式，可填值：json 、xml
    private $enabeLog = true; //日志开关。可填值：true、
    private $Filename="./log.txt"; //日志文件
    private $Handle;
    function __construct($ServerIP,$ServerPort,$SoftVersion)
    {
        $this->Batch = date("YmdHis");
        $this->ServerIP = $ServerIP;
        $this->ServerPort = $ServerPort;
        $this->SoftVersion = $SoftVersion;
        $this->Handle = fopen($this->Filename, 'a');
    }

    /**
     * 设置主帐号
     *
     * @param AccountSid 主帐号
     * @param AccountToken 主帐号Token
     */
    function setAccount($AccountSid,$AccountToken){
        $this->AccountSid = $AccountSid;
        $this->AccountToken = $AccountToken;
    }


    /**
     * 设置应用ID
     *
     * @param AppId 应用ID
     */
    function setAppId($AppId){
        $this->AppId = $AppId;
    }

    /**
     * 打印日志
     *
     * @param log 日志内容
     */
    function showlog($log){
        if($this->enabeLog){
            fwrite($this->Handle,$log."\n");
        }
    }

    /**
     * 发起HTTPS请求
     */
    function curl_post($url,$data,$header,$post=1)
    {
        //初始化curl
        $ch = curl_init();
        //参数设置
        $res= curl_setopt ($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt ($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, $post);
        if($post)
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_HTTPHEADER,$header);
        $result = curl_exec ($ch);
        //连接失败
        if($result == FALSE){
            if($this->BodyType=='json'){
                $result = "{\"statusCode\":\"172001\",\"statusMsg\":\"网络错误\"}";
            } else {
                $result = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><Response><statusCode>172001</statusCode><statusMsg>网络错误</statusMsg></Response>";
            }
        }

        curl_close($ch);
        return $result;
    }



    /**
     * 发送模板短信
     * @param to 短信接收彿手机号码集合,用英文逗号分开
     * @param datas 内容数据
     * @param $tempId 模板Id
     */
    function sendTemplateSMS($to,$datas,$tempId)
    {
        //主帐号鉴权信息验证，对必选参数进行判空。
        $auth=$this->accAuth();
        if($auth!=""){
            return $auth;
        }
        // 拼接请求包体
        if($this->BodyType=="json"){
            $data="";
            for($i=0;$i<count($datas);$i++){
                $data = $data. "'".$datas[$i]."',";
            }
            $body= "{'to':'$to','templateId':'$tempId','appId':'$this->AppId','datas':[".$data."]}";
        }else{
            $data="";
            for($i=0;$i<count($datas);$i++){
                $data = $data. "<data>".$datas[$i]."</data>";
            }
            $body="<TemplateSMS>
                    <to>$to</to>
                    <appId>$this->AppId</appId>
                    <templateId>$tempId</templateId>
                    <datas>".$data."</datas>
                  </TemplateSMS>";
        }
        $this->showlog("request body = ".$body);
        // 大写的sig参数
        $sig =  strtoupper(md5($this->AccountSid . $this->AccountToken . $this->Batch));
        // 生成请求URL
        $url="https://$this->ServerIP:$this->ServerPort/$this->SoftVersion/Accounts/$this->AccountSid/SMS/TemplateSMS?sig=$sig";
        $this->showlog("request url = ".$url);
        // 生成授权：主帐户Id + 英文冒号 + 时间戳。
        $authen = base64_encode($this->AccountSid . ":" . $this->Batch);
        // 生成包头
        $header = array("Accept:application/$this->BodyType","Content-Type:application/$this->BodyType;charset=utf-8","Authorization:$authen");
        // 发送请求
        $result = $this->curl_post($url,$body,$header);
        $this->showlog("response body = ".$result);
        if($this->BodyType=="json"){//JSON格式
            $datas=json_decode($result);
        }else{ //xml格式
            $datas = simplexml_load_string(trim($result," \t\n\r"));
        }
        //  if($datas == FALSE){
//            $datas = new stdClass();
//            $datas->statusCode = '172003';
//            $datas->statusMsg = '返回包体错误';
//        }
        //重新装填数据
        if($datas->statusCode==0){
            if($this->BodyType=="json"){
                $datas->TemplateSMS =$datas->templateSMS;
                unset($datas->templateSMS);
            }
        }

        return $datas;
    }

    /**
     * 主帐号鉴权
     */
    function accAuth()
    {
        if($this->ServerIP==""){
            $data = new stdClass();
            $data->statusCode = '172004';
            $data->statusMsg = 'IP为空';
            return $data;
        }
        if($this->ServerPort<=0){
            $data = new stdClass();
            $data->statusCode = '172005';
            $data->statusMsg = '端口错误（小于等于0）';
            return $data;
        }
        if($this->SoftVersion==""){
            $data = new stdClass();
            $data->statusCode = '172013';
            $data->statusMsg = '版本号为空';
            return $data;
        }
        if($this->AccountSid==""){
            $data = new stdClass();
            $data->statusCode = '172006';
            $data->statusMsg = '主帐号为空';
            return $data;
        }
        if($this->AccountToken==""){
            $data = new stdClass();
            $data->statusCode = '172007';
            $data->statusMsg = '主帐号令牌为空';
            return $data;
        }
        if($this->AppId==""){
            $data = new stdClass();
            $data->statusCode = '172012';
            $data->statusMsg = '应用ID为空';
            return $data;
        }
    }
}

?>
