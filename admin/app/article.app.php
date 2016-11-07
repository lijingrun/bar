<?php

/**
 *    文章管理控制器
 *
 *    @author    Hyber
 *    @usage    none
 */
class ArticleApp extends BackendApp
{
    var $_article_mod;
    var $_uploadedfile_mod;

    function __construct()
    {
        $this->ArticleApp();
    }

    function ArticleApp()
    {
        parent::BackendApp();

        $this->_article_mod =& m('article');
        $this->_uploadedfile_mod = &m('uploadedfile');
    }

    /**
     *    文章索引
     *
     *    @author    Hyber
     *    @return    void
     */
    function index()
    {
        /* 处理cate_id */
        $cate_id = !empty($_GET['cate_id'])? intval($_GET['cate_id']) : 0;
        if ($cate_id > 0) //取得该分类及子分类cate_id
        {
            $acategory_mod = & m('acategory');
            $cate_ids = $acategory_mod->get_descendant($cate_id);
            if (!$cate_ids)
            {
                $this->show_warning('no_this_acategory');
                return;
            }
        }
        $conditions='';
        !empty($cate_ids)&& $conditions = ' AND article.cate_id ' . db_create_in($cate_ids);
        $conditions .= $this->_get_query_conditions(array(
            array(
                'field' => 'title',         //可搜索字段title
                'equal' => 'LIKE',          //等价关系,可以是LIKE, =, <, >, <>
                'assoc' => 'AND',           //关系类型,可以是AND, OR
                'name'  => 'title',         //GET的值的访问键名
                'type'  => 'string',        //GET的值的类型
            ),
        ));
        $page   =   $this->_get_page(10);   //获取分页信息
        $articles=$this->_article_mod->find(array(
        'fields'   => 'article.*,acategory.cate_name',
        'conditions'  => 'store_id=0' . $conditions,
        'limit'   => $page['limit'],
        'join'    => 'belongs_to_acategory',
        'order'   => 'article.sort_order ASC,article.add_time DESC', //必须加别名
        'count'   => true   //允许统计
        ));    //找出所有的文章
        $page['item_count']=$this->_article_mod->getCount();   //获取统计数据
        $if_show = array(
            0 => Lang::get('no'),
            1 => Lang::get('yes'),
        );
        foreach ($articles as $key =>$article){
            $articles[$key]['if_show']  = $if_show[$article['if_show']]; //是否显示
        }
        $this->_format_page($page);
        $this->import_resource(array('script' => 'inline_edit.js'));
        $this->assign('filtered', $conditions? 1 : 0); //是否有查询条件
        $this->assign('parents', $this->_get_options()); //分类树
        $this->assign('page_info', $page);   //将分页信息传递给视图，用于形成分页条
        $this->assign('articles', $articles);
        $this->display('article.index.html');
    }

    /**
     * 将文章群发到用户端
     */
    function to_message(){
        $article_id = $_GET['article_id'];
        $url = "http://126wallpaper.com/index.php?app=article&act=view&article_id=".$article_id;
        $article_model = & m('article');
        $article = $article_model->get("article_id =".$article_id);
        $title = $article['title'];
        $devices_model = & m('devices');
        $data = array(
            'title' => '商城通知',
            'content' => $title,
            'redirect_url' => $url,
        );
        if(!IS_POST){

            $this->display('to_message.html');
        }else{
            $target = $_POST['target'];
            //根据选择的客户查找
            if($target == 'users'){
                $users = $_POST['users'];
                $users = explode(',',$users);
                $to_user = array();
                foreach($users as $user):
                    $to_user[] = "'".$user."'";
                endforeach;
                $to_user = implode(',',$to_user);
                $member_model = & m('member');
                $to_members = $member_model->find("user_name in (".$to_user.")");
                $mqtt = new Mqtt();
                $apns = new Apns();
                foreach($to_members as $member):
                    $devices = $devices_model->get("user_id =".$member['user_id']);
                    if(!empty($devices['serial_id']) && $devices['device_type'] == 'mqtt'){
                        $mqtt->send($devices['serial_id'],json_encode($data));
                    }else if(!empty($devices['serial_id']) && $devices['device_type'] == 'apns'){
                        $apns->send($devices['serial_id'],json_encode($data));
                    }
                endforeach;
            }else if($target == 'all'){
                $devices = $devices_model->find('1=1');
                $mqtt = new Mqtt();
                $apns = new Apns();
                foreach($devices as $device):
                    if(!empty($device['serial_id']) && $device['device_type'] == 'mqtt'){
                        $mqtt->send($device['serial_id'],json_encode($data));
                    }else if(!empty($device['serial_id']) && $device['device_type'] == 'apns'){
                        $apns->send($device['serial_id'],json_encode($data));
                    }
                endforeach;
            }
            exit;
            $this->show_message('发送成功！');
        }
    }

