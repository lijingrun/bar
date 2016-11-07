<?php

/**
 *    购物车控制器，负责会员购物车的管理工作，她与下一步售货员的接口是：购物车告诉售货员，我要买的商品是我购物车内的商品
 *
 *    @author    Garbin
 */
class CartApp extends MallbaseApp {

    /**
     *    列出购物车中的商品
     *
     *    @author    Garbin
     *    @return    void
     */
    function index() {
        $store_id = isset($_GET['store_id']) ? intval($_GET['store_id']) : 0;
        $carts = $this->_get_carts($store_id);
        $this->_curlocal(
                LANG::get('cart')
        );
        $this->_config_seo('title', Lang::get('confirm_goods') . ' - ' . Conf::get('site_title'));

        if (empty($carts)) {
            $this->_cart_empty();

            return;
        }
        //权限
        $sgrade = $_SESSION['user_info']['sgrade'];
        if (empty($sgrade)) {
            $sgrade = 1;
        }
        /*  tyioocom  感兴趣的商品 */
        $goods_mod = &m('goods');
        $gst_mod = &m('goodsstatistics');
        $interest = array();
        //如果购物车里面有基膜，就推荐同品牌胶，有胶，就推荐同品牌基膜
        $goods_ids = array();  //保存所有已入的商品id，最后删除已经有的，以免重复
        $order_rule_store_model = & m('order_rule_store');  //政策优惠
        $order_rule_products_model = & m('order_rule_products');
        $user_id = $_SESSION['user_info']['user_id'];
        foreach ($carts as $key => $cart):
            //查找所有的store对应的qq
            $store_model = & m('store');
            if (!empty($cart['store_id'])) {
                $store = $store_model->get('store_id =' . $cart['store_id']);
                $carts[$key]['im_qq'] = $store['im_qq'];
            }

            //begin for 大姨妈
            //查等级对应的大姨妈政策，并且查这个月是否已经用了
            $mount_policy_model = & m('mount_policy');
            $mount_policy = $mount_policy_model->find("sgrade_id =".$sgrade." AND store_id =".$cart['store_id']);
            $member_mount_policy = array();
            $policy_type_model = & m("policy_type");
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
            $carts[$key]['mount_policy'] = $member_mount_policy;
            //end for 大姨妈

            foreach ($cart['goods'] as $key2 => $val):
                //获取商品对应的优惠，保存到数组
                $rule_products = $order_rule_products_model->find('goods_id ='.$val['goods_id']);
                $ids = array();
                foreach($rule_products as $val):
                    $ids[] = $val['sub_rule_id'];
                endforeach;
                if(!empty($ids)){
                    $ids_str = implode(',', $ids);
                    $order_rules = $order_rule_store_model->find(array(
                        'conditions' => 'id in ('.$ids_str.") AND (user_id=".$_SESSION['user_info']['user_id']." OR user_id is null OR user_id = 0) AND enabled = 1",
                        'fields' => 'spec_name,display',    
                    ));
                    if(!empty($order_rules)){
                        $carts[$key]['goods'][$key2]['rules'] = $order_rules;
                    }
                }
            endforeach;
            $brands = array();
            foreach ($cart['goods'] as $val):
                $goods_ids[] = $val['goods_id'];
                $the_goods = $goods_mod->get("goods_id =".$val['goods_id']);
                $brands[] = "'".$the_goods['brand']."'";
//                $goods_name = $val['goods_name'];
//                if (strstr($goods_name, '基膜')) {
//                    $key2 = '胶';
//                    $key3 = '基膜';
//                } elseif (strstr($goods_name, '胶')) {
//                    $key2 = '基膜';
//                    $key3 = '胶';
//                }
//                $key = substr($goods_name, 0, 3);
//                //如果不是数字，就要获取前9个，因为中文1个=3个字节
//                if (!is_numeric($key)) {
//                    $key = substr($goods_name, 0, 9);
//                }
//                $i_goods = $goods_mod->find(array(
//                    'conditions' => "closed = 0 AND if_show = 1 AND display_sgrade like '%," . $sgrade . ",%' AND goods_name like '" . $key . "%' AND goods_name like '%" . $key2 . "%' AND goods_name not like '%" . $key3 . "%'",
//                    'join' => 'has_goodsstatistics',
//                    'order' => 'views desc,collects desc, sales desc',
//                    'fields' => 'g.goods_id,goods_name,price,sales,default_image',
//                ));
                //直接查找同品牌的所有商品
//                print_r($cart['goods']);
            endforeach;
            $goods_ids_str = implode(',',$goods_ids);
            $brands_str = implode(',',$brands);
            $other_goods = $goods_mod->find("goods_id not in (".$goods_ids_str.") AND brand in (".$brands_str.")");
        endforeach;

//        print_r($other_goods);exit;
        $interest = array_merge($interest, $other_goods);
        //下面是原始的推荐产品搜索条件
//            $interest = $goods_mod->find(array(
//                'conditions' => "closed = 0 AND if_show = 1 AND display_sgrade like '%," . $sgrade . ",%' AND goods_name like '%".$key."%'",
//                'join' => 'has_goodsstatistics',
//                'order' => 'views desc,collects desc, sales desc',
//                'fields' => 'g.goods_id,goods_name,price,sales,default_image',
//                'limit' => 8
//            ));   
        array_unique($interest);
        $spec_model = & m('goodsspec');
        //遍历推荐产品，查出账号权限下面对应的价钱以及规格
        foreach ($interest as $key => $good):
            //如果推荐产品包含已买产品，则删除
            if (in_array($good['goods_id'], $goods_ids)) {
                unset($interest[$key]);
            } else {
                $spec = $spec_model->get(array(
                    'conditions' => "goods_id =" . $good['goods_id'] . " AND spec_2 =" . $sgrade,
                ));
                $interest[$key]['spec'] = $spec['spec_1'];
                $interest[$key]['spec_price'] = $spec['price'];
                $interest[$key]['spec_id'] = $spec['spec_id'];
                $interest[$key]['original_price'] = $spec['original_price'];
            }
        endforeach;
        $this->assign('interest', $interest);
        /* end */

        $this->assign('carts', $carts);
        $this->display('cart.index.html');
    }

