<?php

class Brand_protectApp extends MallbaseApp{
    
    function index(){
        $store_id = $_GET['store_id'];
        $price_model = & m('user_price');
        $user_id = $_SESSION['user_info']['user_id'];
        $this->assign('store_id', $store_id);
        if(empty($user_id)){
            $this->show_warning('请先登录','对不起','index.php?app=member');
            return;
        }
        //查是否在绑定卖场，如果无，就提示需要先申请绑定卖场
        $price = $price_model->get('user_id ='.$user_id." AND store_id =".$store_id);
        if(empty($price['id'])){
            $this->show_warning('对不起，你还没关联任何卖场','请先申请关联卖场','index.php?app=brand_protect&act=add&store_id='.$store_id);
            return;
        }else{
            $this->display('brand_protect_index.html');
        }
    }
    
    //申请添加卖场
    function add(){
        $province_model = & m('province');
        if(!IS_POST){
            //查省份
            $provinces = $province_model->findAll();
            $this->assign('provinces', $provinces);
            $store_id = $_GET['store_id'];
            $this->assign('store_id', $store_id);
            $this->display('protect_add_price.html');
        }else{
            $address = $_POST['address'];
            $city = $_POST['city'];
            $province_id = $_POST['province'];
            $store_id = $_POST['store_id'];
            $province = $province_model->get('id ='.$province_id);
            $protect_apply_model = & m('protect_apply');
            $user_id = $_SESSION['user_info']['user_id'];
            $apply = $protect_apply_model->get('store_id = '.$store_id." AND user_id =".$user_id);
            if(!empty($apply['id'])){
                echo 222;
                exit;
            }
            $new_apply = array(
                'user_id' => $user_id,
                'user_name' => $_SESSION['user_info']['user_name'],
                'store_id' => $store_id,
                'city' => $city,
                'province' => $province['name'],
                'address' => $address,
                'add_time' => gmtime(),
            );
            $protect_apply_model->add($new_apply);
            //发送后台信息到对应店铺
            $message_model = & m('message');
            $new_message = array(
                'to_id' => $store_id,
                'content' => '有客户申请了卖场绑定',
                'add_time' => gmtime(),
                'last_update' => gmtime(),
                'new' => 1,
                'status' => 3,
            );
            $message_model->add($new_message);
            echo 111;
            exit;
        }
    }
    
    
    //获取城市
    function get_count(){
        $province_id = $_POST['province_id'];
        $city_model = & m('city');
        $citys = $city_model->find('topid ='.$province_id);
        echo "<select id='city'><option value='0'>请选择所在城市</option>";
        foreach($citys as $city):
            echo "<option value='".$city['name']."'>".$city['name']."</option>";
        endforeach;
        echo "</select>";
        exit;
    }
    
    //已保护的品牌
    function my_brand(){
        $user_id = $_SESSION['user_info']['user_id'];
        $store_id = $_GET['store_id'];
        $brand_model = & m('brand');
        $user_brand_model = & m('user_brand');
        $my_brands = $user_brand_model->find("user_id =".$user_id." AND status = 1 AND store_id =".$store_id);
        foreach($my_brands as $key=>$brands):
            $brand = $brand_model->get('brand_id ='.$brands['brand_id']);
            $my_brands[$key]['logo'] = $brand['logo'];
        endforeach;
        $this->assign('store_id', $store_id);
        $this->assign('my_brands', $my_brands);
        $this->display('my_brands.html');
    }
    
    //可投放品牌
    function can_brand(){
        $store_id = $_GET['store_id'];
        $this->assign('store_id', $store_id);
        $user_id = $_SESSION['user_info']['user_id'];
        $user_price_model = & m('user_price');
        $my_price = $user_price_model->find('user_id ='.$user_id." AND store_id =".$store_id);
        $can_brands = $this->_get_can_brand($store_id,$my_price);
        $this->assign('can_brands', $can_brands);
        $this->display('can_brand.html');
    }
    
    //获取可投放品牌
    function _get_can_brand($store_id,$prices){
        $user_price_model = & m('user_price');
        //先通过卖场来获取同一卖场下面的所有会员
        $user_ids = array();
        foreach($prices as $price):
            //找店铺下面同个卖场的用户
            $user_prices = $user_price_model->find('price_id ='.$price['price_id']." AND store_id =".$store_id);
            foreach($user_prices as $user_price):
                $user_ids[] = $user_price['user_id'];
            endforeach;
        endforeach;
        $user_ids_str = implode(',', $user_ids);
        if(empty($user_ids_str)){
            $this->show_warning('卖场还未绑定任何客户！');
            return null;
        }
        $user_brand_model = & m('user_brand');
        //查店铺下面这些会员下面已经保护的所有品牌
        $has_brand = $user_brand_model->find(array(
            'conditions' => "user_id in (".$user_ids_str.") AND status = 1 AND store_id =".$store_id,
            'fields'     => 'brand_name',
        ));
        array_unique($has_brand);
        $has_brand_array = array();
        foreach($has_brand as $val):
            $has_brand_array[] = "'".$val['brand_name']."'";
        endforeach;
        $has_brand_str = implode(',', $has_brand_array);
        $brand_model = & m('brand');
        $can_brands = $brand_model->find(array(
            'conditions' => 'store_id ='.$store_id." AND brand_name not in (".$has_brand_str.")",
            'fields'     => 'brand_name, brand_logo',
        ));
        return $can_brands;
    }

    //跳转到店铺分类页面
    function get_category(){
        $store_id = $_GET['store_id'];
        $category_model = & m('gcategory');
        //查找一级分类
        $category_level1 = $category_model->find(array(
            'conditions' => 'parent_id = 0 AND store_id ='.$store_id." AND if_show = 1",
            'order' => 'sort_order',
        ));
        foreach($category_level1 as $key=>$val):
            $level2 = $category_model->find(array(
                'conditions' => "parent_id = ".$val['cate_id']." AND store_id =".$store_id." AND if_show = 1",
                'order' => 'sort_order',
            ));
            $category_level1[$key]['level2'] = $level2;
        endforeach;
        $this->assign('store_id',$store_id);
        $this->assign('categorys',$category_level1);
        $this->display('category_list.html');
    }


}

 