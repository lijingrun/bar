<?php

/**
 *    买家的订单管理控制器
 *
 *    @author    Garbin
 *    @usage    none
 */
class Buyer_orderApp extends MemberbaseApp {

    function index() {
        /* 获取订单列表 */
        $this->_get_orders();

        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('my_order'), 'index.php?app=buyer_order', LANG::get('order_list'));

        /* 当前用户中心菜单 */
        $this->_curitem('my_order');
        $this->_curmenu('order_list');
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('my_order'));
        $this->import_resource(array(
            'script' => array(
                array(
                    'path' => 'dialog/dialog.js',
                    'attr' => 'id="dialog_js"',
                ),
                array(
                    'path' => 'jquery.ui/jquery.ui.js',
                    'attr' => '',
                ),
                array(
                    'path' => 'jquery.ui/i18n/' . i18n_code() . '.js',
                    'attr' => '',
                ),
                array(
                    'path' => 'jquery.plugins/jquery.validate.js',
                    'attr' => '',
                ),
            ),
            'style' => 'jquery.ui/themes/ui-lightness/jquery.ui.css',
        ));


        /* 显示订单列表 */
        $this->display('buyer_order.index.html');
    }

    /**
     *    查看订单详情
     *
     *    @author    Garbin
     *    @return    void
     */
    function view() {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $model_order = & m('order');
        //$order_info  = $model_order->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'));
        $order_info = $model_order->get(array(
            'fields' => "*, order.add_time as order_add_time",
            'conditions' => "order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'),
            'join' => 'belongs_to_store',
        ));
        if (!$order_info) {
            $this->show_warning('no_such_order');

            return;
        }

        /* 团购信息 */
        if ($order_info['extension'] == 'groupbuy') {
            $groupbuy_mod = &m('groupbuy');
            $group = $groupbuy_mod->get(array(
                'join' => 'be_join',
                'conditions' => 'order_id=' . $order_id,
                'fields' => 'gb.group_id',
            ));
            $this->assign('group_id', $group['group_id']);
        }

        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('my_order'), 'index.php?app=buyer_order', LANG::get('view_order'));

        /* 当前用户中心菜单 */
        $this->_curitem('my_order');

        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_detail'));

        /* 调用相应的订单类型，获取整个订单详情数据 */
        $order_type = & ot($order_info['extension']);
//        print_r($order_type);exit;
        $order_detail = $order_type->get_order_detail($order_id, $order_info);
