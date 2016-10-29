<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of mobile_introduction
 *
 * @author lijingrun
 */
class Mobile_introductionApp extends StorebaseApp {

    //put your code here
    function index() {
        $introduction_model = & m('enterprise');
        $enterprise_img_model = & m('enterprise_images');
        $store_id = $_GET['store_id'];
        if (empty($store_id)) {
            $this->show_warning('页面错误！');
            return;
        }
        $introduction = $introduction_model->get('store_id =' . $store_id);
        if(empty($introduction['id'])){
            $this->show_warning('卖家还未配置企业简介！');
            return;
        }
        $images = $enterprise_img_model->find('store_id = ' . $store_id);
        $this->assign('images', $images);
        $this->assign('introduction', $introduction);
        $this->display('introduction.html');
    }

}