    /**
     *    放入商品(根据不同的请求方式给出不同的返回结果)
     *
     *    @author    Garbin
     *    @return    void
     */
    function add() {
        //判断登录，未登录就不能加入购物车
        if (empty($_SESSION['user_info'])) {
            $this->json_error('请先登录再下订单！');
            return;
        }
        $spec_id = isset($_GET['spec_id']) ? intval($_GET['spec_id']) : 0;
        $quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 0;
        $additional_info = isset($_GET['additional_info']) ? ($_GET['additional_info']) : '';

        if (!$spec_id || !$quantity) {
            return;
        }

        /* 是否有商品 */
        $spec_model = & m('goodsspec');
        $spec_info = $spec_model->get(array(
            'fields' => 'g.for_user, g.store_id, g.point_price, g.goods_id, g.goods_name, g.spec_name_1, g.spec_name_2, g.default_image, gs.spec_1, gs.spec_2, gs.stock, gs.price, gs.point',
            'conditions' => $spec_id,
            'join' => 'belongs_to_goods',
        ));
        if (!$spec_info) {
            $this->json_error('no_such_goods');
            /* 商品不存在 */
            return;
        }
        //针对817，只有河南客户可以购买
        if($spec_info['goods_id'] == 2450 || $spec_info['goods_id'] == 2449){
            $store_model = & m('store');
            $my_store = $store_model->get("store_id =".$_SESSION['user_info']['user_id']);
            if($my_store['region_id'] != 10){
                $this->json_error('您所在区域已经有其他客户申请了该品牌的保护，请选择其他品牌产品进行购买！');
                return;
            }
        }
        //贴牌产品只针对贴牌客户开放
        if(!empty($spec_info['for_user']) && $spec_info['for_user'] != $_SESSION['user_info']['user_id']){
            $this->json_error('该商品是其他客户贴牌商品，您不能购买');
            return;
        }
        if($this->check_protect($spec_info['goods_id']) == false){
            $this->json_error('您所在区域已经有其他客户申请了该品牌的保护，请选择其他品牌产品进行购买！');
            return;
        }
        //查是否有活动，是的话直接使用活动价替换了当前价格
        $special_model = & m('special');
        $special = $special_model->get('start <' . gmtime() . " AND end >" . gmtime());
        if (!empty($special)) {
            $special_goods_model = & m('special_goods');
            $special_goods = $special_goods_model->get('special_id =' . $special['special_id'] . " AND spec_id =" . $spec_id);
            if (!empty($special_goods['price'])) {
            $spec_info['price'] = $special_goods['price'];
            }
        }
        
        /* 如果是自己店铺的商品，则不能购买 */
        if ($this->visitor->get('manage_store')) {
            if ($spec_info['store_id'] == $this->visitor->get('manage_store')) {
                $this->json_error('can_not_buy_yourself');

                return;
            }
        }
        
        /* 是否添加过 */
        $model_cart = & m('cart');
        $item_info = $model_cart->get("spec_id={$spec_id} AND session_id='" . SESS_ID . "'");
        if (!empty($item_info)) {
            $this->json_error('goods_already_in_cart');

            return;
        }

        if ($quantity > $spec_info['stock']) {
            $this->json_error('no_enough_goods');
            return;
        }

        $spec_1 = $spec_info['spec_name_1'] ? $spec_info['spec_name_1'] . ':' . $spec_info['spec_1'] : $spec_info['spec_1'];
        $spec_2 = $spec_info['spec_name_2'] ? $spec_info['spec_name_2'] . ':' . $spec_info['spec_2'] : $spec_info['spec_2'];

        $specification = $spec_1; // . ' ' . $spec_2;

        /* 将商品加入购物车 */
        $cart_item = array(
            'user_id' => $this->visitor->get('user_id'),
            'session_id' => SESS_ID,
            'store_id' => $spec_info['store_id'],
            'spec_id' => $spec_id,
            'goods_id' => $spec_info['goods_id'],
            'goods_name' => addslashes($spec_info['goods_name']),
            'specification' => addslashes(trim($specification)),
            'price' => $spec_info['price'],
            'quantity' => $quantity,
            'point' => $spec_info['point'],
            'goods_image' => addslashes($spec_info['default_image']),
            'additional_info' => $additional_info,
            'point_price' => $spec_info['point_price'],
        );

        //如果需要用到积分，先检查账号积分是否足够，不足够则不能购买
        $total_point_price = $spec_info['point_price'] * $quantity;
        if ($total_point_price > 0) {
            //判断账号积分是否足够花费，不足够的话，就不能生成订单
            $user_id = $_SESSION['user_info']['user_id'];
            $member_model = & m('member');
            $member = $member_model->get('user_id =' . $user_id);
            if ($member['coin'] < $total_point_price) {
                $this->json_error('您的蓝币不足！');

                return;
            }
        }

        /* 添加并返回购物车统计即可 */
        $cart_model = & m('cart');
        $cart_model->add($cart_item);

        $cart_status = $this->_get_cart_status();

        /* 更新被添加进购物车的次数 */
        $model_goodsstatistics = & m('goodsstatistics');
        $model_goodsstatistics->edit($spec_info['goods_id'], 'carts=carts+1');

        $this->json_result(array(
            'cart' => $cart_status['status'], //返回购物车状态
                ), 'addto_cart_successed');
    }

