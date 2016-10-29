<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class RedeemApp extends MemberbaseApp {

    function index() {
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=redeem', LANG::get('my_point'), 'index.php?app=redeem', LANG::get('my_point'));
        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
        $this->_curitem('my_point');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));

        $redeem_goods_model = & m('redeem_goods');
        $redeem_types_model = & m('redeem_type');
        $keyword = $_GET['keyword'];
        $type_id = $_GET['type_id'];
        //账号积分
        $my_point = $_SESSION['user_info']['point'];
        $this->assign('my_point', $my_point);
        $conditions = "nums > 0 AND status = 1";
        if (!empty($keyword)) {
            $conditions .= " AND goods_name like '%" . $keyword . "%'";
        }
        if (!empty($type_id)) {
            $conditions .= " AND type_id = " . $type_id;
        }
        $types = $redeem_types_model->findAll(); //查找所有的类型咯
        //显示所有上架的，有货的积分兑换商品
        $goods = $redeem_goods_model->find(array(
            'conditions' => $conditions,
        ));
        $this->assign('keywork', $keyword);
        $this->assign('types', $types);
        $this->assign('goods', $goods);
        $this->display('redeem.index.html');
    }

    //积分商品的详情
    function detail() {
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=redeem', LANG::get('my_point'), 'index.php?app=redeem', LANG::get('my_point'));
        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
        $this->_curitem('my_point');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));


        $redeem_goods_model = & m('redeem_goods');
        $goods_id = $_GET['goods_id'];
        $good = $redeem_goods_model->find('goods_id =' . $goods_id); //查商品
        $good = current($good);
        $user_login = FALSE;
        if (!empty($_SESSION['user_info'])) {
            $user_login = true;
        }
        $user_point = $_SESSION['user_info']['point'];
        $this->assign('islogin', $user_login);
        $this->assign('user_point', $user_point);
        $this->assign('good', $good);
        $this->display('redeem.details.html');
    }

    //购买积分商品
    function buy_redeem() {

        $redeem_goods_model = & m('redeem_goods');
        $member_model = & m('member');
        $address_model = & m('address');

        if (IS_POST) {
            $goods_id = $_POST['id']; //产品id
            $nums = $_POST['nums'];    //购买数量
            //先检查登录情况
            if (empty($_SESSION['user_info'])) {
                $this->show_warning('请先登录！');
                return;
            }



            //找用户资料
            $user_id = $_SESSION['user_info']['user_id'];
            $member = $member_model->find("user_id = " . $user_id);
            $member = current($member);

            //查找用户的地址资料
            $address = $address_model->find('user_id =' . $user_id);

            //找商品资料
            $good = $redeem_goods_model->find('goods_id = ' . $goods_id);
            $good = current($good);

            //判断是否够积分
            $total_price = $nums * $good['price'];
            if ($total_price > $member['point']) {
                $this->show_warning('对不起，您的积分不够！');
                return;
            }
            $i = 22;
            $this->assign('address', $address);
            $this->assign('good', $good);
            $this->assign('total_price', $total_price);
            $this->assign('nums', $nums);
            $this->$i;
            $this->display('redeem.buy.html');
//        $member['point'] -= $total_price;  //扣掉消费了的分数
//        $newmember = $member;
//        //扣掉了分之后update到用户表里面
//        $member_model->edit($user_id,$newmember);
//        
//        //扣分之后新建一个商品订单
//        $order = array(
//            'seller_id' => $good['store_id'],
//            'buyer_id' => $user_id,
//            'buyer_name' => $member['user_name'],
//            'buyer_k3_num' => $member['k3_code'],
//            'goods_id' => $goods_id,
//            'goods_name' => $good['goods_name'],
//            'buy_num' => $nums,
//            'one_price' => $good['price'],
//            'goods_img' => $good['img'],
//            'spee_qty'  => $good['spee_qty'],
//            'total_price' => $total_price,
//            'created' => time()-28800,
//            'status' => 20,
//        );
//        $redeem_orders_model->add($order);
//        
//        //记录扣分
//        $redeem_point = array(
//            'user_id' => $user_id,
//            'user_name' => $member['user_name'],
//            'created' => time(),
//            'status' => 1,
//            'operator' => '系统',
//            'event' => '购买了积分兑换商品消费',
//            'point' => $total_price,
//        );
//        $redeem_points_model->add($redeem_point);
//        
//        $this->show_message('购买成功！','返回继续购买','index.php?app=redeem');
        }
    }

    //PC端的购买方法
    function buy_redeem_pc() {
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=redeem', LANG::get('my_point'), 'index.php?app=redeem', LANG::get('my_point'));
        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
        $this->_curitem('my_point');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));

        $redeem_goods_model = & m('redeem_goods');
        $member_model = & m('member');
        $address_model = & m('address');
        $goods_id = $_GET['goods_id']; //产品id
        $nums = $_GET['num'];    //购买数量
        //先检查登录情况
        if (empty($_SESSION['user_info'])) {
            $this->show_warning('请先登录！');
            return;
        }



        //找用户资料
        $user_id = $_SESSION['user_info']['user_id'];
        $member = $member_model->find("user_id = " . $user_id);
        $member = current($member);

        //查找用户的地址资料
        $address = $address_model->find('user_id =' . $user_id);

        //找商品资料
        $good = $redeem_goods_model->find('goods_id = ' . $goods_id);
        $good = current($good);

        //判断是否够积分
        $total_price = $nums * $good['price'];
        if ($total_price > $member['point']) {
            $this->show_warning('对不起，您的积分不够！');
            return;
        }
        $this->assign('address', $address);
        $this->assign('good', $good);
        $this->assign('total_price', $total_price);
        $this->assign('nums', $nums);
        $this->display('redeem.buy.html');
    }

    //真正购买
    function buy_now() {
        $redeem_goods_model = & m('redeem_goods');
        $address_mode = & m('address');
        $member_model = & m('member');
        $redeem_orders_model = & m('redeem_orders');
        $redeem_points_model = & m('redeem_points');

        $userid = $_SESSION['user_info']['user_id'];

        if (empty($userid)) {
            $this->show_warning('请登录！');
            return;
        }
        if (IS_POST) {
            //获取前台数据
            $goods_id = $_POST['goods_id'];
            $address_id = $_POST['address_id'];
            $nums = $_POST['nums'];
            //查用户
            $member = $member_model->find('user_id =' . $userid);
            $member = current($member);

            //查商品
            $good = $redeem_goods_model->find('goods_id =' . $goods_id);
            $good = current($good);

            //查地址
            $address = $address_mode->find('addr_id =' . $address_id);
            $address = current($address);

            //判断是否够分
            $total_price = $good['price'] * $nums;
            if ($total_price > $member['point']) {
                $this->show_warning('积分不够！');
                return;
            }

            //够分，就扣掉
            $member['point'] -= $total_price;  //扣掉消费了的分数
            $newmember = $member;
            //扣掉了分之后update到用户表里面
            $member_model->edit($userid, $newmember);
//            $check_order = $redeem_orders_model->get('goods_id ='.$goods_id.' and status = 20');
//            if(!empty($check_order['order_id'])){
//                if(($check_order['created'] - time() - 28800)< 60){
//                    $this->show_warning('交易过于频繁','返回列表','index.php?app=redeem');
//                    return;
//                }
//            }
            //生成订单数组
            $order = array(
                'seller_id' => $good['store_id'],
                'buyer_id' => $userid,
                'buyer_name' => $member['user_name'],
                'buyer_k3_num' => $member['k3_code'],
                'goods_id' => $goods_id,
                'goods_name' => $good['goods_name'],
                'buy_num' => $nums,
                'one_price' => $good['price'],
                'goods_img' => $good['img'],
                'spee_qty' => $good['spee_qty'],
                'total_price' => $total_price,
                'created' => time() - 28800,
                'status' => 20,
                'goods_k3_num' => $good['k3_num'],
                'get_name' => $address['consignee'],
                'get_phone' => $address['phone_tel'],
                'region_name' => $address['region_name'],
                'address' => $address['address'],
                'note' => $_POST['note'],
            );
            $redeem_orders_model->add($order);

            //记录扣分
            $redeem_point = array(
                'user_id' => $user_id,
                'user_name' => $member['user_name'],
                'created' => time() - 28800,
                'status' => 1,
                'operator' => '系统',
                'event' => '购买了积分兑换商品消费',
                'point' => $total_price,
            );
            $redeem_points_model->add($redeem_point);

            $this->show_message('购买成功！', '', 'index.php?app=redeem&act=order');
        } else {
            $this->display('index.php?app=redeem');
        }
    }

    //订单情况
    function order() {
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=redeem', LANG::get('my_point'), 'index.php?app=redeem', LANG::get('my_point'));
        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
        $this->_curitem('my_point');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));

        $redeem_orders_model = & m('redeem_orders');
        $user_id = $_SESSION['user_info']['user_id'];
        if (empty($user_id)) {
            $this->show_warning('请先登录！');
            return;
        }
        $conditions = 'buyer_id = ' . $user_id;
        //获取订单状态
        $type = $_GET['order_type'];
        if (empty($type) || $type == 10) {
            $type = 10;
        } else {
            if ($type != 1) {
                $conditions .= " AND status =" . $type;
            } else {
                $conditions .= " AND status = 0";
            }
        }


        $orders = $redeem_orders_model->find(array(
            'conditions' => $conditions,
            'order' => 'created desc',
        ));
        $this->assign('type', $type);
        $this->assign('orders', $orders);
        $this->display('redeem.orders.html');
    }

    //客户取消订单
    function cancle_order() {
        $redeem_orders_model = & m('redeem_orders');
        $member_model = & m('member');
        $redeem_points_model = & m('redeem_points');

        $user_id = $_SESSION['user_info']['user_id'];
        if (empty($user_id)) {
            $this->show_warning('请先登录！');
            return;
        }
        //查用户
        $member = $member_model->find('user_id =' . $user_id);
        $member = current($member);

        $order_id = $_GET['order_id'];
        //查看订单
        $order = $redeem_orders_model->find('order_id = ' . $order_id);
        $order = current($order);

        //判断是否本人操作，不是就不能取消
        if ($order['buyer_id'] != $user_id) {
            $this->show_warning('你没有权限操作！');
            return;
        }

        //检查订单状态，如果已发货，就不能取消
        if ($order['status'] == 30) {
            $this->show_warning('产品已发货，不能取消订单！');
            return;
        } else if ($order['status'] == 0) {
            $this->show_warning('不能重复操作，订单已取消！');
            return;
        }

        $total_price = $order['total_price'];
        $order['deltime'] = time() - 28800;
        $order['delman'] = $_SESSION['user_info']['username'];
        $order['in_k3'] = 2;
        $order['status'] = 0;
        $redeem_orders_model->edit($order['order_id'], $order);

        //返还积分
        $member['point'] += $order['total_price'];
        $member_model->edit($member['user_id'], $member);

        //添加返还记录
        $redeem_point = array(
            'user_id' => $user_id,
            'user_name' => $member['user_name'],
            'created' => time() - 28800,
            'status' => 2,
            'operator' => $member['user_name'],
            'event' => '取消了订单' . $order['order_id'] . '返还积分',
            'point' => $total_price,
        );
        $redeem_points_model->add($redeem_point);

        $this->show_message('操作成功，订单已取消！', '返回订单页面', 'index.php?app=redeem&act=order');
    }

    //客户确认订单
    function confirmation() {
        $redeem_orders_model = & m('redeem_orders');
        $order_id = $_GET['order_id'];
        $user_id = $_SESSION['user_info']['user_id'];

        //查订单
        $order = $redeem_orders_model->find('order_id = ' . $order_id);
        $order = current($order);

        //判断只有自己账号才能确认订单
        if ($user_id != $order['buyer_id']) {
            $this->show_warning('你没有权限！');
            return;
        }

        $order['finish_time'] = time() - 28800;
        $order['status'] = 40;
        $redeem_orders_model->edit($order_id, $order);
        $this->show_message('操作成功！');
    }

    //订单详细情况
    function order_detail() {
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=redeem', LANG::get('my_point'), 'index.php?app=redeem', LANG::get('my_point'));
        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
        $this->_curitem('my_point');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));

        $redeem_orders_model = & m('redeem_orders');
        if (empty($_SESSION['user_info']['user_id'])) {
            $this->show_warning('请先登录！');
            return;
        }
        $order_id = $_GET['order_id'];
        $order = $redeem_orders_model->find('order_id = ' . $order_id);
        $order = current($order);

        $this->assign('order', $order);
        $this->display('redeem.order.detail.html');
    }

}
