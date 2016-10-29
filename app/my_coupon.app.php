<?php

class My_couponApp extends MemberbaseApp {

    var $_user_mod;
    var $_store_mod;
    var $_coupon_mod;

    function index() {
        $page = $this->_get_page(10);
        $this->_user_mod = & m('member');
        $this->_store_mod = & m('store');
        $this->_coupon_mod = & m('coupon');
        $msg = $this->_user_mod->findAll(array(
            'conditions' => 'user_id = ' . $this->visitor->get('user_id'),
            'count' => true,
            'limit' => $page['limit'],
            'include' => array('bind_couponsn' => array())));
        $page['item_count'] = $this->_user_mod->getCount();
        $coupon = array();
        $coupon_ids = array();
        $msg = current($msg);
        if (!empty($msg['coupon_sn'])) {
            foreach ($msg['coupon_sn'] as $key => $val) {
                $coupon_tmp = $this->_coupon_mod->get(array(
                    'fields' => "this.*,store.store_name,store.store_id",
                    'conditions' => 'coupon_id = ' . $val['coupon_id'],
                    'join' => 'belong_to_store',
                ));
                $coupon_tmp['valid'] = 0;
                $time = gmtime();
                if (($val['remain_times'] > 0) && ($coupon_tmp['end_time'] == 0 || $coupon_tmp['end_time'] > $time)) {
                    $coupon_tmp['valid'] = 1;
                }
                $coupon[$key] = array_merge($val, $coupon_tmp);
            }
        }
        $this->import_resource(array(
            'script' => array(
                array(
                    'path' => 'dialog/dialog.js',
                    'attr' => 'id="dialog_js"',
                ),
                array(
                    'path' => 'jquery.ui/jquery.ui.js',
                    'attr' => '',
                ),
                array(
                    'path' => 'jquery.ui/i18n/' . i18n_code() . '.js',
                    'attr' => '',
                ),
                array(
                    'path' => 'jquery.plugins/jquery.validate.js',
                    'attr' => '',
                ),
            ),
            'style' => 'jquery.ui/themes/ui-lightness/jquery.ui.css',
        ));
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('my_coupon'), 'index.php?app=my_coupon', LANG::get('coupon_list'));
        $this->_curitem('my_coupon');

