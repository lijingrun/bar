<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class SpecialApp extends StoreadminbaseApp {

    function index() {
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('活动专场'), 'index.php?app=special', LANG::get('活动专场'));

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
        $this->_curitem('special');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
        //查看所有活动专场
        $special_model = & m('special');
        $specials = $special_model->find('end >' . gmtime());
        $this->assign('specials', $specials);
        $sgrade_model = & m('sgrade');
        $sgrades = $sgrade_model->findAll();
        $this->assign('sgrades', $sgrades);

        $this->display('special.html');
    }

    //参团
    function join_special() {
        if (!IS_POST) {
            $special_id = $_GET['id'];
            $special_model = & m('special');
            $spec_model = & m('goodsspec');
            /* 当前用户中心菜单 */
            $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
            $this->_curitem('special');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
            $this->_curmenu($type);
            $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
            //获取活动
            $special = $special_model->get('special_id =' . $special_id);
            $this->assign('special', $special);
            //获取参加活动的产品
            $special_goods_model = & m('special_goods');
            $special_goods = $special_goods_model->find('special_id =' . $special_id . " AND store_id =" . $_SESSION['user_info']['store_id']);
            $grade_model = & m("sgrade");
            foreach ($special_goods as $key => $special_good):
                $spec = $spec_model->get('spec_id =' . $special_good['spec_id']);
                $special_goods[$key]['spec_name'] = $spec['spec_1'];
                $grade = $grade_model->get("grade_id =".$spec['spec_2']);
                $special_goods[$key]['grade_name'] = $grade['grade_name'];
            endforeach;
            //查对应会员等级
//            $sgrade_model = & m('sgrade');
//            $sgrade = $sgrade_model->get('grade_id =' . $special['sgrade']);
//            $this->assign('sgrade', $sgrade);

            $this->assign('special_goods', $special_goods);
            $this->display('special_goods.html');
        } else {
            $special_id = $_POST['special_id'];
            $goods_id = $_POST['goods_id_post'];
            $specs_array = $_POST['spec'];
            $goods_model = & m('goods');

            $special_goods_model = & m('special_goods');
            if (empty($special_id)) {
                $this->show_warning('数据错误！');
            }
            if (empty($goods_id) || empty($specs_array)) {
                $this->show_warning('你还没选择任何产品！', '点击返回');
            }
            $goods = $goods_model->get(array(
                'conditions' => "goods_id =" . $goods_id,
                'fields' => 'goods_name, default_image',
            ));
            foreach ($specs_array as $spec_array):
                foreach ($spec_array as $key => $spec):
                    //$key = spec_id  $spec_array[$key] = price
                    //输入了价格才保存，不输入价格就不保存
                    if (!empty($spec)) {
                        $new_special_goods = array(
                            'special_id' => $special_id,
                            'goods_id' => $goods_id,
                            'spec_id' => $key,
                            'goods_name' => $goods['goods_name'],
                            'goods_image' => $goods['default_image'],
                            'price' => $spec,
                            'store_id' => $_SESSION['user_info']['store_id'],
                        );
                        $special_goods_model->add($new_special_goods);
                    }
                endforeach;
            endforeach;
            $this->show_message('操作成功！', '', 'index.php?app=special&act=join_special&id=' . $special_id);
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
//        $special_sgrade = $_POST['special_sgrade'];
        $spec_model = & m('goodsspec');
        $sgrade_model = & m('sgrade');
//        $sgrade = $sgrade_model->get('grade_id =' . $special_sgrade);
        $specs = $spec_model->find(array(
            'conditions' => 'goods_id =' . $goods_id ,
            'fields' => 'spec_2,spec_1, price',
        ));
        echo "<div id='spec_div'>";
        echo "<table><tr><th style='width:100px;'>规格</th><th  style='width:100px;'>会员等级</th><th  style='width:100px;'>原价</th><th  style='width:100px;'>活动价</th><th></th></tr>";
        foreach ($specs as $spec):
            $sgrade = $sgrade_model->get('grade_id =' . $spec['spec_2']);
            echo "<tr><th>" . $spec['spec_1'] . "</th><th>" .$sgrade['grade_name'] . "</th><th>￥" . $spec['price'] . "</th><th><input class='spec' type='text' name='spec[][" . $spec['spec_id'] . "]'></th><th><span style='color:red'>如果不填写价格，该规格则不参加活动</span></th></tr>";
        endforeach;

        echo "<tr><td colspan='4'><input type='submit' value='提交'></td></tr></table></div>";
        exit;
    }

    //删除产品
    function del_specialgoods() {
        $id = $_GET['id'];
        $special_goods_model = & m('special_goods');
        if (!empty($id)) {
            $speial_goods = $special_goods_model->get('id =' . $id);
            if ($speial_goods['store_id'] == $_SESSION['user_info']['store_id']) {
                $special_goods_model->drop($id);
                $this->show_message('操作成功！', '删除成功！');
            } else {
                $this->show_warning('你没有权限操作！', '点击返回');
            }
        } else {
            $this->show_warning('系统错误！', '点击返回');
        }
    }

}
