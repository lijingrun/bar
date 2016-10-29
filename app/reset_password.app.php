<?php
/**
 * Created by PhpStorm.
 * User: lijingrun
 * Date: 2016/8/2
 * Time: 14:56
 * 客户找回密码控制器
 */
//include 'CCPRestSmsSDK.php';
//主帐号,对应开官网发者主账号下的 ACCOUNT SID
//$accountSid = '8a48b5514f73ea32014f87f34700281b';

//主帐号令牌,对应官网开发者主账号下的 AUTH TOKEN
//$accountToken = '0f1b1347b19141bc8ccf20de6da3ad51 ';

//应用Id，在官网应用列表中点击应用，对应应用详情中的APP ID
//在开发调试的时候，可以使用官网自动为您分配的测试Demo的APP ID
//$appId = '8a48b5514f73ea32014f87f68dd62838';

//请求地址
//沙盒环境（用于应用开发调试）：sandboxapp.cloopen.com
//生产环境（用户应用上线使用）：app.cloopen.com
//$serverIP = 'sandboxapp.cloopen.com';


//请求端口，生产环境和沙盒环境一致
//$serverPort = '8883';

//REST版本号，在官网文档REST介绍中获得。
//$softVersion = '2013-12-26';

class Reset_passwordApp extends MallbaseApp
{


    public function index()
    {

        $this->display('reset_password.html');
    }

    //获取短信息
    public function get_mes()
    {
        include_once(ROOT_PATH . '/includes/CCPRestSmsSDK.php');
        if (IS_POST) {
            $tel = $_POST['tel'];
            if (empty($tel)) {
                echo '电话号码无效';
                exit;
            }
            $user_name = $_POST['user_name'];
            $store_model = &m('store');
            $store = $store_model->get("tel =" . $tel);
            $member_model = & m('member');
            $member = $member_model->get("user_name like '".$user_name."'");
            if($member['user_id'] != $store['store_id']){
                echo "填写电话与主持号码不一致！";
                exit;
            }
            if (empty($store)) {
                echo '注册电话不存在，请确认';
                exit;
            }
            //主帐号,对应开官网发者主账号下的 ACCOUNT SID
            $accountSid = '8a48b5514f73ea32014f87f34700281b';
            //主帐号令牌,对应官网开发者主账号下的 AUTH TOKEN
            $accountToken = '0f1b1347b19141bc8ccf20de6da3ad51';
            //应用Id，在官网应用列表中点击应用，对应应用详情中的APP ID
            //在开发调试的时候，可以使用官网自动为您分配的测试Demo的APP ID
            $appId = '8a48b5514f73ea32014f87f68dd62838';
            //请求地址
            //沙盒环境（用于应用开发调试）：sandboxapp.cloopen.com
            //生产环境（用户应用上线使用）：app.cloopen.com
            $serverIP = 'app.cloopen.com';
            //请求端口，生产环境和沙盒环境一致
            $serverPort = '8883';
            //REST版本号，在官网文档REST介绍中获得。
            $softVersion = '2013-12-26';
//            global $accountSid, $accountToken, $appId, $serverIP, $serverPort, $softVersion;
            $rest = new REST($serverIP, $serverPort, $softVersion);
            $rest->setAccount($accountSid, $accountToken);
            $rest->setAppId($appId);

            $to = $tel;
            $msg = rand(1000, 9999);
            $data = array($msg, '5');
            $rest_model = & m('reset_password');

            $rest_model->drop('store_id =' . $store['store_id']);
            $new_reset = array(
                'msg' => $msg,
                'store_id' => $store['store_id'],
            );
            $rest_model->add($new_reset);
            $tempId = 107524;
            // 发送模板短信
            // echo "Sending TemplateSMS to $to <br/>";
            $result = $rest->sendTemplateSMS($to, $data, $tempId);
            if ($result == NULL) {
    //                echo "result error!";
    //                break;
                $return_message['error_msg'] = '没有返回信息，请重试！';
            }
            if ($result->statusCode != 0) {
                echo "error code :" . $result->statusCode . "<br>";
                echo "error msg :" . $result->statusMsg . "<br>";
                //TODO 添加错误处理逻辑
            } else {
                echo "短信发送成功，请注意接收！";
                // 获取返回信息
//                $smsmessage = $result->TemplateSMS;
//                echo "dateCreated:" . $smsmessage->dateCreated . "<br/>";
//                echo "smsMessageSid:" . $smsmessage->smsMessageSid . "<br/>";
                //TODO 添加成功处理逻辑
            }
            $return_message = json_encode($return_message);
            return $return_message;
        }
    }

    //确定修改密码
    public function sure_to_reset(){
        if(IS_POST){
            $code = $_POST['code'];
            $new_password = $_POST['new_password'];
            $tel = $_POST['tel'];
            $user_name = $_POST['user_name'];
            if(empty($code) || empty($new_password) || empty($tel)){
                echo 222; //提交数据错误
                exit;
            }else{
                $store_model = & m('store');
                $store = $store_model->get("tel =".$tel);
                if(empty($store)){
                    echo 333; //手机号码不存在
                    exit;
                }else{
                    $member_model = & m('member');
                    $member = $member_model->get("user_id =".$store['store_id']." AND user_name = '".$user_name."'");
                    if(empty($member)){
                        echo 444; //客户名不存在
                        exit;
                    }
                    $rest_model = & m('reset_password');
                    $reset = $rest_model->get("store_id =".$store['store_id']." AND msg =".$code);
                    if(!empty($reset)){
                        $member['password'] = md5($new_password);
                        $member_model->edit($store['store_id'], $member);
                        echo 111;exit;
                    }
                }
            }
        }
    }

}