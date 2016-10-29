<?php

class ActiveApp extends MemberbaseApp {
    
    function index(){
        $id = $_GET['id'];
        if(empty($id)){
            $this->show_warning('该抢购不存在！','','index.php');
            return;
        }
        $active_model = & m('admin_active');
        $active_user_model = & m('active_user');
        $active = $active_model->get("id =".$id." AND grade like '%,".$_SESSION['user_info']['sgrade'].",%'");
        if(empty($active)){
            $this->show_warning('对不起！','该活动不针对你所在等级开放！','index.php');
            return;
        }
        if($active['start_time'] > gmtime()){
            $this->show_warning('对不起！','活动未开始！','index.php');
            return;
        }
        if($active['end_time'] < gmtime()){
            $this->show_warning('对不起！','活动已结束！','index.php');
            return;
        }
        $has_rob = false;
        if(!empty($active)){
            $active_user = $active_user_model->find("active_id =".$active['id']." AND user_id =".$_SESSION['user_info']['user_id']);
            $has_buy = 0;
            foreach($active_user as $val):
                $has_buy += $val['nums'];
            endforeach;
            if($has_buy >= $active['can_buy']){
                $has_rob = true;
            }
        }
        $region_model = & m('region');
        $regions = $region_model->findAll();
        $this->assign('regions', $regions);
        $this->assign('active', $active);
        $this->assign('has_rob', $has_rob);
        $this->display('active.html');
    }
    
    //抢
    function rob(){
        $active_id = $_GET['active_id']; //活动id
        $region_id = $_GET['region']; //省份
        $address = $_GET['address']; //地址
        $name = $_GET['name'];
        $tel = $_GET['tel'];
        $nums = $_GET['nums'];
        if(empty($active_id) || empty($region_id) || empty($address) || empty($name) || empty($tel)) {
            echo 222;
            exit;
        }else{
            $active_model = & m('admin_active');
            $active = $active_model->get("id =".$active_id);
            if($active['nums'] < $nums){
                echo 666;
                exit;
            }
            if($active['nums'] <= 0){
                echo 333;
                exit;
            }
            $goods_model = & m('goods');
            $goods = $goods_model->get("goods_id =".$active['goods_id']); //对应商品
            $user_id = $_SESSION['user_info']['user_id'];
            $spec_model = & m('goodsspec');
            $spec = $spec_model->get("spec_id =".$active['spec_id']);  //对应规格
            $member_model = & m('member');
            $member = $member_model->get("user_id =".$user_id);
            if(empty($member['user_id'])){
                echo 222;
                exit;
            }
            //如果要扣积分，就先扣，不够不能抢
            $total_point_price = $active['point_price']*$nums;
            if($active['point_price'] > 0){
                if($total_point_price > $member['point']){
                    echo 555;
                    exit;
                }else{
                    $member['point'] -= $total_point_price;
                    $member_model->edit($user_id,$member);
                    $point_log_model = & m('redeem_points');
                    $new_point_log = array(
                        'user_id' => $user_id,
                        'user_name' => $member['user_name'],
                        'created' => gmtime(),
                        'status' => 1,
                        'operator' => $member['user_name'],
                        'event' =>  '商城活动抢购商品话费',
                        'point' => $total_point_price,
                    );
                    $point_log_model->add($new_point_log);
                }
            }
            $active['nums']--;
            $active_model->edit($active_id, $active);
            $order_model = & m('order');
            $order_sn = $this->_gen_order_sn();
            $new_order = array(
                'order_sn' => $order_sn,
                'type' => material,
                'extension' => normal,
                'seller_id' => $goods['store_id'],
                'seller_name' => '平台总后台活动',
                'buyer_id' => $user_id,
                'buyer_name' => $member['user_name'],
                'buyer_email' => $member['email'],
                'status' => 11,
                'add_time' => gmtime(),
                'goods_amount' => $active['price']*$nums,
                'order_amount' => $active['price']*$nums,
                'postscript' => '商城活动抢购礼品！',
                'point_price' => $total_point_price,
            );
            $order_model->add($new_order);
            $order = $order_model->get('order_sn ='.$order_sn);
            $order_goods_model = & m('ordergoods');
            $order_extm_model = & m('orderextm');
            $new_order_goods = array(
                'order_id' => $order['order_id'],
                'goods_id' => $goods['goods_id'],
                'goods_name' => $goods['goods_name'],
                'spec_id' => $spec['spec_id'],
                'specification' => '产品规格(型号)：'.$spec['spec_1'],
                'price' => $active['price'],
                'quantity' => $nums,
                'goods_image' => $goods['default_image'],
                'point_price' => $active['point_price'],
            );
            $order_goods_model->add($new_order_goods);
            $region_model = & m('region');
            $region = $region_model->get('region_id ='.$region_id);
            $new_extm = array(
                'order_id' => $order['order_id'],
                'consignee' => $name,
                'region_id' => $region_id,
                'region_name' => $region['region_name'],
                'address' => $address,
                'phone_tel' => $tel,
                'shipping_id' => 4,
                'shipping_name' => '厂家发货',
            );
            $order_extm_model->add($new_extm);         
            if(!empty($member['k3_code'])){
                $order_act_model = & m('order_act');
                $new_act = array(
                    'Order_Id' => $order_sn,
                    'Act_State' => 11,
                );
                $order_act_model->add($new_act);
            }
            $active_user_model = & m('active_user');
            $new_log = array(
                'user_id' => $user_id,
                'active_id' => $active_id,
                'add_time' => gmtime(),
                'nums' => $nums,
            );
            $active_user_model->add($new_log);
            echo 111;exit;
        }
    }
    
    
    /**
     *    生成订单号
     *
     *    @author    Garbin
     *    @return    string
     */
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

    //判断客户是否可以增加购买量
    function add_nums(){
        $active_id = $_POST['active_id'];
        $nums = $_POST['nums'];
        $active_model = & m('admin_active');
        $active = $active_model->get('id ='.$active_id);
        $active_users_model = & m('active_user');
        $active_users = $active_users_model->find("active_id =".$active_id." AND user_id =".$_SESSION['user_info']['user_id']);
        $has_buy = 0;
        foreach($active_users as $val):
            $has_buy += $val['nums'];
        endforeach;
        $can_buy = $active['can_buy'] - $has_buy; //可以买=可买-已买
        //要买<可买 并且 要买 < 剩余数量，则可增加
        if($nums < $can_buy && $nums < $active['nums']){
            echo 111;
        }else{
            echo 222;
        }
        exit;
    }
}