//        print_r($order_detail['data']['goods_list']);exit;
        foreach ($order_detail['data']['goods_list'] as $key => $goods) {
            empty($goods['goods_image']) && $order_detail['data']['goods_list'][$key]['goods_image'] = Conf::get('default_goods_image');
        }
        $this->assign('order', $order_info);
        $this->assign($order_detail['data']);
        $this->display('buyer_order.view.html');
    }

    function cancel_bar_order(){
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $model_order = & m('order');
        $model_order->edit($order_id, array('status' => ORDER_CANCELED));
        /* 加回商品库存 */
        $model_order->change_stock('+', $order_id);
        $cancel_reason = (!empty($_POST['remark'])) ? $_POST['remark'] : $_POST['cancel_reason'];
        /* 记录订单操作日志 */
        $order_log = & m('orderlog');
        $order_log->add(array(
            'order_id' => $order_id,
            'operator' => addslashes($this->visitor->get('user_name')),
            'order_status' => order_status($order_info['status']),
            'changed_status' => order_status(ORDER_CANCELED),
            'remark' => $cancel_reason,
            'log_time' => gmtime(),
        ));
        echo 111;
        exit;
    }

    /**
     *    取消订单
     *
     *    @author    Garbin
     *    @return    void
     */
    function cancel_order() {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if (!$order_id) {
            echo Lang::get('no_such_order');

            return;
        }
        $model_order = & m('order');
        /* 只有待付款的订单可以取消 */
        $order_info = $model_order->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id') . " AND status " . db_create_in(array(ORDER_PENDING, ORDER_SUBMITTED)));
        if (empty($order_info)) {
            echo Lang::get('no_such_order');

            return;
        }
        if (!IS_POST) {
            header('Content-Type:text/html;charset=' . CHARSET);
            $this->assign('order', $order_info);
            $this->display('buyer_order.cancel.html');
        } else {
            $model_order->edit($order_id, array('status' => ORDER_CANCELED));
            if ($model_order->has_error()) {
                $this->pop_warning($model_order->get_error());

                return;
            }
            //查看是否有花费积分，有就返还金币
            $coin_log_model = & m('coin_log');
            if ($order_info['point_price'] > 0) {
                $member_model = & m('member');
                $member = $member_model->get("user_id =" . $this->visitor->get('user_id'));
                $new_coin = $member['coin'] + $order_info['point_price'];
                $member_model->edit($member['user_id'], array('coin' => $new_coin));
                $coin_log = array(
                    'reason' => '取消订单返回金币，订单号：'.$order_info['order_sn'],
                    'lanyu_coin' => $order_info['point_price'],
                    'order_id' => $order_info['order_id'],
                    'member_id' => $order_info['buyer_id'],
                    'add_time' => gmtime(),
                );
                $coin_log_model->add($coin_log);
            }

            //查看是否使用金币抵扣现金，是的话还是返还金币
            $use_coin = $coin_log_model->get("order_id =".$order_id);
            if(!empty($use_coin)){
                $member_model = & m('member');
                $member = $member_model->get("user_id =" . $this->visitor->get('user_id'));
                $coin_update = $member['coin'] - $use_coin['lanyu_coin'];
                $member_model->edit($member['user_id'], array('coin' => $coin_update));
                $coin_log = array(
                    'reason' => '取消订单返回金币，订单号：'.$order_info['order_sn'],
                    'lanyu_coin' => $coin_update,
                    'order_id' => $order_info['order_id'],
                    'member_id' => $order_info['buyer_id'],
                    'add_time' => gmtime(),
                );
                $coin_log_model->add($coin_log);
            }
            /* 加回商品库存 */
            $model_order->change_stock('+', $order_id);
            $cancel_reason = (!empty($_POST['remark'])) ? $_POST['remark'] : $_POST['cancel_reason'];
            /* 记录订单操作日志 */
            $order_log = & m('orderlog');
            $order_log->add(array(
                'order_id' => $order_id,
                'operator' => addslashes($this->visitor->get('user_name')),
                'order_status' => order_status($order_info['status']),
                'changed_status' => order_status(ORDER_CANCELED),
                'remark' => $cancel_reason,
                'log_time' => gmtime(),
            ));

            /* 发送给卖家订单取消通知 */
            $model_member = & m('member');
            $seller_info = $model_member->get($order_info['seller_id']);
            $mail = get_mail('toseller_cancel_order_notify', array('order' => $order_info, 'reason' => $_POST['remark']));
            $this->_mailto($seller_info['email'], addslashes($mail['subject']), addslashes($mail['message']));
            //K3取消
            //增加K3对应表的记录--只针对蓝羽公司
            $member_model = & m('member');
            $member = $member_model->get('user_id =' . $order_info['buyer_id']);
            if ($order_info['seller_id'] == 7 && !empty($member['k3_code'])) {
                $order_info['status'] = 0;
                $this->_order_in_k3($member['k3_code'],$member['user_name'],$order_info,'线下支付');
            }
            $new_data = array(
                'status' => Lang::get('order_canceled'),
                'actions' => array(), //取消订单后就不能做任何操作了
            );

            $this->pop_warning('ok');
        }
    }

    /**
     *    确认订单
     *
     *    @author    Garbin
     *    @return    void
     */
    function confirm_order() {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if (!$order_id) {
            echo Lang::get('no_such_order');

            return;
        }
        $model_order = & m('order');
        /* 只有已发货的订单可以确认 */
        $order_info = $model_order->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id') . " AND status=" . ORDER_SHIPPED);
        if (empty($order_info)) {
            echo Lang::get('no_such_order');

            return;
        }
        if (!IS_POST) {

            header('Content-Type:text/html;charset=' . CHARSET);
            $this->assign('order', $order_info);
            $this->display('buyer_order.confirm.html');
        } else {
            $model_order->edit($order_id, array('status' => ORDER_FINISHED, 'finished_time' => gmtime()));
            if ($model_order->has_error()) {
                $this->pop_warning($model_order->get_error());

                return;
            }

            /* 记录订单操作日志 */
            $order_log = & m('orderlog');
            $order_log->add(array(
                'order_id' => $order_id,
                'operator' => addslashes($this->visitor->get('user_name')),
                'order_status' => order_status($order_info['status']),
                'changed_status' => order_status(ORDER_FINISHED),
                'remark' => Lang::get('buyer_confirm'),
                'log_time' => gmtime(),
            ));

            //确认订单之后就将对应的金币返还给客户
            $order = $model_order->get("order_id =".$order_id);
            if($order['add_time'] > 1475697600) {
                //如果客户是用非余额支付的支付方式支付的话，就查订单金额，转换成为商城币
                if ($order['payment_code'] != advance && $order['order_amount'] > 0) {
                    $store_model = &m('store');
                    $store = $store_model->get("store_id =" . $order['buyer_id']);
                    $coin = $order['order_amount'];
                    if (empty($store)) {
                        $grade = 1;
                    } else {
                        $grade = $store['sgrade'];
                    }
                    switch ($grade) {
                        case 2 :
                            $coin = $coin * 1.1; //金卡
                            break;
                        case 3 :
                            $coin = $coin * 1.2; //铂金
                            break;
                    }
                    //记录蓝币操作记录
                    $coin_log_model = &m("coin_log");
                    $new_log = array(
                        'reason' => '购买商品获得金币，订单号为' . $order['order_sn'],
                        'lanyu_coin' => $coin,
                        'order_id' => $order['order_id'],
                        'member_id' => $_SESSION['user_info']['user_id'],
                        'add_time' => gmtime(),
                    );
                    $member_model = &m("member");
                    $member = $member_model->get("user_id =" . $order['buyer_id']);
                    $member['coin'] += $order['order_amount'];
                    $member_model->edit($member['user_id'], $member);
                    $coin_log_model->add($new_log);
                }
            }

            /* 发送给卖家买家确认收货邮件，交易完成 */
//            $model_member =& m('member');
//            $seller_info   = $model_member->get($order_info['seller_id']);
//            $mail = get_mail('toseller_finish_notify', array('order' => $order_info));
//            $this->_mailto($seller_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

            $new_data = array(
                'status' => Lang::get('order_finished'),
                'actions' => array('evaluate'),
            );

            /* 更新累计销售件数 */
            $model_goodsstatistics = & m('goodsstatistics');
            $model_ordergoods = & m('ordergoods');
            $order_goods = $model_ordergoods->find("order_id={$order_id}");
            foreach ($order_goods as $goods) {
                $model_goodsstatistics->edit($goods['goods_id'], "sales=sales+{$goods['quantity']}");
            }

            $this->pop_warning('ok', 'ok', 'index.php?app=buyer_order&act=evaluate&order_id=' . $order_id);
        }
    }

    /**
     *    给卖家评价
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function evaluate() {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if (!$order_id) {
            $this->show_warning('no_such_order');

            return;
        }

        /* 验证订单有效性 */
        $model_order = & m('order');
        $order_info = $model_order->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'));

        if (!$order_info) {
            $this->show_warning('no_such_order');

            return;
        }
        if ($order_info['status'] != ORDER_FINISHED) {
            /* 不是已完成的订单，无法评价 */
            $this->show_warning('cant_evaluate');

            return;
        }
        if ($order_info['evaluation_status'] != 0) {
            /* 已评价的订单 */
            $this->show_warning('already_evaluate');

            return;
        }
        $model_ordergoods = & m('ordergoods');
        $model_store = & m('store');


        if (!IS_POST) {
            /* 显示评价表单 */
            /* 获取订单商品 */
            $goods_list = $model_ordergoods->find("order_id={$order_id}");
            $store_grade = $model_store->get("store_id=" . $order_info['seller_id']);
            $sgrade = $store_grade['sgrade'];
            $this->assign('order_id',$order_id);
            $this->assign('store_grade', $sgrade);

            foreach ($goods_list as $key => $goods) {
                empty($goods['goods_image']) && $goods_list[$key]['goods_image'] = Conf::get('default_goods_image');
            }
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('my_order'), 'index.php?app=buyer_order', LANG::get('evaluate'));
            $this->assign('goods_list', $goods_list);
            $this->assign('order', $order_info);

            $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('credit_evaluate'));
            $this->display('buyer_order.evaluate.html');
        } else {
            $evaluations = array();
            /* 写入评价 */
            foreach ($_POST['evaluations'] as $rec_id => $evaluation) {
                if ($evaluation['evaluation'] <= 0 || $evaluation['evaluation'] > 3) {
                    $this->show_warning('evaluation_error');

                    return;
                }
                switch ($evaluation['evaluation']) {
                    case 3:
                        $credit_value = 1;
                        break;
                    case 1:
                        $credit_value = -1;
                        break;
                    default:
                        $credit_value = 0;
                        break;
                }
                $evaluations[intval($rec_id)] = array(
                    'evaluation' => $evaluation['evaluation'],
                    'comment' => $evaluation['comment'],
                    'credit_value' => $credit_value
                );
            }
            $goods_list = $model_ordergoods->find("order_id={$order_id}");
            foreach ($evaluations as $rec_id => $evaluation) {
                $model_ordergoods->edit("rec_id={$rec_id} AND order_id={$order_id}", $evaluation);
                $goods_url = SITE_URL . '/' . url('app=goods&id=' . $goods_list[$rec_id]['goods_id']);
                $goods_name = $goods_list[$rec_id]['goods_name'];
                $this->send_feed('goods_evaluated', array(
                    'user_id' => $this->visitor->get('user_id'),
                    'user_name' => $this->visitor->get('user_name'),
                    'goods_url' => $goods_url,
                    'goods_name' => $goods_name,
                    'evaluation' => Lang::get('order_eval.' . $evaluation['evaluation']),
                    'comment' => $evaluation['comment'],
                    'images' => array(
                        array(
                            'url' => SITE_URL . '/' . $goods_list[$rec_id]['goods_image'],
                            'link' => $goods_url,
                        ),
                    ),
                ));
            }

            /* 更新订单评价状态 */
            $model_order->edit($order_id, array(
                'evaluation_status' => 1,
                'evaluation_time' => gmtime()
            ));

            /* 更新卖家信用度及好评率 */
            $model_store = & m('store');

            /* 更新商品评价数 */
            $model_goodsstatistics = & m('goodsstatistics');
            $goods_ids = array();
            foreach ($goods_list as $goods) {
                $goods_ids[] = $goods['goods_id'];
            }
            $model_goodsstatistics->edit($goods_ids, 'comments=comments+1');
            //取消积分---2016-6-23  for ljr
            /* Update Point Informations. */
//			LyLog::log( "<1>Reached loading model member.", 'Point' );

//            $model_store = & m('member');
//            $member = $model_store->get('user_id =' . $order_info['buyer_id']);
//            $point = $member['point'];
//
//			LyLog::log( sprintf( "<2>Member[%s] enter, point[%s].", $order_info['buyer_id'], $point ), 'Point' );
//
//            if (empty($point)) {
//                $point = $order_info['total_point'];
//            } else {
//                $point += $order_info['total_point'];
//            }
//			LyLog::log( sprintf( "Calculate result: %s.", $point ), 'Point' );
//			LyLog::log( "<3>Store into database.", 'Point' );
//                        LyLog::log("total_point :".$order_info['total_point'],'Point');
//                        LyLog::log("order_sn :".$order_info['order_sn'],'Point');
//            $result = $model_store->edit($order_info['buyer_id'], 'point=' . $point);
//			LyLog::log( sprintf( "<4>Finish storing into database, affected lines: %s.", $result ), 'Point' );
//			Lylog::log( '----', 'Point'  );

            $this->show_message('evaluate_successed', 'back_list', 'index.php?app=buyer_order');
        }
    }

    /*
     * 手机端的通过ajax来评价商品，与PC端的不一样
     */
    function evaluation_ajax(){
        if(IS_POST){
            $goods = $_POST['goods'];
            $service = $_POST['service'];
            $log = $_POST['log'];
            $order_id = $_POST['order_id'];
            $comment = $_POST['comment'];
            $order_goods_model = & m('ordergoods');
            $order_goods = $order_goods_model->find("order_id =".$order_id);
            //计算星星数
            $start = round(($goods+$service+$log)/3);
            foreach($order_goods as $good):
                $good['evaluation'] = $start;
                $good['comment'] = $comment;
                $order_goods_model->edit($good['rec_id'],$good);
            endforeach;
            //修改订单
            $order_model = & m('order');
            $order_model->edit($order_id,array('evaluation_status' => 1,'evaluation_time' => gmtime()));
            echo 111;exit;
        }
    }

    /**
     *    获取订单列表
     *
     *    @author    Garbin
     *    @return    void
     */
    function _get_orders() {
        $page = $this->_get_page(10);
        $model_order = & m('order');
        !$_GET['type'] && $_GET['type'] = 'all_orders';
        $con = array(
            array(//按订单状态搜索
                'field' => 'status',
                'name' => 'type',
                'handler' => 'order_status_translator',
            ),
            array(//按店铺名称搜索
                'field' => 'seller_name',
                'equal' => 'LIKE',
            ),
            array(//按下单时间搜索,起始时间
                'field' => 'add_time',
                'name' => 'add_time_from',
                'equal' => '>=',
                'handler' => 'gmstr2time',
            ),
            array(//按下单时间搜索,结束时间
                'field' => 'add_time',
                'name' => 'add_time_to',
                'equal' => '<=',
                'handler' => 'gmstr2time_end',
            ),
            array(//按订单号
                'field' => 'order_sn',
            ),
        );
        $conditions = $this->_get_query_conditions($con);
        /* 查找订单 */

        //如果是待评价订单，直接修改之前的查找条件
        if($_GET['type'] == 'evaluation'){
            $conditions = " AND status = 40 AND evaluation_status = 0";
            $orders = $model_order->findAll(array(
                'conditions' => "buyer_id=" . $this->visitor->get('user_id') . "{$conditions}",
                'fields' => 'this.*',
                'count' => true,
                'limit' => $page['limit'],
                'order' => 'add_time DESC',
                'include' => array(
                    'has_ordergoods', //取出商品
                ),
            ));
        }else{
            $orders = $model_order->findAll(array(
                'conditions' => "buyer_id=" . $this->visitor->get('user_id') . "{$conditions}",
                'fields' => 'this.*',
                'count' => true,
                'limit' => $page['limit'],
                'order' => 'add_time DESC',
                'include' => array(
                    'has_ordergoods', //取出商品
                ),
            ));
        }

        // tyioocom
        $member_mod = & m('member');
        $my_member = $member_mod->get("user_id =".$_SESSION['user_info']['user_id']);
        foreach ($orders as $key1 => $order) {
            if (is_array($order['order_goods'])) {
                foreach ($order['order_goods'] as $key2 => $goods) {
                    empty($goods['goods_image']) && $orders[$key1]['order_goods'][$key2]['goods_image'] = Conf::get('default_goods_image');
                }
            }
            //判断等待付款订单的下单日期，如果超过15日没付款，则系统自动取消订单
            if ($order['status'] == 11) {
                if ((time() - $order['add_time']) > 1296000) {
                    $orderlog_model = & m('orderlog');
                    $model_order->edit($order['order_id'], "status = 0");
                    $order_log = array(
                        'order_id' => $order['order_id'],
                        'operator' => '系统',
                        'order_status' => '等待买家付款',
                        'changed_status' => '过期取消',
                        'remark' => '订单超过15天，系统自动取消',
                        'log_time' => gmtime(),
                    );
                    $orderlog_model->add($order_log);
                    //增加K3对应表的记录--只针对蓝羽公司
                    if ($order['seller_id'] == 7 && !empty($my_member['k3_code'])) {
                        $order['status'] = 0;
                        $this->_order_in_k3($my_member['k3_code'],$my_member['user_name'],$order,'线下支付');
                    }
                }
            }
            // psmb
            $orders[$key1]['goods_quantities'] = count($order['order_goods']);
            $orders[$key1]['seller_info'] = $member_mod->get(array('conditions' => 'user_id=' . $order['seller_id'], 'fields' => 'real_name,im_qq,im_aliww,im_msn'));
        }
        //判断如果是获取待收货的订单，就将线下订单都查出来
        if ($_GET['type']) {
            $underline_model = & m('underline_logistics');
            $username = $_SESSION['user_info']['user_name'];
            $underline_orders = $underline_model->find("buyer_name like '" . $username . "' order by addtime desc");
            $this->assign('underline_orders', $underline_orders);
        }

        $page['item_count'] = $model_order->getCount();
        $this->assign('types', array('all' => Lang::get('all_orders'),
            'pending' => Lang::get('pending_orders'),
            'submitted' => Lang::get('submitted_orders'),
            'accepted' => Lang::get('accepted_orders'),
            'shipped' => Lang::get('shipped_orders'),
            'finished' => Lang::get('finished_orders'),
            'canceled' => Lang::get('canceled_orders')));
        $this->assign('type', $_GET['type']);
        $this->assign('orders', $orders);
        $this->_format_page($page);
        $this->assign('page_info', $page);
    }

    function _get_member_submenu() {
        $menus = array(
            array(
                'name' => 'order_list',
                'url' => 'index.php?app=buyer_order',
            ),
        );
        return $menus;
    }

    //线上确定线下订单
    function under_sure() {
        $id = $_GET['id'];
        $under_model = & m('underline_logistics');
        $member_model = & m('member');
        $under_model->edit($id, 'status=2');
        $user_id = $_SESSION['user_info']['user_id'];
        $member_model->edit($user_id, 'point=(point+2)');
        echo 111;
        exit;
    }

    //物流查询系统
    function check_loginstics() {
        $this->display('check_loginstics.html');
    }

    //按照收货人的电话号码查询
    function check_by_phone() {
        if (!IS_POST) {
            $this->display("check_by_phone.html");
        } else {
            $order_extm_model = & m('orderextm');
            $phone = $_POST['phone'];
            $order_extm = $order_extm_model->find('phone_tel =' . $phone);
        }
    }

}

?>
