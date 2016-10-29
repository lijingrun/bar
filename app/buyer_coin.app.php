<?php
/**
 * Created by PhpStorm.
 * User: lijingrun
 * Date: 2016/7/29
 * Time: 16:35
 */
class Buyer_coinApp extends MemberbaseApp {

    function index(){
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'),    url('app=member'),
            LANG::get('overview'));

        /* 当前用户中心菜单 */
        $this->_curitem('overview');
        $this->_config_seo('title', Lang::get('member_center'));

        $user_id = $_SESSION['user_info']['user_id'];
        $coin_log_model = & m('coin_log');
        $coin_log = $coin_log_model->find("member_id =".$user_id." order by add_time desc");
        $member_model = & m('member');
        $member = $member_model->get("user_id =".$user_id);
//        $point_to_coin_model = & m('point_to_coin');
//        $point_to_coin = $point_to_coin_model->get("user_id =".$user_id);
//        if(!empty($point_to_coin)){
//            $has_convert = true;
//        }
//        $this->assign('has_convert' , $has_convert);
        $this->assign('member',$member);
        $this->assign('coin_log',$coin_log);
        $this->display('coin_log.html');
    }

    //积分转换成为金币的页面
    function point_to_coin(){
        $user_id = $_SESSION['user_info']['user_id'];
        $db = & db();
        $user_id = $this->visitor->get('user_id');
        $point = array(
            '+' => $db->getOne(
                'SELECT sum(total_point) FROM `ecm_order` WHERE `status` >= 40 AND `buyer_id` ='. intval($user_id) ),
            '-' => $db->getOne(
                'SELECT sum(point_price) FROM `ecm_order` WHERE `status` <> 0  AND `buyer_id` ='. intval($user_id) ),
            '^' => $db->getOne(
                'SELECT point FROM `ecm_member` WHERE `user_id` ='. intval($user_id) ),
        );
        $point['0'] = $point['+'] - $point['-'];
        $coin = $point['0']*80;

//        if(floatval($point['0']) > floatval($point['^']) ) {
//            $user_mod = & m('member');
//            $user_mod->edit($this->visitor->get('user_id'), array(
//                'point' => $point['0'],
//            ));
//        }

        $this->assign('coin',$coin);
        $this->assign('point',$point['0']);

        $this->display('point_to_coin.html');
    }

    function sure_to_convert(){
        $store_model = & m("member");
        $users = $store_model->find("point > 2");
        foreach($users as $user):
            $user_id = $user['store_id'];
            $point_to_coin_model = & m('point_to_coin');
            $point_to_coin = $point_to_coin_model->get("user_id =".$user_id);
            if(empty($point_to_coin['user_id'])){
                $member_model = &m('member');
                $member = $member_model->get("user_id =" . $user_id);
//                $db = &db();
//                $point = array(
//                    '+' => $db->getOne(
//                        'SELECT sum(total_point) FROM `ecm_order` WHERE `status` >= 40 AND `buyer_id` =' . intval($user_id)),
//                    '-' => $db->getOne(
//                        'SELECT sum(point_price) FROM `ecm_order` WHERE `status` <> 0  AND `buyer_id` =' . intval($user_id)),
//                    '^' => $db->getOne(
//                        'SELECT point FROM `ecm_member` WHERE `user_id` =' . intval($user_id)),
//                );
//                $point = $point['+'] - $point['-'];
                $point = $member['point'];
                if($point > 0) {
                    $coin = $point * 80;
                    $member['point'] = 0;
                    $member['coin'] += $coin;
                    $coin_log_model = &m('coin_log');
                    $new_coin_log = array(
                        'reason' => "将" . $point . "积分转换成商城币",
                        'lanyu_coin' => $coin,
                        'member_id' => $user_id,
                        'add_time' => gmtime(),
                    );
                    $coin_log_model->add($new_coin_log);
                    $new_log = array(
                        'user_id' => $user_id,
                        'point' => $point,
                        'coin' => $coin,
                        'add_time' => gmtime(),
                    );
                    $point_to_coin_model->add($new_log);
                    $member_model->edit($user_id, $member);
                }
            }
        endforeach;
        echo 111;
        exit;
    }

}