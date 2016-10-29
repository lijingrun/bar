<?php

class RedeemApp extends BackendApp {

    function index() {
        $redeem_goods_model = & m('redeem_goods');
        $page = $_GET['page'];
        $goods_name = $_GET['goods_name'];
        if (empty($page)) {
            $page = 1;
        }
        $page_type = 10; //一页显示条数
        $start_limit = $page_type * ($page - 1);
        $limit_str = $start_limit . ',' . $page_type;
        if (!IS_POST) {
            if (empty($goods_name)) {
                $goods = $redeem_goods_model->find(array(
                    'limit' => $limit_str,
                ));
            } else {
                $goods = $redeem_goods_model->find(array(
                    'conditions' => 'goods_name like%' . $goods_name . '%',
                    'limit' => $limit_str,
                ));
            }
        } else {
            $goods_name = $_POST['goods_name'];
            $goods = $redeem_goods_model->find(array(
                'conditions' => "goods_name like '%" . $goods_name . "%'",
                'limit' => $limit_str,
            ));
        }
        $all_goods = $redeem_goods_model->find("goods_name like '%" . $goods_name . "%'");
        $count = count($all_goods);
        $pages = ceil($count / $page_type);
        $next_page = $page + 1;
        if ($next_page > $pages) {
            $next_page = $pages;
        }
        $prev_page = $page - 1;
        if ($prev_page < 1) {
            $prev_page = 1;
        }
        $this->assign('goods_name', $goods_name);
        $this->assign('next_page', $next_page);
        $this->assign('prev_page', $prev_page);
        $this->assign('page', $page);
        $this->assign('pages', $pages);
        $this->assign('count', $count);
        $this->assign('goods', $goods);
        $this->display('redeem.index.html');
    }

    //增加商品页面
    function add() {
        $redeem_goods_model = & m('redeem_goods');
        $redeem_type_model = & m('redeem_type');
        if (!IS_POST) {
            $redeem_types = $redeem_type_model->find("1=1");

            $this->assign('redeem_types', $redeem_types);
            $this->display('redeem.add.html');
        } else {
            //获取上传图片信息
            $file = $_FILES['theimg'];
            if (!empty($file['name'])) {
                $uploader = new Uploader();
                $uploader->allowed_type(IMAGE_FILE_TYPE);
                $uploader->allowed_size(SIZE_STORE_LOGO);
                $uploader->addFile($file);

                if ($uploader->file_info() == false) {
                    $this->show_warning($uploader->get_error());
                    return;
                }
                $uploader->root_dir(ROOT_PATH); //设置根目录
                $url = '/data/files/store_7/redeem';  //专门保存积分兑换商品图片的文件夹
                $name = $uploader->random_filename(); //随机文件名
                $filename = substr(strrchr($file['name'], '.'), 1); //获取后缀名
                $imgurl = $url . '/' . $name . '.' . $filename;
                $uploader->save($url, $name);
            }
            if (empty($imgurl)) {
                $imgurl = "/data/system/default_goods_image.gif";
            }
            //获取文字信息
            $goods = array(
                'store_id' => 7,
                'goods_name' => $_POST['goods_name'],
                'brand' => $_POST['brand'],
                'type_id' => $_POST['type_id'],
                'spee_qty' => $_POST['spee_qty'],
                'status' => $_POST['status'],
                'nums' => $_POST['nums'],
                'price' => $_POST['price'],
                'tags' => $_POST['tags'],
                'order_by' => $_POST['order_by'],
                'k3_num' => $_POST['k3_num'],
                'img' => $imgurl,
            );
            $redeem_goods_model->add($goods);

            $this->show_message('添加成功', '点击返回', 'index.php?app=redeem');
        }
    }

    //修改商品
    function edit() {
        $redeem_goods_model = & m('redeem_goods');
        $goods_model = & m('redeem_goods');
        $redeem_type_model = & m('redeem_type');
        $goods_id = $_GET['id'];
        $page = $_GET['page'];
        $good = $goods_model->get('goods_id =' . $goods_id);
        if (!IS_POST) {
            $this->assign('good', $good);

            //查找所有类型
            $redeem_types = $redeem_type_model->find("1=1");

            $this->assign('redeem_types', $redeem_types);
            $this->display('redeem.add.html');
        } else {
            //获取上传图片信息
            $file = $_FILES['theimg'];
            if (!empty($file['name'])) {
                $uploader = new Uploader();
                $uploader->allowed_type(IMAGE_FILE_TYPE);
                $uploader->allowed_size(SIZE_STORE_LOGO);
                $uploader->addFile($file);
                if ($uploader->file_info() == false) {
                    $this->show_warning($uploader->get_error());
                    return;
                }
                $uploader->root_dir(ROOT_PATH); //设置根目录
                $url = '/data/files/store_7/redeem';  //专门保存积分兑换商品图片的文件夹
                $name = $uploader->random_filename(); //随机文件名
                $filename = substr(strrchr($file['name'], '.'), 1); //获取后缀名
                $imgurl = $url . '/' . $name . '.' . $filename;
                $uploader->save($url, $name);
            }
            if (empty($imgurl)) {
                $imgurl = $good['img'];
            }
            //获取文字信息
            $goods = array(
                'store_id' => 7,
                'goods_name' => $_POST['goods_name'],
                'brand' => $_POST['brand'],
                'type_id' => $_POST['type_id'],
                'spee_qty' => $_POST['spee_qty'],
                'status' => $_POST['status'],
                'nums' => $_POST['nums'],
                'price' => $_POST['price'],
                'tags' => $_POST['tags'],
                'order_by' => $_POST['order_by'],
                'k3_num' => $_POST['k3_num'],
                'img' => $imgurl,
            );
            $redeem_goods_model->edit($goods_id, $goods);

            $this->show_message('修改成功', '点击返回', 'index.php?app=redeem&page='.$page );
        }
    }

