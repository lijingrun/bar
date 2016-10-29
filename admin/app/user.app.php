<?php

/* 会员控制器 */

class UserApp extends BackendApp {

    var $_admin_mod;
    var $_user_mod;

    function __construct() {
        $this->UserApp();
    }

    function UserApp() {
        parent::__construct();
        $this->_user_mod = & m('member');
        $this->_admin_mod = & m('userpriv');
    }

    function index() {
        $conditions = $this->_get_query_conditions(array(
            array(
                'field' => $_GET['field_name'],
                'name' => 'field_value',
                'equal' => 'like',
            ),
        ));
        //更新排序
        if (isset($_GET['sort']) && !empty($_GET['order'])) {
            $sort = strtolower(trim($_GET['sort']));
            $order = strtolower(trim($_GET['order']));
            if (!in_array($order, array('asc', 'desc'))) {
                $sort = 'user_id';
                $order = 'asc';
            }
        } else {
            if (isset($_GET['sort']) && empty($_GET['order'])) {
                $sort = strtolower(trim($_GET['sort']));
                $order = "desc";
            } else {
                $sort = 'user_id';
                $order = 'asc';
            }
        }
        $page = $this->_get_page();
        $users = $this->_user_mod->find(array(
            'join' => 'has_store,manage_mall',
            'fields' => 'this.*,store.store_id,userpriv.store_id as priv_store_id,userpriv.privs',
            'conditions' => '1=1' . $conditions,
            'limit' => $page['limit'],
            'order' => "$sort $order",
            'count' => true,
        ));
        foreach ($users as $key => $val) {
            if ($val['priv_store_id'] == 0 && $val['privs'] != '') {
                $users[$key]['if_admin'] = true;
            }
        }
        $this->assign('users', $users);
        $page['item_count'] = $this->_user_mod->getCount();
        $this->_format_page($page);
        $this->assign('filtered', $conditions ? 1 : 0); //是否有查询条件
        $this->assign('page_info', $page);
        /* 导入jQuery的表单验证插件 */
        $this->import_resource(array(
            'script' => 'jqtreetable.js,inline_edit.js',
            'style' => 'res:style/jqtreetable.css'
        ));
        $this->assign('query_fields', array(
            'user_name' => LANG::get('user_name'),
            'email' => LANG::get('email'),
            'real_name' => LANG::get('real_name'),
//            'phone_tel' => LANG::get('phone_tel'),
//            'phone_mob' => LANG::get('phone_mob'),
        ));
        $this->assign('sort_options', array(
            'reg_time' => LANG::get('reg_time'),
            'last_login' => LANG::get('last_login'),
            'logins' => LANG::get('logins'),
        ));
        $this->assign('if_system_manager', $this->_admin_mod->check_system_manager($this->visitor->get('user_id')) ? 1 : 0);
        $this->display('user.index.html');
    }

    //信息群发
    function send_message(){
        if(!IS_POST){
            $this->display('send_message.html');
        }else{
            $message = $_POST['message'];
            if(!empty($message)){
                $device_model = & m('devices');
                $devices = $device_model->find("device_type = 'mqtt'");
                $mqtt = new Mqtt();
                foreach($devices as $device):
                    $mqtt->send($device['serial_id'],$message);
                endforeach;
            }
            $this->show_message('发送成功！','已经全部发送完毕！','index.php?app=user&act=send_message');
            return;
        }
    }

    function add() {
        if (!IS_POST) {
            $this->assign('user', array(
                'gender' => 0,
            ));
            /* 导入jQuery的表单验证插件 */
//            $this->import_resource(array(
//                'script' => 'jquery.plugins/jquery.validate.js'
//            ));
            $ms = & ms();
            $this->assign('set_avatar', $ms->user->set_avatar());
            $this->display('user.form.html');
        } else {
            $user_name = trim($_POST['user_name']);
            $password = trim($_POST['password']);
            $email = trim($_POST['email']);
            $real_name = trim($_POST['real_name']);
            $gender = trim($_POST['gender']);
            $im_qq = trim($_POST['im_qq']);
            $im_msn = trim($_POST['im_msn']);


            if (strlen($user_name) < 3 || strlen($user_name) > 60) {
                $this->show_warning('user_name_length_error');

                return;
            }

            if (strlen($password) < 6 || strlen($password) > 20) {
                $this->show_warning('password_length_error');

                return;
            }

            if (!is_email($email)) {
                $this->show_warning('email_error');

                return;
            }

            /* 连接用户系统 */
            $ms = & ms();

            /* 检查名称是否已存在 */
            if (!$ms->user->check_username($user_name)) {
                $this->show_warning($ms->user->get_error());

                return;
            }

            /* 保存本地资料 */
            $data = array(
                'real_name' => $_POST['real_name'],
                'gender' => $_POST['gender'],
//                'phone_tel' => join('-', $_POST['phone_tel']),
//                'phone_mob' => $_POST['phone_mob'],
                'im_qq' => $_POST['im_qq'],
                'im_msn' => $_POST['im_msn'],
                'point' => $_POST['point'],
//                'im_skype'  => $_POST['im_skype'],
//                'im_yahoo'  => $_POST['im_yahoo'],
//                'im_aliww'  => $_POST['im_aliww'],
                'reg_time' => gmtime(),
                'k3_code' => $_POST['k3_code'],
            );

            /* 到用户系统中注册 */
            $user_id = $ms->user->register($user_name, $password, $email, $data);
            if (!$user_id) {
                $this->show_warning($ms->user->get_error());

                return;
            }

            if (!empty($_FILES['portrait'])) {
                $portrait = $this->_upload_portrait($user_id);
                if ($portrait === false) {
                    return;
                }

                $portrait && $this->_user_mod->edit($user_id, array('portrait' => $portrait));
            }


            $this->show_message('add_ok', 'back_list', 'index.php?app=user', 'continue_add', 'index.php?app=user&amp;act=add'
            );
        }
    }

