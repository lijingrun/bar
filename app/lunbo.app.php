<?php

class LunboApp extends StoreadminbaseApp {

    var $_store_id;
    var $_store_mod;

    function __construct() {
        $this->LunboApp();
    }

    function LunboApp() {
        parent::__construct();
        $this->_store_id = intval($this->visitor->get('manage_store'));
        $this->_store_mod = & m('store');
    }

    function index() {
        $store = $this->_store_mod->get(array('conditions' => 'store_id=' . $this->_store_id, 'fields' => 'pic_slides_wap'));
        $lunbo_model = & m('lunbo_img');
        $store_id = $this->_store_id;
        if (!IS_POST) {
            /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('lunbo'));
            $this->_curitem('lunbo');

            $pic_slides_wap = array();

            if ($store['pic_slides_wap']) {
                $pic_slides_wap_arr = json_decode($store['pic_slides_wap'], true);

                foreach ($pic_slides_wap_arr as $key => $slides) {
                    $pic_slides_wap['pic_slides_wap_url_' . $key] = $slides['url'];
                    $pic_slides_wap['pic_slides_wap_link_' . $key] = $slides['link'];
                }
            }

            $this->assign('slides', $pic_slides_wap);
            $lunbo1 = $lunbo_model->get('store_id =' . $store_id . " AND name like 'pic_slides_wap_1'");
            $lunbo2 = $lunbo_model->get('store_id =' . $store_id . " AND name like 'pic_slides_wap_2'");
            $lunbo3 = $lunbo_model->get('store_id =' . $store_id . " AND name like 'pic_slides_wap_3'");
            $this->assign('lunbo1', $lunbo1);
            $this->assign('lunbo2', $lunbo2);
            $this->assign('lunbo3', $lunbo3);
            $this->display('lunbo.index.html');
        } else {

            $pic_slides_wap_arr = $this->_upload_slides();

            if ($pic_slides_wap_arr == FALSE) {
                return;
            }

            // $all_slides = array();

            $url1 = $_POST['pic_slides_wap_link_1'];
            $url2 = $_POST["pic_slides_wap_link_2"];
            $url3 = $_POST["pic_slides_wap_link_3"];
            $status1 = $_POST['status1'];
            $status2 = $_POST['status2'];
            $status3 = $_POST['status3'];
            //先查下有无信息

            $lunbo = $lunbo_model->get('store_id =' . $store_id);
            //如果无记录，就新增,有，就修改
            if (empty($lunbo['id'])) {
                $lunbo1 = array(
                    'url' => $url1,
                    'status' => $status1,
                    'name' => 'pic_slides_wap_1',
                    'img' => 'data/files/store_' . $this->_store_id . '/pic_slides_wap/pic_slides_wap_1',
                    'store_id' => $store_id
                );
                $lunbo_model->add($lunbo1);
                $lunbo2 = array(
                    'url' => $url2,
                    'status' => $status2,
                    'name' => 'pic_slides_wap_2',
                    'img' => 'data/files/store_' . $this->_store_id . '/pic_slides_wap/pic_slides_wap_2',
                    'store_id' => $store_id
                );
                $lunbo_model->add($lunbo2);
                $lunbo3 = array(
                    'url' => $url3,
                    'status' => $status3,
                    'name' => 'pic_slides_wap_3',
                    'img' => 'data/files/store_' . $this->_store_id . '/pic_slides_wap/pic_slides_wap_3',
                    'store_id' => $store_id
                );
                $lunbo_model->add($lunbo3);
            } else {
                $lunbo1 = $lunbo_model->get('store_id =' . $store_id . " AND name like 'pic_slides_wap_1'");
                $lunbo1['url'] = $url1;
                $lunbo1['status'] = $status1;
//                print_r($lunbo1);exit;
                $lunbo_model->edit($lunbo1['id'], $lunbo1);
                $lunbo2 = $lunbo_model->get('store_id =' . $store_id . " AND name like 'pic_slides_wap_2'");
                $lunbo2['url'] = $url2;
                $lunbo2['status'] = $status2;
                $lunbo_model->edit($lunbo2['id'], $lunbo2);
                $lunbo3 = $lunbo_model->get('store_id =' . $store_id . " AND name like 'pic_slides_wap_3'");
                $lunbo3['url'] = $url3;
                $lunbo3['status'] = $status3;
                $lunbo_model->edit($lunbo3['id'], $lunbo3);
            }
//            if (empty($store['pic_slides_wap'])) {
//                $all_slides = json_encode($pic_slides_wap_arr);
//            } else {
//                $old_pic_slides_wap_arr = json_decode($store['pic_slides_wap'], true);
//                foreach ($pic_slides_wap_arr as $key => $slides) {
//                    if (!empty($slides['url'])) {
//                        $old_pic_slides_wap_arr[$key]['url'] = $slides['url'];
//                    }
//                    if (!empty($slides['link'])) {
//                        $old_pic_slides_wap_arr[$key]['link'] = $slides['link'];
//                    }
//                }
//                $all_slides = json_encode($old_pic_slides_wap_arr);
//            }
            // $this->_store_mod->edit($this->_store_id, array('pic_slides_wap' => $all_slides));

            $this->show_message('edit_ok', 'back_list', 'index.php?app=lunbo', 'back_home', 'index.php?app=member');
        }
    }

    function _upload_slides() {
        import('uploader.lib');
        $data = array();
        for ($i = 1; $i <= 3; $i++) {
            $file = $_FILES['pic_slides_wap_url_' . $i];
            if ($file['error'] == UPLOAD_ERR_OK && $file != '') {
                //先删除原来的同名的所有格式图片
                if (file_exists('data/files/store_' . $this->_store_id . '/pic_slides_wap/pic_slides_wap_' . $i . ".jpg")) {
                    unlink('data/files/store_' . $this->_store_id . '/pic_slides_wap/pic_slides_wap_' . $i . ".jpg");
                }
                if (file_exists('data/files/store_' . $this->_store_id . '/pic_slides_wap/pic_slides_wap_' . $i . ".png")) {
                    unlink('data/files/store_' . $this->_store_id . '/pic_slides_wap/pic_slides_wap_' . $i . ".png");
                }
                if (file_exists('data/files/store_' . $this->_store_id . '/pic_slides_wap/pic_slides_wap_' . $i . ".jpeg")) {
                    unlink('data/files/store_' . $this->_store_id . '/pic_slides_wap/pic_slides_wap_' . $i . ".jpeg");
                }
                if (file_exists('data/files/store_' . $this->_store_id . '/pic_slides_wap/pic_slides_wap_' . $i . ".gif")) {
                    unlink('data/files/store_' . $this->_store_id . '/pic_slides_wap/pic_slides_wap_' . $i . ".gif");
                }
                $uploader = new Uploader();
                $uploader->allowed_type(IMAGE_FILE_TYPE);
                $uploader->allowed_size(SIZE_STORE_LOGO); // 2M
                $uploader->addFile($file);
                if ($uploader->file_info() === false) {
                    $this->show_warning($uploader->get_error());
                    return false;
                }
                $uploader->root_dir(ROOT_PATH);
                $data[$i]['url'] = $uploader->save('data/files/store_' . $this->_store_id . '/pic_slides_wap', 'pic_slides_wap_' . $i);
            } else {
                $data[$i]['url'] = '';
            }

            $data[$i]['link'] = trim($_POST['pic_slides_wap_link_' . $i]);
        }
        return $data;
    }

}

?>
