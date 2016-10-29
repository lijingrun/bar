<?php

/*
  墙纸厂需求的版本展示模块
 */

/**
 * Description of edition
 *
 * @author lijingrun
 */
class EditionApp extends StoreadminbaseApp {

    //版本展示编辑页面
    function index() {
        $edition_model = & m('edition');

        if (!IS_POST) {
            $item = array();
                        /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('版本展示'), 'index.php?app=introduction', LANG::get('新增版本'));

            /* 当前用户中心菜单 */
            $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
            $this->_curitem('edition');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
            $this->_curmenu($type);
            $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
            
            $item['submenu'] = $this->_get_member_submenu();
//            print_r($item);exit;
            $this->assign('item', $item);
            $this->display('edition.index.html');
        } else {
            import('uploader.lib');
            //先获取上传图片的相关信息
            $file = $_FILES['img'];
            if (!empty($file['error'])) {
                $this->show_warning('产品图片上传错误！');
                return;
            }
            $uploader = new Uploader();
            $uploader->allowed_type(IMAGE_FILE_TYPE);  //图片格式
            $uploader->allowed_size(SIZE_STORE_LOGO);  //2M
            $uploader->addFile($file);
            if ($uploader->file_info() == false) {
                $this->show_warning($uploader->get_error());
                return false;
            }
            $uploader->root_dir(ROOT_PATH);  //设置根目录
            $url = 'data/files/store_13/edition_img';
            $name = 'edition' . time();
            $uploader->save($url, $name);
            $img = $url . '/' . $name;
            $edition = array(
                'edition_name' => $_POST['edition_name'],
                'brand' => $_POST['brand'],
                'material' => $_POST['material'],
                'technology' => $_POST['technology'],
                'specifications' => $_POST['specifications'],
                'weight' => $_POST['weight'],
                'style' => $_POST['style'],
                "orders" => $_POST['order'],
                'img' => $img,
                'created' => time(),
                'status' => 1,
                'edition_with' => $_POST['with'],
                'store_id' => $_SESSION['user_info']['store_id']
            );

            $edition_model->add($edition);
            if ($edition_model->has_error()) {
                $this->show_warning($edition_model->get_error());
                return;
            }
            $this->show_message('新建成功', '进入图片添加页面', 'index.php?app=edition&act=edition_list');
//            exit;
        }
    }

    //列表
    function edition_list() {
                                /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('版本展示'), 'index.php?app=introduction', LANG::get('版本列表'));

            /* 当前用户中心菜单 */
            $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
            $this->_curitem('edition');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
            $this->_curmenu($type);
            $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
           
        $edition_model = & m('edition');
        $store_id = $_SESSION['user_info']['store_id'];
        $conditions = "store_id =".$store_id;
        $pagesize = 10;
        $page = $_GET['page'];
        if (empty($page)) {
            $page = 1;
        }
        $limit = ($page - 1) * $pagesize . "," . $pagesize;
        if ($page <= 1) {
            $prev_page = 1;
        } else {
            $prev_page = $page - 1;
        }
        $all_editions = $edition_model->find(array(
            'conditions' => $conditions,
            'fields' => 'edition_id',
        ));

        $totalcount = count($all_editions);
        $totalpage = ceil($totalcount / $pagesize);
        if ($page >= $totalpage) {
            $next_page = $totalpage;
        } else {
            $next_page = $page + 1;
        }
        $editions = $edition_model->find(array(
            'conditions' => $conditions,
            'limit' => $limit,
        ));
        $page_info = array(
            'page' => $page,
            'totalpage' => $totalpage,
            'totalcount' => $totalcount,
            'next_page' => $next_page,
            'prev_page' => $prev_page,
        );
        $this->assign('page_info', $page_info);
        $this->assign('editions', $editions);
        $this->display('edition_list.html');
    }

    //批量上传效果图
    function addeffect() {
        if (IS_POST) {
            $images_model = & m('edition_images');
            $id = $_GET['id'];
            import('uploader.lib');
            $files = $_FILES['effectimgs'];
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                $file_array = array();
                $file_array['name'] = $files['name'][$i];
                $file_array['type'] = $files['type'][$i];
                $file_array['tmp_name'] = $files['tmp_name'][$i];
                $file_array['error'] = $files['error'][$i];
                $file_array['size'] = $files['size'][$i];
                if (!empty($file_array[error])) {
                    continue;
                }
                $uploader = new Uploader();
                $uploader->allowed_type(IMAGE_FILE_TYPE);
                $uploader->allowed_size(SIZE_STORE_LOGO); // 2M 
                $uploader->addFile($file_array);
                if ($uploader->file_info() == false) {
                    $this->show_warning($uploader->get_error());
                    return false;
                }
                $uploader->root_dir(ROOT_PATH); //设置根目录，必须要设置，否则会找不到文件夹
                $url = 'data/files/store_13/edition_img';
                $name = 'edition_effect' . time().$i;
                $uploader->save($url, $name);
                $img = $url . '/' . $name;
                $images = array(
                    'img' => $img,
                    'edition_id' => $id,
                    'status' => 2,
                );
                $images_model->add($images);
                if ($images_model->has_error()) {
                    $this->show_warning($images_model->get_error());
                    return;
                }
            }
            $this->show_message('上传成功！','','index.php?app=edition&act=addimages&id='.$id);
        }
    }

    //修改
    function edit() {
        $edition_model = & m('edition');
        $id = $_GET['id'];
        $edition = $edition_model->get('edition_id =' . $id);
        if (!IS_POST) {
             /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('版本展示'), 'index.php?app=introduction', LANG::get('修改版本'));

            /* 当前用户中心菜单 */
            $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
            $this->_curitem('edition');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
            $this->_curmenu($type);
            $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
            
            $edition['edit'] = true;
            $this->assign('edition', $edition);
            $this->display('edition.index.html');
        } else {
            import('uploader.lib');
            //先获取上传图片的相关信息
            $file = $_FILES['img'];
            //如果上传新图，就删除旧的并上传新的
            if (!empty($file['tmp_name'])) {
                @unlink(ROOT_PATH . '/' . $edition['img']);
                $uploader = new Uploader();
                $uploader->allowed_type(IMAGE_FILE_TYPE);  //图片格式
                $uploader->allowed_size(SIZE_STORE_LOGO);  //2M
                $uploader->addFile($file);
                if ($uploader->file_info() == false) {
                    $this->show_warning($uploader->get_error());
                    return false;
                }
                $uploader->root_dir(ROOT_PATH);  //设置根目录
                $url = 'data/files/store_13/edition_img';
                $name = 'edition' . time();
                $uploader->save($url, $name);
                $img = $url . '/' . $name;
            }
            $edition = array(
                'edition_name' => $_POST['edition_name'],
                'brand' => $_POST['brand'],
                'material' => $_POST['material'],
                'technology' => $_POST['technology'],
                'specifications' => $_POST['specifications'],
                'weight' => $_POST['weight'],
                'style' => $_POST['style'],
                'orders' => $_POST['order'],
                'created' => time(),
                'status' => 1,
            );
            if (!empty($img)) {
                $edition['img'] = $img;
            }
            if (!empty($_POST['with'])) {
                $edition[edition_with] = $_POST['with'];
            }
            $edition_model->edit($id, $edition);
            if ($edition_model->has_error()) {
                $this->show_warning($edition_model->get_error());
                return;
            }
            $this->show_message('修改成功', '点击返回', 'index.php?app=edition&act=edition_list');
        }
    }

    //增加展示里面的图片
    function addimages() {
        $edition_model = & m('edition');
        $images_model = & m('edition_images');
        $id = $_GET['id'];
        if (!IS_POST) {
                         /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('版本展示'), 'index.php?app=introduction', LANG::get('增加版本图片'));

            /* 当前用户中心菜单 */
            $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
            $this->_curitem('edition');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
            $this->_curmenu($type);
            $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
            
            $edition = $edition_model->get('edition_id =' . $id); //获取基本信息
            $images = $images_model->find('edition_id =' . $id." AND status = 1");    //获取相关版本图片信息
            $effect_imgs = $images_model->find('edition_id =' . $id." AND status = 2"); //获取效果图
            $this->assign('effect_imgs', $effect_imgs);
            $this->assign('images', $images);
            $this->assign('id', $id);
            $this->assign('edition', $edition);
            $this->display('edition_addimages.html');
        } else {
            $files = $_FILES['img'];
            $model = $_POST['model'];
            if (empty($model) || $file['error'] != 0) {
                $this->show_warning('请填写相关信息');
                return;
            }
            import('uploader.lib');
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                $file_array = array();
                $file_array['name'] = $files['name'][$i];
                $file_array['type'] = $files['type'][$i];
                $file_array['tmp_name'] = $files['tmp_name'][$i];
                $file_array['error'] = $files['error'][$i];
                $file_array['size'] = $files['size'][$i];
                if (!empty($file_array[error])) {
                    continue;
                }
                $uploader = new Uploader();
                $uploader->allowed_type(IMAGE_FILE_TYPE);
                $uploader->allowed_size(SIZE_STORE_LOGO); // 2M 
                $uploader->addFile($file_array);
                if ($uploader->file_info() == false) {
                    $this->show_warning($uploader->get_error());
                    return false;
                }
                $uploader->root_dir(ROOT_PATH); //设置根目录，必须要设置，否则会找不到文件夹
                $url = 'data/files/store_13/edition_img';
                $name = 'edition_child' . time().$i;
                $uploader->save($url, $name);
                $img = $url . '/' . $name;
                $images = array(
                    'img' => $img,
                    'edition_id' => $id,
                    'img_model' => $model[$i],
                );
                $images_model->add($images);
                if ($images_model->has_error()) {
                    $this->show_warning($images_model->get_error());
                    return;
                }
            }