        $this->_curmenu('coupon_list');
        $this->assign('page_info', $page);          //将分页信息传递给视图，用于形成分页条
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('coupon_list'));
        $this->_format_page($page);
        $this->assign('coupons', $coupon);
        $this->display('my_coupon.index.html');
    }

    function bind() {
        if (!IS_POST) {
            header("Content-Type:text/html;charset=" . CHARSET);
            $this->display('my_coupon.form.html');
        } else {
            $coupon_sn = isset($_POST['coupon_sn']) ? trim($_POST['coupon_sn']) : '';
            if (empty($coupon_sn)) {
                $this->pop_warning('coupon_sn_not_empty');
                exit;
            }
            $coupon_sn_mod = &m('couponsn');
            $coupon = $coupon_sn_mod->get_info($coupon_sn);
            if (empty($coupon)) {
                $this->pop_warning('involid_data');
                exit;
            }
            $coupon_sn_mod->createRelation('bind_user', $coupon_sn, $this->visitor->get('user_id'));
            $this->pop_warning('ok', 'my_coupon_bind');
            exit;
        }
    }

    function drop() {
        if (!isset($_GET['id']) && empty($_GET['id'])) {
            $this->show_warning("involid_data");
            exit;
        }
        $ids = explode(',', trim($_GET['id']));
        $couponsn_mod = & m('couponsn');
        $couponsn_mod->unlinkRelation('bind_user', db_create_in($ids, 'coupon_sn'));
        if ($couponsn_mod->has_error()) {
            $this->show_warning($couponsn_mod->get_error());
            exit;
        }
        $this->show_message('drop_ok', 'back_list', 'index.php?app=my_coupon');
    }

    function _get_member_submenu() {
        $menus = array(
            array(
                'name' => 'coupon_list',
                'url' => 'index.php?app=my_coupon',
            ),
        );
        return $menus;
    }

    //客户抢优惠券页面
    function grab_coupon() {
        $coupon_get_model = & m('coupon_get');
        $coupon_get_user_model = & m('coupon_get_user');
        $user_id = $_SESSION['user_info']['user_id'];
        $now = time() - 28800;
        $get_array = array();
        //获取可以抢噶优惠券
        $coupon_gets = $coupon_get_model->find("status = 1 AND stime <=" . $now . " AND etime >=" . $now);
        //循环查找用户是否已经领取过
        foreach ($coupon_gets as $key => $coupon_get):
            $get_user = $coupon_get_user_model->get("user_id =" . $user_id . " AND id =" . $coupon_get['coupon_id']);
            if (!empty($get_user['id'])) {
                $get_array[$key]['has_grab'] = true;
            }
            $get_array[$key]['coupon_img'] = $coupon_get['coupon_img'];
            $get_array[$key]['coupon_id'] = $coupon_get['coupon_id'];
        endforeach;
        $this->assign('coupon_gets', $get_array);
        $this->display("grab_coupon.html");
    }
    
    
    //领取优惠券
    function extend() {
        $user_model = & m('member');
        $coupon_get_user_model = & m('coupon_get_user');
        $coupon_get_model = & m('coupon_get');
        $coupon_id = $_GET['coupon_id'];
        $user_id = $_SESSION['user_info']['user_id'];
        //查看是否已经领取
        $coupon_get_user = $coupon_get_user_model->get("id =".$coupon_id." AND user_id =".$user_id);
        if(!empty($coupon_get_user['id'])){
            show_warning("您已经领取过，不能重复领取");
            return;
        }
        //保存领取记录
        $new_user = array(
            'id' => $coupon_id,
            'user_id' => $user_id,
            'get_time' => time(),
        );
        //生成新的coupon_sn
        $users = $user_model->find("user_id =".$user_id);
        $this->assign_user($coupon_id, $users);
        
        $coupon_get_user_model->add($new_user);
        if($coupon_get_user_model->has_error()){
            $this->show_warning($coupon_get_user_model->get_error());
            return;
        }
        $this->show_message("成功领取！","","index.php?app=my_coupon");
        
        
    }
    
    function assign_user($id, $users) {
        $_user_mod = & m('member');
        $count = count($users);
        $users = array_values($users);
        $arr = $this->generate($count, $id);
        $i = 0;
        foreach ($users as $key => $user) {
            $users[$key]['coupon'] = $arr[$i];
            $_user_mod->createRelation('bind_couponsn', $user['user_id'], array($arr[$i]['coupon_sn'] => array('coupon_sn' => $arr[$i]['coupon_sn'])));
            $i = $i + 1;
        }
        return $users;
    }

    function generate($num, $id) {
        $coupon_model = & m('coupon');
        $use_times = $coupon_model->get(array('fields' => 'use_times', 'conditions' =>  ' coupon_id = ' . $id));
        if ($num > 1000) {
            $num = 1000;
        }
        if ($num < 1) {
            $num = 1;
        }
        $times = $use_times['use_times'];
        $add_data = array();
        $str = '';
        $pix = 0;
        if (file_exists(ROOT_PATH . '/data/generate.txt')) {
            $s = file_get_contents(ROOT_PATH . '/data/generate.txt');
            $pix = intval($s);
        }
        $max = $pix + $num;
        file_put_contents(ROOT_PATH . '/data/generate.txt', $max);
        $couponsn = '';
        $tmp = '';
        $cpm = '';
        $str = '';
        for ($i = $pix + 1; $i <= $max; $i++) {
            $cpm = sprintf("%08d", $i);
            $tmp = mt_rand(1000, 9999);
            $couponsn = $cpm . $tmp;
            $str .= "('{$couponsn}', {$id}, {$times}),";
            $add_data[] = array(
                'coupon_sn' => $couponsn,
                'coupon_id' => $id,
                'remain_times' => $times,
            );
        }
        $string = substr($str, 0, strrpos($str, ','));
//        $this->_couponsn_mod->db->query("INSERT INTO {$this->_couponsn_mod->table} (coupon_sn, coupon_id, remain_times) VALUES {$string}", 'SILENT');
        $coupon_sn_model = & m('couponsn');
        $coupon_sn_model->add($add_data);
        return $add_data;
    }
    
    //优惠券详细情况
    function coupon_detail(){
        $coupon_model = & m('coupon');
        $coupon_id = $_GET['coupon_id'];
        $coupon = $coupon_model->get("coupon_id =".$coupon_id);
        $this->assign('coupon', $coupon);
        $this->display("coupon_detail.html");
    }

}

?>