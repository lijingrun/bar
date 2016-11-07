<?php

class DefaultApp extends MallbaseApp {

    function index() {
//        //先获取code，看是否微信登录
//        $code = $_GET['code'];
//        if(!empty($code)){
//            //通过code获取用户的open_id
//            $appid = "wx7ccf7c8a377ea11b";
//            $secret = "4cf617291ca6d73b022c13d085dc6da1";
//            $code = $_GET["code"];
//            $get_token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$appid.'&secret='.$secret.'&code='.$code.'&grant_type=authorization_code';
//            $ch = curl_init();
//            curl_setopt($ch,CURLOPT_URL,$get_token_url);
//            curl_setopt($ch,CURLOPT_HEADER,0);
//            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
//            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
//            $res = curl_exec($ch);
//            curl_close($ch);
//            $json_obj = json_decode($res,true);
//            //根据openid和access_token查询用户信息
//            $openid = $json_obj['openid'];
//            //判断该openid是否绑定了账号，是的话直接登录账号
//            $member_model = & m('member');
//            $member = $member_model->get("openid = '".$openid."' AND openid IS NOT NULL AND openid <> ''");
//            if(!empty($member['user_id'])){
//                $this->_do_login($member['user_id']);
//            }
//            $_SESSION['weixin']['openid'] = $openid;
//        }
//
        $index_img_model = & m('index_img');
        $index_images = $index_img_model->find('status = 1 and the_order < 100 order by the_order');
        $images = array();
        $i = 0;
        foreach($index_images as $image){
            $images[$i] = $image;
            $i++;
        }
        $this->assign('images', $images);
        $index_under_images = $index_img_model->find('status = 1 and the_order >= 100 order by the_order');
        $under_images = array();
        $i = 0;
        foreach($index_under_images as $image){
            $under_images[$i] = $image;
            $i++;
        }
        $this->assign('under_images',$under_images);
//        $this->assign('index', 1); // 标识当前页面是首页，用于设置导航状态
//        $this->assign('icp_number', Conf::get('icp_number'));
//
//
//        //获取最近一个专场活动
//        $special_model = & m('special');
//        $special = $special_model->get(array(
//            'conditions' => "end > ".gmtime()." AND is_show = 1",
//            'order'      => 'start',
//        ));
//        if(!empty($special)){
//            $this->assign('special', $special);
//        }
//
//        //获取active
//        $active_model = & m('admin_active');
//        $active = $active_model->get("end_time > ".gmtime()." AND enable = 1");
//        if($active['start_time'] > gmtime()){
//            $the_time = $active['start_time'] - gmtime();
//            $a_to_begin = true;
//        }else{
//            $the_time = $active['end_time'] - gmtime();
//            $a_to_begin = false;
//        }
//        $this->assign('a_to_begin', $a_to_begin);
//        $this->assign('active', $active);
//        $this->assign('the_time', $the_time);
//
//
//        //获取推荐产品
//        $recommend_model = & m('recommend');
//        $recommend = $recommend_model->get_recommended_goods(3, 20);
//        //获取登录账号的权限.如果未登录，就按消费者来看
//        $sgrade = $_SESSION['user_info']['sgrade'];
//        if (empty($sgrade)) {
//            $sgrade = 5;
//        }
//        //循环商品，如果客户所在等级下面的所有规格数量都为0，就显示已售罄图片
//        $spec_model = & m('goodsspec');
//        foreach ($recommend as $key => $goods) {
//            $specs = $spec_model->find('goods_id =' . $goods['goods_id'] . " AND spec_2 =" . $sgrade);
////                            print_r($specs);
//            $num = 0;
//            foreach ($specs as $val):
//                $num += $val['stock'];
//            endforeach;
//            $recommend[$key]['stock'] = $num;
//        }
//
//        $this->assign('r_goods', $recommend);
//        $this->_config_seo(array(
//            'title' => Lang::get('mall_index') . ' - ' . Conf::get('site_title'),
//        ));
//        $goods_model = & m('goods');
//        //获取促销产品
//        $c_goods = $goods_model->find(array(
//            'conditions' => "closed = 0 AND if_show = 1 AND recommended = 1",
//            'fields' => 'goods_name, default_image, price, recommended, price_distributor',
//            'order' => 'order_by asc , add_time desc',
//            'limit' => "0,12",
//        ));
//        foreach ($c_goods as $key => $goods) {
//            $specs = $spec_model->find('goods_id =' . $goods['goods_id'] . " AND spec_2 =" . $sgrade);
//            $num = 0;
//            foreach ($specs as $val):
//                $num += $val['stock'];
//            endforeach;
//            $c_goods[$key]['stock'] = $num;
//        }
//        $this->assign('c_goods', $c_goods);
//
//        //获取最新产品,最新的10款
//
//        $new_goods = $goods_model->find(array(
//            'conditions' => "closed = 0 AND if_show = 1 AND store_id = 7",
//            'fields' => 'goods_name, default_image, price, recommended, price_distributor',
//            'order' => 'order_by asc , add_time desc',
//            'limit' => "0,12",
//        ));
//        $this->assign('new_goods', $new_goods);
//        //各种会员都会显示经销商价
//        if ($_SESSION['user_info']['sgrade'] == 5 || $_SESSION['user_info']['sgrade'] == 2 || $_SESSION['user_info']['sgrade'] == 3 || $_SESSION['user_info']['sgrade'] == 4) {
//            $is_jingxiaoshang = true;
//            $this->assign('is_jingxiaoshang', $is_jingxiaoshang);
//        }
//        //如果登录了，查有无提醒过客户
//        if(!empty($_SESSION['user_info']['user_id'])) {
//            $grade_log_model = &m('grade_log');
//            $grade_log = $grade_log_model->get("user_id =" . $_SESSION['user_info']['user_id'] . " AND grade =" . $_SESSION['user_info']['sgrade']);
//            if (($_SESSION['user_info']['sgrade'] == 2 || $_SESSION['user_info']['sgrade'] == 3 || $_SESSION['user_info']['sgrade'] == 4) && empty($grade_log)) {
//                switch($_SESSION['user_info']['sgrade']){
//                    case 2 : $grade_name = '金卡会员';
//                        break;
//                    case 3 : $grade_name = '铂金会员';
//                        break;
//                    case 4 : $grade_name = '钻石会员';
//                        break;
//                }
//                $grade_remind = true;
//                $this->assign('grade_remind', $grade_remind);
////                $this->assign('member_name',$_SESSION['user_info']['user_name']);
//                $this->assign('grade_name',$grade_name);
//            }
//        }
////    if ($_SESSION['user_info']['user_id'] == 12 || $_SESSION['user_info']['user_id'] == 9668 || $_SESSION['user_info']['user_id'] == 10205 ) {
////            $test = true;
////        }
////        $this->assign('test', $test);
//        //查是否有以量定价
//        $nums_model = & m('groupbuy_nums');
//        $nums = $nums_model->get('status = 1 AND start_time <=' . gmtime() . " AND end_time >= " . gmtime());
//        $this->assign('nums', $nums);
//
//        $this->assign('page_description', Conf::get('site_description'));
//        $this->assign('page_keywords', Conf::get('site_keywords'));
//        $this->display('index.html');
        //主页
        $goods_model = & m('goods');
        $goods = $goods_model->find(array(
            'conditions' => "closed = 0 AND if_show = 1",
            'fields'     => 'goods_name, default_image, recommended',
        ));
        if(empty($_SESSION['user_info']['sgrade'])){
            $grade = 1;
        }else{
            $grade = $_SESSION['user_info']['sgrade'];
        }
        $spec_model = & m('goodsspec');
        foreach($goods as $key=>$good){
            $spec = $spec_model->get(array(
                'conditions' => "goods_id =".$good['goods_id']." AND spec_2 =".$grade,
                'fields'     => 'spec_1, price, original_price',
                'order' => 'price',
            ));
            $goods[$key]['spec'] = $spec;
        }
        $this->assign('goods',$goods);
        $this->display("bar_index.html");
    }

    function wapview() {
        /* 店铺预览 */
        $this->assign('id', intval($_GET['id']));
        $this->display('index.wapview.html');
    }

    function version() {
        echo 'ecmall_1400910_815129187179899';
    }

    function app_download(){
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        if (strpos($user_agent, 'MicroMessenger') === false) {
            // 非微信浏览器禁止浏览
//            echo "HTTP/1.1 401 Unauthorized";、
            $this->display("app_download.html");
//            $this->display("out_to_weixin.html");
        } else {
            // 微信浏览器，允许访问
//            echo "MicroMessenger";
            // 获取版本号
//            preg_match('/.*?(MicroMessenger\/([0-9.]+))\s*/', $user_agent, $matches);
//            echo '<br>Version:'.$matches[2];
            $this->display("out_to_weixin.html");
        }
    }

}

?>
