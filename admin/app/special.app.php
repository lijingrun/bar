<?php

//整个商城的品牌专场活动
class SpecialApp extends BackendApp {

    function index() {
        $special_model = & m('special');
        $sgrade_model = & m('sgrade');
        $specials = $special_model->find(array(
            'conditions' => "1=1",
            'order' => 'special_id desc',
        ));
        $sgrade = $sgrade_model->findAll();
        $this->assign('sgrade', $sgrade);
        $this->assign('specials', $specials);
        $this->display('special.html');
    }

    //新增
    function special_add() {
        $special_model = & m('special');
        if (!IS_POST) {
            $sgrade_model = & m('sgrade');
            $sgrade = $sgrade_model->findAll();
            $this->assign('sgrade', $sgrade);
            $this->display('special_add.html');
        } else {
            $sgrade = $_POST['sgrade'];
            if(!empty($sgrade)){
                $sgrade = implode(',',$sgrade);
            }else{
                $this->show_message('请选择目标客户等级！');
                return;
            }
            import('uploader.lib');
            $image = $_FILES['image'];
            $uploader = new Uploader();
            $uploader->allowed_type(IMAGE_FILE_TYPE);
            $uploader->allowed_size(SIZE_STORE_LOGO);
            $uploader->addFile($image);
            if ($uploader->file_info() == false) {
                $this->show_warning($uploader->get_error());
                return false;
            }

            $uploader->root_dir(ROOT_PATH);
            $save_url = 'admin/templates/style/images/special';
            $filename = 'special' . time();
            $uploader->save($save_url, $filename);
            $name = $_POST['name'];
            $start = strtotime($_POST['start']) - 28800;
            $end = strtotime($_POST['end']) - 28800;
            $is_show = $_POST['show'];
            $new_special = array(
                'name' => $name,
                'start' => $start,
                'end' => $end,
                'is_show' => $is_show,
                'image' => '/' . $save_url . "/" . $filename,
                'sgrade' => $sgrade,
            );
            $special_model->add($new_special);
            $this->show_message('操作成功！', '保存成功！', 'index.php?app=special');
        }
    }

    //删除
    function del_special() {
        $special_model = & m('special');
        $id = $_GET['id'];
        $special_model->drop($id);
        $this->show_message('删除成功！', '操作成功', 'index.php?app=special');
    }

    //修改
    function edit() {
        $special_model = & m('special');
        if (!IS_POST) {
            $id = $_GET['id'];
            $special = $special_model->get('special_id =' . $id);
            $sgrade_model = & m('sgrade');
            $sgrade = $sgrade_model->findAll();
            $start_day = date("Y-m-d" , $special['start']);
            $start_time = date ("H:i",$special['start']+28800);
            $start = $start_day."T".$start_time;
            $end_day = date("Y-m-d" , $special['end']);
            $end_time = date ("H:i",$special['end']+28800);
            $end = $end_day."T".$end_time;
            $this->assign('start' , $start);
            $this->assign('end' , $end);
            $this->assign('special', $special);
            $this->assign('sgrade', $sgrade);
            $this->display('special_add.html');
        } else {
            $id = $_GET['id'];
            $special = $special_model->get('special_id =' . $id);
            $image = $_FILES['image'];
            if ($image['error'] == 0) {
                import('uploader.lib');
                $uploader = new Uploader();
                $uploader->allowed_type(IMAGE_FILE_TYPE);
                $uploader->allowed_size(SIZE_STORE_LOGO);
                $uploader->addFile($image);
                if ($uploader->file_info() == false) {
                    $this->show_warning($uploader->get_error());
                    return false;
                }
                $uploader->root_dir(ROOT_PATH);
                $save_url = 'admin/templates/style/images/special';
                $filename = 'special' . time();
                $uploader->save($save_url, $filename);
                $special['image'] = '/' . $save_url . "/" . $filename;
            }
            $name = $_POST['name'];
            $start = strtotime($_POST['start']) - 28800;
            $end = strtotime($_POST['end']) - 28800;
            $is_show = $_POST['show'];
            $sgrade = $_POST['sgrade'];
            $special['name'] = $name;
            $special['start'] = $start;
            $special['end'] = $end;
            $special['is_show'] = $is_show;
            $special['sgrade'] = $sgrade;
            $special_model->edit($id, $special);
            $this->show_message('操作成功！', '保存成功！', 'index.php?app=special');
        }
    }
    
    function change_show(){
        $id = $_POST['id'];
        $special_model = & m('special');
        $special = $special_model->get('special_id ='.$id);
        if($special['is_show'] == 1){
            $special['is_show'] = 0;
        }else{
            $special['is_show'] = 1;
        }
        $special_model->edit($id,$special);
        exit;
    }

}
