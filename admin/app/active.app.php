<?php

/* 商城主页活跃用的显示点击抢购模块 */
class ActiveApp extends BackendApp
{
    function index(){
        $active_model = & m('admin_active');
        $actives = $active_model->findAll();
        $goods_model = & m('goods');
        foreach($actives as $key=>$active):
            $good = $goods_model->get('goods_id ='.$active['goods_id']);
            $actives[$key]['goods_name'] = $good['goods_name'];
        endforeach;
        $this->assign('actives', $actives);
        $this->display('active_index.html');
    }
    
    //删除活动
    function del(){
        $id = $_GET['id'];
        $active_model = & m('admin_active');
        $active_model->drop($id);
        $this->show_message('操作成功！','已经删除','index.php?app=active');
    }
    
    //增加活动
    function add(){
        if(!IS_POST){
            $sgrade_model = & m('sgrade');
            $grades = $sgrade_model->findAll();
            $this->assign('grades', $grades);
            $this->display('active_add.html');
        }else{
            $grades = $_POST['grade'];
            $grade = implode(',', $grades);
            $grade = ','.$grade.",";
            $url = '/admin/data/activeimg';
            $file = $_FILES['img'];
            $uploader = new Uploader();
            $uploader->allowed_type(IMAGE_FILE_TYPE);
            $uploader->allowed_size(SIZE_STORE_LOGO);
            $uploader->addFile($file);
            if ($uploader->file_info() == false) {
                $this->show_warning($uploader->get_error());
                return false;
            }
            $uploader->root_dir(ROOT_PATH);
            $name = gmtime().$file['name'];
            $upload_name = substr($name,'.', -4);
            $uploader->save($url, $upload_name);
            $active_model = & m('admin_active');
            $new_active = array(
                'goods_id' => $_POST['goods_id'],
                'spec_id' => $_POST['spec'],
                'start_time' => strtotime($_POST['start_time'])-28800,
                'end_time' => strtotime($_POST['end_time'])-28800,
                'price' => $_POST['price'],
                'nums' => $_POST['nums'],
                'img' => $url."/".$name,
                'status' => 1,
                'grade' => $grade,
                'point_price' => $_POST['point'],
                'can_buy' => $_POST['can_buy'],
            );  
            $active_model->add($new_active);
            $this->show_message('操作成功！','保存成功！','index.php?app=active');
        }
    }
    
    //激活
    function enable(){
        $id = $_GET['id'];
        if(empty($id)){
            $this->show_warning('活动不存在！');
            return;
        }
        $active_model = & m('admin_active');
        $active_model->edit($id,'enable = 1');
        $this->show_message('操作成功！','活动已激活','index.php?app=active');
    }
    
    //搜索全商城的商品
    function find_goods(){
        $goods_name = $_POST['goods_name'];
        if(empty($goods_name)){
            echo "无产品！";
            exit;
        }
        $goods_mode = & m('goods');
        $goods = $goods_mode->find(array(
            'conditions'  => "goods_name like '%".$goods_name."%'",
            'fields'     => "goods_name",
        ));
        echo "<select id='goods' name='goods_id' onChange='find_spec();'>";
        echo "<option value='0'>请选择商品</option>";
        foreach($goods as $good):
            echo "<option value='".$good['goods_id']."'>".$good['goods_name']."</option>";
        endforeach;
        echo "</select>";
        exit;
    }
    
    //搜索规格
    function find_spec(){
        $goods_id = $_POST['goods_id'];
        $spec_model = & m('goodsspec');
        $specs = $spec_model->find("goods_id =".$goods_id);
        echo "<div id='spec_list' style='padding-top:20px;'>";
        foreach($specs as $val):
            echo "<input type='radio' name='spec' value='".$val['spec_id']."' />".$val['spec_1']."（".$val['spec_2']."）<br/>";
        endforeach;
        echo "</div>";
        exit;
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
