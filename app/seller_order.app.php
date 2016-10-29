<?php

/**
 *    买家的订单管理控制器
 *
 *    @author    Garbin
 *    @usage    none
 */
class Seller_orderApp extends StoreadminbaseApp {

    function index() {
        /* 获取订单列表 */
        $this->_get_orders();
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('order_manage'), 'index.php?app=seller_order', LANG::get('order_list'));

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
        $this->_curitem('order_manage');
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
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
        $this->display('seller_order.index.html');
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
        $order_info = $model_order->findAll(array(
            'conditions' => "order_alias.order_id={$order_id} AND seller_id=" . $this->visitor->get('manage_store'),
            'join' => 'has_orderextm',
        ));
        $order_info = current($order_info);
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
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('order_manage'), 'index.php?app=seller_order', LANG::get('view_order'));

        /* 当前用户中心菜单 */
        $this->_curitem('order_manage');
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('detail'));

        /* 调用相应的订单类型，获取整个订单详情数据 */
        $order_type = & ot($order_info['extension']);
        $order_detail = $order_type->get_order_detail($order_id, $order_info);
        $spec_ids = array();
        foreach ($order_detail['data']['goods_list'] as $key => $goods) {
            empty($goods['goods_image']) && $order_detail['data']['goods_list'][$key]['goods_image'] = Conf::get('default_goods_image');
            $spec_ids[] = $goods['spec_id'];
        }

        /* 查出最新的相应的货号 */
        $model_spec = & m('goodsspec');
        $spec_info = $model_spec->find(array(
            'conditions' => $spec_ids,
            'fields' => 'sku',
        ));
        foreach ($order_detail['data']['goods_list'] as $key => $goods) {
            $order_detail['data']['goods_list'][$key]['sku'] = $spec_info[$goods['spec_id']]['sku'];
        }

        $this->assign('order', $order_info);
        $this->assign($order_detail['data']);
        $this->display('seller_order.view.html');
    }

    /**
     *    收到货款
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function received_pay() {
        list($order_id, $order_info) = $this->_get_valid_order_info(ORDER_PENDING);
        if (!$order_id) {
            echo Lang::get('no_such_order');

            return;
        }
        if (!IS_POST) {
            header('Content-Type:text/html;charset=' . CHARSET);
            $this->assign('order', $order_info);
            $this->display('seller_order.received_pay.html');
        } else {
            $model_order = & m('order');
            $model_order->edit(intval($order_id), array('status' => ORDER_ACCEPTED, 'pay_time' => gmtime()));
            if ($model_order->has_error()) {
                $this->pop_warning($model_order->get_error());

                return;
            }

            #TODO 发邮件通知
            /* 记录订单操作日志 */
            $order_log = & m('orderlog');
            $order_log->add(array(
                'order_id' => $order_id,
                'operator' => addslashes($this->visitor->get('user_name')),
                'order_status' => order_status($order_info['status']),
                'changed_status' => order_status(ORDER_ACCEPTED),
                'remark' => $_POST['remark'],
                'log_time' => gmtime(),
            ));

            /* 发送给买家邮件，提示等待安排发货 */
            $model_member = & m('member');
            $buyer_info = $model_member->get($order_info['buyer_id']);
            $mail = get_mail('tobuyer_offline_pay_success_notify', array('order' => $order_info));
            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));
            //增加K3对应表的记录--只针对蓝羽公司
            $member_model = & m('member');
            $member = $member_model->get('user_id =' . $order_info['buyer_id']);
            if ($order_info['seller_id'] == 7 && !empty($member['k3_code'])) {
                $this->_order_in_k3($member['k3_code'],$member['user_name'],$order_info,'线下支付');
            }
            $new_data = array(
                'status' => Lang::get('order_accepted'),
                'actions' => array(
                    'cancel',
                    'shipped'
                ), //可以取消可以发货
            );

            $this->pop_warning('ok');
        }
    }

    /**
     *    货到付款的订单的确认操作
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function confirm_order() {
        list($order_id, $order_info) = $this->_get_valid_order_info(ORDER_SUBMITTED);
        if (!$order_id) {
            echo Lang::get('no_such_order');

            return;
        }
        if (!IS_POST) {
            header('Content-Type:text/html;charset=' . CHARSET);
            $this->assign('order', $order_info);
            $this->display('seller_order.confirm.html');
        } else {
            $model_order = & m('order');
            $model_order->edit($order_id, array('status' => ORDER_ACCEPTED));
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
                'changed_status' => order_status(ORDER_ACCEPTED),
                'remark' => $_POST['remark'],
                'log_time' => gmtime(),
            ));

            /* 发送给买家邮件，订单已确认，等待安排发货 */
            $model_member = & m('member');
            $buyer_info = $model_member->get($order_info['buyer_id']);
            $mail = get_mail('tobuyer_confirm_cod_order_notify', array('order' => $order_info));
            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

            $new_data = array(
                'status' => Lang::get('order_accepted'),
                'actions' => array(
                    'cancel',
                    'shipped'
                ), //可以取消可以发货
            );

            $this->pop_warning('ok');
            ;
        }
    }

    /**
     *    调整费用
     *
     *    @author    Garbin
     *    @return    void
     */
    function adjust_fee() {
        list($order_id, $order_info) = $this->_get_valid_order_info(array(ORDER_SUBMITTED, ORDER_PENDING));
        if (!$order_id) {
            echo Lang::get('no_such_order');

            return;
        }
        $model_order = & m('order');
        $model_orderextm = & m('orderextm');
        $shipping_info = $model_orderextm->get($order_id);
        if (!IS_POST) {
            header('Content-Type:text/html;charset=' . CHARSET);
            $this->assign('order', $order_info);
            $this->assign('shipping', $shipping_info);
            $this->display('seller_order.adjust_fee.html');
        } else {
            /* 配送费用 */
            $shipping_fee = isset($_POST['shipping_fee']) ? abs(floatval($_POST['shipping_fee'])) : 0;
            /* 折扣金额 */
            $goods_amount = isset($_POST['goods_amount']) ? abs(floatval($_POST['goods_amount'])) : 0;
            /* 订单实际总金额 */
            $order_amount = round($goods_amount + $shipping_fee, 2);
            if ($order_amount <= 0) {
                /* 若商品总价＋配送费用扣队折扣小于等于0，则不是一个有效的数据 */
                $this->pop_warning('invalid_fee');

                return;
            }
            $data = array(
                'goods_amount' => $goods_amount, //修改商品总价
                'order_amount' => $order_amount, //修改订单实际总金额
                'pay_alter' => 1    //支付变更
            );

            if ($shipping_fee != $shipping_info['shipping_fee']) {
                /* 若运费有变，则修改运费 */

                $model_extm = & m('orderextm');
                $model_extm->edit($order_id, array('shipping_fee' => $shipping_fee));
            }
            $model_order->edit($order_id, $data);

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
                'changed_status' => order_status($order_info['status']),
                'remark' => Lang::get('adjust_fee'),
                'log_time' => gmtime(),
            ));

            /* 发送给买家邮件通知，订单金额已改变，等待付款 */
            $model_member = & m('member');
            $buyer_info = $model_member->get($order_info['buyer_id']);
            $mail = get_mail('tobuyer_adjust_fee_notify', array('order' => $order_info));
            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

            $new_data = array(
                'order_amount' => price_format($order_amount),
            );

            $this->pop_warning('ok');
        }
    }

    /**
     *    待发货的订单发货
     *
     *    @author    Garbin
     *    @return    void
     */
    function shipped() {
        list($order_id, $order_info) = $this->_get_valid_order_info(array(ORDER_ACCEPTED, ORDER_SHIPPED));
        if (!$order_id) {
            echo Lang::get('no_such_order');

            return;
        }
        $model_order = & m('order');
        if (!IS_POST) {
            /* 显示发货表单 */
            header('Content-Type:text/html;charset=' . CHARSET);
            $this->assign('order', $order_info);
            $this->display('seller_order.shipped.html');
        } else {
            if (!$_POST['invoice_no']) {
                $this->pop_warning('invoice_no_empty');

                return;
            }
            $edit_data = array(
                'status' => ORDER_SHIPPED,
                'invoice_no' => $_POST['invoice_no'],
                'invoice_origin_phone' => $_POST['invoice_origin_phone'],
                'invoice_contact' => $_POST['invoice_contact'],
                'invoice_phone' => $_POST['invoice_phone'],
                'invoice_company' => $_POST['invoice_company'],
                'invoice_status' => $_POST['invoice_status'],
                'invoice_forecast_time' => $_POST['invoice_forecast_time'],
                'invoice_change_time' => $_POST['invoice_change_time'],
            );
            $is_edit = true;
            if (empty($order_info['invoice_no'])) {
                /* 不是修改发货单号 */
                $edit_data['ship_time'] = gmtime();
                $is_edit = false;
            }
            $model_order->edit(intval($order_id), $edit_data);
            if ($model_order->has_error()) {
                $this->pop_warning($model_order->get_error());

                return;
            }

            #TODO 发邮件通知
            /* 记录订单操作日志 */
            $order_log = & m('orderlog');
            $order_log->add(array(
                'order_id' => $order_id,
                'operator' => addslashes($this->visitor->get('user_name')),
                'order_status' => order_status($order_info['status']),
                'changed_status' => order_status(ORDER_SHIPPED),
                'remark' => $_POST['remark'],
                'log_time' => gmtime(),
            ));


            /* 发送给买家订单已发货通知 */
            $model_member = & m('member');
            $buyer_info = $model_member->get($order_info['buyer_id']);
            $device_model = & m('devices');
            $device = $device_model->get("user_id =".$order_info['buyer_id']);
            if(!empty($device['serial_id']) && $device['device_type'] == 'mqtt'){
                $mqtt = new Mqtt();
                $mqtt->send($device['serial_id'],'您的订单'.$order_info['order_sn'].'已发货，请注意查收');
            }else if(!empty($device['serial_id']) && $device['device_type'] == 'apns'){
                $apns = new Apns();
                $apns->send($device['serial_id'],'您的订单'.$order_info['order_sn'].'已发货，请注意查收');
            }

            $order_info['invoice_no'] = $edit_data['invoice_no'];
//            $mail = get_mail('tobuyer_shipped_notify', array('order' => $order_info));
//            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

            $new_data = array(
                'status' => Lang::get('order_shipped'),
                'actions' => array(
                    'cancel',
                    'edit_invoice_no'
                ), //可以取消可以发货
            );
            if ($order_info['payment_code'] == 'cod') {
                $new_data['actions'][] = 'finish';
            }

            $this->pop_warning('ok');
        }
    }

    /**
     *    取消订单
     *
     *    @author    Garbin
     *    @return    void
     */
    function cancel_order() {
        /* 取消的和完成的订单不能再取消 */
        //list($order_id, $order_info)    = $this->_get_valid_order_info(array(ORDER_SUBMITTED, ORDER_PENDING, ORDER_ACCEPTED, ORDER_SHIPPED));
        $order_id = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';
        if (!$order_id) {
            echo Lang::get('no_such_order');
        }
        $status = array(ORDER_SUBMITTED, ORDER_PENDING, ORDER_ACCEPTED, ORDER_SHIPPED);
        $order_ids = explode(',', $order_id);
        if ($ext) {
            $ext = ' AND ' . $ext;
        }

        $model_order = & m('order');
        /* 只有已发货的货到付款订单可以收货 */
        $order_info = $model_order->find(array(
            'conditions' => "order_id" . db_create_in($order_ids) . " AND seller_id=" . $this->visitor->get('manage_store') . " AND status " . db_create_in($status) . $ext,
        ));
        $ids = array_keys($order_info);
        if (!$order_info) {
            echo Lang::get('no_such_order');

            return;
        }
        if (!IS_POST) {
            header('Content-Type:text/html;charset=' . CHARSET);
            $this->assign('orders', $order_info);
            $this->assign('order_id', count($ids) == 1 ? current($ids) : implode(',', $ids));
            $this->display('seller_order.cancel.html');
        } else {
            $coin_log_model = & m("coin_log");
            $model_order = & m('order');
            $order = $model_order->get('order_id ='.$order_id);
            //查看是否有花费积分，有就返还积分
            if ($order['point_price'] != 0) {
                $member_model = & m('member');
                $member = $member_model->get("user_id =" . $order['buyer_id']);
                $new_coin = $member['coin'] + $order['point_price'];
                $member_model->edit($member['user_id'], array('coin' => $new_coin));
                //记录积分内容
//                $new_point_record = array(
//                    'user_id' => $member['user_id'],
//                    'user_name' => $member['user_name'],
//                    'created' => time(),
//                    'status' => 2,
//                    'operator' => $_SESSION['user_info']['user_name'],
//                    'event' => '店家取消订单' . $order['order_sn'] . "返还积分",
//                    'point' => $order['point_price'],
//                );
//                $remember_point_model = & m('redeem_points');
//                $remember_point_model->add($new_point_record);
                //蓝币操作记录
                $coin_log = array(
                    'reason' => '后台取消客户订单返还，订单号：'.$order['order_sn'],
                    'lanyu_coin' => $order['point_price'],
                    'order_id' => $order['order_id'],
                    'member_id' => $order['buyer_id'],
                    'add_time' => gmtime(),
                );
                $coin_log_model->add($coin_log);
            }

            //查看是否使用金币抵扣现金，是的话还是返还金币
            $use_coin = $coin_log_model->get("order_id =".$order_id);
            if(!empty($use_coin)){
                $member_model = & m('member');
                $member = $member_model->get("user_id =" . $order['buyer_id']);
                $coin_update = $member['coin'] - $use_coin['lanyu_coin'];
                $member_model->edit($member['user_id'], array('coin' => $coin_update));
                $coin_log = array(
                    'reason' => '取消订单返回金币，订单号：'.$order['order_sn'],
                    'lanyu_coin' => $coin_update,
                    'order_id' => $order['order_id'],
                    'member_id' => $order['buyer_id'],
                    'add_time' => gmtime(),
                );
                $coin_log_model->add($coin_log);
            }
            
            foreach ($ids as $val) {
                $id = intval($val);
                $model_order->edit($id, array('status' => ORDER_CANCELED));
                if ($model_order->has_error()) {
                    //$_erros = $model_order->get_error();
                    //$error = current($_errors);
                    //$this->json_error(Lang::get($error['msg']));
                    //return;
                    continue;
                }

                /* 加回订单商品库存 */
                $model_order->change_stock('+', $id);
                $cancel_reason = (!empty($_POST['remark'])) ? $_POST['remark'] : $_POST['cancel_reason'];
                /* 记录订单操作日志 */
                $order_log = & m('orderlog');
                $order_log->add(array(
                    'order_id' => $id,
                    'operator' => addslashes($this->visitor->get('user_name')),
                    'order_status' => order_status($order_info[$id]['status']),
                    'changed_status' => order_status(ORDER_CANCELED),
                    'remark' => $cancel_reason,
                    'log_time' => gmtime(),
                ));
                //K3取消
                //增加K3对应表的记录--只针对蓝羽公司
                $member_model = & m('member');
                $member = $member_model->get('user_id =' . $order['buyer_id']);
                if ($order['seller_id'] == 7 && !empty($member['k3_code'])) {
                    $order['status'] = 0;
                    $this->_order_in_k3($member['k3_code'],$member['user_name'],$order,'线下支付');
                }
                /* 发送给买家订单取消通知 */
//                $model_member = & m('member');
//                $buyer_info = $model_member->get($order_info[$id]['buyer_id']);
//                $mail = get_mail('tobuyer_cancel_order_notify', array('order' => $order_info[$id], 'reason' => $_POST['remark']));
//                $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

                $new_data = array(
                    'status' => Lang::get('order_canceled'),
                    'actions' => array(), //取消订单后就不能做任何操作了
                );
            }
            $this->pop_warning('ok', 'seller_order_cancel_order');
        }
    }

    /**
     *    完成交易(货到付款的订单)
     *
     *    @author    Garbin
     *    @return    void
     */
    function finished() {
        list($order_id, $order_info) = $this->_get_valid_order_info(ORDER_SHIPPED, 'payment_code=\'cod\'');
        if (!$order_id) {
            echo Lang::get('no_such_order');

            return;
        }
        if (!IS_POST) {
            header('Content-Type:text/html;charset=' . CHARSET);
            /* 当前用户中心菜单 */
            $this->_curitem('seller_order');
            /* 当前所处子菜单 */
            $this->_curmenu('finished');
            $this->assign('_curmenu', 'finished');
            $this->assign('order', $order_info);
            $this->display('seller_order.finished.html');
        } else {
            $now = gmtime();
            $model_order = & m('order');
            $model_order->edit($order_id, array('status' => ORDER_FINISHED, 'pay_time' => $now, 'finished_time' => $now));
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
                'remark' => $_POST['remark'],
                'log_time' => gmtime(),
            ));

            /* 更新累计销售件数 */
            $model_goodsstatistics = & m('goodsstatistics');
            $model_ordergoods = & m('ordergoods');
            $order_goods = $model_ordergoods->find("order_id={$order_id}");
            foreach ($order_goods as $goods) {
                $model_goodsstatistics->edit($goods['goods_id'], "sales=sales+{$goods['quantity']}");
            }


            /* 发送给买家交易完成通知，提示评论 */
            $model_member = & m('member');
            $buyer_info = $model_member->get($order_info['buyer_id']);
            $mail = get_mail('tobuyer_cod_order_finish_notify', array('order' => $order_info));
            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

            $new_data = array(
                'status' => Lang::get('order_finished'),
                'actions' => array(), //完成订单后就不能做任何操作了
            );

            $this->pop_warning('ok');
        }
    }

    /**
     *    获取有效的订单信息
     *
     *    @author    Garbin
     *    @param     array $status
     *    @param     string $ext
     *    @return    array
     */
    function _get_valid_order_info($status, $ext = '') {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if (!$order_id) {

            return array();
        }
        if (!is_array($status)) {
            $status = array($status);
        }

        if ($ext) {
            $ext = ' AND ' . $ext;
        }

        $model_order = & m('order');
        /* 只有已发货的货到付款订单可以收货 */
        $order_info = $model_order->get(array(
            'conditions' => "order_id={$order_id} AND seller_id=" . $this->visitor->get('manage_store') . " AND status " . db_create_in($status) . $ext,
        ));
        if (empty($order_info)) {

            return array();
        }

        return array($order_id, $order_info);
    }

    /**
     *    获取订单列表
     *
     *    @author    Garbin
     *    @return    void
     */
    function _get_orders() {
        $page = $this->_get_page();
        $model_order = & m('order');

        !$_GET['type'] && $_GET['type'] = 'all_orders';

        $conditions = '';

        // 团购订单
        if (!empty($_GET['group_id']) && intval($_GET['group_id']) > 0) {
            $groupbuy_mod = &m('groupbuy');
            $order_ids = $groupbuy_mod->get_order_ids(intval($_GET['group_id']));
            $order_ids && $conditions .= ' AND order_alias.order_id' . db_create_in($order_ids);
        }

        $conditions .= $this->_get_query_conditions(array(
            array(//按订单状态搜索
                'field' => 'status',
                'name' => 'type',
                'handler' => 'order_status_translator',
            ),
            array(//按买家名称搜索
                'field' => 'buyer_name',
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
        ));

        /* 查找订单 */
        $orders = $model_order->findAll(array(
            'conditions' => "seller_id=" . $this->visitor->get('manage_store') . "{$conditions}",
            'count' => true,
            'join' => 'has_orderextm',
            'limit' => $page['limit'],
            'order' => 'add_time DESC',
            'include' => array(
                'has_ordergoods', //取出商品
            ),
        ));

        // psmb
        $member_mod = & m('member');
        $model_spec = & m('goodsspec');

        foreach ($orders as $key1 => $order) {
            //由于出现产品为一些空的订单，需要先判断是否是数组
            if(is_array($order['order_goods'])){
                foreach ($order['order_goods'] as $key2 => $goods) {
                    empty($goods['goods_image']) && $orders[$key1]['order_goods'][$key2]['goods_image'] = Conf::get('default_goods_image');

                    $spec = $model_spec->get(array('conditions' => 'spec_id=' . $goods['spec_id'], 'fields' => 'sku'));
                    $orders[$key1]['order_goods'][$key2]['sku'] = $spec['sku'];
                }
            }
            // psmb
            $orders[$key1]['goods_quantities'] = count($order['order_goods']);
            $orders[$key1]['buyer_info'] = $member_mod->get(array('conditions' => 'user_id=' . $order['buyer_id'], 'fields' => 'real_name,im_qq,im_aliww,im_msn'));
        }

        $page['item_count'] = $model_order->getCount();
        $this->_format_page($page);
        $this->assign('types', array('all' => Lang::get('all_orders'),
            'pending' => Lang::get('pending_orders'),
            'submitted' => Lang::get('submitted_orders'),
            'accepted' => Lang::get('accepted_orders'),
            'shipped' => Lang::get('shipped_orders'),
            'finished' => Lang::get('finished_orders'),
            'canceled' => Lang::get('canceled_orders')));
        $this->assign('type', $_GET['type']);
        $this->assign('orders', $orders);
        $this->assign('page_info', $page);
    }

    /* 三级菜单 */

    function _get_member_submenu() {
        $array = array(
            array(
                'name' => 'all_orders',
                'url' => 'index.php?app=seller_order&amp;type=all_orders',
            ),
            array(
                'name' => 'pending',
                'url' => 'index.php?app=seller_order&amp;type=pending',
            ),
            array(
                'name' => 'submitted',
                'url' => 'index.php?app=seller_order&amp;type=submitted',
            ),
            array(
                'name' => 'accepted',
                'url' => 'index.php?app=seller_order&amp;type=accepted',
            ),
            array(
                'name' => 'shipped',
                'url' => 'index.php?app=seller_order&amp;type=shipped',
            ),
            array(
                'name' => 'finished',
                'url' => 'index.php?app=seller_order&amp;type=finished',
            ),
            array(
                'name' => 'canceled',
                'url' => 'index.php?app=seller_order&amp;type=canceled',
            ),
        );
        return $array;
    }

    //卖家店铺批量导入发货
    function import_orders() {
        $model_order = & m('order');
        if (!IS_POST) {

            $this->display('seller_order_import.html');
        } else {
            $file = $_FILES['excel_file'];
            $filesuffix = substr(strchr($file['name'], "."), 1);   //1.1.xls 的文件为 1.xls
            //判断导入文件格式
            if (!empty($filesuffix) && ($filesuffix == 'xls' || $filesuffix == 'xlsx')) {
                //导入各种excel工具
                import('PHPExcel');
                import('PHPExcel/Reader/Excel2007');
                import('PHPExcel/Reader/Excel5');
                //新建Excel类
                $PHPExcel = new PHPExcel();
                //新建读取类
                if ($filesuffix == 'xls') {
                    $phpRead = new PHPExcel_Reader_Excel5();
                } else {
                    $phpRead = new PHPExcel_Reader_Excel2007();
                }
                //读取文件
                $PHPExcel = $phpRead->load($file['tmp_name']);
                //获取第一个表
                $sheetExcel = $PHPExcel->getSheet(0);

                // 取得一共有多少列 
                $allColumn = $sheetExcel->getHighestColumn();

                //取得一共有多少行 
                $allRow = $sheetExcel->getHighestRow();

                $allColumn++;
                //由第二行开始获取数据
                $all_data = array(); //整个表的数据
                for ($startRow = 2; $startRow <= $allRow; $startRow++) {
                    $one_data = array(); //一行数据
                    for ($startCol = 'A'; $startCol != $allColumn; $startCol++) {
                        $name = trim($sheetExcel->getCell($startCol . $startRow)->getValue()); //获取内容，2边去空格
                        $one_data[] = $name;
                    }
                    $all_data[] = $one_data;
                }
                //数组去空
                $all_data = array_filter($all_data);
                //循环操作
                foreach ($all_data as $val):
                    //判断导入文件必要内容是否为空
                    if (empty($val[5]) || empty($val[4]) || empty($val[9]) || empty($val[10]) || empty($val[8]) || empty($val[7])) {
                        $this->show_warning('导入文件必要内容不能为空！');
                        return;
                    }
                    $order_sn = $val[4];  //订单编号
                    $order = $model_order->get('order_sn = ' . $order_sn);
                    //订单是未发货状态才操作
                    if ($order['status'] != 0) {
                        $order_id = $order['order_id'];
                        if (empty($order['invoice_change_time'])) {
                            $invoice_change_time = gmtime();
                        } else {
                            $invoice_change_time = $order['invoice_change_time'];
                        }
                        $edit_data = array(
                            'status' => ORDER_SHIPPED, //订单状态
                            'invoice_no' => $val[5], //物流单号
                            'invoice_origin_phone' => $val[7], //发货电话
                            'invoice_contact' => $val[8], //联系人
                            'invoice_phone' => $val[10], //物流电话
                            'invoice_company' => $val[9], //物流公司
                            'invoice_status' => $val[11], //状态
                            'invoice_forecast_time' => $val[12], //预达时间
                            'invoice_change_time' => $invoice_change_time, //发货时间
                        );

                        $is_edit = true;
                        if (empty($order['invoice_no'])) {
                            /* 不是修改发货单号 */
                            $edit_data['ship_time'] = gmtime();
                            $is_edit = false;
                        }
                        $model_order->edit(intval($order_id), $edit_data);
                        if ($model_order->has_error()) {
                            $this->pop_warning($model_order->get_error());

                            return;
                        }

                        #TODO 发邮件通知
                        /* 记录订单操作日志 */
                        $order_log = & m('orderlog');
                        $order_log->add(array(
                            'order_id' => $order_id,
                            'operator' => addslashes($this->visitor->get('user_name')),
                            'order_status' => order_status($order['status']),
                            'changed_status' => order_status(ORDER_SHIPPED),
                            'remark' => $val[6],
                            'log_time' => gmtime(),
                        ));


                        /* 发送给买家订单已发货通知 */
                        $model_member = & m('member');
                        $buyer_info = $model_member->get($order['buyer_id']);
                        $order['invoice_no'] = $edit_data['invoice_no'];
                        $mail = get_mail('tobuyer_shipped_notify', array('order' => $order));
                        $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

                        $new_data = array(
                            'status' => Lang::get('order_shipped'),
                            'actions' => array(
                                'cancel',
                                'edit_invoice_no'
                            ), //可以取消可以发货
                        );
                        if ($order['payment_code'] == 'cod') {
                            $new_data['actions'][] = 'finish';
                        }

                        $this->pop_warning('ok');
                    }
                endforeach;
                $this->show_message('批量发货成功！', '返回订单页面', 'index.php?app=seller_order');
            } else {
                $this->show_warning('导入文件格式错误，请导入.xls或者.xlsx文件');
                return;
            }
        }
    }

    //订单统计
    function statistics() {
        $orders_model = & m('order');
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('order_manage'), 'index.php?app=seller_order', LANG::get('ordre_statistics'));

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
        $this->_curitem('order_manage');
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));

        $store_id = $_SESSION['user_info']['user_id'];

        //获取年份,无则为今年
        $year = $_GET['year'];
        if (empty($year)) {
            $year = date('Y', time());
        }
        //获取每个月份的时间戳,以凌晨为开始
        //1月
        $Jan_start = strtotime($year . '-01-01');
        $Jan_end = strtotime($year . '-02-01');
        //2月
        $Fer_start = strtotime($year . '-02-01');
        $Fer_end = strtotime($year . '-03-01');
        //3月
        $Mar_start = strtotime($year . '-03-01');
        $Mar_end = strtotime($year . '-04-01');
        //4月
        $Apr_start = strtotime($year . '-04-01');
        $Apr_end = strtotime($year . '-05-01');
        //5月
        $May_start = strtotime($year . '-05-01');
        $May_end = strtotime($year . '-06-01');
        //6月
        $Jun_start = strtotime($year . '-06-01');
        $Jun_end = strtotime($year . '-07-01');
        //7月
        $Jul_start = strtotime($year . '-07-01');
        $Jul_end = strtotime($year . '-08-01');
        //8月
        $Aug_start = strtotime($year . '-08-01');
        $Aug_end = strtotime($year . '-09-01');
        //9月
        $Sep_start = strtotime($year . '-09-01');
        $Sep_end = strtotime($year . '-10-01');
        //10月
        $Oct_start = strtotime($year . '-10-01');
        $Oct_end = strtotime($year . '-11-01');
        //11月
        $Nov_start = strtotime($year . '-11-01');
        $Nov_end = strtotime($year . '-12-01');
        //12月
        $Dec_start = strtotime($year . '-12-01');
        $Dec_end = strtotime(($year + 1) . '-01-01');

        $prices = array();
        $total_price = 0;
        /*
         * 查询每月的订单量以及金额
         */
        //1月
        $conditions = 'add_time >=' . $Jan_start . " AND add_time <=" . $Jan_end . " AND status >= 30 AND seller_id =" . $store_id;
        $Jan_orders = $orders_model->find(array(
            'conditions' => $conditions,
            'fields' => 'order_amount',
        ));
        $Jan_price = 0;
        foreach ($Jan_orders as $val) {
            $Jan_price += $val['order_amount'];
        }
        $prices['1'] = $Jan_price / 10000;
        $total_price += $Jan_price;

        //2月
        $conditions = 'add_time >=' . $Fer_start . " AND add_time <=" . $Fer_end . " AND status >= 30 AND seller_id =" . $store_id;
        $Fer_orders = $orders_model->find(array(
            'conditions' => $conditions,
            'fields' => 'order_amount',
        ));
        $Fer_price = 0;
        foreach ($Fer_orders as $val) {
            $Fer_price += $val['order_amount'];
        }
        $prices['2'] = $Fer_price / 10000;
        $total_price += $Fer_price;

        //3月
        $conditions = 'add_time >=' . $Mar_start . " AND add_time <=" . $Mar_end . " AND status >= 30 AND seller_id =" . $store_id;
        $Mar_orders = $orders_model->find(array(
            'conditions' => $conditions,
            'fields' => 'order_amount',
        ));
        $Mar_price = 0;
        foreach ($Mar_orders as $val) {
            $Mar_price += $val['order_amount'];
        }
        $prices['3'] = $Mar_price / 10000;
        $total_price += $Mar_price;

        //4月
        $conditions = 'add_time >=' . $Apr_start . " AND add_time <=" . $Apr_end . " AND status >= 30 AND seller_id =" . $store_id;
        $Apr_orders = $orders_model->find(array(
            'conditions' => $conditions,
            'fields' => 'order_amount',
        ));
        $Apr_price = 0;
        foreach ($Apr_orders as $val) {
            $Apr_price += $val['order_amount'];
        }
        $prices['4'] = $Apr_price / 10000;
        $total_price += $Apr_price;

        //5月
        $conditions = 'add_time >=' . $May_start . " AND add_time <=" . $May_end . " AND status >= 30 AND seller_id =" . $store_id;
        $May_orders = $orders_model->find(array(
            'conditions' => $conditions,
            'fields' => 'order_amount',
        ));
        $May_price = 0;
        foreach ($May_orders as $val) {
            $May_price += $val['order_amount'];
        }
        $prices['5'] = $May_price / 10000;
        $total_price += $May_price;

        //6月
        $conditions = 'add_time >=' . $Jun_start . " AND add_time <=" . $Jun_end . " AND status >= 30 AND seller_id =" . $store_id;
        $Jun_orders = $orders_model->find(array(
            'conditions' => $conditions,
            'fields' => 'order_amount',
        ));
        $Jun_price = 0;
        foreach ($Jun_orders as $val) {
            $Jun_price += $val['order_amount'];
        }
        $prices['6'] = $Jun_price / 10000;
        $total_price += $Jun_price;

        //7月
        $conditions = 'add_time >=' . $Jul_start . " AND add_time <=" . $Jul_end . " AND status >= 30 AND seller_id =" . $store_id;
        $Jul_orders = $orders_model->find(array(
            'conditions' => $conditions,
            'fields' => 'order_amount',
        ));
        $Jul_price = 0;
        foreach ($Jul_orders as $val) {
            $Jul_price += $val['order_amount'];
        }
        $prices['7'] = $Jul_price / 10000;
        $total_price += $Jul_price;

        //8月
        $conditions = 'add_time >=' . $Aug_start . " AND add_time <=" . $Aug_end . " AND status >= 30 AND seller_id =" . $store_id;
        $Aug_orders = $orders_model->find(array(
            'conditions' => $conditions,
            'fields' => 'order_amount',
        ));
        $Aug_price = 0;
        foreach ($Aug_orders as $val) {
            $Aug_price += $val['order_amount'];
        }
        $prices['8'] = $Aug_price / 10000;
        $total_price += $Aug_price;

        //9月
        $conditions = 'add_time >=' . $Sep_start . " AND add_time <=" . $Sep_end . " AND status >= 30 AND seller_id =" . $store_id;
        $Sep_orders = $orders_model->find(array(
            'conditions' => $conditions,
            'fields' => 'order_amount',
        ));
        $Sep_price = 0;
        foreach ($Sep_orders as $val) {
            $Sep_price += $val['order_amount'];
        }
        $prices['9'] = $Sep_price / 10000;
        $total_price += $Sep_price;

        //10月
        $conditions = 'add_time >=' . $Oct_start . " AND add_time <=" . $Oct_end . " AND status >= 30 AND seller_id =" . $store_id;
        $Oct_orders = $orders_model->find(array(
            'conditions' => $conditions,
            'fields' => 'order_amount',
        ));
        $Oct_price = 0;
        foreach ($Oct_orders as $val) {
            $Oct_price += $val['order_amount'];
        }
        $prices['10'] = $Oct_price / 10000;
        $total_price += $Oct_price;

        //11月
        $conditions = 'add_time >=' . $Nov_start . " AND add_time <=" . $Nov_end . " AND status >= 30 AND seller_id =" . $store_id;
        $Nov_orders = $orders_model->find(array(
            'conditions' => $conditions,
            'fields' => 'order_amount',
        ));
        $Nov_price = 0;
        foreach ($Nov_orders as $val) {
            $Nov_price += $val['order_amount'];
        }
        $prices['11'] = $Nov_price / 10000;
        $total_price += $Nov_price;

        //12月
        $conditions = 'add_time >=' . $Dec_start . " AND add_time <=" . $Dec_end . " AND status >= 30 AND seller_id =" . $store_id;
        $Dec_orders = $orders_model->find(array(
            'conditions' => $conditions,
            'fields' => 'order_amount',
        ));
        $Dec_price = 0;
        foreach ($Dec_orders as $val) {
            $Dec_price += $val['order_amount'];
        }
        $prices['12'] = $Dec_price / 10000;
        $total_price += $Dec_price;
        $total_price = $total_price / 10000;

        //销量top10
        $my_order_ids = $this->_get_store_orderids($store_id);
        $order_goods_model = & m('ordergoods');
        $topgoods = $order_goods_model->find(array(
            'conditions' => "order_id in (".$my_order_ids.") group by goods_id",
            'fields' => 'goods_id,count(goods_id),goods_name',
            'order' => 'count(goods_id) desc',
            'limit' => '10',
        ));
        $tops = array();
        $i = 1;
        foreach ($topgoods as $val):
            $top = array();
            $top['num'] = $i;
            $top['count'] = $val['count(goods_id)'];
            $top['goods_name'] = $val['goods_name'];
            $tops[] = $top;
            $i++;
        endforeach;
        $this->assign('tops', $tops);

        //消费top10
