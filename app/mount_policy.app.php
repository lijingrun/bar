<?php
/**
 * Created by PhpStorm.
 * User: lijingrun
 * Date: 2016/8/20
 * Time: 9:17
 * 大姨妈政策（一个月一次）
 */

class Mount_policyApp extends StoreadminbaseApp {

    public function Index(){
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', '大姨妈政策', 'index.php?app=mount_policy', '政策列表');

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
        $this->_curitem('order_manage');
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));



        $store_id = $_SESSION['user_info']['store_id'];
        $grade_model = & m('sgrade');
        $mount_policy_model = & m('mount_policy');
        $policy_model = & m("policy_type");
        $store_mount_policys = $mount_policy_model->find('store_id ='.$store_id);
        if(!empty($store_mount_policys)){
            foreach($store_mount_policys as $key=>$val):
                $store_mount_policys[$key]['policy'] = $policy_model->get("id =".$val['policy_id']);
                $store_mount_policys[$key]['grade'] = $grade_model->get("grade_id =".$val['sgrade_id']);
            endforeach;
        }
        $this->assign('store_policy',$store_mount_policys );
        $this->display("mount_policy.html");
    }

    //增加大姨妈政策
    public function Add(){
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', '大姨妈政策', 'index.php?app=mount_policy', '增加政策');

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
        $this->_curitem('order_manage');
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));

        if(!IS_POST) {
            $sgrade_model = &m("sgrade");
            $grades = $sgrade_model->findAll();
            $policy_type_model = &m("policy_type");
            $policy_types = $policy_type_model->findAll();
            if (empty($policy_types)) {
                $this->show_message('后台还未设置任何政策', '请先通知后台设置政策', 'index.php?app=seller_admin');
            }

            $this->assign('grades', $grades);
            $this->assign('policy_types', $policy_types);
            $this->display("mount_policy_add.html");
        }else{
            $store_id = $_SESSION['user_info']['store_id'];
            $mount_policy_model = & m("mount_policy");
            $check = $mount_policy_model->get("policy_id =".$_POST['policy_id']." AND store_id =".$store_id." AND sgrade_id =".$_POST
            ['grade_id']);
            if(!empty($check)){
                $this->show_message("政策已经存在！",'','');
            }
            $new_policy = array(
                'policy_id' => $_POST['policy_id'],
                'sgrade_id' => $_POST['grade_id'],
                'store_id' => $store_id,
            );
            $mount_policy_model->add($new_policy);
            $this->show_message('保存成功！','操作完成','index.php?app=mount_policy');
        }
    }

}