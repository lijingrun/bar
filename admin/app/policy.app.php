<?php
/**
 * Created by PhpStorm.
 * User: lijingrun
 * Date: 2016/8/20
 * Time: 9:41
 */
class PolicyApp extends BackendApp {

    public function Index(){
        $policy_type_model = &m("policy_type");
        if(!IS_POST) {
            $policy_types = $policy_type_model->findAll();
            $this->assign('policy_types', $policy_types);

            $this->display("policy.html");
        }else{
            $new_policy = array(
                'coe' => $_POST['coe'],
                'name' => $_POST['name'],
                'give' => $_POST['give'],
                'content' => $_POST['content'],
            );
            $policy_type_model->add($new_policy);
            $this->show_message('保存成功！','点击返回','index.php?app=policy');
        }
    }

}