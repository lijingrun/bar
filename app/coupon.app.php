<?php

class CouponApp extends StoreadminbaseApp {

    var $_coupon_mod;
    var $_store_id;
    var $_store_mod;
    var $_couponsn_mod;

    function __construct() {
        $this->CouponApp();
    }

    function CouponApp() {
        parent::__construct();
        $this->_store_id = intval($this->visitor->get('manage_store'));
        $this->_store_mod = & m('store');
        $this->_coupon_mod = & m('coupon');
        $this->_couponsn_mod = & m('couponsn');
    }

    function index() {
        $page = $this->_get_page(10);
        $coupon = $this->_coupon_mod->find(array(
            'conditions' => 'store_id = ' . $this->_store_id,
            'limit' => $page['limit'],
            'count' => true,
        ));
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('coupon'), 'index.php?app=coupon', LANG::get('coupons_list'));
        $page['item_count'] = $this->_coupon_mod->getCount();
        $this->_format_page($page);
        $this->assign('page_info', $page);

        $this->_curitem('coupon');
        $this->_curmenu('coupons_list');
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('coupon'));
        $this->assign('coupons', $coupon);
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
        $this->assign('time', gmtime());
        $this->display('coupon.index.html');
    }

    function add() {
        if (!IS_POST) {
            header("Content-Type:text/html;charset=" . CHARSET);
            $this->assign('today', gmtime());
            $this->display('coupon.form.html');
        } else {

            $coupon_value = floatval(trim($_POST['coupon_value']));
            $use_times = intval(trim($_POST['use_times']));
            $min_amount = floatval(trim($_POST['min_amount']));
            if (empty($coupon_value) || $coupon_value < 0) {
                $this->pop_warning('coupon_value_not');
                exit;
            }
            if (empty($use_times)) {
                $this->pop_warning('use_times_not_zero');
                exit;
            }
            if ($min_amount < 0) {
                $this->pop_warning("min_amount_gt_zero");
                exit;
            }
            $start_time = gmstr2time(trim($_POST['start_time']));
            $end_time = gmstr2time_end(trim($_POST['end_time'])) - 1;
            if ($end_time < $start_time) {
                $this->pop_warning('end_gt_start');
                exit;
            }
            $coupon = array(
                'coupon_name' => trim($_POST['coupon_name']),
                'coupon_value' => $coupon_value,
                'store_id' => $this->_store_id,
                'use_times' => $use_times,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'min_amount' => $min_amount,
                'if_issue' => trim($_POST['if_issue']) == 1 ? 1 : 0,
            );
            $this->_coupon_mod->add($coupon);
            if ($this->_coupon_mod->has_error()) {
                $this->pop_warning($this->_coupon_mod->get_error());
                exit;
            }
            $this->pop_warning('ok', 'coupon_add');
        }
    }

    function edit() {
        $coupon_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (empty($coupon_id)) {
            echo Lang::get("no_coupon");
        }
        if (!IS_POST) {
            header("Content-Type:text/html;charset=" . CHARSET);
            $coupon = $this->_coupon_mod->get_info($coupon_id);
            $this->assign('coupon', $coupon);
            $this->display('coupon.form.html');
        } else {
            $coupon_value = floatval(trim($_POST['coupon_value']));
            $use_times = intval(trim($_POST['use_times']));
            $min_amount = floatval(trim($_POST['min_amount']));
            if (empty($coupon_value) || $coupon_value < 0) {
                $this->pop_warning('coupon_value_not');
                exit;
            }
            if (empty($use_times)) {
                $this->pop_warning('use_times_not_zero');
                exit;
            }
            if ($min_amount < 0) {
                $this->pop_warning("min_amount_gt_zero");
                exit;
            }
            $start_time = gmstr2time(trim($_POST['start_time']));
            $end_time = gmstr2time_end(trim($_POST['end_time'])) - 1;
            //echo gmstr2time_end(trim($_POST['end_time'])) . '-------' .$end_time;exit; 
            if ($end_time < $start_time) {
                $this->pop_warning('end_gt_start');
                exit;
            }
            $coupon = array(
                'coupon_name' => trim($_POST['coupon_name']),
                'coupon_value' => $coupon_value,
                'store_id' => $this->_store_id,
                'use_times' => $use_times,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'min_amount' => $min_amount,
                'if_issue' => trim($_POST['if_issue']) == 1 ? 1 : 0,
            );
            $this->_coupon_mod->edit($coupon_id, $coupon);
            if ($this->_coupon_mod->has_error()) {
                $this->pop_warning($this->_coupon_mod->get_error());
                exit;
            }
            $this->pop_warning('ok', 'coupon_edit');
        }
    }

    function issue() {
        $coupon_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (empty($coupon_id)) {
            $this->show_warning("no_coupon");
            exit;
        }
        $this->_coupon_mod->edit($coupon_id, array('if_issue' => 1));
        if ($this->_coupon_mod->has_error()) {
            $this->show_message($this->_coupon_mod->get_error());
            exit;
        }
        $this->show_message('issue_success', 'back_list', 'index.php?app=coupon');
    }

    function drop() {
        $coupon_id = isset($_GET['id']) ? intval($_GET['id']) : '';
        if (empty($coupon_id)) {
            $this->show_warning('no_coupon');
            exit;
        }
        $time = gmtime();
        $coupon_ids = explode(',', $coupon_id); //vdump($this->_coupon_mod->find("((if_issue = 1 AND end_time > {$time})) AND coupon_id ".db_create_in($coupon_ids)));
        $this->_coupon_mod->drop("(if_issue = 0 OR (if_issue = 1 AND end_time < {$time})) AND coupon_id " . db_create_in($coupon_ids));
        if ($this->_coupon_mod->has_error()) {
            $this->show_warning($this->_coupon_mod->get_error());
        }
        $this->show_message('drop_ok', 'back_list', 'index.php?app=coupon');
    }

    function export() {
        $coupon_id = isset($_GET['id']) ? intval($_GET['id']) : '';
        if (empty($coupon_id)) {
            echo Lang::get('no_coupon');
            exit;
        }
        if (!IS_POST) {
            header("Content-Type:text/html;charset=" . CHARSET);
            $this->assign('id', $coupon_id);
            $this->display('coupon_export.html');
        } else {
            $amount = intval(trim($_POST['amount']));
            if (empty($amount)) {
                $this->pop_warning('involid_data');
                exit;
            }
            $info = $this->_coupon_mod->get_info($coupon_id);
            $coupon_name = ecm_iconv(CHARSET, 'gbk', $info['coupon_name']);
            header('Content-type: application/txt');
            header('Content-Disposition: attachment; filename="coupon_' . date('Ymd') . '_' . $coupon_name . '.txt"');
            $sn_array = $this->generate($amount, $coupon_id);
            $crlf = get_crlf();
            foreach ($sn_array as $val) {
                echo $val['coupon_sn'] . $crlf;
            }
        }
    }
    
    //将coupon发布到领取页面去让客户自己领取
    function get_coupon(){
        $coupon_get_model = & m('coupon_get');
        $coupon_model = & m('coupon');
        $coupon_get_user_model = & m('coupon_get_user');
        $coupon_id = $_GET['id'];
        $coupon_get = $coupon_get_model->get("coupon_id =".$coupon_id);
        if(!IS_POST){
            /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=coupon', LANG::get('优惠券'), 'index.php?app=coupon', LANG::get('优惠券发布'));

            /* 当前用户中心菜单 */
            $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
            $this->_curitem('coupon');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
            $this->_curmenu($type);
            $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
            $coupon = $coupon_model->get("coupon_id =".$coupon_id);
            $this->assign('coupon_get', $coupon_get);
            $this->assign('coupon', $coupon);
            $this->display('get_coupons.html');
        }else{
            $coupon_id = $_POST['coupon_id'];
            $coupon_img = $_POST['coupon_img'];
            $stime = strtotime($_POST['stime'])-8*60*60;
            $etime = strtotime($_POST['etime'])-8*60*60+86399;
            $new_get = array(
                'coupon_id' => $coupon_id,
                'coupon_img' => $coupon_img,
                'stime' => $stime,
                'etime' => $etime,
            );
            if(empty($coupon_get)){
                $coupon_get_model->add($new_get);
            }else{
                $coupon_get_model->edit($coupon_get['id'],$new_get);
            }
            $this->show_message('发布成功','','index.php?app=coupon&act=get_coupon&id='.$coupon_id);
        }
    }

    function extend() {
        $coupon_id = isset($_GET['id']) ? intval($_GET['id']) : '';
        if (empty($coupon_id)) {
            echo Lang::get('no_coupon');
            exit;
        }
        if (!IS_POST) {
            header("Content-Type:text/html;charset=" . CHARSET);
            $this->assign('id', $coupon_id);
            $this->assign('send_model', Lang::get('send_model'));
            $this->display("coupon_extend.html");
        } else {
            if(empty($coupon_id)){
                $coupon_id = $_POST['coupon_id'];
            }
            if (empty($_POST['user_name'])) {
                $this->pop_warning("involid_data");
                exit;
            }
            $user_name = str_replace(array("\r", "\r\n"), "\n", trim($_POST['user_name']));
            $user_name = explode("\n", $user_name);
            $user_mod = &m('member');
            $users = $user_mod->find(db_create_in($user_name, 'user_name'));
            if (empty($users)) {
                $this->pop_warning('involid_data');
                exit;
            }
            if (count($users) > 30) {
                $this->pop_warning("amount_gt");
                exit;
            } else {
                $users = $this->assign_user($coupon_id, $users);
                $store = $this->_store_mod->get_info($this->_store_id);
                $coupon = $this->_coupon_mod->get_info($coupon_id);
                $coupon['store_name'] = $store['store_name'];
                $coupon['store_id'] = $this->_store_id;
                $this->_message_to_user($users, $coupon);
                $this->_mail_to_user($users, $coupon);
                $this->pop_warning("ok", "coupon_extend");
            }
        }
    }

    function _message_to_user($users, $coupon) {
        $ms = & ms();
        foreach ($users as $key => $val) {
            $content = get_msg('touser_send_coupon', array(
                'price' => $coupon['coupon_value'],
                'start_time' => local_date('Y-m-d', $coupon['start_time']),
                'end_time' => local_date("Y-m-d", $coupon['end_time']),
                'coupon_sn' => $val['coupon']['coupon_sn'],
                'min_amount' => $coupon['min_amount'],
                'url' => SITE_URL . '/' . url('app=store&id=' . $coupon['store_id']),
                'store_name' => $coupon['store_name'],
            ));
            $msg_id = $ms->pm->send(MSG_SYSTEM, $val['user_id'], '', $content);
        }
    }

    function _mail_to_user($users, $coupon) {
        foreach ($users as $val) {
//            $mail = get_mail('touser_send_coupon', array('user' => $val, 'coupon' => $coupon));
//            if (!$mail) {
//                continue;
//            }
//            $this->_mailto($val['email'], addslashes($mail['subject']), addslashes($mail['message']));
            //发邮件改为发微信模板信息,优惠券领取模板
            $member_model = & m('member');
            $member = $member_model->get('user_id ='.$val['user_id']);
            if(!empty($member['openid'])) {
                $this->_send_message_to_weixin($member['openid'], $member['user_id'], 'mjYYFdHlN91zCtFmsqWKG5wqRIgSlQLRHfCX6srchJc', $coupon, $val);
            }
        }
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
        $use_times = $this->_coupon_mod->get(array('fields' => 'use_times', 'conditions' => 'store_id = ' . $this->_store_id . ' AND coupon_id = ' . $id));

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
        $this->_couponsn_mod->db->query("INSERT INTO {$this->_couponsn_mod->table} (coupon_sn, coupon_id, remain_times) VALUES {$string}", 'SILENT');
        return $add_data;
    }

    function _sql_insert($data) {
        $str = '';
        foreach ($data as $val) {
            $str .= "('{$val['coupon_sn']}', {$val['coupon_id']}, {$val['remain_times']}),";
        }
        $string = substr($str, 0, strrpos($str, ','));
        $res = $this->_couponsn_mod->db->query("INSERT INTO {$this->_couponsn_mod->table} (coupon_sn, coupon_id, remain_times) VALUES {$string}", 'SILENT');
        $error = $this->_couponsn_mod->db->errno();
        return array('res' => $res, 'errno' => $error);
    }

    function _create_random($num, $id, $times) {
        $arr = array();
        for ($i = 1; $i <= $num; $i++) {
            $arr[$i]['coupon_sn'] = mt_rand(10000, 99999);
            $arr[$i]['coupon_id'] = $id;
            $arr[$i]['remain_times'] = $times;
        }
        return $arr;
    }

    function _get_member_submenu() {
        $menus = array(
            array(
                'name' => 'coupons_list',
                'url' => 'index.php?app=coupon',
            ),
        );
        return $menus;
    }

    //上传优惠券对应的图片
    function upload_coupon_img() {
        if (IS_POST) {
            $coupon_id = $_POST['coupon_id'];
            import('uploader.lib');
            $file = $_FILES['coupon_img'];
            
            $uploader = new Uploader();;
            $uploader->allowed_type(IMAGE_FILE_TYPE); //上传格式
            $uploader->allowed_size(SIZE_STORE_LOGO); // 2M 
            $uploader->addFile($file); //将上传的文件放到upload实例
            if ($uploader->file_info() == false) {
                $this->show_warning($uploader->get_error());
                return false;
            }
            $uploader->root_dir(ROOT_PATH); //设置根目录，必须要设置，否则会找不到文件夹
            $url = "/data/indeximages/coupon_images"; //保存地址
            $name = $coupon_id;
            $uploader->save($url,$name);
            $coupon_model = & m('coupon');
            $coupon = $coupon_model->get("coupon_id =".$coupon_id);
            $coupon['img'] = $url."/".$name;
            $coupon_model->edit($coupon_id,$coupon);
            $this->show_message('上传成功','点击返回','index.php?app=coupon');
        } else {
            /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=redeem', LANG::get('coupon'), 'index.php?app=coupon', LANG::get('coupon'));
            /* 当前用户中心菜单 */
            $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
            $this->_curitem('my_point');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
            $this->_curmenu($type);
            $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
            $coupon_id = $_GET['id'];
            $this->assign('coupon_id',$coupon_id);
            $this->display('upload_coupon_img.html');
        }
    }


    //发送微信信息
    function _send_message_to_weixin($openid,$user_id,$template_id,$coupon,$user)
    {
        $access_token_model = & m('access_token');
        //先获取服务器上面的access_token;
        $effective_time = time()-7200;  //有效时间2小时
        $access_token_record = $access_token_model->get("create_time > ".$effective_time);
        //如果access_token失效，则重新获取
        if (empty($access_token_record)) {


            $access_token = $this->_get_access_token();


        }else{
            $access_token = $access_token_record['access_token'];
        }
        $url = 'http://open.weixin.qq.com/connect/oauth2/authorize?appid=wx7ccf7c8a377ea11b&redirect_uri=http%3A%2F%2Fwww.126wallpaper.com&response_type=code&scope=snsapi_base&state=123#wechat_redirect';
        $touser = $openid;
        $data = array('first'=>array(value=>'优惠券已经成功发放到你账号上！','topcolor'=> '#0F12D'),
            'keyword1'=>array(value=>$coupon['store_name'].$coupon['coupon_name'] ,'topcolor'=> '#0F12D'),
            'keyword2'=>array(value=>$user['coupon']['coupon_sn'] ,'topcolor'=> '#0F12D'),
            'keyword3'=>array(value=>$coupon['use_times'] ,'topcolor'=> '#0F12D'),
            'remark' => array(value=>'在商城对应店铺购买产品即可抵用','topcolor'=> '#0F12D'),
        );
        $template = array(
            'touser' => $touser,
            'template_id' => $template_id,
            'url' => $url,
            'data' => $data
        );
        $json_template = json_encode($template);
        $url = "http://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $access_token;
        $dataRes = $this->request_post($url, urldecode($json_template));
//        return $dataRes['errcode'];
        if ($dataRes['errcode'] == 0) {
            return true;
        } else {
            return false;
        }


    }

    /**
     * 发送post请求
     * @param string $url
     * @param string $param
     * @return bool|mixed
     */
    function request_post($url = '', $param = '')
    {
        if (empty($url) || empty($param)) {
            return false;
        }
        $postUrl = $url;
        $curlPost = $param;
        $ch = curl_init(); //初始化curl
        curl_setopt($ch, CURLOPT_URL, $postUrl); //抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0); //设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1); //post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
//        echo 123;exit;
        $data = curl_exec($ch); //运行curl
        $data =curl_multi_getcontent($ch);
        curl_close($ch);

        return $data;
    }

    //获取access_token
    function _get_access_token(){
        $appid = "wx7ccf7c8a377ea11b";
        $secret = "4cf617291ca6d73b022c13d085dc6da1";
        $token_access_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $secret;
        $res = file_get_contents($token_access_url); //获取文件内容或获取网络请求的内容
        $result = json_decode($res, true); //接受一个 JSON 格式的字符串并且把它转换为 PHP 变量
        $access_token = $result['access_token'];
        $access_token_model = & m('access_token');
        $new_access_token = array(
            'access_token' => $access_token,
            'create_time' => time(),
        );
        $access_token_model->edit(1, $new_access_token);
        return $result['access_token'];
    }

}

?>