//        $topbuyers = $orders_model->find(array(
//            'conditions' => "status >=30 group by order_amount",
//            'fields' => "buyer_name,sum(order_amount)",
//            'order' => "sum(order_amount) desc",
//            "limit" => 10,
//        ));
//        $this->assign("topbuyers", $topbuyers);
//        print_r($topbuyers);exit;
        
        //各地区销售量
        $region_model = & m('region');
        $region = $region_model->findAll();
//        $store_id
        $region_orders = $this->get_volume_province(all);
        $province_price = array();
        foreach ($region as $val):
            $regions = $region_orders[$val['region_id']];
            $order_ids = array();
            foreach ($regions as $key => $val2):
                $order_ids[] = $key;
            endforeach;
            $order_ids = implode(',', $order_ids);
            $conditions = "seller_id =" . $store_id . " AND status >= 30";
            if (!empty($order_ids)) {
                $conditions .= " AND order_id in(" . $order_ids . ")";
                $order_price = $orders_model->get(array(
                    'conditions' => $conditions,
                    'fields' => 'sum(order_amount) as price',
                ));
                $province_price[$val['region_id']] = array(
                    'price' => $order_price['price'],
                    'region_name' => $val['region_name'],
                );
            } else {
                $province_price[$val['region_id']] = array(
                    'price' => 0,
                    'region_name' => $val['region_name'],
                );
            }
        endforeach;
        $this->assign('province_price', $province_price);


        $this->assign('year', $year);
        $this->assign('prices', $prices);
        $this->assign('total_price', $total_price);
        $this->display('seller_statistics.html');
    }

    //省份销售量情况
    function get_volume_province($province) {
        $region_model = & m('region');
        $extm_model = & m('orderextm');
        //求地区对应编码的所有订单
        switch ($province):
            case "all":
                $region = $region_model->findAll();
                $region_orders = array();
                foreach ($region as $val):
                    $orders = $extm_model->find(array(
                        'conditions' => "shipping_id = 4 AND region_id = " . $val['region_id'],
                        'fields' => 'order_id',
                    ));
                    $region_orders[$val['region_id']] = $orders;
                endforeach;
                break;
        endswitch;
        return $region_orders;
    }
    
    //根据店铺id获取店铺的所有订单id字符串
    function _get_store_orderids($store_id){
        $orders_model = & m('order');
        $my_orders = $orders_model->find('status >= 30 AND seller_id = '.$store_id);
        $my_order_ids = array();
        foreach($my_orders as $val):
            $my_order_ids[] = $val['order_id'];
        endforeach;
        $my_order_ids = implode(',',$my_order_ids);
        return $my_order_ids;
    }

}

?>
