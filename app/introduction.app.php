<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of introduction
 *
 * @author lijingrun
 */

/**
 * 企业简介控制器
 */
class IntroductionApp extends StoreadminbaseApp {

    function index() {
        $store_id = $_SESSION['user_info']['store_id'];
        $introduction_model = & m('enterprise');
        $enterprise_img_model = & m('enterprise_images');
        if (!IS_POST) {
            $introduction = $introduction_model->get('store_id =' . $store_id);
            $images = $enterprise_img_model->find('store_id =' . $store_id);
            /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('企业简介'), 'index.php?app=introduction', LANG::get('企业简介'));

            /* 当前用户中心菜单 */
            $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
            $this->_curitem('introduction');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
            $this->_curmenu($type);
            $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
            
            $this->assign('images', $images);
            $this->assign('introduction', $introduction);
            $this->display('introduction.html');
        } else {
            import('uploader.lib');
            $files = $_FILES['image'];    //企业证书
            $enames = $_POST['name'];   //证书名
            $introduction_image = $_FILES['introduction_image'];   //形象
            $url = "data/files/store_" . $store_id . "/introduction_images";
            $introduction = $_POST['introduction'];
            /*
             * 先保存形象图和记录
             */
            if ($introduction_image['error'] == 0) {
                $uploader = new Uploader();
                $uploader->allowed_type(IMAGE_FILE_TYPE);
                $uploader->allowed_size(SIZE_STORE_LOGO); // 2M 
                $uploader->addFile($introduction_image);

                if ($uploader->file_info() == false) {
                    $this->show_warning($uploader->get_error());
                    return false;
                }
                $name = "introduction" . time();
                $uploader->root_dir(ROOT_PATH); //设置根目录，必须要设置，否则会找不到文件夹
                $uploader->save($url, $name);
            }
            if ($introduction_image['error'] == 0) {
                $introduction_arr = array(
                    'store_id' => $store_id,
                    'image' => $url . "/" . $name,
                    'enterprise' => $introduction,
                );
            } else {
                $introduction_arr = array(
                    'store_id' => $store_id,
                    'enterprise' => $introduction,
                );
            }
            $introduct = $introduction_model->get('store_id =' . $store_id);
            if (empty($introduct['id'])) {
                $introduction_model->add($introduction_arr);
            } else {
                $introduction_model->edit($introduct['id'], $introduction_arr);
            }
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
                $name = time() . $i;
                $uploader->save($url, $name);
                $enterprise_images = array(
                    'store_id' => $store_id,
                    'image' => $url . "/" . $name,
                    'name' => $enames[$i],
                );
                $enterprise_img_model->add($enterprise_images);
            }
            $this->show_message('上传成功！','','index.php?app=introduction');
        }
    }

    //删除证书
    function delimg() {
        $id = $_POST['id'];
        $enterprise_img_model = & m('enterprise_images');
        $enterprise_img_model->drop($id);
        echo "已成功删除！";
        exit;
    }

}
