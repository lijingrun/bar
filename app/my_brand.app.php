<?php

/**
 *    我的品牌控制器
 *
 *    @author    Garbin
 *    @usage    none
 */
class My_brandApp extends MemberbaseApp {

    function index() {
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=my_brand', LANG::get('我的品牌'), 'index.php?app=my_brand', LANG::get('品牌列表'));

        /* 当前用户中心菜单 */
        $this->_curitem('my_brand');

        /* 当前所处子菜单 */
        $this->_curmenu('my_brand');
        
//        查找品牌
        $user_brand_model = & m('user_brand');
        $user_brands = $user_brand_model->find('user_id ='.$_SESSION['user_info']['user_id']." AND status = 1");
        $this->assign('user_brands', $user_brands);
        $this->display('my_brand.html');
    }

}

?>
