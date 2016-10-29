<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of main
 *
 * @author lijingrun
 */
class Taco_index_styleWidget extends BaseWidget {
    var $_name = 'taocz_index_style';
    
    //获取数据
    function _get_data() {
        $data = array();
        //获取推荐的产品
        $goods_model = & m('goods');
        $r_goods = $goods_model->find('recommended = 1 AND if_show = 1');
        $data['r_goods'] = $r_goods;
        
        return $data;
    }
    
    function get_config_datasrc(){
        
    }
    
    function _get_acategory_options($input){
        
    }
}
