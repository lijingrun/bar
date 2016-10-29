<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 * 
 * 营销专用端口，方便营销查询一些商城内的资料，仅限手机端
 */

class MarketingApp extends StorebaseApp {
    
    function index(){
        
        
        $this->display('marketing.html');
    }
    
    
    //查看可投放品牌
    function brand(){
        $store_id = $_GET['store_id'];
        if(empty($store_id)){
            $this->show_warning('请先选择店铺！');
            return;
        }
        $provicne_model = & m('province');
        $provinces = $provicne_model->findAll();
        $this->assign('provinces', $provinces);
        $this->assign('store_id', $store_id);
        $this->display('market_brand.html');
    }
    
    //按省份查找城市
    function find_city(){
        $province_id = $_POST['province_id'];
        $city_model = & m('city');
        $citys = $city_model->find('topid ='.$province_id);
        echo "<select id='city' onchange='find_price();'>";
        echo "<option value='0'>请选择城市</option>";
        foreach($citys as $city):
            echo "<option value='".$city['id']."'>".$city['name']."</option>";
        endforeach;
        echo "</select>";
        exit;
    }


    //获取产品分类
    function get_category(){
        $store_id = $_GET['store_id'];
        $category_model = & m('gcategory');
        $price_id = $_GET['price_id'];
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
        $this->assign('price_id',$price_id);
        $this->display('market_category.html');
    }

    public function goods_list(){
        $store_id = $_GET['store_id'];
        $price_id = $_GET['price_id'];
        $cate_id = $_GET['cate_id'];
        $page = $_GET['page'];
        if(empty($page)){
            $page = 1;
        }
        $page_size = 10;
        $limit = $page*$page_size-$page_size.",".$page_size;
        if(empty($store_id) || empty($price_id) || empty($cate_id)){
            $this->show_warning('信息不足');
            return;
        }
        //先查可做品牌
        $brands = $this->find_brand($store_id,$price_id);
        $brands_array = array();
        foreach($brands as $brand):
            $brands_array[] = "'".$brand['brand_name']."'";
        endforeach;
        $brand_str = implode(',',$brands_array);
        //查店铺类型下面的产品
        $category = & m('category_goods');
        $category = $category->getall(
            "Select * From ecm_category_goods Where cate_id =".$cate_id
        );
        $goods_id_array = array();
        foreach($category as $val):
            $goods_id_array[] = $val['goods_id'];
        endforeach;
        $goods_id_str = implode(',',$goods_id_array);

        $goods_model = & m('goods');
        $goods_list = $goods_model->find(array(
            'conditions' => 'store_id = '.$store_id." AND if_show = 1 AND goods_id in (".$goods_id_str.") AND brand in (".$brand_str.") ",
            'fields' => 'goods_name, default_image, price_distributor',
            'limit' => $limit,
        ));
        //循环一次产品，将
        if($page <= 1){
            $pev_page = 1;
        }else{
            $pev_page = $page-1;
        }

        $next_page = $page+1;
        $this->assign('page',$page);
        $this->assign('store_id',$store_id);
        $this->assign('price_id',$price_id);
        $this->assign('cate_id',$cate_id);
        $this->assign('pev_page',$pev_page);
        $this->assign('next_page',$next_page);
        $this->assign('goods_list',$goods_list);
        $this->display('marketing_list.html');
    }

    //按省份城市查找卖场
    function find_place(){
        $province_id = $_POST['province_id'];
        $city_id = $_POST['city_id'];
        $store_id = $_POST['store_id'];
        $place_model = & m('protect_price');
        $places = $place_model->find('province_id = '.$province_id." AND city_id =".$city_id." AND store_id =".$store_id);
        if($places){
        echo "<select id='place' >";
        echo "<option value='0'>请选择卖场</option>";
        foreach($places as $place):
            echo "<option value='".$place['price_id']."'>".$place['price_name']."</option>";
        endforeach;
        echo "</select>";
        }else{
            echo "<span id='place'>您的店铺在该城市还未设置任何卖场！</span>";
        }
        exit;
        
    }
    
    
    //根据卖场获取可投放品牌
    function find_brand($store_id,$place_id ){
        $place_model = & m('user_price');
        $places = $place_model->find('price_id ='.$place_id);
        $brands = $this->_get_can_brand($store_id, $places);
        return $brands;
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
            $this->show_warning('本卖场还未关联任何客户！');
            return;
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
            'fields'     => 'brand_name',
        ));
        return $can_brands;
    }
    
}