    /* 检查会员名称的唯一性 */

    function check_user() {
        $user_name = empty($_GET['user_name']) ? null : trim($_GET['user_name']);
        if (!$user_name) {
            echo ecm_json_encode(false);
            return;
        }

        /* 连接到用户系统 */
        $ms = & ms();
        echo ecm_json_encode($ms->user->check_username($user_name));
    }

    function edit() {
        $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        //判断是否是系统初始管理员，如果是系统管理员，必须是自己才能编辑，其他管理员不能编辑系统管理员
        if ($this->_admin_mod->check_system_manager($id) && !$this->_admin_mod->check_system_manager($this->visitor->get('user_id'))) {
            $this->show_warning('system_admin_edit');
            return;
        }
        if (!IS_POST) {
            /* 是否存在 */
            $user = $this->_user_mod->get_info($id);
            if (!$user) {
                $this->show_warning('user_empty');
                return;
            }

            $ms = & ms();
            $this->assign('set_avatar', $ms->user->set_avatar($id));
            $this->assign('user', $user);
            $this->assign('phone_tel', explode('-', $user['phone_tel']));
            //查找客户消费记录
            $order_model = & m('order');
            $total_price = $order_model->get(array(
                'conditions'  => 'status = 40 AND buyer_id ='.$id,
                'fields' => 'sum(order_amount) as total_price',
            ));
            $this->assign('total_price', $total_price);
            /* 导入jQuery的表单验证插件 */
            $this->import_resource(array(
                'script' => 'jquery.plugins/jquery.validate.js'
            ));
            $this->display('user.form.html');
        } else {
            $data = array(
                'real_name' => $_POST['real_name'],
                'gender' => $_POST['gender'],
//                'phone_tel' => join('-', $_POST['phone_tel']),
//                'phone_mob' => $_POST['phone_mob'],
                'im_qq' => $_POST['im_qq'],
                'im_msn' => $_POST['im_msn'],
                'k3_code' => $_POST['k3_code'],
                'point' => $_POST['point'],
//                'im_skype'  => $_POST['im_skype'],
//                'im_yahoo'  => $_POST['im_yahoo'],
//                'im_aliww'  => $_POST['im_aliww'],
            );
            if (!empty($_POST['password'])) {
                $password = trim($_POST['password']);
                if (strlen($password) < 6 || strlen($password) > 20) {
                    $this->show_warning('password_length_error');

                    return;
                }
            }
            if (!is_email(trim($_POST['email']))) {
                $this->show_warning('email_error');

                return;
            }

            if (!empty($_FILES['portrait'])) {
                $portrait = $this->_upload_portrait($id);
                if ($portrait === false) {
                    return;
                }
                $data['portrait'] = $portrait;
            }

            /* 修改本地数据 */
            $this->_user_mod->edit($id, $data);

            /* 修改用户系统数据 */
            $user_data = array();
            !empty($_POST['password']) && $user_data['password'] = trim($_POST['password']);
            !empty($_POST['email']) && $user_data['email'] = trim($_POST['email']);
            if (!empty($user_data)) {
                $ms = & ms();
                $ms->user->edit($id, '', $user_data, true);
            }

            $this->show_message('edit_ok', 'back_list', 'index.php?app=user', 'edit_again', 'index.php?app=user&amp;act=edit&amp;id=' . $id
            );
        }
    }