    //添加积分兑换商品的类型
    function addtype() {
        $redeem_type_model = & m('redeem_type');
        if (!IS_POST) {
            //查找所有的类型
            $redeem_types = $redeem_type_model->find("1=1");
            //传数据
            $this->assign('redeem_types', $redeem_types);

            $this->display('redeem.addtype.html');
        } else {
            $type = array(
                'name' => $_POST['name'],
            );
            $redeem_type_model->add($type);
            $this->show_message('保存成功！');
//            $redeem_type->add('name');
//            $typename = $_POST['typename'];
//            echo $typename;
//            exit;
        }
    }

    //删除积分兑换里面的商品
    function delete_goods() {
        //登录判断

        if ($_SESSION['admin_info']['user_name'] != 'admin') {
            $this->show_warning('对不起，你没有该权限！');
            return;
        }
        $redeem_goods_model = & m('redeem_goods');
        $id = $_GET['id'];
        $redeem_goods_model->drop($id);
        $this->show_message('删除成功！', '点击返回主页', 'index.php?app=redeem');
    }

    //积分兑换订单管理
    function orders() {
        $redeem_orders_model = & m('redeem_orders');
        $conditions = "1=1";
        $page_size = 10;
        $page = $_GET['page'];
        if (empty($page)) {
            $page = 1;
        }
        $limit = $page_size * ($page - 1) . "," . $page_size;

        $status = $_GET['status'];
        if (empty($status)) {
            $status = 10;
        }
        $orderid = $_GET['orderid'];
        $name = $_GET['name'];
        if ($status != 10) {
            if ($status == 1) {
                $conditions .= " AND status=0";
            } else {
                $conditions .= " AND status=" . $status;
            }
        }
        if (!empty($name)) {
            $conditions .= " AND buyer_name like '%" . $name . "%'";
        }
        if (!empty($orderid)) {
            $conditions .= " AND order_id =" . $orderid;
        }

        $all_orders = $redeem_orders_model->find($conditions);
        $count = count($all_orders);
        $total_pages = ceil($count / $page_size);

        $orders = $redeem_orders_model->find(array(
            'conditions' => $conditions,
            'order' => 'created desc',
            'limit' => $limit,
        ));
        if ($page >= $total_pages) {
            $next_page = $total_pages;
        } else {
            $next_page = $page + 1;
        }
        if ($page == 1) {
            $prev_page = 1;
        } else {
            $prev_page = $page - 1;
        }


        $this->assign('total_pages', $total_pages);
        $this->assign('page', $page);
        $this->assign('count', $count);
        $this->assign('prev_page', $prev_page);
        $this->assign('next_page', $next_page);
        $this->assign('nextpage', $nextpage);
        $this->assign('status', $status);
        $this->assign('name', $name);
        $this->assign('orderid', $orderid);
        $this->assign('orders', $orders);
        $this->display('redeem.orders.html');
    }

    //取消商品订单
    function cancle_order() {
        //判断登录
        if (empty($_SESSION['admin_info']['user_name'])) {
            $this->show_warning('对不起，你没有这权限！');
            return;
        }
        $redeem_orders_model = & m('redeem_orders');
        $member_model = & m('member');
        //查找订单
        $order_id = $_GET['order_id'];
        $order = $redeem_orders_model->find('order_id =' . $order_id);
        $order = current($order);

        //查找购买用户
        $member = $member_model->find('user_id =' . $order['buyer_id']);
        $member = current($member);
        //返还积分
        $member['point'] += $order['total_price'];
        $member_model->edit($member['user_id'], $member);

        //改变订单属性
        $order['status'] = 0;
        $order['deltime'] = time() - 28800;
        $order['in_k3'] = 2;
        $order['delman'] = $_SESSION['admin_info']['user_name'];
        $redeem_orders_model->edit($order['order_id'], $order);
        $this->show_message('已成功取消订单！');
    }

    //订单详细页
    function detail() {
        $redeem_orders_model = & m('redeem_orders');
        $redeem_goods_model = & m('redeem_goods');
        $member_model = & m('member');
        $order_id = $_GET['order_id'];
        //查订单
        $order = $redeem_orders_model->find('order_id =' . $order_id);
        $order = current($order);
        //查商品
        $good = $redeem_goods_model->find('goods_id =' . $order['goods_id']);
        $good = current($good);
        //查用户
        $member = $member_model->find('user_id =' . $order['buyer_id']);
        $member = current($member);

        $this->assign('member', $member);
        $this->assign('good', $good);
        $this->assign('order', $order);
        $this->display('redeem.order.detail.html');
    }