     /**
     *    新增文章
     *
     *    @author    Hyber
     *    @return    void
     */
    function add()
    {
        if (!IS_POST)
        {
            /* 显示新增表单 */
            $cate_id = isset ($_GET['cate_id']) ? intval($_GET['cate_id']) : 0;//方便在某个分类下新增文章
            $article = array('cate_id' => $cate_id, 'sort_order' => 255, 'link' => '', 'if_show' => 1);

            /* 文章模型未分配的附件 */
            $files_belong_article = $this->_uploadedfile_mod->find(array(
                'conditions' => 'store_id = 0 AND belong = ' . BELONG_ARTICLE . ' AND item_id = 0',
                'fields' => 'this.file_id, this.file_name, this.file_path',
                'order' => 'add_time DESC'
            ));

            $this->assign("id", 0);
            $this->assign('belong', BELONG_ARTICLE);

            $this->import_resource(array('script' => 'jquery.plugins/jquery.validate.js,change_upload.js'));
            $this->assign('article', $article);
            $this->assign('files_belong_article', $files_belong_article);
            $this->assign('parents', $this->_get_options()); //分类树
            
            $template_name = $this->_get_template_name();
            $style_name    = $this->_get_style_name();
            $this->assign('build_editor', $this->_build_editor(array(
                'name' => 'content',
                'content_css' => SITE_URL . "/themes/mall/{$template_name}/styles/{$style_name}/css/ecmall.css"
            )));
            
            $this->assign('build_upload', $this->_build_upload(array('belong' => BELONG_ARTICLE, 'item_id' => 0))); // 构建swfupload上传组件
            $this->display('article.form.html');
        }
        else
        {
            import('uploader.lib');
            $file = $_FILES['banner'];
            if ($file['error'] == 0) {
                $uploader = new Uploader();
                $uploader->allowed_type(IMAGE_FILE_TYPE);
                $uploader->allowed_size(SIZE_STORE_LOGO); // 2M
                $uploader->addFile($file);
                $url = "data/indeximages/banner";
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
            $data = array();
            $data['title']      =   $_POST['title'];
            $data['cate_id']    =   $_POST['cate_id'];
            $data['link']       =   $_POST['link'] == 'http://' ? '' : $_POST['link'];
            $data['if_show']    =   $_POST['if_show'];
            $data['sort_order'] =   $_POST['sort_order'];
            $data['content'] =   $_POST['content'];
            $data['add_time']   =   gmtime();
            if(!empty($img)){
                $data['banner'] = $img;
            }
            if (!$article_id = $this->_article_mod->add($data))  //获取article_id
            {
                $this->show_warning($this->_article_mod->get_error());

                return;
            }

            /* 附件入库 */
            if (isset($_POST['file_id']))
            {
                foreach ($_POST['file_id'] as $file_id)
                {
                    $this->_uploadedfile_mod->edit($file_id, array('item_id' => $article_id));
                }
            }
            $this->show_message('add_article_successed',
                'back_list',    'index.php?app=article',
                'continue_add', 'index.php?app=article&amp;act=add'
            );
        }
    }

    function get_extension($file)
    {
        return end(explode('.', $file));
    }

     /**
     *    编辑文章
     *
     *    @author    Hyber
     *    @return    void
     */
    function edit()
    {
        $article_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$article_id)
        {
            $this->show_warning('no_such_article');
            return;
        }
         if (!IS_POST)
        {
            /* 当前文章的附件 */
            $files_belong_article = $this->_uploadedfile_mod->find(array(
                'conditions' => 'store_id = 0 AND belong = ' . BELONG_ARTICLE . ' AND item_id=' . $article_id,
                'fields' => 'this.file_id, this.file_name, this.file_path',
                'order' => 'add_time DESC'
            ));

            $find_data     = $this->_article_mod->find($article_id);
            if (empty($find_data))
            {
                $this->show_warning('no_such_article');

                return;
            }
            $article    =   current($find_data);
            $article['link'] = $article['link'] ? $article['link'] : '';
            $this->assign("id", $article_id);
            $this->assign("belong", BELONG_ARTICLE);
            $this->import_resource(array('script' => 'jquery.plugins/jquery.validate.js,change_upload.js'));
            $this->assign('parents', $this->_get_options());
            $this->assign('files_belong_article', $files_belong_article);
            $this->assign('article', $article);

            $template_name = $this->_get_template_name();
            $style_name    = $this->_get_style_name();
            $this->assign('build_editor', $this->_build_editor(array(
                'name' => 'content',
                'content_css' => SITE_URL . "/themes/mall/{$template_name}/styles/{$style_name}/css/ecmall.css"
            )));
            
            $this->assign('build_upload', $this->_build_upload(array('belong' => BELONG_ARTICLE, 'item_id' => $article_id))); // 构建swfupload上传组件
            $this->display('article.form.html');
        }
        else
        {
            import('uploader.lib');
            $file = $_FILES['banner'];
            if ($file['error'] == 0) {
                $uploader = new Uploader();
                $uploader->allowed_type(IMAGE_FILE_TYPE);
                $uploader->allowed_size(SIZE_STORE_LOGO); // 2M
                $uploader->addFile($file);
                $url = "data/indeximages/banner";
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
            $data = array();
            $data['title']          =   $_POST['title'];
            if (!empty($_POST['cate_id']))
            {
                $data['cate_id']        =   $_POST['cate_id'];
            }
            $data['link']           =   $_POST['link'] == 'http://' ? '' : $_POST['link'];
            $data['if_show']        =   $_POST['if_show'];
            $data['sort_order']     =   $_POST['sort_order'];
            $data['content']        =   $_POST['content'];
            if(!empty($img)){
                $data['banner'] = $img;
            }

            $rows=$this->_article_mod->edit($article_id, $data);
            if ($this->_article_mod->has_error())
            {
                $this->show_warning($this->_article_mod->get_error());

                return;
            }

            $this->show_message('edit_article_successed',
                'back_list',        'index.php?app=article',
                'edit_again',    'index.php?app=article&amp;act=edit&amp;id=' . $article_id);
        }
    }

    //异步修改数据
   function ajax_col()
   {
       $id     = empty($_GET['id']) ? 0 : intval($_GET['id']);
       $column = empty($_GET['column']) ? '' : trim($_GET['column']);
       $value  = isset($_GET['value']) ? trim($_GET['value']) : '';
       $data   = array();

       if (in_array($column ,array('if_show', 'sort_order')))
       {
           $data[$column] = $value;
           $this->_article_mod->edit($id, $data);
           if(!$this->_article_mod->has_error())
           {
               echo ecm_json_encode(true);
           }
       }
       else
       {
           return ;
       }
       return ;
   }
    function drop()
    {
        $article_ids = isset($_GET['id']) ? trim($_GET['id']) : '';
        if (!$article_ids)
        {
            $this->show_warning('no_such_article');

            return;
        }
        $article_ids=explode(',', $article_ids);
        $message = 'drop_ok';
        foreach ($article_ids as $key=>$article_id){
            $article=$this->_article_mod->find(intval($article_id));
            $article=current($article);
            if($article['code']!=null)
            {
                unset($article_ids[$key]);  //有部分是系统文章 过滤掉
                $message = 'drop_ok_system_article';
            }
            else
            {

            }
        }
        if (!$article_ids)
        {
            $message = 'system_article'; //全是系统文章
            $this->show_warning($message);

            return;
        }
        if (!$this->_article_mod->drop($article_ids))    //删除
        {
            $this->show_warning($this->_article_mod->get_error());

            return;
        }

        $this->show_message($message);
    }

    /* 更新排序 */
    function update_order()
    {
        if (empty($_GET['id']))
        {
            $this->show_warning('Hacking Attempt');
            return;
        }

        $ids = explode(',', $_GET['id']);
        $sort_orders = explode(',', $_GET['sort_order']);
        foreach ($ids as $key => $id)
        {
            $this->_article_mod->edit($id, array('sort_order' => $sort_orders[$key]));
        }

        $this->show_message('update_order_ok');
    }

        /* 构造并返回树 */
    function &_tree($acategories)
    {
        import('tree.lib');
        $tree = new Tree();
        $tree->setTree($acategories, 'cate_id', 'parent_id', 'cate_name');
        return $tree;
    }
        /* 取得可以作为上级的文章分类数据 */
    function _get_options()
    {
        $mod_acategory = &m('acategory');
        $acategorys = $mod_acategory->get_list();
        $tree =& $this->_tree($acategorys);
        return $tree->getOptions();
    }

    /* 异步删除附件 */
    function drop_uploadedfile()
    {
        $file_id = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;
        if ($file_id && $this->_uploadedfile_mod->drop($file_id))
        {
            $this->json_result('drop_ok');
            return;
        }
        else
        {
            $this->json_error('drop_error');
            return;
        }
    }
}

?>