    /**
     *    直接到购物车
     *
     *    @author    Garbin
     *    @return    void
     */
    function to_shop() {
        //判断登录，未登录就不能下单
        if (empty($_SESSION['user_info'])) {
            $this->json_error('请先登录再下订单！');
            return;
        }
        $spec_id = isset($_GET['spec_id']) ? intval($_GET['spec_id']) : 0;
        $quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 0;
        $additional_info = isset($_GET['additional_info']) ? ($_GET['additional_info']) : '';


        if (!$spec_id || !$quantity) {
            return;
        }

        /* 是否有商品 */
        $spec_model = & m('goodsspec');
        $spec_info = $spec_model->get(array(
            'fields' => 'g.for_user, g.store_id, g.point_price, g.goods_id, g.goods_name, g.spec_name_1, g.spec_name_2, g.default_image, gs.spec_1, gs.spec_2, gs.stock, gs.price, gs.point',
            'conditions' => $spec_id,
            'join' => 'belongs_to_goods',
        ));
        //针对817，只有河南客户可以购买
        if($spec_info['goods_id'] == 2450 || $spec_info['goods_id'] == 2449){
            $store_model = & m('store');
            $my_store = $store_model->get("store_id =".$_SESSION['user_info']['user_id']);
            if($my_store['region_id'] != 10){
                $this->json_error('您所在区域已经有其他客户申请了该品牌的保护，请选择其他品牌产品进行购买！');
                return;
            }
        }
        //贴牌产品只针对贴牌客户开放
        if(!empty($spec_info['for_user']) && $spec_info['for_user'] != $_SESSION['user_info']['user_id']){
            $this->json_error('该商品是其他客户贴牌商品，您不能购买');
            return;
        }
        if (!$spec_info) {
            $this->json_error('no_such_goods');
            /* 商品不存在 */
            return;
        }
        if($this->check_protect($spec_info['goods_id']) == false){
            $this->json_error('您所在区域已经有其他客户申请了该品牌的保护，请选择其他品牌产品进行购买！');
            return;
        }
        //查是否有活动，是的话直接使用活动价替换了当前价格
        $special_model = & m('special');
        $special = $special_model->get('start <' . gmtime() . " AND end >" . gmtime());
        if (!empty($special)) {
            $special_goods_model = & m('special_goods');
            $special_goods = $special_goods_model->get('special_id =' . $special['special_id'] . " AND spec_id =" . $spec_id);
            if (!empty($special_goods['price'])) {
            $spec_info['price'] = $special_goods['price'];
            }
        }

        /* 如果是自己店铺的商品，则不能购买 */
        if ($this->visitor->get('manage_store')) {
            if ($spec_info['store_id'] == $this->visitor->get('manage_store')) {
                $this->json_error('can_not_buy_yourself');

                return;
            }
        }

        if ($quantity > $spec_info['stock']) {
            $this->json_error('请选择箱为单位购买');
            return;
        }

        /* 是否添加过 */
        $model_cart = & m('cart');
        $item_info = $model_cart->get("spec_id={$spec_id} AND session_id='" . SESS_ID . "'");
        if (!empty($item_info)) {

            $spec_1 = $spec_info['spec_name_1'] ? $spec_info['spec_name_1'] . ':' . $spec_info['spec_1'] : $spec_info['spec_1'];
            $spec_2 = $spec_info['spec_name_2'] ? $spec_info['spec_name_2'] . ':' . $spec_info['spec_2'] : $spec_info['spec_2'];

            $specification = $spec_1; // . ' ' . $spec_2;

            $this->json_result(array(
                'cart' => $cart_status['status'], //返回购物车状态
                    ), 'addto_cart_successed');
        } else {
            $spec_1 = $spec_info['spec_name_1'] ? $spec_info['spec_name_1'] . ':' . $spec_info['spec_1'] : $spec_info['spec_1'];
            $spec_2 = $spec_info['spec_name_2'] ? $spec_info['spec_name_2'] . ':' . $spec_info['spec_2'] : $spec_info['spec_2'];

            $specification = $spec_1; // . ' ' . $spec_2;

            /* 将商品加入购物车 */
            $cart_item = array(
                'user_id' => $this->visitor->get('user_id'),
                'session_id' => SESS_ID,
                'store_id' => $spec_info['store_id'],
                'spec_id' => $spec_id,
                'goods_id' => $spec_info['goods_id'],
                'goods_name' => addslashes($spec_info['goods_name']),
                'specification' => addslashes(trim($specification)),
                'price' => $spec_info['price'],
                'quantity' => $quantity,
                'point' => $spec_info['point'],
                'goods_image' => addslashes($spec_info['default_image']),
                'additional_info' => $additional_info,
                'point_price' => $spec_info['point_price'],
            );

            //如果需要用到积分，先检查账号积分是否足够，不足够则不能购买
            $total_point_price = $spec_info['point_price'] * $quantity;
            if ($total_point_price > 0) {
                //判断账号积分是否足够花费，不足够的话，就不能生成订单
                $user_id = $_SESSION['user_info']['user_id'];
                $member_model = & m('member');
                $member = $member_model->get('user_id =' . $user_id);
                if ($member['coin'] < $total_point_price) {
                    $this->json_error('您的蓝币不足！');

                    return;
                }
            }

            /* 添加并返回购物车统计即可 */
            $cart_model = & m('cart');
            $cart_model->add($cart_item);
            $cart_status = $this->_get_cart_status();

            /* 更新被添加进购物车的次数 */
            $model_goodsstatistics = & m('goodsstatistics');
            $model_goodsstatistics->edit($spec_info['goods_id'], 'carts=carts+1');

            $this->json_result(array(
                'cart' => $cart_status['status'], //返回购物车状态
                    ), 'addto_cart_successed');
        }
    }