    //发货操作
    function out_put() {
        $redeem_order_model = & m('redeem_orders');
        //判断登录
        if (empty($_SESSION['user_info']['user_name']) && empty($_SESSION['admin_info']['user_name'])) {
            $this->show_warning('你没有该权限操作');
            return;
        }
        //查订单
        $order_id = $_GET['order_id'];


        //获取页面信息
        $logistics_num = $_GET['logistics_num'];
        $out_man_k3 = $_GET['out_man_k3'];
        $out_phone = $_GET['out_phone'];
        $out_man = $_GET['out_man'];
        $logistics_name = $_GET['logistics_name'];
        $logistics_phone = $_GET['logistics_phone'];
        $get_time = $_GET['get_time'];
        if (empty($logistics_num)) {
            $this->show_warning('请输入物流单号！');
            return;
        }

        $order = $redeem_order_model->find('order_id = ' . $order_id);
        $order = current($order);
        //修改发货内容
        $order['status'] = 30;
        $order['out_time'] = time() - 28800;
        $order['logistics_num'] = $logistics_num;
        $order['out_man_k3'] = $out_man_k3;
        $order['out_phone'] = $out_phone;
        $order['out_man'] = $out_man;
        $order['logistics_name'] = $logistics_name;
        $order['logistics_phone'] = $logistics_phone;
        $order['get_time'] = $get_time;
        $redeem_order_model->edit($order_id, $order);
        $this->show_message('操作成功！');
    }

}

//内置upload类
class Uploader extends Object {

    var $_file = null;
    var $_allowed_file_type = null;
    var $_allowed_file_size = null;
    var $_root_dir = null;

    /**
     *    添加由POST上来的文件
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function addFile($file) {
        if (!is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        $this->_file = $this->_get_uploaded_info($file);
    }

    /**
     *    设定允许添加的文件类型
     *
     *    @author    Garbin
     *    @param     string $type （小写）示例：gif|jpg|jpeg|png
     *    @return    void
     */
    function allowed_type($type) {
        $this->_allowed_file_type = explode('|', $type);
    }

    /**
     *    允许的大小
     *
     *    @author    Garbin
     *    @param     mixed $size
     *    @return    void
     */
    function allowed_size($size) {
        $this->_allowed_file_size = $size;
    }

    function _get_uploaded_info($file) {
        $pathinfo = pathinfo($file['name']);
        $file['extension'] = $pathinfo['extension'];
        $file['filename'] = $pathinfo['basename'];
        if (!$this->_is_allowd_type($file['extension'])) {
            $this->_error('not_allowed_type', $file['extension']);

            return false;
        }
        if (!$this->_is_allowd_size($file['size'])) {
            $this->_error('not_allowed_size', $file['size']);

            return false;
        }

        return $file;
    }

    function _is_allowd_type($type) {
        if (!$this->_allowed_file_type) {
            return true;
        }
        return in_array(strtolower($type), $this->_allowed_file_type);
    }

    function _is_allowd_size($size) {
        if (!$this->_allowed_file_size) {
            return true;
        }

        return is_numeric($this->_allowed_file_size) ?
                ($size <= $this->_allowed_file_size) :
                ($size >= $this->_allowed_file_size[0] && $size <= $this->_allowed_file_size[1]);
    }

    /**
     *    获取上传文件的信息
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function file_info() {
        return $this->_file;
    }

    /**
     *    若没有指定root，则将会按照所指定的path来保存，但是这样一来，所获得的路径就是一个绝对或者相对当前目录的路径，因此用Web访问时就会有问题，所以大多数情况下需要指定
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function root_dir($dir) {
        $this->_root_dir = $dir;
    }

    function save($dir, $name = false) {
        if (!$this->_file) {
            return false;
        }
        if (!$name) {
            $name = $this->_file['filename'];
        } else {
            $name .= '.' . $this->_file['extension'];
        }
        $path = $dir . '/' . $name;

        return $this->move_uploaded_file($this->_file['tmp_name'], $path);
    }

    /**
     *    将上传的文件移动到指定的位置
     *
     *    @author    Garbin
     *    @param     string $src
     *    @param     string $target
     *    @return    bool
     */
    function move_uploaded_file($src, $target) {
        $abs_path = $this->_root_dir ? $this->_root_dir . '/' . $target : $target;
        $dirname = dirname($target);
        if (!ecm_mkdir(ROOT_PATH . '/' . $dirname)) {
            $this->_error('dir_doesnt_exists');

            return false;
        }

        if (move_uploaded_file($src, $abs_path)) {
            @chmod($abs_path, 0666);
            return $target;
        } else {
            return false;
        }
    }

    /**
     * 生成随机的文件名
     */
    function random_filename() {
        $seedstr = explode(" ", microtime(), 5);
        $seed = $seedstr[0] * 10000;
        srand($seed);
        $random = rand(1000, 10000);

        return date("YmdHis", time()) . $random;
    }

}
