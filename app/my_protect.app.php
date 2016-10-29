<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class My_protectApp extends StoreadminbaseApp {

    function index() {
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=my_protect', LANG::get('品牌保护'), 'index.php?app=my_protect', LANG::get('品牌区域保护'));

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'my_protect';
        $this->_curitem('my_protect');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
        //查找品牌列表
        $brand_model = & m('brand');
        $brands = $brand_model->find(array(
            'conditions' => 'store_id ='.$_SESSION['user_info']['store_id'].' AND if_show = 1',
            'order' => 'brand_name',
        ));
        $count = count($brands);
        $this->assign('count', $count);
        $this->assign('brands', $brands);
        
        
        $this->display('my_protect.html');
    }

    //添加区域
    function add_price() {
        if($_SESSION['user_info']['sgrade'] != 6){
            $this->show_warning('对不起，该功能只对厂家开放！','请返回','index.php?app=my_protect');
            return;
        }
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=my_protect', LANG::get('品牌保护'), 'index.php?app=my_protect', LANG::get('添加区域'));

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'my_protect';
        $this->_curitem('my_protect');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));

        $protect_price_model = & m('protect_price');
        //获取省份
        $province_model = & m('province');
        $provinces = $province_model->findAll();
        $this->assign('provinces', $provinces);

        $conditions = "store_id = " . $_SESSION['user_info']['store_id'];
        $province_id = $_GET['province_id'];
        $city_id = $_GET['city_id'];
        $price_name = $_GET['price_name'];
        if (!empty($province_id)) {
            $conditions .= " AND province_id =" . $province_id;
        }
        if (!empty($city_id)) {
            $conditions .= " AND city_id =" . $city_id;
        }
        if (!empty($price_name)) {
            $conditions .= " AND price_name like '%" . $price_name . "%'";
        }
        
        $page = $this->_get_page();
        $protect_prices = $protect_price_model->find(array(
            'conditions' => $conditions,
            'limit' => $page['limit'],
        ));
        $all_price = $protect_price_model->find(array(
            'conditions' => $conditions,
            'fields' => 'price_id',
        ));
        $page['item_count'] = count($all_price);
        $this->_format_page($page);
        $this->assign('page', $page);
        $this->assign('page_info', $page);
        $this->assign('protect_prices', $protect_prices);
        $this->display('add_price.html');
    }

    //获取城市
    function get_city() {
        $province_id = $_POST['province_id'];
        if (!empty($province_id)) {
            $city_model = & m('city');
            $citys = $city_model->find('topid =' . $province_id);
//            echo "<select id='city'>";
//            echo "<option value='0'>请选择城市</option>";
            foreach ($citys as $city):
                echo "<option value='" . $city['id'] . "'>" . $city['name'] . "</option>";
            endforeach;
//            echo "</select>";
        }
        exit;
    }

    //添加区域ajax
    function add_price_ajax() {
        $province_id = $_POST['province_id'];
        $city_id = $_POST['city_id'];
        $price_name = $_POST['price_name'];
        if (empty($province_id) || empty($city_id) || empty($price_name)) {
            echo 333;
            exit;
        }
        $province_model = & m('province');
        $province = $province_model->get("id = " . $province_id);
        $city_model = & m('city');
        $city = $city_model->get("id =" . $city_id);
        $protect_price_model = & m('protect_price');
        $protect_price = $protect_price_model->get('province_id =' . $province_id . " AND city_id =" . $city_id . " AND price_name like '" . $price_name . "'");
        if (!empty($protect_price['price_id'])) {
            echo 555;  //重复
            exit;
        }
        $store_id = $_SESSION['user_info']['store_id'];
        $new_price = array(
            'price_name' => $price_name,
            'province_id' => $province_id,
            'province_name' => $province['name'],
            'city_id' => $city_id,
            'city_name' => $city['name'],
            'store_id' => $store_id,
            'add_time' => gmtime(),
        );
//        print_r($new_price);exit;
        $protect_price_model->add($new_price);
        echo 111;
        exit;
    }
    
    //申请列表
    function apply_list(){
        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'my_protect';
        $this->_curitem('my_protect');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
        
        

        //待审
        $protect_apply_model = & m('protect_apply');
        $applys = $protect_apply_model->find(array(
            'conditions'  => 'store_id ='.$_SESSION['user_info']['store_id']." AND status = 1",
            'order' => 'add_time desc',
            ));
        $this->assign('applys', $applys);

        
        $this->display('apply_list.html');
    }
    
    //根据申请的id获取区域列表
    function get_price(){
        $id = $_POST['id'];
        $protect_price_model = & m('protect_price');
        $user_brand_model = & m('user_brand');
        $user_brand = $user_brand_model->get('id ='.$id);
        $prices = $protect_price_model->find(array(
                'conditions' => "province_name like '".$user_brand['province']."' AND city_name like '".$user_brand['city']."' AND store_id =".$_SESSION['user_info']['store_id'],
                'fields' => 'price_name',
            ));
        echo "<div>";
        echo "<select id='".$id."'>";
        echo "<option value='0'>请选择对应区域</option>";
        foreach($prices as $price):
            echo "<option value='".$price['price_id']."'>".$price['price_name']."</option>";
        endforeach;
        echo "</select>";
        echo "<input type='button' value='确定' onclick='choose_price(".$id.",".$user_brand['user_id'].");'>";
        echo "</div>";
        exit;
    }
    
    //将客户关联到卖场
    function relation_to_price(){
        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'my_protect';
        $this->_curitem('my_protect');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
        $protect_apply_model = & m('protect_apply');
        $price_model = & m('protect_price');
        if(!IS_POST){
            $id = $_GET['id'];
            $apply = $protect_apply_model->get('id ='.$id);
            $this->assign('apply', $apply);
            //查找本店所有卖场
            $prices = $price_model->find("province_name = '".$apply['province']."' AND city_name = '".$apply['city']."' AND store_id =".$_SESSION['user_info']['store_id']);
            $this->assign('prices', $prices);
            $this->display('relation_to_price.html');
        }else{
            $apply_id = $_POST['apply_id'];
            $price_id = $_POST['price_id'];
            $apply = $protect_apply_model->get('id ='.$apply_id);
            $price = $price_model->get('price_id ='.$price_id);
            $user_price_model = & m('user_price');
            $member_model = & m('member');
            $member = $member_model->get('user_id ='.$apply['user_id']);
            $new_price = array(
                'user_id' => $member['user_id'],
                'user_name' => $member['user_name'],
                'price_name' => $price['name'],
                'price_id' => $price['price_id'],
                'store_id' => $_SESSION['user_info']['store_id']
            );
            $protect_apply_model->edit($apply_id,'status = 2');
            $user_price_model->add($new_price);
            echo 111;
            exit;
        }
    }
    
    //将可以关联都卖场
    function choose_price(){
        $user_id = $_POST['user_id'];
        $price_id = $_POST['price_id'];
        $member_model = & m('member');
        $price_model = & m('protect_price');
        $member = $member_model->get('user_id ='.$user_id);
        $price = $price_model->get("price_id =".$price_id);
        
        if($price['store_id'] != $_SESSION['user_info']['store_id']){
            echo 555;exit;
        }else{
            $user_price_model = & m('user_price');
            $user_price = $user_price_model->get('user_id ='.$user_id." AND price_id =".$price_id);
            if(!empty($user_price['id'])){
                echo 222;
                exit;
            }
            $new_user_price = array(
                'user_id' => $user_id,
                'user_name' => $member['user_name'],
                'price_id' => $price_id,
                'price_name' => $price['price_name'],
                'store_id' => $_SESSION['user_info']['store_id'],
            );
            $user_price_model->add($new_user_price);
            echo 111;
        }
        exit;
    }
    
    //审核通过
    function apply(){
        $id = $_POST['id'];
        if(empty($id)){
            echo 555;
            exit;
        }
        $store_id = $_SESSION['user_info']['store_id'];
        $user_brand_model = & m('user_brand');
        //查申请记录
        $user_brand = $user_brand_model->get('id ='.$id." AND store_id=".$store_id." AND status = 3");
        if(empty($user_brand['id'])){
            echo 555;  //无申请记录
            exit;
        }
        //查客户是否已经关联到卖场，不是就提示先关联卖场
        $user_price_model = & m('user_price');
        $price = $user_price_model->get('user_id ='.$user_brand['user_id']);
        if(empty($price['id'])){
            echo 333; //未关联区域
            exit;
        }
        //关联了，改变审核状态
        $user_brand['status'] = 1;
        $user_brand['through_time'] = gmtime();
        $user_brand_model->edit($id,$user_brand);
        echo 111;
        exit;
    }
    
    //审核不通过
    function un_apply(){
        $id = $_GET['id'];
        $user_brand_model = & m('user_brand');
        $user_brand = $user_brand_model->get("id =".$id);
        if($user_brand['store_id'] != $_SESSION['user_info']['store_id']){
            $this->show_warning('对不起，你没有权限操作！');
            return;
        }else{
            $user_brand_model->edit($id,'status = 2');
            $this->show_message('操作成功！','','index.php?app=my_protect&act=apply_list');
        }
    }
    
    //删除
    function drop(){
        if(empty($_SESSION['user_info']['store_id'])){
            $this->show_warning('你没有权限','','index.php');
            return;
        }
        $price_id = $_GET['id'];
        $price_model = & m('protect_price');
        $price = $price_model->get('price_id ='.$price_id);
        if($price['store_id'] != $_SESSION['user_info']['store_id']){
            $this->show_warning('你没有权限','','index.php');
            return;
        }
        $price_model->drop($price_id);
        $this->show_message('操作成功！','','index.php?app=my_protect&act=add_price');
    }
    
    //按照品牌查找品牌下面的客户
    function buyers(){
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=my_protect', LANG::get('品牌保护'), 'index.php?app=my_protect', LANG::get('品牌区域保护'));

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'my_protect';
        $this->_curitem('my_protect');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
        $brand_id = $_GET['brand_id'];
        $user_brand_model = & m('user_brand');
        $user_brands = $user_brand_model->find(array(
            'conditions' => 'status = 1 AND brand_id ='.$brand_id,
            'fields'     => 'user_name'
        ));
        $count = count($user_brands);
        $this->assign('brand_id', $brand_id);
        $this->assign('count', $count);
        $this->assign('user_brands', $user_brands);
        $this->display('brand_buyers.html');
    }
    
    //根据客户名查找客户
    function find_user(){
        $user_name = $_POST['user_name'];
        $member_model = & m('member');
        $members = $member_model->find(array(
            'conditions' => "user_name like '%".$user_name."%'",
            'fields'     => 'user_name',
        ));
        echo "<select id='choose_user'>";
        echo "<option value='0'>请选择需要增加的客户</option>";
        foreach($members as $member):
            echo "<option value='".$member['user_id']."'>".$member['user_name']."</option>";
        endforeach;
        echo "</select>";
        echo "<input type='button' value='添加' onclick='add_user()'>";
        exit;
    }
    
    //手动增加客户与品牌关联
    function add_user_to_brand(){
        $user_id = $_POST['user_id'];
        $brand_id = $_POST['brand_id'];
        $user_brand_model = & m('user_brand');
        $user_brand = $user_brand_model->get('brand_id ='.$brand_id." AND user_id =".$user_id);
        if(!empty($user_brand['id']) && $user_brand['status'] == 1){
            echo 2; //已经关联了
            exit;
        }
        if(!empty($user_brand['id']) && $user_brand['status'] == 2){
            $user_brand['status'] = 1;
            $user_brand['updated'] = gmtime();
            $user_brand_model->edit($user_brand['id'],$user_brand);
            echo 1;
            exit;
        }
        $user_price_model = & m('user_price');
        $user_price = $user_price_model->get('user_id ='.$user_id);
        if(empty($user_price['id'])){
            echo 3;  //未关联任何卖场
            exit;
        }
        $brand_model = & m('brand');
        $brand = $brand_model->get('brand_id ='.$brand_id." AND if_show = 1");
        if(empty($brand['brand_id'])){
            echo 4; //品牌不存在或不显示
            exit;
        }
        $member_model = & m('member');
        $member = $member_model->get(array(
            'conditions'  => 'user_id ='.$user_id,
            'fields'     => 'user_name',
        ));
        $store_id = $_SESSION['user_info']['store_id'];
        $new_user_brand = array(
            'user_id' => $user_id,
            'user_name' => $member['user_name'],
            'brand_id' => $brand_id,
            'brand_name' => $brand['brand_name'],
            'store_id' => $store_id,
            'add_time' => gmtime(),
            'status' => 1,
        );
        $user_brand_model->add($new_user_brand);
        echo 1;
        exit;
    }
    
    //手动增加客户与卖场关联
    function add_user_to_price(){
        $user_id = $_POST['user_id'];
        $price_id = $_POST['price_id'];
        
        $member_model = & m('member');
        $user_price_model = & m('user_price');
        $user_price = $user_price_model->get('user_id ='.$user_id." AND price_id =".$price_id);
        if(!empty($user_price['id'])){
            echo 2; //已经关联
            exit;
        }
        $member = $member_model->get('user_id ='.$user_id);
        $price_model = & m('protect_price');
        $price = $price_model->get('price_id ='.$price_id);
        $new_user_price = array(
            'user_id' => $user_id,
            'user_name' => $member['user_name'],
            'price_name' => $price['price_name'],
            'price_id' => $price_id,
            'store_id' => $_SESSION['user_info']['store_id'],
        );
        $user_price_model->add($new_user_price);
        echo 1;
        exit;
    }
    
    //删除客户的品牌关联
    function del_user(){
        $id = $_GET['id'];
        $user_brand_model = & m('user_brand');
        $user_brand = $user_brand_model->get('id ='.$id);
        if(empty($user_brand) || $user_brand['store_id'] != $_SESSION['user_info']['store_id']){
            $this->show_warning('该关联不存在/你没有权限');
            return;
        }
        $user_brand['status'] = 2;
        $user_brand['del_time'] = gmtime();
        $user_brand_model->edit($id , $user_brand);
        $this->show_message('操作成功！');
        return;
    }
    
    //区域关联的客户
    function user_price(){
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=my_protect', LANG::get('品牌保护'), 'index.php?app=my_protect', LANG::get('品牌区域保护'));

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'my_protect';
        $this->_curitem('my_protect');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
        
        $price_id = $_GET['id'];
        $user_price_model = & m('user_price');
        $user_price = $user_price_model->find('price_id ='.$price_id);
        $protect_price_model = & m('protect_price');
        $price_info = $protect_price_model->get('price_id='.$price_id);
        $this->assign('price_info', $price_info);
        $this->assign('user_price', $user_price);
        $this->display('user_price.html');
        
    }
    
    //根据user_id查找客户在做的品牌
    function find_brands(){
        $user_id = $_POST['user_id'];
        $user_brand_model = & m('user_brand');
        $user_brands = $user_brand_model->find(array(
            'conditions'  => 'user_id ='.$user_id." AND status = 1 AND store_id =".$_SESSION['user_info']['store_id'],
            'fields'     => 'brand_name',
        ));
        foreach($user_brands as $brand):
            echo $brand['brand_name']."\n";
        endforeach;
    }
    
    //删除客户与卖场的关联
    function del_price(){
        $id = $_POST['id'];
        $user_price_model = & m('user_price');
        $user_price = $user_price_model->get('id ='.$id);
        if($user_price['store_id'] == $_SESSION['user_info']['store_id']){
            $user_price_model->drop($id);
            echo 111;
            exit;
        }
        echo 222;
        exit;
    }
    
    //查卖场可以投放的品牌
    function find_brand(){
        $price_id = $_POST['price_id'];
        $store_id = $_SESSION['user_info']['store_id'];
        $price_users_model = & m('user_price');
        //查找卖场下面所有客户
        $users = $price_users_model->find('store_id ='.$store_id." AND price_id =".$price_id);
        $user_id = array();
        foreach($users as $user):
            $user_id[] = $user['user_id'];
        endforeach;
        $user_id_str = implode(',', $user_id);
        $user_brands_model = & m('user_brand');
        $has_brand = $user_brands_model->find("user_id in (".$user_id_str.")");
        $brand_name = array();
        foreach($has_brand as $user_brand):
            $brand_name[] = "'".$user_brand['brand_name']."'";
        endforeach;
        $brand_name_str = implode(',', $brand_name);
//        echo $brand_name_str;
        $brand_model = & m('brand');
        $can_brand = $brand_model->find("store_id =".$store_id." AND brand_name not in ($brand_name_str)");
        $count = count($can_brand);
        echo "可投放品牌(".$count."个)：<div>";
        foreach($can_brand as $val):
            echo "<span style='padding:10px;display:inline-block'>".$val['brand_name']."</span>";
        endforeach;
        echo "</div>";
        exit;
    }
    
    function _get_member_submenu() {
        $menus = array(
            array(
                'name' => '品牌保护',
                'url' => 'index.php?app=my_protect',
            ),
            array(
                'name' => '区域分布情况',
                'url' => 'index.php?app=my_protect&amp;act=add_price',
            ),
            array(
                'name' => '客户申请',
                'url' => 'index.php?app=my_protect&amp;act=apply_list'
            ),
        );

        if (ACT == 'batch_edit') {
            $menus[] = array(
                'name' => 'batch_edit',
                'url' => '',
            );
        } elseif (ACT == 'edit') {
            $menus[] = array(
                'name' => 'edit_goods',
                'url' => '',
            );
        }

        return $menus;
    }
}