    //ajax修改客户登陆名称
    function change_name_ajax(){
        if(IS_POST){
            $user_id = $_POST['user_id'];
            $new_name = trim($_POST['new_name']);
            if(empty($user_id) || empty($new_name)){
                echo 222;
                exit;
            }else{
                $member_model = & m('member');
                $member = $member_model->get("user_id =".$user_id);
                $member_check = $member_model->get("user_name like '".$new_name."'");
                if(!empty($member_check)){
                    echo 333;
                    exit;
                }
                $member['user_name'] = $new_name;
                $member_model->edit($user_id,$member);
                echo 111;
                exit;
            }
        }
    }

    function drop() {
        $id = isset($_GET['id']) ? trim($_GET['id']) : '';
        if (!$id) {
            $this->show_warning('no_user_to_drop');
            return;
        }
        $admin_mod = & m('userpriv');
        if (!$admin_mod->check_admin($id)) {
            $this->show_message('cannot_drop_admin', 'drop_admin', 'index.php?app=admin');
            return;
        }

        $ids = explode(',', $id);

        /* 连接用户系统，从用户系统中删除会员 */
        $ms = & ms();
        if (!$ms->user->drop($ids)) {
            $this->show_warning($ms->user->get_error());

            return;
        }

        $this->show_message('drop_ok');
    }

    /**
     * 上传头像
     *
     * @param int $user_id
     * @return mix false表示上传失败,空串表示没有上传,string表示上传文件地址
     */
    function _upload_portrait($user_id) {
        $file = $_FILES['portrait'];
        if ($file['error'] != UPLOAD_ERR_OK) {
            return '';
        }

        import('uploader.lib');
        $uploader = new Uploader();
        $uploader->allowed_type(IMAGE_FILE_TYPE);
        $uploader->addFile($file);
        if ($uploader->file_info() === false) {
            $this->show_warning($uploader->get_error(), 'go_back', 'index.php?app=user&amp;act=edit&amp;id=' . $user_id);
            return false;
        }

        $uploader->root_dir(ROOT_PATH);
        return $uploader->save('data/files/mall/portrait/' . ceil($user_id / 500), $user_id);
    }

    //批量导入会员账号（初始密码为123456）
    function importusers() {
        echo "功能已屏蔽，请联系工程师开启";
        exit;
        if (IS_POST) {
            $file = $_FILES['import_file'];
            $filesuffix = substr(strchr($file['name'], "."), 1);   //1.1.xls 的文件为 1.xls
            //判断导入文件格式
            if (!empty($filesuffix) && ($filesuffix == 'xls' || $filesuffix == 'xlsx')) {
                //导入各种excel工具
                import('PHPExcel');
                import('PHPExcel/Reader/Excel2007');
                import('PHPExcel/Reader/Excel5');

                //新建Excel类
                $PHPExcel = new PHPExcel();
                //新建读取类
                if ($filesuffix == 'xls') {
                    $phpRead = new PHPExcel_Reader_Excel5();
                } else {
                    $phpRead = new PHPExcel_Reader_Excel2007();
                }
                //读取文件
                $PHPExcel = $phpRead->load($file['tmp_name']);
                //获取第一个表
                $sheetExcel = $PHPExcel->getSheet(0);

                // 取得一共有多少列 
                $allColumn = $sheetExcel->getHighestColumn();

                //取得一共有多少行 
                $allRow = $sheetExcel->getHighestRow();

                $allColumn++;
                //由第二行开始获取数据
                $all_data = array(); //整个表的数据
                for ($startRow = 2; $startRow <= $allRow; $startRow++) {
                    $one_data = array(); //一行数据
                    for ($startCol = 'A'; $startCol != $allColumn; $startCol++) {
                        $name = trim($sheetExcel->getCell($startCol . $startRow)->getValue()); //获取内容，2边去空格
                        $one_data[] = $name;
                    }
                    $all_data[] = $one_data;
                }
                $member_model = & m('member');
                $store_model = & m('store');
                $false_name = array();
                $region_model = & m('region');
                //循环取数
                foreach ($all_data as $data):
                    //查重复
                    $member = $member_model->get("user_name like'" . $data[0] . "'");
                    if (empty($member['user_id'])) {
                        $new_member = array(
                            'user_name' => $data[0],
                            'password' => "21218cca77804d2ba1922c33e0151105", //123456
                            'k3_code' => $data[1],
                            'point' => 0.00,
                            'real_name' => $data[0],
                        );
                        $member_model->add($new_member);
                        //开启店铺
                        $member = $member_model->get("user_name like'" . $data[0] . "'");
                        $region = $region_model->get("region_name like '" . $member['province'] . "'");
                        $store = array(
                            'store_id' => $member['user_id'],
                            'store_name' => $data[0],
                            'region_id' => $region['region_id'],
                            'region_name' => $region['region_name'],
                            'sgrade' => 5,
                            'state' => 1,
                            'add_time' => time(),
                            'sort_order' => 65535,
                            'owner_name' => $data[0],
                        );
                        $store_model->add($store);
                    } else {
                        $false_name[] = $member['user_name'];
                    }
                endforeach;
                if (!empty($false_name)) {
                    echo "存在下列会员导入失败：<br />";
                    print_r($false_name);
                    exit;
                } else {
                    $this->show_message('导入成功！', '操作完成', 'index.php?app=user');
                }
            }
        } else {
            $this->display('importusers.html');
        }
    }