//            $uploader = new Uploader();
//            $uploader->allowed_type(IMAGE_FILE_TYPE);  //图片格式
//            $uploader->allowed_size(SIZE_GOODS_IMAGE);  //2M
//            $uploader->addFile($file);
//            if ($uploader->file_info() == false) {
//                $this->show_warning($uploader->get_error());
//                return false;
//            }
//            $uploader->root_dir(ROOT_PATH);  //设置根目录
//            $url = 'data/files/store_13/edition_img';
//            $name = 'edition_child' . time();
//            $uploader->save($url, $name);
//            $img = $url . '/' . $name;
//            $images = array(
//                'img' => $img,
//                'edition_id' => $id,
//                'img_model' => $model,
//            );
//            $images_model->add($images);
//            if ($images_model->has_error()) {
//                $this->show_warning($images_model->get_error());
//                return;
//            }
            $this->show_message('添加成功！', '继续添加', 'index.php?app=edition&act=addimages&id=' . $id);
        }
    }

    //删除相关的图片
    function delimg() {
        $edition_images_model = & m('edition_images');
        $id = $_GET['id'];
        $images = $edition_images_model->get('id =' . $id);
        if (empty($_SESSION['user_info']['user_id'])) {
            $this->show_warning('请先登录');
            return;
        }
        //删除图片
        @unlink($images['img']);
        $edition_images_model->drop($id);
        $this->show_message('操作成功', '图片已删除');
        return;
    }

    function _get_member_submenu() {
        $menus = array(
            array(
                'name' => 'goods_list',
                'url' => 'index.php?app=my_goods',
            ),
            array(
                'name' => 'goods_add',
                'url' => 'index.php?app=my_goods&amp;act=add',
            ),
            array(
                'name' => 'import_taobao',
                'url' => 'index.php?app=my_goods&amp;act=import_taobao',
            ),
            array(
                'name' => 'brand_apply_list',
                'url' => 'index.php?app=my_goods&amp;act=brand_list'
            ),
        );

        if (ACT == 'batch_edit') {
            $menus[] = array(
                'name' => 'batch_edit',
                'url' => '',
            );
        } elseif (ACT == 'edit') {
            $menus[] = array(
                'name' => 'edit_goods',
                'url' => '',
            );
        }

        return $menus;
    }

}
