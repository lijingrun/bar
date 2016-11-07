<?php
/**
 * Created by PhpStorm.
 * User: lijingrun
 * Date: 2016/10/29
 * Time: 10:56
 *
 * 业务员端口，增加业务员之后手机端可以查看业务员的相关信息，点击可以直接打电话给业务员进行预约操作
 */
class SalesApp extends StoreadminbaseApp {

    function Index(){
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('业务员设置'), 'index.php?app=seals', LANG::get('业务员设置'));
        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
        $this->_curitem('seals');
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
        $sales_model = & m('sales');
        $sales = $sales_model->find(array(
            'conditions' => "store_id =".$_SESSION['user_info']['user_id'],
            'order'      => 'sales_order',
        ));
        $this->assign('sales',$sales);
        $this->display("sales.html");
    }

    //增加业务员
    function Add(){
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('业务员设置'), 'index.php?app=seals', LANG::get('业务员设置'));
        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
        $this->_curitem('seals');
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));

        if(!IS_POST){


            $this->display("sales_add.html");
        }else{
            import('uploader.lib');
            $file = $_FILES['sale_image'];
            if ($file['error'] == 0) {
                $uploader = new Uploader();
                $uploader->allowed_type(IMAGE_FILE_TYPE);
                $uploader->allowed_size(SIZE_STORE_LOGO); // 2M
                $uploader->addFile($file);
                $url = "data/indeximages/sales";
                $file_name = $this->get_extension($file['name']);
                $name = 'sales'.time();
                if ($uploader->file_info() == false) {
                    $this->show_warning($uploader->get_error());
                    return false;
                }
                $uploader->root_dir(ROOT_PATH); //设置根目录，必须要设置，否则会找不到文件夹
                $uploader->save($url, $name);
                $img = $url."/".$name.".".$file_name;

            }else{
                $img = '';
            }
            $sales_model = & m("sales");
            $store_id = $_SESSION['user_info']['user_id'];
            $new_sales = array(
                'name' => $_POST['name'],
                'sex' => $_POST['sex'],
                'telephone' => $_POST['telephone'],
                'age' => $_POST['age'],
                'images' => $img,
                'sales_order' => $_POST['order'],
                'store_id' => $store_id,
            );
            $sales_model->add($new_sales);
            $this->show_message('添加成功','正在返回','index.php?app=sales');
        }
    }

    function del(){
        if(IS_POST){
            $id = $_POST['id'];
            $sale_model = & m("sales");
            $sale_model->drop($id);
            echo 111;
            exit;
        }
    }

    function edit(){
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('业务员设置'), 'index.php?app=seals', LANG::get('业务员设置'));
        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'all_orders';
        $this->_curitem('seals');
        $this->_curmenu($type);
        $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('order_manage'));
        $sales_model = & m('sales');
        $id = $_GET['id'];
        if(!IS_POST){
            $sale = $sales_model->get("id=".$id);
            $this->assign('sale',$sale);

            $this->display("sales_add.html");
        }else{
            import('uploader.lib');
            $file = $_FILES['sale_image'];
            if ($file['error'] == 0) {
                $uploader = new Uploader();
                $uploader->allowed_type(IMAGE_FILE_TYPE);
                $uploader->allowed_size(SIZE_STORE_LOGO); // 2M
                $uploader->addFile($file);
                $url = "data/indeximages/sales";
                $file_name = $this->get_extension($file['name']);
                $name = 'sales'.time();
                if ($uploader->file_info() == false) {
                    $this->show_warning($uploader->get_error());
                    return false;
                }
                $uploader->root_dir(ROOT_PATH); //设置根目录，必须要设置，否则会找不到文件夹
                $uploader->save($url, $name);
                $img = $url."/".$name.".".$file_name;

            }
            $store_id = $_SESSION['user_info']['user_id'];
            $sale = $sales_model->get('id ='.$id);
                $sale['name'] = $_POST['name'];
                $sale['sex'] = $_POST['sex'];
                $sale['telephone'] = $_POST['telephone'];
                $sale['age'] = $_POST['age'];
                $sale['sales_order'] = $_POST['order'];
                $sale['store_id'] = $store_id;
            if(!empty($img)){
                $sale['images'] = $img;
            }

            $sales_model->edit($id,$sale);
            $this->show_message('添加成功','正在返回','index.php?app=sales');
        }
    }

    function get_extension($file)
    {
        return end(explode('.', $file));
    }

}