    //批量升级经销商
    function storeimport() {
        if (IS_POST) {
            $file = $_FILES['import_file'];
            $filesuffix = substr(strchr($file['name'], "."), 1);   //1.1.xls 的文件为 1.xls
            //判断导入文件格式
            if (!empty($filesuffix) && ($filesuffix == 'xls' || $filesuffix == 'xlsx')) {
                //导入各种excel工具
                import('PHPExcel');
                import('PHPExcel/Reader/Excel2007');
                import('PHPExcel/Reader/Excel5');

                //新建Excel类
                $PHPExcel = new PHPExcel();
                //新建读取类
                if ($filesuffix == 'xls') {
                    $phpRead = new PHPExcel_Reader_Excel5();
                } else {
                    $phpRead = new PHPExcel_Reader_Excel2007();
                }
                //读取文件
                $PHPExcel = $phpRead->load($file['tmp_name']);
                //获取第一个表
                $sheetExcel = $PHPExcel->getSheet(0);

                // 取得一共有多少列 
                $allColumn = $sheetExcel->getHighestColumn();

                //取得一共有多少行 
                $allRow = $sheetExcel->getHighestRow();

                $allColumn++;
                //由第二行开始获取数据
                $all_data = array(); //整个表的数据
                for ($startRow = 2; $startRow <= $allRow; $startRow++) {
                    $one_data = array(); //一行数据
                    for ($startCol = 'A'; $startCol != $allColumn; $startCol++) {
                        $name = trim($sheetExcel->getCell($startCol . $startRow)->getValue()); //获取内容，2边去空格
                        $one_data[] = $name;
                    }
                    $all_data[] = $one_data;
                }
                $member_model = & m('member');
                $store_model = & m('store');
//                $region_model = & m('region');
                //循环取数
                foreach ($all_data as $data):
                    //开启店铺
                    $member = $member_model->get("user_name like'" . $data[0] . "'");
                    $store = $store_model->get("store_name like '" . $data[0] . "'");
                    if (!$store['store_id']) {
                        
                    }
//                    $regoin = $region_model->get('regoin_name ='.$data[2]);
                    $store = array(
                        'store_id' => $member['user_id'],
                        'store_name' => $data[0],
                        'sgrade' => 5,
                        'state' => 1,
                        'add_time' => time(),
                        'sort_order' => 65535,
                        'owner_name' => $data[0],
                    );
                    $store_model->add($store);
                    print_t($store_model->get_error());
                    exit;
                endforeach;
                $this->show_message('导入成功！', '操作完成', 'index.php?app=user');
            }
        } else {
            $this->display('importstore.html');
        }
    }
    
    //点击用户名查看用户在商城的所有成交订单，包括总金额以及总得分
    //2016-10-20积分取消，显示修改为金币
    function detail(){
        $user_id = $_GET['user_id'];
//        $order_model = & m('order');
//        $point_model = & m('redeem_points');
//        $member_model = & m('member');
//        $orders = $order_model->find(array(
//            'conditions' => "status = 40 AND total_point != 0 AND buyer_id =".$user_id,
//            'fields'     => 'order_sn, total_point',
//            'order'      => 'add_time desc',
//        ));
//        $points = $point_model->find("user_id =".$user_id.' AND point != 0');
//        $member = $member_model->get('user_id ='.$user_id);
//        $total_point = 0.00;
//        foreach($orders as $order):
//            $total_point += $order['total_point'];
//        endforeach;
//        $total_use_point = 0.00;
//        foreach($points as $point):
//            if($point['status'] == 1){
//                $total_use_point += $point['point'];
//            }else{
//                $total_use_point -= $point['point'];
//            }
//        endforeach;
        $member_model = & m('member');
        $coin_log_moel = & m('coin_log');
        $member = $member_model->get("user_id =".$user_id);
        $coin_log = $coin_log_moel->find('member_id ='.$user_id);
//        $this->assign('total_use_point', $total_use_point);
//        $this->assign('total_point', $total_point);
        $this->assign('member', $member);
//        $this->assign('orders', $orders);
//        $this->assign('points', $points);
        $this->assign('coin_log',$coin_log);
        $this->display('redeem_detail.html');
    }

}

?>
