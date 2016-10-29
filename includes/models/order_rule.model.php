<?php

class Order_ruleModel extends BaseModel
{
    var $table  = 'order_rule';
    var $prikey = 'id';
    var $_name  = 'order_rule';

    /* 添加编辑时自动验证 */
    var $_autov = array(
        'name' => array(
            'required'  => true,    //必填
            'min'       => 1,       //最短1个字符
            'max'       => 255,     //最长100个字符
            'filter'    => 'trim',
        ),
		/*
		'condition'  => array(
            'required'  => true,    //必填
        ),
        'operation'  => array(
            'required'  => true,    //必填
		),
		 */
        'priority'  => array(
            'filter'    => 'intval',
            'required'  => true,    //必填
        ),
    );
}

?>
