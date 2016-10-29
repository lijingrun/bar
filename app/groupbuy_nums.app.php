<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Groupbuy_numsApp extends StoreadminbaseApp {

    function index() {
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=groupbuy_nums', LANG::get('团购'), 'index.php?app=groupbuy_nums', LANG::get('以量定价'));

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'groupbuy_nums';
        $this->_curitem('groupbuy_nums');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);

        $nums_model = & m('groupbuy_nums');
        $goods_model = & m('goods');
        $nums = $nums_model->find(array(
            'conditions' => "store_id =" . $_SESSION['user_info']['store_id'],
            'order' => 'add_time desc',
            'limit' => 20,
        ));
        foreach ($nums as $key => $num):
            if($num['end_time'] < time()){
                $nums[$key]['end'] = true;
            }
            $goods = $goods_model->get('goods_id =' . $num['goods_id']);
            $nums[$key]['goods'] = $goods;
        endforeach;


        $this->assign('nums', $nums);
        $this->display('nums_list.html');
    }

    function add() {


        if (!IS_POST) {
            /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=groupbuy_nums', LANG::get('团购'), 'index.php?app=groupbuy_nums', LANG::get('以量定价'));

            /* 当前用户中心菜单 */
            $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'groupbuy_nums';
            $this->_curitem('groupbuy_nums');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
            $this->_curmenu($type);
//            $this->_config_seo('title', '团购' . ' - ' . '以量定价');
            
            $this->display('groupbuy_nums.html');
        } else {
            $group_muns_model = & m('groupbuy_nums');
            $group_nums_price_model = & m('groupbuy_nums_price');
            $name = $_POST['name'];
            $goods_id = $_POST['goods'];
            $price = $_POST['price'];
            $start_time = strtotime($_POST['start_time']);
            $end_time = strtotime($_POST['end_time']);
            $min_array = $_POST['min_nums'];
            $max_array = $_POST['max_nums'];
            $unit_array = $_POST['unit_price'];
            $total_array = $_POST['total_price'];
            $spec_id = $_POST['spec'];
            $point = $_POST['point'];
            $spec_unit = $_POST['spec_unit'];
            if (empty($name) || empty($goods_id) || empty($price) || empty($start_time) || empty($end_time) || empty($spec_id)) {
                $this->show_warning('请填写所有需要填写的数据', '', 'index.php?app=groupbuy_nums');
                return;
            } else {
                $time = gmtime();
                $spec_model = & m('goodsspec');
                $spec = $spec_model->get('spec_id ='.$spec_id);
                $new_nums = array(
                    'name' => $name,
                    'goods_id' => $goods_id,
                    'price' => $price,
                    'start_time' => $start_time-28800,
                    'end_time' => $end_time-28800,
                    'total_nums' => 0,
                    'store_id' => $_SESSION['user_info']['store_id'],
                    'add_time' => $time,
                    'spec_id' => $spec['spec_id'],
                    'spec_name' => $spec['spec_1'],
                    'sgrade' => $spec['spec_2'],
                    'status' => 2,
                    'point' => $point,
                    'spec_unit' => $spec_unit,
                );
                $group_muns_model->add($new_nums);
                $group = $group_muns_model->get('add_time ='.$time." AND store_id =".$_SESSION['user_info']['store_id']);
                foreach ($min_array as $key => $val):
                    $new_price = array(
                        'group_id' => $group['id'],
                        'min_num' => $val,
                        'max_num' => $max_array[$key],
                        'unit_price' => $unit_array[$key],
                        'total_price' => $total_array[$key],
                        
                    );
                    $group_nums_price_model->add($new_price);
                endforeach;
                $this->show_message('操作成功！', '保存成功', 'index.php?app=groupbuy_nums');
            }
        }
    }

    //根据商品名称获取对应商品
    function find_goods() {
        $goods_name = $_POST['goods_name'];
        $goods_model = & m('goods');
        $goods = $goods_model->find("goods_name like '%$goods_name%' AND store_id =" . $_SESSION['user_info']['store_id']);
        $count = count($goods);
        if ($count == 0) {
            echo "<div id='goods_list'>该商品不存在！</div>";
            exit;
        }
        echo "<div id='goods_list'>";
        echo "<select onchange='get_sprice();' id='goods' name='goods'>";
        echo "<option value='0'>请选择商品</option>";
        foreach ($goods as $good):
            echo "<option value='".$good['goods_id']."'>".$good['goods_name']."</option>";
        endforeach;
        echo "</select>";
        echo "</div>";
        exit;
    }

    //价格规则
    function price() {
        $id = $_GET['id'];
        if (empty($id)) {
            $this->show_warning('对不起，该活动不存在', '', 'index.php?app=groupbuy_nums');
            return;
        }
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=groupbuy_nums', LANG::get('团购'), 'index.php?app=groupbuy_nums', LANG::get('以量定价'));

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'groupbuy_nums';
        $this->_curitem('groupbuy_nums');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);
        
        $nums_model = & m('groupbuy_nums');
        $nums = $nums_model->get('id ='.$id);
        
        $nums_price_model = & m('groupbuy_nums_price');
        $prices = $nums_price_model->find('group_id =' . $id);

        $this->assign('nums', $nums);
        $this->assign('price', $prices);


        $this->display('nums_detail.html');
    }
    
    //发布ajax
    function release(){
        $id = $_POST['id'];
        $nums_model = & m('groupbuy_nums');
        $nums = $nums_model->get('id ='.$id);
        if($nums['store_id'] != $_SESSION['user_info']['store_id']){
            echo "你没有权限！";
            exit;
        }else{
            if($nums['status'] != 2){
                echo "活动已经发布了，不能重复发布！";
                exit;
            }
            $nums_model->edit($nums['id'],'status = 1');
            echo "操作成功！";
        }
        exit;
    }
    
    //ajax获取产品的所有规格
    function get_spec(){
        $goods_id = $_POST['goods_id'];
        if(empty($goods_id)){
            echo 222;
            exit;
        }
        $spec_model = & m('goodsspec');
        $spec = $spec_model->find('goods_id ='.$goods_id);
        echo "<br />";
        foreach($spec as $val):
            echo "<input type='radio' style='padding-top:5px;' name='spec' value='".$val['spec_id']."' />".$val['spec_1'];
            switch ($val['spec_2']):
                case 5 : echo '(VIP会员)';
                    break;
                case 2 : echo '(金卡会员)';
                    break;
                case 3 : echo '(铂金会员)';
                    break;
                case 4 : echo '(钻石会员)';
                    break;
                case 1 : echo '(普通账号)';
                    break;
            endswitch;
            echo "---<span style='color:red' >原价".$val['price']."</span>";
            echo "<br />";
        endforeach;
        exit;
    }
    
    //点击生成订单
    function order_born(){
   
        //先获取活动id
        $group_id = $_GET['group_id'];
        if(empty($group_id)){
            $this->show_warning('不好了，系统错误','请联系工程师','index.php?app=groupbuy_nums');
            return;
        }
        $nums_model = & m('groupbuy_nums');
        //找对应的活动，看活动是否结束了，状态不对不能操作
        $nums = $nums_model->get('id = '.$group_id);
        if($nums['start_time'] > gmtime()){
            $this->show_warning('活动还未开始','不能操作','index.php?app=groupbuy_nums');
            return;
        }
        if($nums['end_time'] > gmtime()){
            $this->show_warning('活动还未结束','不能操作','index.php?app=groupbuy_nums');
            return;
        }
        if($nums['status'] != 1){
            $this->show_warning('活动不是正常状态','不能操作','index.php?app=groupbuy_nums');
            return;
        }
        //查现在所属的价格区间
        $nums_price_model = & m('groupbuy_nums_price');
        $price = $nums_price_model->get("group_id = ".$group_id." AND min_num <=".$nums['total_nums']." AND max_num >".$nums['total_nums']);
        $now_price = $price['total_price'];
        //如果都不在区间中，就是最大的
        if(empty($now_price)){
            $max_price = $nums_price_model->get(array(
               'conditions' =>  'group_id ='.$id,
                'order' => 'min_num desc', 
            ));
            $now_price = $max_price['total_price'];
        }
        
        //可以操作，就查所有下订的单
        $nums_buy_model = & m('groupbuy_nums_buy');
        $nums_buy = $nums_buy_model->find('group_id ='.$group_id);
        
        $order_model = & m('order');
        $order_goods_model = & m('ordergoods');
        $orderextm_model = & m('orderextm');
        $goods_model = & m('goods');
        $spec_model = & m('goodsspec');
        
        //查找活动对应的商品
        $goods = $goods_model->get('goods_id ='.$nums['goods_id']);
        $spec = $spec_model->get('spec_id ='.$nums['spec_id']);
        
        //如果价格还是为空，就按规格对应的价格来算
        if(empty($now_price)){
            $now_price = $spec['price'];
        }
        
        //查找店铺
        $store_model = & m('store');
        $store = $store_model->get('store_id ='.$goods['store_id']);
        
        
        
        //遍历买的用户，然后按照用户相关信息新增订单,复杂的取数过程，期待以后优化啦。。。。
        foreach($nums_buy as $buy):
            //查找用户
            $member_model = & m('member');
            $member = $member_model->get('user_id ='.$buy['user_id']);
            //产品总价
            $goods_price = $buy['nums']*$now_price;
            
            $total_point = 0;  //总积分
            $point = 0;   //单个积分
            //如果活动算积分，就将对应积分计算下去
            if($nums['point'] == 1){
                $total_point = $buy['nums']*$spec['point'];
                $point = $spec['point'];
            }
            $order_sn = $this->_gen_order_sn();
            $new_order = array(
                'order_sn' => $order_sn,
                'extension' => 'groupbuy',
                'type' => $goods['type'],
                'seller_id' => $goods['store_id'],
                'seller_name' => $store['store_name'],
                'buyer_id' => $buy['user_id'],
                'buyer_name' => $member['user_name'],
                'status' => 11,
                'buyer_email' => $member['email'],
                'add_time' => gmtime(),
                'goods_amount' => $goods_price,
                'order_amount' => $goods_price,
                'total_point' => $total_point,
            );
            //保存订单并未查出订单的id
            $order_model->add($new_order);
            
            $order = $order_model->get("order_sn =".$order_sn);
            //保存订单产品
            $new_order_goods = array(
                'order_id' => $order['order_id'],
                'goods_id' => $goods['goods_id'],
                'goods_name' => $goods['goods_name'],
                'spec_id' => $spec['spec_id'],
                'specification' => '产品规格(型号):'.$spec['spec_1'],
                'price' => $now_price,
                'quantity' => $buy['nums'],
                'point' => $point,
                'goods_image' => $goods['default_image'],
            );
            $order_goods_model->add($new_order_goods);
            
            //保存收货信息
            $new_order_extm = array(
                'order_id' => $order['order_id'],
                'consignee' => $buy['name'],
                'region_id' => $buy['region_id'],
                'region_name' => $buy['region_name'],
                'address' => $buy['address'],
                'phone_tel' => $buy['phone'],
                'shipping_id' => 4,
                'shipping_name' => '厂家发货',  
            );
            $orderextm_model->add($new_order_extm);
            
            //将发货信息关联到对应的记录中
            $nums_buy_model->edit($buy['id'],'order_sn ='.$order['order_sn']);
            
        endforeach;
        
        $nums['status'] = 3;//变状态
        $nums_model->edit($nums['id'],$nums);
        $this->show_message('操作成功','已生成订单','index.php?app=groupbuy_nums');
        
    }

    function _gen_order_sn()
    {
        /* 选择一个随机的方案 */
        mt_srand((double) microtime() * 1000000);
        $timestamp = gmtime();
        $y = date('y', $timestamp);
        $z = date('z', $timestamp);
        $order_sn = $y . str_pad($z, 3, '0', STR_PAD_LEFT) . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

        $model_order =& m('order');
        $orders = $model_order->find('order_sn=' . $order_sn);
        if (empty($orders))
        {
            /* 否则就使用这个订单号 */
            return $order_sn;
        }

        /* 如果有重复的，则重新生成 */
        return $this->_gen_order_sn();
    }
    
    //活动不成立
    function cencle(){
        $group_id = $_GET['group_id'];
        if(empty($group_id)){
            $this->show_warning('不好了，系统错误','请联系工程师','index.php?app=groupbuy_nums');
            return;
        }
        $nums_model = & m('groupbuy_nums');
        $nums = $nums_model->get('id = '.$group_id);
        $nums['status'] = 4;
        $nums_model->edit($group_id,$nums);
        $this->show_message('操作成功','状态已变','index.php?app=groupbuy_nums');
    }
    
    //参加活动的详细情况
    function buys(){
         /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=groupbuy_nums', LANG::get('团购'), 'index.php?app=groupbuy_nums', LANG::get('以量定价'));

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'groupbuy_nums';
        $this->_curitem('groupbuy_nums');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);
        
        $id = $_GET['id'];
        $nums_buy_model = & m('groupbuy_nums_buy');
        $buys = $nums_buy_model->find('group_id ='.$id);
        //循环购买记录，讲账号以及订单保存到数组里面
        $member_model = & m('member');
        $order_model = & m('order');
        foreach($buys as $key=>$buy):
            //账号信息
            $member = $member_model->get('user_id ='.$buy['user_id']);
            $buys[$key]['member'] = $member;
            if(!empty($buy['order_sn'])){
                $order = $order_model->get(array(
                    'conditions' => "order_sn =".$buy['order_sn'],
                    'fields'     => 'status',
                ));
                $buys[$key]['order'] = $order;
            }
        endforeach;
        $this->assign('buys', $buys);
        return $this->display('nums_buys_detail.html');
    }
    
}
