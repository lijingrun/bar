<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of join
 *
 * @author lijingrun
 */
class JoinApp extends StoreadminbaseApp {

    function index() {
        $join_model = & m('join');
        $store_id = $_SESSION['user_info']['store_id'];
        $join = $join_model->get('store_id =' . $store_id);
        if (!IS_POST) {
                        /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('加盟介绍'), 'index.php?app=introduction', LANG::get('加盟介绍'));

            /* 当前用户中心菜单 */
            $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
            $this->_curitem('join');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
            $this->_curmenu($type);
            $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
            
            $this->assign('join', $join);
            $this->display('wallpaper_join.html');
        } else {
            $newjoin = array(
                'store_id' => $store_id,
                'join_detail' => $_POST['join_detail'],
            );
            if (empty($join)) {
                $join_model->add($newjoin);
            } else {
                $join_model->edit($store_id, $newjoin);
            }
            $this->show_message('操作成功！');
            return;
        }
    }



}
