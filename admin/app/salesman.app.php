<?php

/**
 *    业务员管理控制器
 *
 *    @author    Hyber
 *    @usage    none
 */
class SalesmanApp extends BackendApp
{
    var $_salesman_mod;

    function __construct()
    {
        $this->SalesmanApp();
    }

    function SalesmanApp()
    {
        parent::BackendApp();

        $this->_salesman_mod =& m('salesman');
    }

    /**
     *    @author    Hyber
     *    @return    void
     */
    function index()
    {
        $conditions='';
        $page   =   $this->_get_page(10);   //获取分页信息
        $salesmen = $this->_salesman_mod->find(array(
        'fields'   => 'salesman.*',
        //'conditions'  => $conditions,
        'limit'   => $page['limit'],
        //'join'    => 'belongs_to_acategory',
        'count'   => true   //允许统计
        ));    //找出所有的
        $page['item_count']=$this->_salesman_mod->getCount();   //获取统计数据
        $this->_format_page($page);
        $this->import_resource(array('script' => 'inline_edit.js'));
        //$this->assign('filtered', $conditions? 1 : 0); //是否有查询条件
        $this->assign('page_info', $page);   //将分页信息传递给视图，用于形成分页条
        $this->assign('salesmen', $salesmen);
        $this->display('salesman.index.html');
    }

     /**
     *    Append new saleman
     *
     *    @author    Hyber
     *    @return    void
     */
    function add()
    {
        if (!IS_POST)
        {
            $this->assign("id", 0);
            
            $template_name = $this->_get_template_name();
            $style_name    = $this->_get_style_name();
            $this->assign('build_editor', $this->_build_editor(array(
                'name' => 'content',
                'content_css' => SITE_URL . "/themes/mall/{$template_name}/styles/{$style_name}/css/ecmall.css"
            )));
            
            $this->display('salesman.form.html');
        }
        else
        {
            $data = array();
            $data['name']   =   $_POST['name'];
            $data['serial']	=   $_POST['serial'];

            if (!$salesman_id = $this->_salesman_mod->add($data))
            {
                $this->show_warning($this->_salesman_mod->get_error());

                return;
            }

            $this->show_message('add_salesman_successed',
                'back_list',    'index.php?app=salesman',
                'continue_add', 'index.php?app=salesman&amp;act=add'
            );
        }
    }

     /**
     *    Edit a salesman
     *
     *    @author    Hyber
     *    @return    void
     */
    function edit()
    {

		$salesman_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
		if (!$salesman_id )
		{
			$this->show_warning('no_such_salesman');
			return;
		}

        if (!IS_POST)
        {
			$this->assign("id", $salesman_id);
            $find_data     = $this->_salesman_mod->find($salesman_id);
            $saleman	=   current($find_data);
            $template_name = $this->_get_template_name();
            $style_name    = $this->_get_style_name();
            $this->assign('build_editor', $this->_build_editor(array(
                'name' => 'content',
                'content_css' => SITE_URL . "/themes/mall/{$template_name}/styles/{$style_name}/css/ecmall.css"
            )));
            $this->assign('saleman', $saleman);
            
            $this->display('salesman.form.html');
        }
        else
        {
            $data = array();
            $data['name']   =   $_POST['name'];
            $data['serial']	=   $_POST['serial'];

            if (!$salesman_id = $this->_salesman_mod->edit($salesman_id, $data))
            {
                $this->show_warning($this->_salesman_mod->get_error());
                return;
            }

            $this->show_message('edit_salesman_successed',
                'back_list',    'index.php?app=salesman',
                'continue_add', 'index.php?app=salesman&amp;act=add'
            );
        }
    }

    function drop()
	{ 
        $salesman_ids = isset($_GET['id']) ? trim($_GET['id']) : '';
        if (!$salesman_ids)
        {
            $this->show_warning('no_such_salesman');

            return;
        }
        $salesman_ids=explode(',', $salesman_ids);
        $message = 'drop_ok';
        foreach ($salesman_ids as $key=>$salesman_id){
            $salesman=$this->_salesman_mod->find(intval($salesman_id));
            $salesman=current($salesman);
        }
        if (!$this->_salesman_mod->drop($salesman_ids))    //删除
        {
            $this->show_warning($this->_salesman_mod->get_error());

            return;
        }

        $this->show_message($message);
    }
}

?>
