<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of mobile_join
 *
 * @author lijingrun
 */
class Mobile_joinApp extends StorebaseApp {
    
    
    function index(){
        $join_model = & m('join');
        $store_id = $_GET['store_id'];
        if(empty($store_id)){
            $this->show_warning('该加盟信息不存在！');
            return;
        }
        $join = $join_model->get('store_id ='.$store_id);
        if(empty($join)){
            $this->show_warning('该商家还未设置加盟信息');
            return;
        }
        $this->assign('join', $join);
        $this->display('wallpaper_join.html');
    }
}
