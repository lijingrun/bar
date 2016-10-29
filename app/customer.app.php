<?php
/**
 * Created by PhpStorm.
 * User: lijingrun
 * Date: 2016/7/20
 * Time: 13:41
 */

class CustomerApp extends MallbaseApp {

    function index(){
        echo 111;exit;
    }

    function get_customer_url(){
        $store_id = $_POST['store_id'];
        $user_id = $_SESSION['user_info']['user_id'];
        if(empty($user_id)){
            $user_id = rand(1000,9999);
            $user_id = "tourist".$user_id;
        }else{
            $member_model = & m('member');
            $member = $member_model->get("user_id = ".$user_id);
            $region_id = $member['region_id'];
        }
        if(empty($store_id)){
            $store_id = 7;
        }
        if(empty($region_id) || $region_id == 0){
            $region_id = 1;
        }
        $qq_model = & m('qq_service');
        $customer_info = $qq_model->get("store_id =".$store_id." AND region_id =".$region_id);
        if(!empty($customer_info)){
            $customer_no = $customer_info['qq'];
        }else{
            $store_model = & m('store');
            $store = $store_model->get("store_id =".$store_id);
            $customer_no = $store['im_qq'];
        }
        if(empty($customer_no)){
            $customer_no = '2880366017';
        }
//        $url = "http://localhost:3000/?from=".$user_id."&to=".$customer_no;
        $url = "http://128wallpaper.com:3000/?from=".$user_id."&to=".$customer_no;
        echo $url;
        exit;
    }

    function get_username(){
        header('Access-Control-Allow-Origin:*'); 
        $user_id = $_GET['user_id'];
        if(empty($user_id) || !is_numeric($user_id)){
            echo "访客";
        }else {
            $member_model = &m('member');
            $member = $member_model->get("user_id =" . $user_id);
            if (!empty($member)) {
                echo $member['user_name'];
            } else {
                echo "访客";
            }
        }
        return;
    }

}