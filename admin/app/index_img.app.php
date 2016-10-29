<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of index_img
 *
 * @author lijingrun
 */
class Index_imgApp extends BackendApp {
    function index(){
        $imdex_img_model = & m('index_img');
        if(!IS_POST){
            $images = $imdex_img_model->find('status = 1 order by the_order');
            $this->assign('images', $images);
//            print_r($images);exit;
            $this->display('index_img.html');
        }else{
            import('uploader.lib');
            $file = $_FILES['index_img'];
            $the_url = $_POST['the_url'];
            $the_order = $_POST['the_order'];
            if ($file['error'] == 0) {
                $uploader = new Uploader();
                $uploader->allowed_type(IMAGE_FILE_TYPE);
                $uploader->allowed_size(SIZE_STORE_LOGO); // 2M 
                $uploader->addFile($file);
                $url = "data/indeximages";
                $name = 'indeximg'.time();
                
                if ($uploader->file_info() == false) {
                    $this->show_warning($uploader->get_error());
                    return false;
                }
                $uploader->root_dir(ROOT_PATH); //设置根目录，必须要设置，否则会找不到文件夹
                $uploader->save($url, $name);
                $image = array(
                    'image' => $url."/".$name,
                    'url'   => $the_url,
                    'the_order' => $the_order,
                );
                $imdex_img_model->add($image);
                $this->show_message('添加成功','正在返回','index.php?app=index_img');
            }
        }
    }
    
    //ajax修改url
    function changeurl(){
        $id = $_POST['id'];
        $url = $_POST['url'];
        $imdex_img_model = & m('index_img');
        $image = $imdex_img_model->get('id = '.$id);
        $image['url'] = $url;
        $imdex_img_model->edit($id,$image);
        echo "修改成功！";
        exit;
    }
    
    //ajax修改排序
    function changeorder(){
        $id = $_POST['id'];
        $order = $_POST['order'];
        $imdex_img_model = & m('index_img');
        $image = $imdex_img_model->get('id = '.$id);
        $image['the_order'] = $order;
        $imdex_img_model->edit($id,$image);
        echo "修改成功！";
        exit;
    }
    
    //删除图片
    function delimg(){
        $id = $_POST['id'];
        $imdex_img_model = & m('index_img');
        $imdex_img_model->drop($id);
        echo "删除成功！";
        exit;
    }
    
    //修改图片
    function changeimg(){
        $id = $_GET['id'];
        $imdex_img_model = & m('index_img');
        if(!IS_POST){
            $this->display('changeimg.html');
        }else{
            import('uploader.lib');
            $file = $_FILES['index_img'];
            if ($file['error'] == 0) {
                $uploader = new Uploader();
                $uploader->allowed_type(IMAGE_FILE_TYPE);
                $uploader->allowed_size(SIZE_STORE_LOGO); // 2M 
                $uploader->addFile($file);
                $url = "data/indeximages";
                $name = 'indeximg'.time();
                
                if ($uploader->file_info() == false) {
                    $this->show_warning($uploader->get_error());
                    return false;
                }
                $uploader->root_dir(ROOT_PATH); //设置根目录，必须要设置，否则会找不到文件夹
                $uploader->save($url, $name);
                $image = $imdex_img_model->get('id ='.$id);
                $image['image'] = $url."/".$name;
                $imdex_img_model->edit($id,$image);
                $this->show_message('修改成功','正在返回','index.php?app=index_img');
            }
        }
    }
}
