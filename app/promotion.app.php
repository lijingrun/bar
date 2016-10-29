<?php

/*
 * 限时促销后台管理模块
 */

class PromotionApp extends MemberbaseApp {

    function index() {
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=promotion', '限时促销', 'index.php?app=promotion', '限时促销');
        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'promotion';
        $this->_curitem('promotion');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));


        $store_id = $_SESSION['user_info']['store_id'];
        $promotion_model = & m('promotion');
        $promotions = $promotion_model->find('store_id =' . $store_id);

        foreach ($promotions as $key => $promotion):
            $promotions[$key]['start_time'] = $promotion['start_time'] - 28800;
            $promotions[$key]['end_time'] = $promotion['end_time'] - 28800;
        endforeach;


        $this->assign('promotions', $promotions);
        $this->display('promotion.html');
    }

    //增加天天特价
    function add() {
        $promotion_model = & m('promotion');
        if (!IS_POST) {
            /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=promotion', '限时促销', 'index.php?app=promotion', '新增促销');
            /* 当前用户中心菜单 */
            $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'promotion';
            $this->_curitem('promotion');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
            $this->_curmenu($type);
            $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));

            $this->display('promotion_add.html');
        } else {
            $date = strtotime($_POST['date']);
            $spec_array = $_POST['spec'];
            $goods_id = $_POST['goods_id'];
            $goods_mode = & m('goods');
            $goods = $goods_mode->get('goods_id ='.$goods_id);
            
            foreach($spec_array as $specs):
                foreach($specs as $key=>$spec):
                    if(!empty($spec)){
                        $spec_model = & m('goodsspec');
                        $spec1 = $spec_model->get('spec_id ='.$key);
                        $new_promotion = array(
                            'store_id' => $goods['store_id'],
                            'goods_name' => $goods['goods_name'],
                            'goods_id' => $goods['goods_id'],
                            'start' => $date+43200,
                            'end' => $date+64800,
                            'spec_id' => $key,
                            'goods_image' => $goods['default_image'],
                            'price' => $spec,
                            'spec_1' => $spec1['spec_1'],
                            'date' => $date,
                            'b_price' => $spec1['price'],
                        );
                        $promotion_model->add($new_promotion);
                    }
                endforeach;
            endforeach;
            return $this->show_message('操作成功！', '新增成功', 'index.php?app=promotion');
        }
    }

    //获取商品
    function find_goods() {
        $goods_name = trim($_POST['goods_name']);
        $goods_model = & m('goods');
        $goods = $goods_model->find(array(
            'conditions' => "goods_name like '%" . $goods_name . "%'",
            'fields' => 'goods_id,goods_name'
        ));
        echo "<div id='goods_select'><select id='goods_id' Onchange='get_spec();'>";
        echo "<option value=0>请选择商品</option>";
        foreach ($goods as $good):
            echo "<option value=" . $good['goods_id'] . ">" . $good['goods_name'] . "</option>";
        endforeach;
        echo "</select></div>";
        exit;
    }

    //根据商品id和活动的面向对象获取对应的规格
    function get_specs() {
        $goods_id = $_POST['goods_id'];
        $special_sgrade = $_POST['special_sgrade'];
        $spec_model = & m('goodsspec');
        $sgrade_model = & m('sgrade');
        $sgrade = $sgrade_model->get('grade_id =' . $special_sgrade);
        $specs = $spec_model->find(array(
            'conditions' => 'goods_id =' . $goods_id . " AND spec_2 =" . $special_sgrade,
            'fields' => 'spec_1, price, original_price',
        ));
        echo "<div id='spec_div'>";
        echo "<table><tr><th style='width:100px;'>规格</th><th  style='width:100px;'>会员等级</th><th  style='width:100px;'>原价</th><th  style='width:100px;'>活动价</th><th></th></tr>";
        foreach ($specs as $spec):
            echo "<tr><th>" . $spec['spec_1'] . "</th><th>" . $sgrade['grade_name'] . "</th><th>￥" . $spec['price'] . "</th><th><input class='spec' type='text' name='spec[][" . $spec['spec_id'] . "]'></th><th><span style='color:red'>如果不填写价格，该规格则不参加活动</span></th></tr>";
        endforeach;

        echo "<tr><td colspan='4'><input type='submit' value='提交'></td></tr></table></div>";
        exit;
    }
    
    public function del_promotion(){
        $id = $_GET['id'];
        $promotion_model = & m('promotion');
        $promotion = $promotion_model->get('id ='.$id);
        if($promotion['store_id'] == $_SESSION['user_info']['store_id']){
            $promotion_model->drop($id);
            $this->show_message('操作成功！','已经删除','index.php?app=promotion');
        }else{
            $this->show_warning('对不起','你没有权限操作','index.php');
        }
    }
    
    //产品列表
    public function promotion_list(){
        if($_SESSION['user_info']['sgrade'] == 1){
            $this->show_warning('vip以上客户才权限访问该模块','请申请升级','index.php');
            return;
        }
        $date_str = $_GET['date'];
        if(empty($date_str) || $date_str == 'today'){
            $date_str1 = date('Y-m-d',time());
            $date_str = 'today';
        }elseif($date_str == 'tomorrow'){
            $date_str1 = date('Y-m-d',time()+86400);
        }elseif($date_str == 'day_after'){
            $date_str1 = date('Y-m-d',time()+172800);
        }
        $date = strtotime($date_str1);
        $promotion_model = & m('promotion');
        $promotions = $promotion_model->find('date ='.$date);
        $today = date('m-d',time());
        $tomorrow = date('m-d',time()+86400);
        $day_after = date('m-d',time()+172800);
        //产品去重操作
        foreach($promotions as $key=>$promotion):
            
        endforeach;
        
        $this->assign('day_after', $day_after);
        $this->assign('today', $today);
        $this->assign('tomorrow', $tomorrow);
        $this->assign('date_str', $date_str);
        $this->assign('promotions', $promotions);
        $this->display('promotion_list.html');
    }

    function on_seal(){
        //查询所有促销商品
        $key_word = $_GET['key_word'];
        $goods_model = & m('goods');
        $ids = "(161,164,165,166,167,123,124,122,125,126,128,129,130,131,132,127,137,140,141,142,143,150,134,135,136,133,121,138,139,163,162,152,151,156,157,158,159,160)";
        $goods = $goods_model->find("goods_id in ".$ids." AND if_show = 1 AND goods_name like '%".$key_word."%'");
        if($_SESSION['user_info']['sgrade'] == 5){
            $is_distributor = true;
        }
        foreach($goods as $key=>$good):
            $goods[$key]['max'] = $good['price_distributor']*0.96;
            $goods[$key]['min'] = $good['price_distributor']*0.86;
        endforeach;
        $this->assign('is_distributor',$is_distributor);
        $this->assign('goods',$goods);
        $this->display('on_seal_test.html');
    }
    
}