    /**
     *    丢弃商品
     *
     *    @author    Garbin
     *    @return    void
     */
    function drop() {
        /* 传入rec_id，删除并返回购物车统计即可 */
        $rec_id = isset($_GET['rec_id']) ? intval($_GET['rec_id']) : 0;
        if (!$rec_id) {
            return;
        }

        /* 从购物车中删除 */
        $model_cart = & m('cart');
        $droped_rows = $model_cart->drop('rec_id=' . $rec_id . ' AND session_id=\'' . SESS_ID . '\'', 'store_id');
        if (!$droped_rows) {
            return;
        }

        /* 返回结果 */
        $dropped_data = $model_cart->getDroppedData();
        $store_id = $dropped_data[$rec_id]['store_id'];
        $cart_status = $this->_get_cart_status();
        $this->json_result(array(
            'cart' => $cart_status['status'], //返回总的购物车状态
            'amount' => $cart_status['carts'][$store_id]['amount']   //返回指定店铺的购物车状态
                ), 'drop_item_successed');
    }

    /**
     *    更新购物车中商品的数量，以商品为单位，AJAX更新
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function update() {
        $spec_id = isset($_GET['spec_id']) ? intval($_GET['spec_id']) : 0;
        $quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 0;
        if (!$spec_id || !$quantity) {
            /* 不合法的请求 */
            return;
        }

        /* 判断库存是否足够 */
        $model_spec = & m('goodsspec');
        $spec_info = $model_spec->get($spec_id);
        if (empty($spec_info)) {
            /* 没有该规格 */
            $this->json_error('no_such_spec');
            return;
        }

        if ($quantity > $spec_info['stock']) {
            /* 数量有限 */
            $this->json_error('no_enough_goods');
            return;
        }

        /* 修改数量 */
        $where = "spec_id={$spec_id} AND session_id='" . SESS_ID . "'";
        $model_cart = & m('cart');


        /* 获取购物车中的信息，用于获取价格并计算小计 */
        $cart_spec_info = $model_cart->get($where);
        if (empty($cart_spec_info)) {
            /* 并没有添加该商品到购物车 */
            return;
        }

        $store_id = $cart_spec_info['store_id'];

        /* 修改数量 */
        $model_cart->edit($where, array(
            'quantity' => $quantity,
        ));

        /* 小计 */
        $subtotal = $quantity * $cart_spec_info['price'];

        /* 返回JSON结果 */
        $cart_status = $this->_get_cart_status();
        $this->json_result(array(
            'cart' => $cart_status['status'], //返回总的购物车状态
            'subtotal' => $subtotal, //小计
            'amount' => $cart_status['carts'][$store_id]['amount']  //店铺购物车总计
                ), 'update_item_successed');
    }

    /**
     *    获取购物车状态
     *
     *    @author    Garbin
     *    @return    array
     */
    function _get_cart_status() {
        /* 默认的返回格式 */
        $data = array(
            'status' => array(
                'quantity' => 0, //总数量
                'amount' => 0, //总金额
                'kinds' => 0, //总种类
            ),
            'carts' => array(), //购物车列表，包含每个购物车的状态
        );

        /* 获取所有购物车 */
        $carts = $this->_get_carts();
        if (empty($carts)) {
            return $data;
        }
        $data['carts'] = $carts;
        foreach ($carts as $store_id => $cart) {
            $data['status']['quantity'] += $cart['quantity'];
            $data['status']['amount'] += $cart['amount'];
            $data['status']['kinds'] += $cart['kinds'];
        }

        return $data;
    }

    /**
     *    购物车为空
     *
     *    @author    Garbin
     *    @return    void
     */
    function _cart_empty() {
        $goods_mod = &m('goods');
        $sgrade = $_SESSION['user_info']['sgrade'];
        $gst_mod = &m('goodsstatistics');
        $interest = $goods_mod->find(array(
            'conditions' => 'if_show = 1',
            'join' => 'has_goodsstatistics',
            'order' => 'views desc,collects desc, sales desc',
            'fields' => 'g.goods_id,goods_name,price,sales,default_image,price_distributor',
            'limit' => 6
        ));
        array_unique($interest);
        $spec_model = & m('goodsspec');
        //遍历推荐产品，查出账号权限下面对应的价钱以及规格
        foreach ($interest as $key => $good):
            //如果推荐产品包含已买产品，则删除
                $spec = $spec_model->get(array(
                    'conditions' => "goods_id =" . $good['goods_id'] . " AND spec_2 =" . $sgrade,
                ));
                $interest[$key]['spec'] = $spec['spec_1'];
                $interest[$key]['spec_price'] = $spec['price'];
                $interest[$key]['spec_id'] = $spec['spec_id'];
                $interest[$key]['original_price'] = $spec['original_price'];
        endforeach;
        $this->assign('sgrade', $sgrade);
        $this->assign('interest', $interest);
        $this->display('cart.empty.html');
    }

    /**
     *    以购物车为单位获取购物车列表及商品项
     *
     *    @author    Garbin
     *    @return    void
     */
    function _get_carts($store_id = 0) {
        $carts = array();

        /* 获取所有购物车中的内容 */
        $where_store_id = $store_id ? ' AND cart.store_id=' . $store_id : '';

        /* 只有是自己购物车的项目才能购买 */
        $where_user_id = $this->visitor->get('user_id') ? " AND cart.user_id=" . $this->visitor->get('user_id') : '';
        $cart_model = & m('cart');
        $cart_items = $cart_model->find(array(
            'conditions' => 'session_id = \'' . SESS_ID . "'" . $where_store_id . $where_user_id,
            'fields' => 'this.*,store.store_name,store.store_id',
            'join' => 'belongs_to_store',
        ));
        if (empty($cart_items)) {
            return $carts;
        }
        $kinds = array();
        foreach ($cart_items as $item) {
            /* 小计 */
            $item['subtotal'] = $item['price'] * $item['quantity'];
            $kinds[$item['store_id']][$item['goods_id']] = 1;

            /* 以店铺ID为索引 */
            empty($item['goods_image']) && $item['goods_image'] = Conf::get('default_goods_image');
            $carts[$item['store_id']]['store_name'] = $item['store_name'];
            $carts[$item['store_id']]['store_id'] = $item['store_id'];
            $carts[$item['store_id']]['amount'] += $item['subtotal'];   //各店铺的总金额
            $carts[$item['store_id']]['quantity'] += $item['quantity'];   //各店铺的总数量
            $carts[$item['store_id']]['goods'][] = $item;
        }

        foreach ($carts as $_store_id => $cart) {
            $carts[$_store_id]['kinds'] = count(array_keys($kinds[$_store_id]));  //各店铺的商品种类数
        }

        return $carts;
    }
    
    //品牌保护审核
    function check_protect($goods_id){
        $goods_model = & m('goods');
        $goods = $goods_model->get(array(
            'conditions' => 'goods_id ='.$goods_id,
            'fields'     => 'brand',    
            ));
        //没有品牌，直接通过
        if(empty($goods['brand'])){
            return true;
        }
        //有，就查找该品牌
        $brand_model = & m('brand');
        $brand = $brand_model->get("brand_name like '".$goods['brand']."'");
        if(empty($brand['brand_id'])){
            return true;
        }
        //查是否已经绑定，已绑定则可买
        $user_brand_model = & m('user_brand');
        $user_brand = $user_brand_model->get('status = 1 AND brand_id ='.$brand['brand_id']." AND user_id =".$_SESSION['user_info']['user_id']);
        if(!empty($user_brand['id'])){
            return true;
        }
        //未绑定，就查卖场
        $user_price_model = & m('user_price');
        $user_price = $user_price_model->find('user_id ='.$_SESSION['user_info']['user_id']);
        //如果未有归属卖场，就可买
        if(empty($user_price)){
            return true;
        }
        //查卖场所有绑定的用户
        foreach($user_price as $price):
            $prices = $user_price_model->find("price_id =".$price['price_id']);
            //查卖场中是否存在绑定了该品牌的客户
            foreach($prices as $val):
                $user_has_brand = $user_brand_model->get('status = 1 AND brand_id='.$brand['brand_id']." AND user_id =".$val['user_id']);
                //有人绑定，就不可以买了
                if(!empty($user_has_brand['id'])){
                    return false;
                }
            endforeach;
        endforeach;
        return true;
    }
}

?>
