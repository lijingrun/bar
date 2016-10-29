<?php

/**
 *    订单规则管理控制器
 *
 *    @author    Hyber
 *    @usage    none
 */
class Order_ruleApp extends BackendApp
{
    var $_order_rule_mod;

    function __construct()
    {
        $this->Order_ruleApp();
    }

    function Order_ruleApp()
    {
        parent::BackendApp();

        $this->_order_rule_mod =& m('order_rule');
    }

    /**
     *    文章索引
     *
     *    @author    Hyber
     *    @return    void
     */
    function index()
    {

        $conditions='';
        $page   =   $this->_get_page(10);   //获取分页信息
        $rules = $this->_order_rule_mod->find(array(
        'fields'   => 'order_rule.*',
        //'conditions'  => $conditions,
        'limit'   => $page['limit'],
        //'join'    => 'belongs_to_acategory',
        'count'   => true   //允许统计
        ));    //找出所有的
        $page['item_count']=$this->_order_rule_mod->getCount();   //获取统计数据
        $this->_format_page($page);
        $this->import_resource(array('script' => 'inline_edit.js'));
        //$this->assign('filtered', $conditions? 1 : 0); //是否有查询条件
        $this->assign('page_info', $page);   //将分页信息传递给视图，用于形成分页条
        $this->assign('rules', $rules);
        $this->display('order_rule.index.html');
    }

     /**
     *    Append new rule
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
            
            $this->display('order_rule.form.html');
        }
        else
        {
            $data = array();
            $data['name']      =   $_POST['name'];
            $data['arguments'] =   $_POST['arguments'];
            $data['item_cycle'] =  $_POST['item_cycle'];
            $data['condition'] =   $_POST['condition'];
            $data['operation'] =   $_POST['operation'];
			$data['priority']  =   intval($_POST['priority']);

            if (!$order_rule_id = $this->_order_rule_mod->add($data))
            {
                $this->show_warning($this->_order_rule_mod->get_error());

                return;
            }

            $this->show_message('add_rule_successed',
                'back_list',    'index.php?app=order_rule',
                'continue_add', 'index.php?app=order_rule&amp;act=add'
            );
        }
    }

     /**
     *    Edit a rule
     *
     *    @author    Hyber
     *    @return    void
     */
    function edit()
    {

		$rule_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
		if (!$rule_id )
		{
			$this->show_warning('no_such_rule');
			return;
		}

        if (!IS_POST)
        {
			$this->assign("id", $rule_id);
            $find_data     = $this->_order_rule_mod->find($rule_id);
            $rules	=   current($find_data);
            $template_name = $this->_get_template_name();
            $style_name    = $this->_get_style_name();
            $this->assign('build_editor', $this->_build_editor(array(
                'name' => 'content',
                'content_css' => SITE_URL . "/themes/mall/{$template_name}/styles/{$style_name}/css/ecmall.css"
            )));
            $this->assign('order_rule', $rules);
            
            $this->display('order_rule.form.html');
        }
        else
        {
            $data = array();
            $data['name']      =   $_POST['name'];
            $data['arguments'] =   $_POST['arguments'];
            $data['item_cycle'] =  $_POST['item_cycle'];
            $data['condition'] =   $_POST['condition'];
            $data['operation'] =   $_POST['operation'];
			$data['priority']  =   intval($_POST['priority']);

            if (!$order_rule_id = $this->_order_rule_mod->edit($rule_id, $data))
            {
                $this->show_warning($this->_order_rule_mod->get_error());
                return;
            }

            $this->show_message('edit_rule_successed',
                'back_list',    'index.php?app=order_rule',
                'continue_add', 'index.php?app=order_rule&amp;act=add'
            );
        }
    }

    function drop()
    {
        $order_rule_ids = isset($_GET['id']) ? trim($_GET['id']) : '';
        if (!$order_rule_ids)
        {
            $this->show_warning('no_such_rule');

            return;
        }
        $order_rule_ids=explode(',', $order_rule_ids);
        $message = 'drop_ok';
        foreach ($order_rule_ids as $key=>$order_rule_id){
            $order_rule=$this->_order_rule_mod->find(intval($order_rule_id));
            $order_rule=current($order_rule);
        }
        if (!$this->_order_rule_mod->drop($order_rule_ids))    //删除
        {
            $this->show_warning($this->_order_rule_mod->get_error());

            return;
        }

        $this->show_message($message);
    }
}

?>
