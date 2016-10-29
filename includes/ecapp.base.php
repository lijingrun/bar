<?php

define('IMAGE_FILE_TYPE', 'gif|jpg|jpeg|png'); // 图片类型，上传图片时使用

define('SIZE_GOODS_IMAGE', '2097152');   // 商品大小限制2M
define('SIZE_STORE_LOGO', '1048576');      // 店铺LOGO大小限制1M
define('SIZE_STORE_BANNER', '1048576');  // 店铺BANNER大小限制1M
define('SIZE_STORE_CERT', '409600');     // 店铺证件执照大小限制400K
define('SIZE_STORE_PARTNER', '102400');  // 店铺合作伙伴图片大小限制100K
define('SIZE_CSV_TAOBAO', '2097152');     // 淘宝助理CSV大小限制2M

/* 店铺状态 */
define('STORE_APPLYING', 0); // 申请中
define('STORE_OPEN', 1); // 开启
define('STORE_CLOSED', 2); // 关闭

/* 订单状态 */
define('ORDER_SUBMITTED', 10);                 // 针对货到付款而言，他的下一个状态是卖家已发货
define('ORDER_PENDING', 11);                   // 等待买家付款
define('ORDER_ACCEPTED', 20);                  // 买家已付款，等待卖家发货
define('ORDER_SHIPPED', 30);                   // 卖家已发货
define('ORDER_FINISHED', 40);                  // 交易成功
define('ORDER_CANCELED', 0);                   // 交易已取消

/* 特殊文章分类ID */
define('STORE_NAV', -1); // 店铺导航
define('ACATE_HELP', 1); // 商城帮助
define('ACATE_NOTICE', 2); // 商城快讯（公告）
define('ACATE_SYSTEM', 3); // 内置文章

/* 系统文章分类code字段 */
define('ACC_NOTICE', 'notice');                 //acategory表中code字段为notice时——商城公告类别
define('ACC_SYSTEM', 'system');                 //acategory表中code字段为system时——内置文章类别
define('ACC_HELP', 'help');                     //acategory表中code字段为help时——商城帮助类别

/* 邮件的优先级 */
define('MAIL_PRIORITY_LOW', 1);
define('MAIL_PRIORITY_MID', 2);
define('MAIL_PRIORITY_HIGH', 3);

/* 发送邮件的协议类型 */
define('MAIL_PROTOCOL_LOCAL', 0, true);
define('MAIL_PROTOCOL_SMTP', 1, true);

/* 数据调用的类型 */
define('TYPE_GOODS', 1);

/* 上传文件归属 */
define('BELONG_ARTICLE', 1);
define('BELONG_GOODS', 2);
define('BELONG_STORE', 3);

/* 二级域名开关 */
!defined('ENABLED_SUBDOMAIN') && define('ENABLED_SUBDOMAIN', 0);

/* 环境 */
define('CHARSET', substr(LANG, 3));
define('IS_AJAX', isset($_REQUEST['ajax']));
/* 短消息的标志 */
define('MSG_SYSTEM', 0); //系统消息

/* 团购活动状态 */
define('GROUP_PENDING', 0);            // 未发布
define('GROUP_ON', 1);            // 正在进行
define('GROUP_END', 2);            // 已结束
define('GROUP_FINISHED', 3);            // 已完成
define('GROUP_CANCELED', 4);            // 已取消

define('GROUP_CANCEL_INTERVAL', 5);     // 团购结束后自动取消的间隔天数

/* 通知类型 */
define('NOTICE_MAIL', 1); // 邮件通知
define('NOTICE_MSG', 2); // 站内短消息

/**
 *    ECBaseApp
 *
 *    @author    Garbin
 *    @usage    none
 */
class ECBaseApp extends BaseApp {

    var $outcall;

    function __construct() {
        $this->ECBaseApp();
    }

    function ECBaseApp() {
        parent::__construct();

        if (!defined('MODULE')) { // 临时处理方案，此处不应对模块进行特殊处理
            /* GZIP */
            if ($this->gzip_enabled()) {
                ob_start('ob_gzhandler');
            } else {
                ob_start();
            }

            /* 非utf8转码 */
            if (CHARSET != 'utf-8' && isset($_REQUEST['ajax'])) {
                $_FILES = ecm_iconv_deep('utf-8', CHARSET, $_FILES);
                $_GET = ecm_iconv_deep('utf-8', CHARSET, $_GET);
                $_POST = ecm_iconv_deep('utf-8', CHARSET, $_POST);
            }

            /* 载入配置项 */
            $setting = & af('settings');
            Conf::load($setting->getAll());

            /* 初始化访问者(放在此可能产生问题) */
            $this->_init_visitor();

            /* 计划任务守护进程 */
            $this->_run_cron();
        }
    }

    function _init_visitor() {
        
    }

    /**
     *    初始化Session
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function _init_session() {
        import('session.lib');
        if (!defined('SESSION_TYPE')) {
            define('SESSION_TYPE', 'mysql');
        }
        if (SESSION_TYPE == 'mysql' || defined('IN_BACKEND')) {
            $this->_session = & new SessionProcessor(db(), '`ecm_sessions`', '`ecm_sessions_data`', 'ECM_ID');
            /* 清理超时的购物车项目 */
            $this->_session->add_related_table('`ecm_cart`', 'cart', 'session_id', 'user_id=0');
        } else if (SESSION_TYPE == 'memcached') {
            $this->_session = & new MemcacheSession(SESSION_MEMCACHED, 'ECM_ID');
        } else {
            exit('Unkown session type.');
        }
        define('SESS_ID', $this->_session->get_session_id());

        $this->_session->my_session_start();
        env('session', $this->_session);
    }

    function _config_view() {
        $this->_view->caching = ((DEBUG_MODE & 1) == 0);  // 是否缓存
        $this->_view->force_compile = ((DEBUG_MODE & 2) == 2);  // 是否需要强制编译
        $this->_view->direct_output = ((DEBUG_MODE & 4) == 4);  // 是否直接输出
        $this->_view->gzip = (defined('ENABLED_GZIP') && ENABLED_GZIP === 1);
        $this->_view->lib_base = site_url() . '/includes/libraries/javascript';
    }

    /**
     *    转发至模块
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function do_action($action) {
        /* 指定了要运行的模块则调用模块控制器 */
        (!empty($_GET['module']) && !defined('MODULE')) && $action = 'run_module';
        parent::do_action($action);
    }

    function _run_action() {
        /*
          if (!$this->visitor->i_can('do_action'))
          {
          if (!$this->visitor->has_login)
          {
          $this->login();
          }
          else
          {
          $this->show_warning($this->visitor->get_error());
          }

          return;
          }
         */
        if ($this->_hook('on_run_action')) {
            return;
        }
        parent::_run_action();

        if ($this->_hook('end_run_action')) {
            return;
        }
    }

    function run_module() {
        $module_name = empty($_REQUEST['module']) ? false : strtolower(preg_replace('/(\W+)/', '', $_REQUEST['module']));
        if (!$module_name) {
            $this->show_warning('no_such_module');

            return;
        }
        $file = defined('IN_BACKEND') ? 'admin' : 'index';
        $module_class_file = ROOT_PATH . '/external/modules/' . $module_name . '/' . $file . '.module.php';
        require(ROOT_PATH . '/includes/module.base.php');
        require($module_class_file);
        define('MODULE', $module_name);
        $module_class_name = ucfirst($module_name) . 'Module';

        /* 判断模块是否启用 */
        $model_module = & m('module');
        $find_data = $model_module->find('index:' . $module_name);
        if (empty($find_data)) {
            /* 没有安装 */
            $this->show_warning('no_such_module');

            return;
        }
        $info = current($find_data);
        if (!$info['enabled']) {
            /* 尚未启用 */
            $this->show_warning('module_disabled');

            return;
        }

        /* 加载模块配置 */
        Conf::load(array($module_name . '_config' => unserialize($info['module_config'])));

        /* 运行模块 */
        $module = new $module_class_name();
        c($module);
        $module->do_action(ACT);
        $module->destruct();
    }

    function login() {
        $this->display('login.html');
    }

    function logout() {
        $this->visitor->logout();
    }

    function jslang($lang) {
        header('Content-Encoding:' . CHARSET);
        header("Content-Type: application/x-javascript\n");
        header("Expires: " . date(DATE_RFC822, strtotime("+1 hour")) . "\n");
        if (!$lang) {
            echo 'var lang = null;';
        } else {
            echo 'var lang = ' . ecm_json_encode($lang) . ';';
            echo <<<EOT
lang.get = function(key){
    eval('var langKey = lang.' + key);
    if(typeof(langKey) == 'undefined'){
        return key;
    }else{
        return langKey;
    }
}
EOT;
        }
    }

    /**
     *    插件
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function _hook($event, $data = array()) {
        if ($this->outcall) {
            return;
        }
        static $plugins = null;
        $conf_file = ROOT_PATH . '/data/plugins.inc.php';
        if ($plugins === null) {
            is_file($conf_file) && $plugins = include($conf_file);
            if (!is_array($plugins)) {
                $plugins = false;
            }
        }
        if (!isset($plugins[$event])) {
            return null;
        }

        /* 获取可用插件列表 */
        $plugin_list = $plugins[$event];
        if (empty($plugin_list)) {
            return null;
        }
        foreach ($plugin_list as $plugin_name => $plugin_info) {
            $plugin_main_file = ROOT_PATH . "/external/plugins/{$plugin_name}/main.plugin.php";
            if (is_file($plugin_main_file)) {
                include_once($plugin_main_file);
            }
            $plugin_class_name = ucfirst($plugin_name) . 'Plugin';
            $plugin = new $plugin_class_name($data, $plugin_info);
            $this->outcall = true;

            /* 返回一个结果，若要停止当前控制器流程则会返回true */
            $stop_flow = $this->_run_plugin($plugin);
            $plugin = null;
            $this->outcall = false;
            /* 停止原控制器流程 */
            if ($stop_flow) {
                return $stop_flow;
            }
        }
    }

    /**
     *    运行插件
     *
     *    @author    Garbin
     *    @param     Plugin $plugin
     *    @return    void
     */
    function _run_plugin(&$plugin) {
        return $plugin->execute();
    }

    /**
     *    head标签内的内容
     *
     *    @author    Garbin
     *    @param     string $contents
     *    @return    void
     */
    function headtag($string) {
        $this->_init_view();
        $this->assign('_head_tags', $this->_view->fetch('str:' . $string));
    }

    /**
     *    导入资源到模板
     *
     *    @author    Garbin
     *    @param     mixed $resources
     *    @return    string
     */
    function import_resource($resources, $spec_type = null) {
        $headtag = '';
        if (is_string($resources) || $spec_type) {
            !$spec_type && $spec_type = 'script';
            $resources = $this->_get_resource_data($resources);
            foreach ($resources as $params) {
                $headtag .= $this->_get_resource_code($spec_type, $params) . "\r\n";
            }
            $this->headtag($headtag);
        } elseif (is_array($resources)) {
            foreach ($resources as $type => $res) {
                $headtag .= $this->import_resource($res, $type);
            }
            $this->headtag($headtag);
        }

        return $headtag;
    }

    /**
     * 配置seo信息
     *
     * @param array/string $seo_info
     * @return void
     */
    function _config_seo($seo_info, $ext_info = null) {
        if (is_string($seo_info)) {
            $this->_assign_seo($seo_info, $ext_info);
        } elseif (is_array($seo_info)) {
            foreach ($seo_info as $type => $info) {
                $this->_assign_seo($type, $info);
            }
        }
    }

    function _assign_seo($type, $info) {
        $this->_init_view();
        $_seo_info = $this->_view->get_template_vars('_seo_info');
        if (is_array($_seo_info)) {
            $_seo_info[$type] = $info;
        } else {
            $_seo_info = array($type => $info);
        }
        $this->assign('_seo_info', $_seo_info);
        $this->assign('page_seo', $this->_get_seo_code($_seo_info));
    }

    function _get_seo_code($_seo_info) {
        $html = '';
        foreach ($_seo_info as $type => $info) {
            $info = trim(htmlspecialchars($info));
            switch ($type) {
                case 'title' :
                    $html .= "<{$type}>{$info}</{$type}>";
                    break;
                case 'description' :
                case 'keywords' :
                default :
                    $html .= "<meta name=\"{$type}\" content=\"{$info}\" />";
                    break;
            }
            $html .= "\r\n";
        }
        return $html;
    }

    /**
     *    获取资源数据
     *
     *    @author    Garbin
     *    @param     mixed $resources
     *    @return    array
     */
    function _get_resource_data($resources) {
        $return = array();
        if (is_string($resources)) {
            $items = explode(',', $resources);
            array_walk($items, create_function('&$val, $key', '$val = trim($val);'));
            foreach ($items as $path) {
                $return[] = array('path' => $path, 'attr' => '');
            }
        } elseif (is_array($resources)) {
            foreach ($resources as $item) {
                !isset($item['attr']) && $item['attr'] = '';
                $return[] = $item;
            }
        }

        return $return;
    }

    /**
     *    获取资源文件的HTML代码
     *
     *    @author    Garbin
     *    @param     string $type
     *    @param     array  $params
     *    @return    string
     */
    function _get_resource_code($type, $params) {
        switch ($type) {
            case 'script':
                $pre = '<script charset="utf-8" type="text/javascript"';
                $path = ' src="' . $this->_get_resource_url($params['path']) . '"';
                $attr = ' ' . $params['attr'];
                $tail = '></script>';
                break;
            case 'style':
                $pre = '<link rel="stylesheet" type="text/css"';
                $path = ' href="' . $this->_get_resource_url($params['path']) . '"';
                $attr = ' ' . $params['attr'];
                $tail = ' />';
                break;
        }
        $html = $pre . $path . $attr . $tail;

        return $html;
    }

    /**
     *    获取真实的资源路径
     *
     *    @author    Garbin
     *    @param     string $res
     *    @return    void
     */
    function _get_resource_url($res) {
        $res_par = explode(':', $res);
        $url_type = $res_par[0];
        $return = '';
        switch ($url_type) {
            case 'url':
                $return = $res_par[1];
                break;
            case 'res':
                $return = '{res file="' . $res_par[1] . '"}';
                break;
            default:
                $res_path = empty($res_par[1]) ? $res : $res_par[1];
                $return = '{lib file="' . $res_path . '"}';
                break;
        }

        return $return;
    }

    function display($f) {
        if ($this->_hook('on_display', array('display_file' => & $f))) {
            return;
        }
        $this->assign('site_url', SITE_URL);
        $this->assign('real_site_url', defined('IN_BACKEND') ? dirname(site_url()) : site_url());
        $this->assign('ecmall_version', VERSION);
        $this->assign('random_number', rand());

        /* 语言项 */
        $this->assign('lang', Lang::get());

        /* 用户信息 */
        $this->assign('visitor', isset($this->visitor) ? $this->visitor->info : array());


        $this->assign('charset', CHARSET);
        $this->assign('price_format', Conf::get('price_format'));
        $this->assign('async_sendmail', $this->_async_sendmail());
        $this->_assign_query_info();

        parent::display($f);

        if ($this->_hook('end_display', array('display_file' => & $f))) {
            return;
        }
    }

    /* 页面查询信息 */

    function _assign_query_info() {
        $query_time = ecm_microtime() - START_TIME;

        $this->assign('query_time', $query_time);
        $db = & db();
        $this->assign('query_count', $db->_query_count);
        $this->assign('query_user_count', $this->_session->get_users_count());

        /* 内存占用情况 */
        if (function_exists('memory_get_usage')) {
            $this->assign('memory_info', memory_get_usage() / 1048576);
        }

        $this->assign('gzip_enabled', $this->gzip_enabled());
        $this->assign('site_domain', urlencode(get_domain()));
        $this->assign('ecm_version', VERSION . ' ' . RELEASE);
    }

    function gzip_enabled() {
        static $enabled_gzip = NULL;

        if ($enabled_gzip === NULL) {
            $enabled_gzip = (defined('ENABLED_GZIP') && ENABLED_GZIP === 1 && function_exists('ob_gzhandler'));
        }

        return $enabled_gzip;
    }

    /**
     *    显示错误警告
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function show_warning() {
        $args = func_get_args();
        call_user_func_array('show_warning', $args);
    }

    /**
     *    显示提示消息
     *
     *    @author    Garbin
     *    @return    void
     */
    function show_message() {
        $args = func_get_args();
        call_user_func_array('show_message', $args);
    }

    /**
     * Make a error message by JSON format
     *
     * @param   string  $msg
     *
     * @return  void
     */
    function json_error($msg = '', $retval = null, $jqremote = false) {
        if (!empty($msg)) {
            $msg = Lang::get($msg);
        }
        $result = array('done' => false, 'msg' => $msg);
        if (isset($retval))
            $result['retval'] = $retval;

        $this->json_header();
        $json = ecm_json_encode($result);
        if ($jqremote === false) {
            $jqremote = isset($_GET['jsoncallback']) ? trim($_GET['jsoncallback']) : false;
        }
        if ($jqremote) {
            $json = $jqremote . '(' . $json . ')';
        }

        echo $json;
    }

    /**
     * Make a successfully message
     *
     * @param   mixed   $retval
     * @param   string  $msg
     *
     * @return  void
     */
    function json_result($retval = '', $msg = '', $jqremote = false) {
        if (!empty($msg)) {
            $msg = Lang::get($msg);
        }
        $this->json_header();
        $json = ecm_json_encode(array('done' => true, 'msg' => $msg, 'retval' => $retval));
        if ($jqremote === false) {
            $jqremote = isset($_GET['jsoncallback']) ? trim($_GET['jsoncallback']) : false;
        }
        if ($jqremote) {
            $json = $jqremote . '(' . $json . ')';
        }

        echo $json;
    }

    /**
     * Send a Header
     *
     * @author weberliu
     *
     * @return  void
     */
    function json_header() {
        header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
        header("Content-type:text/plain;charset=" . CHARSET, true);
    }

    /**
     *    验证码
     *
     *    @author    Garbin
     *    @return    void
     */
    function _captcha($width, $height) {
        import('captcha.lib');
        $word = generate_code();
        $_SESSION['captcha'] = base64_encode($word);
        $code = new Captcha(array(
            'width' => $width,
            'height' => $height,
        ));
        $code->display($word);
    }

    /**
     *    获取分页信息
     *
     *    @author    Garbin
     *    @return    array
     */
    function _get_page($page_per = 10) {
        $page = empty($_REQUEST['page']) ? 1 : intval($_REQUEST['page']);
        $start = ($page - 1) * $page_per;

        return array('limit' => "{$start},{$page_per}", 'curr_page' => $page, 'pageper' => $page_per);
    }

    /**
     * 格式化分页信息
     * @param   array   $page
     * @param   int     $num    显示几页的链接
     */
    function _format_page(&$page, $num = 7) {
        $page['page_count'] = ceil($page['item_count'] / $page['pageper']);
        $mid = ceil($num / 2) - 1;
        if ($page['page_count'] <= $num) {
            $from = 1;
            $to = $page['page_count'];
        } else {
            $from = $page['curr_page'] <= $mid ? 1 : $page['curr_page'] - $mid + 1;
            $to = $from + $num - 1;
            $to > $page['page_count'] && $to = $page['page_count'];
        }

        /*
          if (preg_match('/[&|\?]?page=\w+/i', $_SERVER['REQUEST_URI']) > 0)
          {
          $url_format = preg_replace('/[&|\?]?page=\w+/i', '', $_SERVER['REQUEST_URI']);
          }
          else
          {
          $url_format = $_SERVER['REQUEST_URI'];
          }
         */

        /* 生成app=goods&act=view之类的URL */
        if (preg_match('/[&|\?]?page=\w+/i', $_SERVER['QUERY_STRING']) > 0) {
            $url_format = preg_replace('/[&|\?]?page=\w+/i', '', $_SERVER['QUERY_STRING']);
        } else {
            $url_format = $_SERVER['QUERY_STRING'];
        }

        $page['page_links'] = array();
        $page['first_link'] = ''; // 首页链接        
        $page['first_suspen'] = ''; // 首页省略号
        $page['last_link'] = ''; // 尾页链接
        $page['last_suspen'] = ''; // 尾页省略号
        for ($i = $from; $i <= $to; $i++) {
            $page['page_links'][$i] = url("{$url_format}&page={$i}");
        }
        if (($page['curr_page'] - $from) < ($page['curr_page'] - 1) && $page['page_count'] > $num) {
            $page['first_link'] = url("{$url_format}&page=1");
            if (($page['curr_page'] - 1) - ($page['curr_page'] - $from) != 1) {
                $page['first_suspen'] = '..';
            }
        }
        if (($to - $page['curr_page']) < ($page['page_count'] - $page['curr_page']) && $page['page_count'] > $num) {
            $page['last_link'] = url("{$url_format}&page=" . $page['page_count']);
            if (($page['page_count'] - $page['curr_page']) - ($to - $page['curr_page']) != 1) {
                $page['last_suspen'] = '..';
            }
        }

        $page['prev_link'] = $page['curr_page'] > $from ? url("{$url_format}&page=" . ($page['curr_page'] - 1)) : "";
        $page['next_link'] = $page['curr_page'] < $to ? url("{$url_format}&page=" . ($page['curr_page'] + 1)) : "";
    }

    /**
     *    获取查询条件
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function _get_query_conditions($query_item) {
        $str = '';
        $query = array();
        foreach ($query_item as $options) {
            if (is_string($options)) {
                $field = $options;
                $options['field'] = $field;
                $options['name'] = $field;
            }
            !isset($options['equal']) && $options['equal'] = '=';
            !isset($options['assoc']) && $options['assoc'] = 'AND';
            !isset($options['type']) && $options['type'] = 'string';
            !isset($options['name']) && $options['name'] = $options['field'];
            !isset($options['handler']) && $options['handler'] = 'trim';
            if (isset($_GET[$options['name']])) {
                $input = $_GET[$options['name']];
                $handler = $options['handler'];
                $value = ($input == '' ? $input : $handler($input));
                if ($value === '' || $value === false) {  //若未输入，未选择，或者经过$handler处理失败就跳过
                    continue;
                }
                strtoupper($options['equal']) == 'LIKE' && $value = "%{$value}%";
                if ($options['type'] != 'numeric') {
                    $value = "'{$value}'";      //加上单引号，安全第一
                } else {
                    $value = floatval($value);  //安全起见，将其转换成浮点型
                }
                $str .= " {$options['assoc']} {$options['field']} {$options['equal']} {$value}";
                $query[$options['name']] = $input;
            }
        }
        $this->assign('query', stripslashes_deep($query));

        return $str;
    }

    /**
     *    使用编辑器
     *
     *    @author    Garbin
     *    @param     array $params
     *    @return    string
     */
    function _build_editor($params = array()) {
        $name = isset($params['name']) ? $params['name'] : null;
        $theme = isset($params['theme']) ? $params['theme'] : 'normal';
        $ext_js = isset($params['ext_js']) ? $params['ext_js'] : true;
        $content_css = isset($params['content_css']) ? 'content_css:"' . $params['content_css'] . '",' : null;
        $if_media = false;
        $visit = $this->visitor->get('manage_store');
        $store_id = isset($visit) ? intval($visit) : 0;
        $privs = $this->visitor->get('privs');
        if (!empty($privs)) {
            if ($privs == 'all') {
                $if_media = true;
            } else {
                $privs_array = explode(',', $privs);
                if (in_array('article|all', $privs_array)) {
                    $if_media = true;
                }
            }
        }
        if (!empty($store_id) && !$if_media) {
            $store_mod = & m('store');
            $store = $store_mod->get_info($store_id);
            $sgrade_mod = & m('sgrade');
            $sgrade = $sgrade_mod->get_info($store['sgrade']);
            $functions = explode(',', $sgrade['functions']);
            if (in_array('editor_multimedia', $functions)) {
                $if_media = true;
            }
        }

        $include_js = $ext_js ? '<script type="text/javascript" src="{lib file="tiny_mce/tiny_mce.js"}"></script>' : '';

        /* 指定哪个(些)textarea需要编辑器 */
        if ($name === null) {
            $mode = 'mode:"textareas",';
        } else {
            $mode = 'mode:"exact",elements:"' . $name . '",';
        }

        /* 指定使用哪种主题 */
        $themes = array(
            'normal' => 'plugins:"inlinepopups,preview,fullscreen,paste' . ($if_media ? ',media' : '' ) . '",
            theme:"advanced",
            theme_advanced_buttons1:"code,fullscreen' . ($content_css ? ',preview' : '' ) . ',removeformat,|,bold,italic,underline,strikethrough,|," +
                "formatselect,fontsizeselect,|,forecolor,backcolor",
            theme_advanced_buttons2:"bullist,numlist,|,outdent,indent,blockquote,|,justifyleft,justifycenter," +
                "justifyright,justifyfull,|,link,unlink,charmap,image,|,pastetext,pasteword,|,undo,redo,|,media",
            theme_advanced_buttons3 : "",',
            'simple' => 'theme:"simple",',
        );
        switch ($theme) {
            case 'simple':
                $theme_config = $themes['simple'];
                break;
            case 'normal':
                $theme_config = $themes['normal'];
                break;
            default:
                $theme_config = $themes['normal'];
                break;
        }
        /* 配置界面语言 */
        $_lang = substr(LANG, 0, 2);
        switch ($_lang) {
            case 'sc':
                $lang = 'zh_cn';
                break;
            case 'tc':
                $lang = 'zh';
                break;
            case 'en':
                $lang = 'en';
                break;
            default:
                $lang = 'zh_cn';
                break;
        }

        /* 输出 */
        $str = <<<EOT
$include_js
<script type="text/javascript">
    tinyMCE.init({
        {$mode}
        {$theme_config}
        {$content_css}
        language:"{$lang}",
        convert_urls : false,
        relative_urls : false,
        remove_script_host : false,
        theme_advanced_toolbar_location:"top",
        theme_advanced_toolbar_align:"left"
});
</script>
EOT;

        return $this->_view->fetch('str:' . $str);
        ;
    }

    /**
     *    使用swfupload
     *
     *    @author    Hyber
     *    @param     array $params
     *    @return    string
     */
    function _build_upload($params = array()) {
        $belong = isset($params['belong']) ? $params['belong'] : 0; //上传文件所属模型
        $item_id = isset($params['item_id']) ? $params['item_id'] : 0; //所属模型的ID
        $file_size_limit = isset($params['file_size_limit']) ? $params['file_size_limit'] : '2 MB'; //默认最大2M
        $button_text = isset($params['button_text']) ? Lang::get($params['button_text']) : Lang::get('bat_upload'); //上传按钮文本
        $image_file_type = isset($params['image_file_type']) ? $params['image_file_type'] : IMAGE_FILE_TYPE;
        $upload_url = isset($params['upload_url']) ? $params['upload_url'] : 'index.php?app=swfupload';
        $button_id = isset($params['button_id']) ? $params['button_id'] : 'spanButtonPlaceholder';
        $progress_id = isset($params['progress_id']) ? $params['progress_id'] : 'divFileProgressContainer';
        $if_multirow = isset($params['if_multirow']) ? $params['if_multirow'] : 0;
        $define = isset($params['obj']) ? 'var ' . $params['obj'] . ';' : '';
        $assign = isset($params['obj']) ? $params['obj'] . ' = ' : '';
        $ext_js = isset($params['ext_js']) ? $params['ext_js'] : true;
        $ext_css = isset($params['ext_css']) ? $params['ext_css'] : true;

        $include_js = $ext_js ? '<script type="text/javascript" charset="utf-8" src="{lib file="swfupload/swfupload.js"}"></script>
<script type="text/javascript" charset="utf-8" src="{lib file="swfupload/js/handlers.js"}"></script>' : '';
        $include_css = $ext_css ? '<link type="text/css" rel="stylesheet" href="{lib file="swfupload/css/default.css"}"/>' : '';
        /* 允许类型 */
        $file_types = '';
        $image_file_type = explode('|', $image_file_type);
        foreach ($image_file_type as $type) {
            $file_types .= '*.' . $type . ';';
        }
        $file_types = trim($file_types, ';');
        $str = <<<EOT

{$include_js}
{$include_css}
<script type="text/javascript">
{$define}
$(function(){
    {$assign}new SWFUpload({
        upload_url: "{$upload_url}",
        flash_url: "{lib file="swfupload/swfupload.swf"}",
        post_params: {
            "ECM_ID": "{$_COOKIE['ECM_ID']}",
            "HTTP_USER_AGENT":"{$_SERVER['HTTP_USER_AGENT']}",
            'belong': {$belong},
            'item_id': {$item_id},
            'ajax': 1
        },
        file_size_limit: "{$file_size_limit}",
        file_types: "{$file_types}",
        custom_settings: {
            upload_target: "{$progress_id}",
            if_multirow: {$if_multirow}
        },

        // Button Settings
        button_image_url: "{lib file="swfupload/images/SmallSpyGlassWithTransperancy_17x18.png"}",
        button_width: 86,
        button_height: 18,
        button_text: '<span class="button">{$button_text}</span>',
        button_text_style: '.button { font-family: Helvetica, Arial, sans-serif; font-size: 12pt; font-weight: bold; color: #3F3D3E; } .buttonSmall { font-size: 10pt; }',
        button_text_top_padding: 0,
        button_text_left_padding: 18,
        button_window_mode: SWFUpload.WINDOW_MODE.TRANSPARENT,
        button_cursor: SWFUpload.CURSOR.HAND,

        // The event handler functions are defined in handlers.js
        file_queue_error_handler: fileQueueError,
        file_dialog_complete_handler: fileDialogComplete,
        upload_progress_handler: uploadProgress,
        upload_error_handler: uploadError,
        upload_success_handler: uploadSuccess,
        upload_complete_handler: uploadComplete,
        button_placeholder_id: "{$button_id}",
        file_queued_handler : fileQueued
    });
});
</script>
EOT;
        return $this->_view->fetch('str:' . $str);
    }

    /**
     *    发送邮件
     *
     *    @author    Garbin
     *    @param     mixed  $to
     *    @param     string $subject
     *    @param     string $message
     *    @param     int    $priority
     *    @return    void
     */
    function _mailto($to, $subject, $message, $priority = MAIL_PRIORITY_LOW) {
        /* 加入邮件队列，并通知需要发送 */
        $model_mailqueue = & m('mailqueue');
        $mails = array();
        $to_emails = is_array($to) ? $to : array($to);
        foreach ($to_emails as $_to) {
            $mails[] = array(
                'mail_to' => $_to,
                'mail_encoding' => CHARSET,
                'mail_subject' => $subject,
                'mail_body' => $message,
                'priority' => $priority,
                'add_time' => gmtime(),
            );
        }

        $model_mailqueue->add($mails);

        /*
         * 由于以前的邮件发送功能不能用，现在重写发送功能 2015-04-18
         */
        $mail = new PHPMailer(); //建立邮件发送类
        $mail->CharSet='UTF-8';
        $address = $_to; //收件人地址
        $mail->IsSMTP(); // 使用SMTP方式发送
        $mail->Host = "smtp.qq.com"; // 您的企业邮局域名
        $mail->SMTPAuth = true; // 启用SMTP验证功能
        $mail->Username = "517203160@qq.com"; // 邮局用户名(请填写完整的email地址)发件人邮箱
        $mail->Password = "ljr309run@123"; // 邮局密码密码

        $mail->From = "517203160@qq.com"; //邮件发送者email地址
        $mail->FromName = "蓝羽维涅斯商城";
        $mail->AddAddress($address, ""); //收件人地址，可以替换成任何想要接收邮件的email信箱,格式是AddAddress("收件人email","收件人姓名")
//$mail->AddReplyTo("", "");
//$mail->AddAttachment("/var/tmp/file.tar.gz"); // 添加附件
//$mail->IsHTML(true); // set email format to HTML //是否使用HTML格式

        $mail->Subject = $subject; //邮件标题
        $mail->Body = $message; //邮件内容
        $mail->AltBody = "This is the body in plain text for non-HTML mail clients"; //附加信息，可以省略

//        $mail->Send(); 


        /* 默认采用异步发送邮件，这样可以解决响应缓慢的问题 */
        //  $this->_sendmail();  //这个功有问题，先注释，用上面重写的发邮件方法
    }
    
    /**
     *    发送邮件
     *
     *    @author    Garbin
     *    @param     mixed  $to
     *    @param     string $subject
     *    @param     string $message
     *    @param     int    $priority
     *    @return    void
     * 由于之前的存在问题，而且太多的邮件会导致邮箱出现问题，现在直接重新写一个2的方法
     */
    function _mailto2($to, $subject, $message, $priority = MAIL_PRIORITY_LOW) {
        /* 加入邮件队列，并通知需要发送 */
        $model_mailqueue = & m('mailqueue');
        $mails = array();
        $to_emails = is_array($to) ? $to : array($to);
        foreach ($to_emails as $_to) {
            $mails[] = array(
                'mail_to' => $_to,
                'mail_encoding' => CHARSET,
                'mail_subject' => $subject,
                'mail_body' => $message,
                'priority' => $priority,
                'add_time' => gmtime(),
            );
        }

        $model_mailqueue->add($mails);

        /*
         * 由于以前的邮件发送功能不能用，现在重写发送功能 2015-04-18
         */
        $mail = new PHPMailer(); //建立邮件发送类
        $mail->CharSet='UTF-8';
        $address = $_to; //收件人地址
        $mail->IsSMTP(); // 使用SMTP方式发送
        $mail->Host = "smtp.qq.com"; // 您的企业邮局域名
        $mail->SMTPAuth = true; // 启用SMTP验证功能
        $mail->Username = "517203160@qq.com"; // 邮局用户名(请填写完整的email地址)发件人邮箱
        $mail->Password = "ljr309run@123"; // 邮局密码密码

        $mail->From = "517203160@qq.com"; //邮件发送者email地址
        $mail->FromName = "蓝羽维涅斯商城";
        $mail->AddAddress($address, ""); //收件人地址，可以替换成任何想要接收邮件的email信箱,格式是AddAddress("收件人email","收件人姓名")
//$mail->AddReplyTo("", "");
//$mail->AddAttachment("/var/tmp/file.tar.gz"); // 添加附件
//$mail->IsHTML(true); // set email format to HTML //是否使用HTML格式

        $mail->Subject = $subject; //邮件标题
        $mail->Body = $message; //邮件内容
        $mail->AltBody = "This is the body in plain text for non-HTML mail clients"; //附加信息，可以省略

        $mail->Send(); 


        /* 默认采用异步发送邮件，这样可以解决响应缓慢的问题 */
        //  $this->_sendmail();  //这个功有问题，先注释，用上面重写的发邮件方法
    }
    
    
    /**
     *    发送邮件
     *
     *    @author    Garbin
     *    @param     bool $is_sync
     *    @return    void
     */
    function _sendmail($is_sync = false) {
        if (!$is_sync) {
            /* 采用异步方式发送邮件，与模板引擎配合达到目的 */
            $_SESSION['ASYNC_SENDMAIL'] = true;

            return true;
        } else {
            /* 同步发送邮件，将异步发送的命令去掉 */
            unset($_SESSION['ASYNC_SENDMAIL']);
            $model_mailqueue = & m('mailqueue');

            return $model_mailqueue->send(5);
        }
    }

    /**
     *     获取异步发送邮件代码
     *
     *    @author    Garbin
     *    @return    string
     */
    function _async_sendmail() {
        $script = '';
        if (isset($_SESSION['ASYNC_SENDMAIL']) && $_SESSION['ASYNC_SENDMAIL']) {
            /* 需要异步发送 */
            $async_sendmail = SITE_URL . '/index.php?app=sendmail';
            $script = '<script type="text/javascript">sendmail("' . $async_sendmail . '");</script>';
        }

        return $script;
    }

    function _get_new_message() {
        $user_id = $this->visitor->get('user_id');
        if (empty($user_id)) {
            return '';
        }
        $ms = & ms();
        return $ms->pm->check_new($user_id);
    }

    /**
     *    计划任务守护进程
     *
     *    @author    Garbin
     *    @return    void
     */
    function _run_cron() {

        register_shutdown_function(create_function('', '
            /*if (ob_get_level() > 0)
            {
                ob_end_flush();         //输出
            }*/
            if (!is_file(ROOT_PATH . "/data/tasks.inc.php"))
            {
                $default_tasks = array(
                    "cleanup" =>
                        array (
                            "cycle" => "custom",
                            "interval" => 3600,     //每一个小时执行一次清理
                        ),
                );
                file_put_contents(ROOT_PATH . "/data/tasks.inc.php", "<?php\r\n\r\nreturn " . var_export($default_tasks, true) . ";\r\n\r\n", LOCK_EX);
            }
            import("cron.lib");
            $cron = new Crond(array(
                "task_list" => ROOT_PATH . "/data/tasks.inc.php",
                "task_path" => ROOT_PATH . "/includes/tasks",
                "lock_file" => ROOT_PATH . "/data/crond.lock"
            ));                     //计划任务实例
            $cron->execute();       //执行
        '));
    }

    /**
     * 发送Feed
     *
     * @author Garbin
     * @param
     * @return void
     * */
    function send_feed($event, $data) {
        $ms = &ms();
        if (!$ms->feed->feed_enabled()) {
            return;
        }

        $feed_config = $this->visitor->get('feed_config');
        $feed_config = empty($feed_config) ? Conf::get('default_feed_config') : unserialize($feed_config);
        if (!$feed_config[$event]) {
            return;
        }

        $ms->feed->add($event, $data);
    }

}

/**
 *    访问者基础类，集合了当前访问用户的操作
 *
 *    @author    Garbin
 *    @return    void
 */
class BaseVisitor extends Object {

    var $has_login = false;
    var $info = null;
    var $privilege = null;
    var $_info_key = '';

    function __construct() {
        $this->BaseVisitor();
    }

    function BaseVisitor() {
        if (!empty($_SESSION[$this->_info_key]['user_id'])) {
            $this->info = $_SESSION[$this->_info_key];
            $this->has_login = true;
        } else {
            $this->info = array(
                'user_id' => 0,
                'user_name' => Lang::get('guest')
            );
            $this->has_login = false;
        }
    }

    function assign($user_info) {
        $_SESSION[$this->_info_key] = $user_info;
    }

    /**
     *    获取当前登录用户的详细信息
     *
     *    @author    Garbin
     *    @return    array      用户的详细信息
     */
    function get_detail() {
        /* 未登录，则无详细信息 */
        if (!$this->has_login) {
            return array();
        }

        /* 取出详细信息 */
        static $detail = null;

        if ($detail === null) {
            $detail = $this->_get_detail();
        }

        return $detail;
    }

    /**
     *    获取用户详细信息
     *
     *    @author    Garbin
     *    @return    array
     */
    function _get_detail() {
        $model_member = & m('member');

        /* 获取当前用户的详细信息，包括权限 */
        $member_info = $model_member->findAll(array(
            'conditions' => "member.user_id = '{$this->info['user_id']}'",
            'join' => 'has_store', //关联查找看看是否有店铺
            'fields' => 'email, password, real_name, logins, ugrade, portrait, store_id, state, sgrade , feed_config',
            'include' => array(//找出所有该用户管理的店铺
                'manage_store' => array(
                    'fields' => 'user_priv.privs, store.store_name',
                ),
            ),
        ));
        $detail = current($member_info);

        /* 如果拥有店铺，则默认管理的店铺为自己的店铺，否则需要用户自行指定 */
        if ($detail['store_id'] && $detail['state'] != STORE_APPLYING) { // 排除申请中的店铺
            $detail['manage_store'] = $detail['has_store'] = $detail['store_id'];
        }

        return $detail;
    }

    /**
     *    获取当前用户的指定信息
     *
     *    @author    Garbin
     *    @param     string $key  指定用户信息
     *    @return    string  如果值是字符串的话
     *               array   如果是数组的话
     */
    function get($key = null) {
        $info = null;

        if (empty($key)) {
            /* 未指定key，则返回当前用户的所有信息：基础信息＋详细信息 */
            $info = array_merge((array) $this->info, (array) $this->get_detail());
        } else {
            /* 指定了key，则返回指定的信息 */
            if (isset($this->info[$key])) {
                /* 优先查找基础数据 */
                $info = $this->info[$key];
            } else {
                /* 若基础数据中没有，则查询详细数据 */
                $detail = $this->get_detail();
                $info = isset($detail[$key]) ? $detail[$key] : null;
            }
        }

        return $info;
    }

    /**
     *    登出
     *
     *    @author    Garbin
     *    @return    void
     */
    function logout() {
        unset($_SESSION[$this->_info_key]);
    }

    function i_can($event, $privileges = array()) {
        $fun_name = 'check_' . $event;

        return $this->$fun_name($privileges);
    }

    function check_do_action($privileges) {
        $mp = APP . '|' . ACT;

        if ($privileges == 'all') {
            /* 拥有所有权限 */
            return true;
        } else {
            /* 查看当前操作是否在白名单中，如果在，则允许，否则不允许 */
            $privs = explode(',', $privileges);
            if (in_array(APP . '|all', $privs) || in_array($mp, $privs)) {
                return true;
            }

            return false;
        }
    }

}

class smtp2 {
    /* Public Variables */

    var $smtp_port;
    var $time_out;
    var $host_name;
    var $log_file;
    var $relay_host;
    var $debug;
    var $auth;
    var $user;
    var $pass;

    /* Private Variables */
    var $sock;

    /* Constractor */

    function smtp($relay_host = "", $smtp_port = 25, $auth = false, $user, $pass) {
        $this->debug = FALSE;
        $this->smtp_port = $smtp_port;
        $this->relay_host = $relay_host;
        $this->time_out = 30; //is used in fsockopen()  
        $this->auth = $auth; //auth 
        $this->user = $user;
        $this->pass = $pass;
        $this->host_name = "localhost"; //is used in HELO command  
        $this->log_file = "";
        $this->sock = FALSE;
    }

    /* Main Function */

    function sendmail($to, $from, $subject = "", $body = "", $mailtype, $cc = "", $bcc = "", $additional_headers = "") {
        $mail_from = $this->get_address($this->strip_comment($from));
        $body = ereg_replace("(^|(\r\n))(\.)", "\1.\3", $body);
        $header .= "MIME-Version:1.0\r\n";
        if ($mailtype == "HTML") {
            $header .= "Content-Type:text/html\r\n";
        }
        $header .= "To: " . $to . "\r\n";
        if ($cc != "") {
            $header .= "Cc: " . $cc . "\r\n";
        }
        $header .= "From: $from<" . $from . ">\r\n";
        $header .= "Subject: " . $subject . "\r\n";
        $header .= $additional_headers;
        $header .= "Date: " . date("r") . "\r\n";
        $header .= "X-Mailer:By Redhat (PHP/" . phpversion() . ")\r\n";
        list($msec, $sec) = explode(" ", microtime());
        $header .= "Message-ID: <" . date("YmdHis", $sec) . "." . ($msec * 1000000) . "." . $mail_from . ">\r\n";
        $TO = explode(",", $this->strip_comment($to));

        if ($cc != "") {
            $TO = array_merge($TO, explode(",", $this->strip_comment($cc)));
        }
        if ($bcc != "") {
            $TO = array_merge($TO, explode(",", $this->strip_comment($bcc)));
        }
        $sent = TRUE;
        foreach ($TO as $rcpt_to) {
            $rcpt_to = $this->get_address($rcpt_to);
            if (!$this->smtp_sockopen($rcpt_to)) {
                $this->log_write("Error: Cannot send email to " . $rcpt_to . "\n");
                $sent = FALSE;
                continue;
            }
            if ($this->smtp_send($this->host_name, $mail_from, $rcpt_to, $header, $body)) {
                $this->log_write("E-mail has been sent to <" . $rcpt_to . ">\n");
            } else {
                $this->log_write("Error: Cannot send email to <" . $rcpt_to . ">\n");
                $sent = FALSE;
            }
            fclose($this->sock);
            $this->log_write("Disconnected from remote host\n");
        }
        return $sent;
    }

    /* Private Functions */

    function smtp_send($helo, $from, $to, $header, $body = "") {
        if (!$this->smtp_putcmd("HELO", $helo)) {
            return $this->smtp_error("sending HELO command");
        }

        #auth 
        if ($this->auth) {
            if (!$this->smtp_putcmd("AUTH LOGIN", base64_encode($this->user))) {
                return $this->smtp_error("sending HELO command");
            }
            if (!$this->smtp_putcmd("", base64_encode($this->pass))) {
                return $this->smtp_error("sending HELO command");
            }
        }
        if (!$this->smtp_putcmd("MAIL", "FROM:<" . $from . ">")) {
            return $this->smtp_error("sending MAIL FROM command");
        }
        if (!$this->smtp_putcmd("RCPT", "TO:<" . $to . ">")) {
            return $this->smtp_error("sending RCPT TO command");
        }
        if (!$this->smtp_putcmd("DATA")) {
            return $this->smtp_error("sending DATA command");
        }
        if (!$this->smtp_message($header, $body)) {
            return $this->smtp_error("sending message");
        }
        if (!$this->smtp_eom()) {
            return $this->smtp_error("sending <CR><LF>.<CR><LF> [EOM]");
        }
        if (!$this->smtp_putcmd("QUIT")) {
            return $this->smtp_error("sending QUIT command");
        }
        return TRUE;
    }

    function smtp_sockopen($address) {
        if ($this->relay_host == "") {
            return $this->smtp_sockopen_mx($address);
        } else {
            return $this->smtp_sockopen_relay();
        }
    }

    function smtp_sockopen_relay() {
        $this->log_write("Trying to " . $this->relay_host . ":" . $this->smtp_port . "\n");
        $this->sock = @fsockopen($this->relay_host, $this->smtp_port, $errno, $errstr, $this->time_out);
        if (!($this->sock && $this->smtp_ok())) {
            $this->log_write("Error: Cannot connenct to relay host " . $this->relay_host . "\n");
            $this->log_write("Error: " . $errstr . " (" . $errno . ")\n");
            return FALSE;
        }
        $this->log_write("Connected to relay host " . $this->relay_host . "\n");
        return TRUE;
        ;
    }

    function smtp_sockopen_mx($address) {
        $domain = ereg_replace("^.+@([^@]+)$", "\1", $address);
        if (!@getmxrr($domain, $MXHOSTS)) {
            $this->log_write("Error: Cannot resolve MX \"" . $domain . "\"\n");
            return FALSE;
        }
        foreach ($MXHOSTS as $host) {
            $this->log_write("Trying to " . $host . ":" . $this->smtp_port . "\n");
            $this->sock = @fsockopen($host, $this->smtp_port, $errno, $errstr, $this->time_out);
            if (!($this->sock && $this->smtp_ok())) {
                $this->log_write("Warning: Cannot connect to mx host " . $host . "\n");
                $this->log_write("Error: " . $errstr . " (" . $errno . ")\n");
                continue;
            }
            $this->log_write("Connected to mx host " . $host . "\n");
            return TRUE;
        }
        $this->log_write("Error: Cannot connect to any mx hosts (" . implode(", ", $MXHOSTS) . ")\n");
        return FALSE;
    }

    function smtp_message($header, $body) {
        fputs($this->sock, $header . "\r\n" . $body);
        $this->smtp_debug("> " . str_replace("\r\n", "\n" . "> ", $header . "\n> " . $body . "\n> "));
        return TRUE;
    }

    function smtp_eom() {
        fputs($this->sock, "\r\n.\r\n");
        $this->smtp_debug(". [EOM]\n");
        return $this->smtp_ok();
    }

    function smtp_ok() {
        $response = str_replace("\r\n", "", fgets($this->sock, 512));
        $this->smtp_debug($response . "\n");
        if (!ereg("^[23]", $response)) {
            fputs($this->sock, "QUIT\r\n");
            fgets($this->sock, 512);
            $this->log_write("Error: Remote host returned \"" . $response . "\"\n");
            return FALSE;
        }
        return TRUE;
    }

    function smtp_putcmd($cmd, $arg = "") {
        if ($arg != "") {
            if ($cmd == "") {
                $cmd = $arg;
            } else {
                $cmd = $cmd . " " . $arg;
            }
        }
        fputs($this->sock, $cmd . "\r\n");
        $this->smtp_debug("> " . $cmd . "\n");
        return $this->smtp_ok();
    }

    function smtp_error($string) {
        $this->log_write("Error: Error occurred while " . $string . ".\n");
        return FALSE;
    }

    function log_write($message) {
        $this->smtp_debug($message);
        if ($this->log_file == "") {
            return TRUE;
        }
        $message = date("M d H:i:s ") . get_current_user() . "[" . getmypid() . "]: " . $message;
        if (!@file_exists($this->log_file) || !($fp = @fopen($this->log_file, "a"))) {
            $this->smtp_debug("Warning: Cannot open log file \"" . $this->log_file . "\"\n");
            return FALSE;
            ;
        }
        flock($fp, LOCK_EX);
        fputs($fp, $message);
        fclose($fp);
        return TRUE;
    }

    function strip_comment($address) {
        $comment = "\([^()]*\)";
        while (ereg($comment, $address)) {
            $address = ereg_replace($comment, "", $address);
        }
        return $address;
    }

    function get_address($address) {
        $address = ereg_replace("([ \t\r\n])+", "", $address);
        $address = ereg_replace("^.*<(.+)>.*$", "\1", $address);
        return $address;
    }

    function smtp_debug($message) {
        if ($this->debug) {
            echo $message;
        }
    }

}

class PHPMailer {
    /////////////////////////////////////////////////
    // PROPERTIES, PUBLIC
    /////////////////////////////////////////////////

    /**
     * Email priority (1 = High, 3 = Normal, 5 = low).
     * @var int
     */
    public $Priority = 3;

    /**
     * Sets the CharSet of the message.
     * @var string
     */
    public $CharSet = 'UTF-8';

    /**
     * Sets the Content-type of the message.
     * @var string
     */
    public $ContentType = 'text/plain';

    /**
     * Sets the Encoding of the message. Options for this are
     *  "8bit", "7bit", "binary", "base64", and "quoted-printable".
     * @var string
     */
    public $Encoding = '8bit';

    /**
     * Holds the most recent mailer error message.
     * @var string
     */
    public $ErrorInfo = '';

    /**
     * Sets the From email address for the message.
     * @var string
     */
    public $From = 'root@localhost';

    /**
     * Sets the From name of the message.
     * @var string
     */
    public $FromName = 'Root User';

    /**
     * Sets the Sender email (Return-Path) of the message.  If not empty,
     * will be sent via -f to sendmail or as 'MAIL FROM' in smtp mode.
     * @var string
     */
    public $Sender = '';

    /**
     * Sets the Subject of the message.
     * @var string
     */
    public $Subject = '';

    /**
     * Sets the Body of the message.  This can be either an HTML or text body.
     * If HTML then run IsHTML(true).
     * @var string
     */
    public $Body = '';

    /**
     * Sets the text-only body of the message.  This automatically sets the
     * email to multipart/alternative.  This body can be read by mail
     * clients that do not have HTML email capability such as mutt. Clients
     * that can read HTML will view the normal Body.
     * @var string
     */
    public $AltBody = '';

    /**
     * Sets word wrapping on the body of the message to a given number of
     * characters.
     * @var int
     */
    public $WordWrap = 0;

    /**
     * Method to send mail: ("mail", "sendmail", or "smtp").
     * @var string
     */
    public $Mailer = 'mail';

    /**
     * Sets the path of the sendmail program.
     * @var string
     */
    public $Sendmail = '/usr/sbin/sendmail';

    /**
     * Path to PHPMailer plugins.  Useful if the SMTP class
     * is in a different directory than the PHP include path.
     * @var string
     */
    public $PluginDir = '';

    /**
     * Sets the email address that a reading confirmation will be sent.
     * @var string
     */
    public $ConfirmReadingTo = '';

    /**
     * Sets the hostname to use in Message-Id and Received headers
     * and as default HELO string. If empty, the value returned
     * by SERVER_NAME is used or 'localhost.localdomain'.
     * @var string
     */
    public $Hostname = '';

    /**
     * Sets the message ID to be used in the Message-Id header.
     * If empty, a unique id will be generated.
     * @var string
     */
    public $MessageID = '';

    /////////////////////////////////////////////////
    // PROPERTIES FOR SMTP
    /////////////////////////////////////////////////

    /**
     * Sets the SMTP hosts.  All hosts must be separated by a
     * semicolon.  You can also specify a different port
     * for each host by using this format: [hostname:port]
     * (e.g. "smtp1.example.com:25;smtp2.example.com").
     * Hosts will be tried in order.
     * @var string
     */
    public $Host = 'localhost';

    /**
     * Sets the default SMTP server port.
     * @var int
     */
    public $Port = 25;

    /**
     * Sets the SMTP HELO of the message (Default is $Hostname).
     * @var string
     */
    public $Helo = '';

    /**
     * Sets connection prefix.
     * Options are "", "ssl" or "tls"
     * @var string
     */
    public $SMTPSecure = '';

    /**
     * Sets SMTP authentication. Utilizes the Username and Password variables.
     * @var bool
     */
    public $SMTPAuth = false;

    /**
     * Sets SMTP username.
     * @var string
     */
    public $Username = '';

    /**
     * Sets SMTP password.
     * @var string
     */
    public $Password = '';

    /**
     * Sets the SMTP server timeout in seconds.
     * This function will not work with the win32 version.
     * @var int
     */
    public $Timeout = 10;

    /**
     * Sets SMTP class debugging on or off.
     * @var bool
     */
    public $SMTPDebug = false;

    /**
     * Prevents the SMTP connection from being closed after each mail
     * sending.  If this is set to true then to close the connection
     * requires an explicit call to SmtpClose().
     * @var bool
     */
    public $SMTPKeepAlive = false;

    /**
     * Provides the ability to have the TO field process individual
     * emails, instead of sending to entire TO addresses
     * @var bool
     */
    public $SingleTo = false;

    /**
     * Provides the ability to change the line ending
     * @var string
     */
    public $LE = "\n";

    /**
     * Sets the PHPMailer Version number
     * @var string
     */
    public $Version = '5.0.2';
    /////////////////////////////////////////////////
    // PROPERTIES, PRIVATE AND PROTECTED
    /////////////////////////////////////////////////

    private $smtp = NULL;
    private $to = array();
    private $cc = array();
    private $bcc = array();
    private $ReplyTo = array();
    private $all_recipients = array();
    private $attachment = array();
    private $CustomHeader = array();
    private $message_type = '';
    private $boundary = array();
    protected $language = array();
    private $error_count = 0;
    private $sign_cert_file = "";
    private $sign_key_file = "";
    private $sign_key_pass = "";
    private $exceptions = false;

    /////////////////////////////////////////////////
    // CONSTANTS
    /////////////////////////////////////////////////

    const STOP_MESSAGE = 0; // message only, continue processing
    const STOP_CONTINUE = 1; // message?, likely ok to continue processing
    const STOP_CRITICAL = 2; // message, plus full stop, critical error reached

    /////////////////////////////////////////////////
    // METHODS, VARIABLES
    /////////////////////////////////////////////////

    /**
     * Constructor
     * @param boolean $exceptions Should we throw external exceptions?
     */
    public function __construct($exceptions = false) {
        $this->exceptions = ($exceptions == true);
    }

    /**
     * Sets message type to HTML.
     * @param bool $ishtml
     * @return void
     */
    public function IsHTML($ishtml = true) {
        if ($ishtml) {
            $this->ContentType = 'text/html';
        } else {
            $this->ContentType = 'text/plain';
        }
    }

    /**
     * Sets Mailer to send message using SMTP.
     * @return void
     */
    public function IsSMTP() {
        $this->Mailer = 'smtp';
    }

    /**
     * Sets Mailer to send message using PHP mail() function.
     * @return void
     */
    public function IsMail() {
        $this->Mailer = 'mail';
    }

    /**
     * Sets Mailer to send message using the $Sendmail program.
     * @return void
     */
    public function IsSendmail() {
        if (!stristr(ini_get('sendmail_path'), 'sendmail')) {
            $this->Sendmail = '/var/qmail/bin/sendmail';
        }
        $this->Mailer = 'sendmail';
    }

    /**
     * Sets Mailer to send message using the qmail MTA.
     * @return void
     */
    public function IsQmail() {
        if (stristr(ini_get('sendmail_path'), 'qmail')) {
            $this->Sendmail = '/var/qmail/bin/sendmail';
        }
        $this->Mailer = 'sendmail';
    }

    /////////////////////////////////////////////////
    // METHODS, RECIPIENTS
    /////////////////////////////////////////////////

    /**
     * Adds a "To" address.
     * @param string $address
     * @param string $name
     * @return boolean true on success, false if address already used
     */
    public function AddAddress($address, $name = '') {
        return $this->AddAnAddress('to', $address, $name);
    }

    /**
     * Adds a "Cc" address.
     * Note: this function works with the SMTP mailer on win32, not with the "mail" mailer.
     * @param string $address
     * @param string $name
     * @return boolean true on success, false if address already used
     */
    public function AddCC($address, $name = '') {
        return $this->AddAnAddress('cc', $address, $name);
    }

    /**
     * Adds a "Bcc" address.
     * Note: this function works with the SMTP mailer on win32, not with the "mail" mailer.
     * @param string $address
     * @param string $name
     * @return boolean true on success, false if address already used
     */
    public function AddBCC($address, $name = '') {
        return $this->AddAnAddress('bcc', $address, $name);
    }

    /**
     * Adds a "Reply-to" address.
     * @param string $address
     * @param string $name
     * @return boolean
     */
    public function AddReplyTo($address, $name = '') {
        return $this->AddAnAddress('ReplyTo', $address, $name);
    }

    /**
     * Adds an address to one of the recipient arrays
     * Addresses that have been added already return false, but do not throw exceptions
     * @param string $kind One of 'to', 'cc', 'bcc', 'ReplyTo'
     * @param string $address The email address to send to
     * @param string $name
     * @return boolean true on success, false if address already used or invalid in some way
     * @access private
     */
    private function AddAnAddress($kind, $address, $name = '') {
        if (!preg_match('/^(to|cc|bcc|ReplyTo)$/', $kind)) {
            echo 'Invalid recipient array: ' . kind;
            return false;
        }
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name)); //Strip breaks and trim
        if (!self::ValidateAddress($address)) {
            $this->SetError($this->Lang('invalid_address') . ': ' . $address);
            if ($this->exceptions) {
                throw new phpmailerException($this->Lang('invalid_address') . ': ' . $address);
            }
            echo $this->Lang('invalid_address') . ': ' . $address;
            return false;
        }
        if ($kind != 'ReplyTo') {
            if (!isset($this->all_recipients[strtolower($address)])) {
                array_push($this->$kind, array($address, $name));
                $this->all_recipients[strtolower($address)] = true;
                return true;
            }
        } else {
            if (!array_key_exists(strtolower($address), $this->ReplyTo)) {
                $this->ReplyTo[strtolower($address)] = array($address, $name);
                return true;
            }
        }
        return false;
    }

    /**
     * Set the From and FromName properties
     * @param string $address
     * @param string $name
     * @return boolean
     */
    public function SetFrom($address, $name = '') {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name)); //Strip breaks and trim
        if (!self::ValidateAddress($address)) {
            $this->SetError($this->Lang('invalid_address') . ': ' . $address);
            if ($this->exceptions) {
                throw new phpmailerException($this->Lang('invalid_address') . ': ' . $address);
            }
            echo $this->Lang('invalid_address') . ': ' . $address;
            return false;
        }
        $this->From = $address;
        $this->FromName = $name;
        return true;
    }

    /**
     * Check that a string looks roughly like an email address should
     * Static so it can be used without instantiation
     * Tries to use PHP built-in validator in the filter extension (from PHP 5.2), falls back to a reasonably competent regex validator
     * Conforms approximately to RFC2822
     * @link http://www.hexillion.com/samples/#Regex Original pattern found here
     * @param string $address The email address to check
     * @return boolean
     * @static
     * @access public
     */
    public static function ValidateAddress($address) {
        if (function_exists('filter_var')) { //Introduced in PHP 5.2
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === FALSE) {
                return false;
            } else {
                return true;
            }
        } else {
            return preg_match('/^(?:[\w\!\#\$\%\&\'\*\+\-\/\=\?\^\`\{\|\}\~]+\.)*[\w\!\#\$\%\&\'\*\+\-\/\=\?\^\`\{\|\}\~]+@(?:(?:(?:[a-zA-Z0-9_](?:[a-zA-Z0-9_\-](?!\.)){0,61}[a-zA-Z0-9_-]?\.)+[a-zA-Z0-9_](?:[a-zA-Z0-9_\-](?!$)){0,61}[a-zA-Z0-9_]?)|(?:\[(?:(?:[01]?\d{1,2}|2[0-4]\d|25[0-5])\.){3}(?:[01]?\d{1,2}|2[0-4]\d|25[0-5])\]))$/', $address);
        }
    }

    /////////////////////////////////////////////////
    // METHODS, MAIL SENDING
    /////////////////////////////////////////////////

    /**
     * Creates message and assigns Mailer. If the message is
     * not sent successfully then it returns false.  Use the ErrorInfo
     * variable to view description of the error.
     * @return bool
     */
    public function Send() {
        try {
            if ((count($this->to) + count($this->cc) + count($this->bcc)) < 1) {
                throw new phpmailerException($this->Lang('provide_address'), self::STOP_CRITICAL);
            }

            // Set whether the message is multipart/alternative
            if (!empty($this->AltBody)) {
                $this->ContentType = 'multipart/alternative';
            }

            $this->error_count = 0; // reset errors
            $this->SetMessageType();
            $header = $this->CreateHeader();
            $body = $this->CreateBody();

            if (empty($this->Body)) {
                throw new phpmailerException($this->Lang('empty_message'), self::STOP_CRITICAL);
            }

            // Choose the mailer and send through it
            switch ($this->Mailer) {
                case 'sendmail':
                    return $this->SendmailSend($header, $body);
                case 'smtp':
                    return $this->SmtpSend($header, $body);
                case 'mail':
                default:
                    return $this->MailSend($header, $body);
            }
        } catch (phpmailerException $e) {
            $this->SetError($e->getMessage());
            if ($this->exceptions) {
                throw $e;
            }
            echo $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Sends mail using the $Sendmail program.
     * @param string $header The message headers
     * @param string $body The message body
     * @access protected
     * @return bool
     */
    protected function SendmailSend($header, $body) {
        if ($this->Sender != '') {
            $sendmail = sprintf("%s -oi -f %s -t", escapeshellcmd($this->Sendmail), escapeshellarg($this->Sender));
        } else {
            $sendmail = sprintf("%s -oi -t", escapeshellcmd($this->Sendmail));
        }
        if (!@$mail = popen($sendmail, 'w')) {
            throw new phpmailerException($this->Lang('execute') . $this->Sendmail, self::STOP_CRITICAL);
        }
        fputs($mail, $header);
        fputs($mail, $body);
        $result = pclose($mail);
        if ($result != 0) {
            throw new phpmailerException($this->Lang('execute') . $this->Sendmail, self::STOP_CRITICAL);
        }
        return true;
    }

    /**
     * Sends mail using the PHP mail() function.
     * @param string $header The message headers
     * @param string $body The message body
     * @access protected
     * @return bool
     */
    protected function MailSend($header, $body) {
        $toArr = array();
        foreach ($this->to as $t) {
            $toArr[] = $this->AddrFormat($t);
        }
        $to = implode(', ', $toArr);

        $params = sprintf("-oi -f %s", $this->Sender);
        if ($this->Sender != '' && strlen(ini_get('safe_mode')) < 1) {
            $old_from = ini_get('sendmail_from');
            ini_set('sendmail_from', $this->Sender);
            if ($this->SingleTo === true && count($toArr) > 1) {
                foreach ($toArr as $key => $val) {
                    $rt = @mail($val, $this->EncodeHeader($this->SecureHeader($this->Subject)), $body, $header, $params);
                }
            } else {
                $rt = @mail($to, $this->EncodeHeader($this->SecureHeader($this->Subject)), $body, $header, $params);
            }
        } else {
            if ($this->SingleTo === true && count($toArr) > 1) {
                foreach ($toArr as $key => $val) {
                    $rt = @mail($val, $this->EncodeHeader($this->SecureHeader($this->Subject)), $body, $header, $params);
                }
            } else {
                $rt = @mail($to, $this->EncodeHeader($this->SecureHeader($this->Subject)), $body, $header);
            }
        }
        if (isset($old_from)) {
            ini_set('sendmail_from', $old_from);
        }
        if (!$rt) {
            throw new phpmailerException($this->Lang('instantiate'), self::STOP_CRITICAL);
        }
        return true;
    }

    /**
     * Sends mail via SMTP using PhpSMTP
     * Returns false if there is a bad MAIL FROM, RCPT, or DATA input.
     * @param string $header The message headers
     * @param string $body The message body
     * @uses SMTP
     * @access protected
     * @return bool
     */
    protected function SmtpSend($header, $body) {
        require_once $this->PluginDir . 'class.smtp.php';
        $bad_rcpt = array();

        if (!$this->SmtpConnect()) {
            throw new phpmailerException($this->Lang('smtp_connect_failed'), self::STOP_CRITICAL);
        }
        $smtp_from = ($this->Sender == '') ? $this->From : $this->Sender;
        if (!$this->smtp->Mail($smtp_from)) {
            throw new phpmailerException($this->Lang('from_failed') . $smtp_from, self::STOP_CRITICAL);
        }

        // Attempt to send attach all recipients
        foreach ($this->to as $to) {
            if (!$this->smtp->Recipient($to[0])) {
                $bad_rcpt[] = $to[0];
            }
        }
        foreach ($this->cc as $cc) {
            if (!$this->smtp->Recipient($cc[0])) {
                $bad_rcpt[] = $cc[0];
            }
        }
        foreach ($this->bcc as $bcc) {
            if (!$this->smtp->Recipient($bcc[0])) {
                $bad_rcpt[] = $bcc[0];
            }
        }
        if (count($bad_rcpt) > 0) { //Create error message for any bad addresses
            $badaddresses = implode(', ', $bad_rcpt);
            throw new phpmailerException($this->Lang('recipients_failed') . $badaddresses);
        }
        if (!$this->smtp->Data($header . $body)) {
            throw new phpmailerException($this->Lang('data_not_accepted'), self::STOP_CRITICAL);
        }
        if ($this->SMTPKeepAlive == true) {
            $this->smtp->Reset();
        }
        return true;
    }

    /**
     * Initiates a connection to an SMTP server.
     * Returns false if the operation failed.
     * @uses SMTP
     * @access public
     * @return bool
     */
    public function SmtpConnect() {
        if (is_null($this->smtp)) {
            $this->smtp = new SMTP();
        }

        $this->smtp->do_debug = $this->SMTPDebug;
        $hosts = explode(';', $this->Host);
        $index = 0;
        $connection = $this->smtp->Connected();

        // Retry while there is no connection
        try {
            while ($index < count($hosts) && !$connection) {
                $hostinfo = array();
                if (preg_match('/^(.+):([0-9]+)$/', $hosts[$index], $hostinfo)) {
                    $host = $hostinfo[1];
                    $port = $hostinfo[2];
                } else {
                    $host = $hosts[$index];
                    $port = $this->Port;
                }

                $tls = ($this->SMTPSecure == 'tls');
                $ssl = ($this->SMTPSecure == 'ssl');

                if ($this->smtp->Connect(($ssl ? 'ssl://' : '') . $host, $port, $this->Timeout)) {

                    $hello = ($this->Helo != '' ? $this->Helo : $this->ServerHostname());
                    $this->smtp->Hello($hello);

                    if ($tls) {
                        if (!$this->smtp->StartTLS()) {
                            throw new phpmailerException($this->Lang('tls'));
                        }

                        //We must resend HELO after tls negotiation
                        $this->smtp->Hello($hello);
                    }

                    $connection = true;
                    if ($this->SMTPAuth) {
                        if (!$this->smtp->Authenticate($this->Username, $this->Password)) {
                            throw new phpmailerException($this->Lang('authenticate'));
                        }
                    }
                }
                $index++;
                if (!$connection) {
                    throw new phpmailerException($this->Lang('connect_host'));
                }
            }
        } catch (phpmailerException $e) {
            $this->smtp->Reset();
            throw $e;
        }
        return true;
    }

    /**
     * Closes the active SMTP session if one exists.
     * @return void
     */
    public function SmtpClose() {
        if (!is_null($this->smtp)) {
            if ($this->smtp->Connected()) {
                $this->smtp->Quit();
                $this->smtp->Close();
            }
        }
    }

    /**
     * Sets the language for all class error messages.
     * Returns false if it cannot load the language file.  The default language is English.
     * @param string $langcode ISO 639-1 2-character language code (e.g. Portuguese: "br")
     * @param string $lang_path Path to the language file directory
     * @access public
     */
    function SetLanguage($langcode = 'en', $lang_path = 'language/') {
        //Define full set of translatable strings
        $PHPMAILER_LANG = array(
            'provide_address' => 'You must provide at least one recipient email address.',
            'mailer_not_supported' => ' mailer is not supported.',
            'execute' => 'Could not execute: ',
            'instantiate' => 'Could not instantiate mail function.',
            'authenticate' => 'SMTP Error: Could not authenticate.',
            'from_failed' => 'The following From address failed: ',
            'recipients_failed' => 'SMTP Error: The following recipients failed: ',
            'data_not_accepted' => 'SMTP Error: Data not accepted.',
            'connect_host' => 'SMTP Error: Could not connect to SMTP host.',
            'file_access' => 'Could not access file: ',
            'file_open' => 'File Error: Could not open file: ',
            'encoding' => 'Unknown encoding: ',
            'signing' => 'Signing Error: ',
            'smtp_error' => 'SMTP server error: ',
            'empty_message' => 'Message body empty',
            'invalid_address' => 'Invalid address',
            'variable_set' => 'Cannot set or reset variable: '
        );
        //Overwrite language-specific strings. This way we'll never have missing translations - no more "language string failed to load"!
        $l = true;
        if ($langcode != 'en') { //There is no English translation file
            $l = @include $lang_path . 'phpmailer.lang-' . $langcode . '.php';
        }
        $this->language = $PHPMAILER_LANG;
        return ($l == true); //Returns false if language not found
    }

    /**
     * Return the current array of language strings
     * @return array
     */
    public function GetTranslations() {
        return $this->language;
    }

    /////////////////////////////////////////////////
    // METHODS, MESSAGE CREATION
    /////////////////////////////////////////////////

    /**
     * Creates recipient headers.
     * @access public
     * @return string
     */
    public function AddrAppend($type, $addr) {
        $addr_str = $type . ': ';
        $addresses = array();
        foreach ($addr as $a) {
            $addresses[] = $this->AddrFormat($a);
        }
        $addr_str .= implode(', ', $addresses);
        $addr_str .= $this->LE;

        return $addr_str;
    }

    /**
     * Formats an address correctly.
     * @access public
     * @return string
     */
    public function AddrFormat($addr) {
        if (empty($addr[1])) {
            return $this->SecureHeader($addr[0]);
        } else {
            return $this->EncodeHeader($this->SecureHeader($addr[1]), 'phrase') . " <" . $this->SecureHeader($addr[0]) . ">";
        }
    }

    /**
     * Wraps message for use with mailers that do not
     * automatically perform wrapping and for quoted-printable.
     * Original written by philippe.
     * @param string $message The message to wrap
     * @param integer $length The line length to wrap to
     * @param boolean $qp_mode Whether to run in Quoted-Printable mode
     * @access public
     * @return string
     */
    public function WrapText($message, $length, $qp_mode = false) {
        $soft_break = ($qp_mode) ? sprintf(" =%s", $this->LE) : $this->LE;
        // If utf-8 encoding is used, we will need to make sure we don't
        // split multibyte characters when we wrap
        $is_utf8 = (strtolower($this->CharSet) == "utf-8");

        $message = $this->FixEOL($message);
        if (substr($message, -1) == $this->LE) {
            $message = substr($message, 0, -1);
        }

        $line = explode($this->LE, $message);
        $message = '';
        for ($i = 0; $i < count($line); $i++) {
            $line_part = explode(' ', $line[$i]);
            $buf = '';
            for ($e = 0; $e < count($line_part); $e++) {
                $word = $line_part[$e];
                if ($qp_mode and ( strlen($word) > $length)) {
                    $space_left = $length - strlen($buf) - 1;
                    if ($e != 0) {
                        if ($space_left > 20) {
                            $len = $space_left;
                            if ($is_utf8) {
                                $len = $this->UTF8CharBoundary($word, $len);
                            } elseif (substr($word, $len - 1, 1) == "=") {
                                $len--;
                            } elseif (substr($word, $len - 2, 1) == "=") {
                                $len -= 2;
                            }
                            $part = substr($word, 0, $len);
                            $word = substr($word, $len);
                            $buf .= ' ' . $part;
                            $message .= $buf . sprintf("=%s", $this->LE);
                        } else {
                            $message .= $buf . $soft_break;
                        }
                        $buf = '';
                    }
                    while (strlen($word) > 0) {
                        $len = $length;
                        if ($is_utf8) {
                            $len = $this->UTF8CharBoundary($word, $len);
                        } elseif (substr($word, $len - 1, 1) == "=") {
                            $len--;
                        } elseif (substr($word, $len - 2, 1) == "=") {
                            $len -= 2;
                        }
                        $part = substr($word, 0, $len);
                        $word = substr($word, $len);

                        if (strlen($word) > 0) {
                            $message .= $part . sprintf("=%s", $this->LE);
                        } else {
                            $buf = $part;
                        }
                    }
                } else {
                    $buf_o = $buf;
                    $buf .= ($e == 0) ? $word : (' ' . $word);

                    if (strlen($buf) > $length and $buf_o != '') {
                        $message .= $buf_o . $soft_break;
                        $buf = $word;
                    }
                }
            }
            $message .= $buf . $this->LE;
        }

        return $message;
    }

    /**
     * Finds last character boundary prior to maxLength in a utf-8
     * quoted (printable) encoded string.
     * Original written by Colin Brown.
     * @access public
     * @param string $encodedText utf-8 QP text
     * @param int    $maxLength   find last character boundary prior to this length
     * @return int
     */
    public function UTF8CharBoundary($encodedText, $maxLength) {
        $foundSplitPos = false;
        $lookBack = 3;
        while (!$foundSplitPos) {
            $lastChunk = substr($encodedText, $maxLength - $lookBack, $lookBack);
            $encodedCharPos = strpos($lastChunk, "=");
            if ($encodedCharPos !== false) {
                // Found start of encoded character byte within $lookBack block.
                // Check the encoded byte value (the 2 chars after the '=')
                $hex = substr($encodedText, $maxLength - $lookBack + $encodedCharPos + 1, 2);
                $dec = hexdec($hex);
                if ($dec < 128) { // Single byte character.
                    // If the encoded char was found at pos 0, it will fit
                    // otherwise reduce maxLength to start of the encoded char
                    $maxLength = ($encodedCharPos == 0) ? $maxLength :
                            $maxLength - ($lookBack - $encodedCharPos);
                    $foundSplitPos = true;
                } elseif ($dec >= 192) { // First byte of a multi byte character
                    // Reduce maxLength to split at start of character
                    $maxLength = $maxLength - ($lookBack - $encodedCharPos);
                    $foundSplitPos = true;
                } elseif ($dec < 192) { // Middle byte of a multi byte character, look further back
                    $lookBack += 3;
                }
            } else {
                // No encoded character found
                $foundSplitPos = true;
            }
        }
        return $maxLength;
    }

    /**
     * Set the body wrapping.
     * @access public
     * @return void
     */
    public function SetWordWrap() {
        if ($this->WordWrap < 1) {
            return;
        }

        switch ($this->message_type) {
            case 'alt':
            case 'alt_attachments':
                $this->AltBody = $this->WrapText($this->AltBody, $this->WordWrap);
                break;
            default:
                $this->Body = $this->WrapText($this->Body, $this->WordWrap);
                break;
        }
    }

    /**
     * Assembles message header.
     * @access public
     * @return string The assembled header
     */
    public function CreateHeader() {
        $result = '';

        // Set the boundaries
        $uniq_id = md5(uniqid(time()));
        $this->boundary[1] = 'b1_' . $uniq_id;
        $this->boundary[2] = 'b2_' . $uniq_id;

        $result .= $this->HeaderLine('Date', self::RFCDate());
        if ($this->Sender == '') {
            $result .= $this->HeaderLine('Return-Path', trim($this->From));
        } else {
            $result .= $this->HeaderLine('Return-Path', trim($this->Sender));
        }

        // To be created automatically by mail()
        if ($this->Mailer != 'mail') {
            if (count($this->to) > 0) {
                $result .= $this->AddrAppend('To', $this->to);
            } elseif (count($this->cc) == 0) {
                $result .= $this->HeaderLine('To', 'undisclosed-recipients:;');
            }
        }

        $from = array();
        $from[0][0] = trim($this->From);
        $from[0][1] = $this->FromName;
        $result .= $this->AddrAppend('From', $from);

        // sendmail and mail() extract Cc from the header before sending
        if (count($this->cc) > 0) {
            $result .= $this->AddrAppend('Cc', $this->cc);
        }

        // sendmail and mail() extract Bcc from the header before sending
        if ((($this->Mailer == 'sendmail') || ($this->Mailer == 'mail')) && (count($this->bcc) > 0)) {
            $result .= $this->AddrAppend('Bcc', $this->bcc);
        }

        if (count($this->ReplyTo) > 0) {
            $result .= $this->AddrAppend('Reply-to', $this->ReplyTo);
        }

        // mail() sets the subject itself
        if ($this->Mailer != 'mail') {
            $result .= $this->HeaderLine('Subject', $this->EncodeHeader($this->SecureHeader($this->Subject)));
        }

        if ($this->MessageID != '') {
            $result .= $this->HeaderLine('Message-ID', $this->MessageID);
        } else {
            $result .= sprintf("Message-ID: <%s@%s>%s", $uniq_id, $this->ServerHostname(), $this->LE);
        }
        $result .= $this->HeaderLine('X-Priority', $this->Priority);
        $result .= $this->HeaderLine('X-Mailer', 'PHPMailer ' . $this->Version . ' (phpmailer.codeworxtech.com)');

        if ($this->ConfirmReadingTo != '') {
            $result .= $this->HeaderLine('Disposition-Notification-To', '<' . trim($this->ConfirmReadingTo) . '>');
        }

        // Add custom headers
        for ($index = 0; $index < count($this->CustomHeader); $index++) {
            $result .= $this->HeaderLine(trim($this->CustomHeader[$index][0]), $this->EncodeHeader(trim($this->CustomHeader[$index][1])));
        }
        if (!$this->sign_key_file) {
            $result .= $this->HeaderLine('MIME-Version', '1.0');
            $result .= $this->GetMailMIME();
        }

        return $result;
    }

    /**
     * Returns the message MIME.
     * @access public
     * @return string
     */
    public function GetMailMIME() {
        $result = '';
        switch ($this->message_type) {
            case 'plain':
                $result .= $this->HeaderLine('Content-Transfer-Encoding', $this->Encoding);
                $result .= sprintf("Content-Type: %s; charset=\"%s\"", $this->ContentType, $this->CharSet);
                break;
            case 'attachments':
            case 'alt_attachments':
                if ($this->InlineImageExists()) {
                    $result .= sprintf("Content-Type: %s;%s\ttype=\"text/html\";%s\tboundary=\"%s\"%s", 'multipart/related', $this->LE, $this->LE, $this->boundary[1], $this->LE);
                } else {
                    $result .= $this->HeaderLine('Content-Type', 'multipart/mixed;');
                    $result .= $this->TextLine("\tboundary=\"" . $this->boundary[1] . '"');
                }
                break;
            case 'alt':
                $result .= $this->HeaderLine('Content-Type', 'multipart/alternative;');
                $result .= $this->TextLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
        }

        if ($this->Mailer != 'mail') {
            $result .= $this->LE . $this->LE;
        }

        return $result;
    }

    /**
     * Assembles the message body.  Returns an empty string on failure.
     * @access public
     * @return string The assembled message body
     */
    public function CreateBody() {
        $body = '';

        if ($this->sign_key_file) {
            $body .= $this->GetMailMIME();
        }

        $this->SetWordWrap();

        switch ($this->message_type) {
            case 'alt':
                $body .= $this->GetBoundary($this->boundary[1], '', 'text/plain', '');
                $body .= $this->EncodeString($this->AltBody, $this->Encoding);
                $body .= $this->LE . $this->LE;
                $body .= $this->GetBoundary($this->boundary[1], '', 'text/html', '');
                $body .= $this->EncodeString($this->Body, $this->Encoding);
                $body .= $this->LE . $this->LE;
                $body .= $this->EndBoundary($this->boundary[1]);
                break;
            case 'plain':
                $body .= $this->EncodeString($this->Body, $this->Encoding);
                break;
            case 'attachments':
                $body .= $this->GetBoundary($this->boundary[1], '', '', '');
                $body .= $this->EncodeString($this->Body, $this->Encoding);
                $body .= $this->LE;
                $body .= $this->AttachAll();
                break;
            case 'alt_attachments':
                $body .= sprintf("--%s%s", $this->boundary[1], $this->LE);
                $body .= sprintf("Content-Type: %s;%s" . "\tboundary=\"%s\"%s", 'multipart/alternative', $this->LE, $this->boundary[2], $this->LE . $this->LE);
                $body .= $this->GetBoundary($this->boundary[2], '', 'text/plain', '') . $this->LE; // Create text body
                $body .= $this->EncodeString($this->AltBody, $this->Encoding);
                $body .= $this->LE . $this->LE;
                $body .= $this->GetBoundary($this->boundary[2], '', 'text/html', '') . $this->LE; // Create the HTML body
                $body .= $this->EncodeString($this->Body, $this->Encoding);
                $body .= $this->LE . $this->LE;
                $body .= $this->EndBoundary($this->boundary[2]);
                $body .= $this->AttachAll();
                break;
        }

        if ($this->IsError()) {
            $body = '';
        } elseif ($this->sign_key_file) {
            try {
                $file = tempnam('', 'mail');
                file_put_contents($file, $body); //TODO check this worked
                $signed = tempnam("", "signed");
                if (@openssl_pkcs7_sign($file, $signed, "file://" . $this->sign_cert_file, array("file://" . $this->sign_key_file, $this->sign_key_pass), NULL)) {
                    @unlink($file);
                    @unlink($signed);
                    $body = file_get_contents($signed);
                } else {
                    @unlink($file);
                    @unlink($signed);
                    throw new phpmailerException($this->Lang("signing") . openssl_error_string());
                }
            } catch (phpmailerException $e) {
                $body = '';
                if ($this->exceptions) {
                    throw $e;
                }
            }
        }

        return $body;
    }

    /**
     * Returns the start of a message boundary.
     * @access private
     */
    private function GetBoundary($boundary, $charSet, $contentType, $encoding) {
        $result = '';
        if ($charSet == '') {
            $charSet = $this->CharSet;
        }
        if ($contentType == '') {
            $contentType = $this->ContentType;
        }
        if ($encoding == '') {
            $encoding = $this->Encoding;
        }
        $result .= $this->TextLine('--' . $boundary);
        $result .= sprintf("Content-Type: %s; charset = \"%s\"", $contentType, $charSet);
        $result .= $this->LE;
        $result .= $this->HeaderLine('Content-Transfer-Encoding', $encoding);
        $result .= $this->LE;

        return $result;
    }

    /**
     * Returns the end of a message boundary.
     * @access private
     */
    private function EndBoundary($boundary) {
        return $this->LE . '--' . $boundary . '--' . $this->LE;
    }

    /**
     * Sets the message type.
     * @access private
     * @return void
     */
    private function SetMessageType() {
        if (count($this->attachment) < 1 && strlen($this->AltBody) < 1) {
            $this->message_type = 'plain';
        } else {
            if (count($this->attachment) > 0) {
                $this->message_type = 'attachments';
            }
            if (strlen($this->AltBody) > 0 && count($this->attachment) < 1) {
                $this->message_type = 'alt';
            }
            if (strlen($this->AltBody) > 0 && count($this->attachment) > 0) {
                $this->message_type = 'alt_attachments';
            }
        }
    }

    /**
     *  Returns a formatted header line.
     * @access public
     * @return string
     */
    public function HeaderLine($name, $value) {
        return $name . ': ' . $value . $this->LE;
    }

    /**
     * Returns a formatted mail line.
     * @access public
     * @return string
     */
    public function TextLine($value) {
        return $value . $this->LE;
    }

    /////////////////////////////////////////////////
    // CLASS METHODS, ATTACHMENTS
    /////////////////////////////////////////////////

    /**
     * Adds an attachment from a path on the filesystem.
     * Returns false if the file could not be found
     * or accessed.
     * @param string $path Path to the attachment.
     * @param string $name Overrides the attachment name.
     * @param string $encoding File encoding (see $Encoding).
     * @param string $type File extension (MIME) type.
     * @return bool
     */
    public function AddAttachment($path, $name = '', $encoding = 'base64', $type = 'application/octet-stream') {
        try {
            if (!@is_file($path)) {
                throw new phpmailerException($this->Lang('file_access') . $path, self::STOP_CONTINUE);
            }
            $filename = basename($path);
            if ($name == '') {
                $name = $filename;
            }

            $this->attachment[] = array(
                0 => $path,
                1 => $filename,
                2 => $name,
                3 => $encoding,
                4 => $type,
                5 => false, // isStringAttachment
                6 => 'attachment',
                7 => 0
            );
        } catch (phpmailerException $e) {
            $this->SetError($e->getMessage());
            if ($this->exceptions) {
                throw $e;
            }
            echo $e->getMessage() . "\n";
            if ($e->getCode() == self::STOP_CRITICAL) {
                return false;
            }
        }
        return true;
    }

    /**
     * Return the current array of attachments
     * @return array
     */
    public function GetAttachments() {
        return $this->attachment;
    }

    /**
     * Attaches all fs, string, and binary attachments to the message.
     * Returns an empty string on failure.
     * @access private
     * @return string
     */
    private function AttachAll() {
        // Return text of body
        $mime = array();
        $cidUniq = array();
        $incl = array();

        // Add all attachments
        foreach ($this->attachment as $attachment) {
            // Check for string attachment
            $bString = $attachment[5];
            if ($bString) {
                $string = $attachment[0];
            } else {
                $path = $attachment[0];
            }

            if (in_array($attachment[0], $incl)) {
                continue;
            }
            $filename = $attachment[1];
            $name = $attachment[2];
            $encoding = $attachment[3];
            $type = $attachment[4];
            $disposition = $attachment[6];
            $cid = $attachment[7];
            $incl[] = $attachment[0];
            if ($disposition == 'inline' && isset($cidUniq[$cid])) {
                continue;
            }
            $cidUniq[$cid] = true;

            $mime[] = sprintf("--%s%s", $this->boundary[1], $this->LE);
            $mime[] = sprintf("Content-Type: %s; name=\"%s\"%s", $type, $this->EncodeHeader($this->SecureHeader($name)), $this->LE);
            $mime[] = sprintf("Content-Transfer-Encoding: %s%s", $encoding, $this->LE);

            if ($disposition == 'inline') {
                $mime[] = sprintf("Content-ID: <%s>%s", $cid, $this->LE);
            }

            $mime[] = sprintf("Content-Disposition: %s; filename=\"%s\"%s", $disposition, $this->EncodeHeader($this->SecureHeader($name)), $this->LE . $this->LE);

            // Encode as string attachment
            if ($bString) {
                $mime[] = $this->EncodeString($string, $encoding);
                if ($this->IsError()) {
                    return '';
                }
                $mime[] = $this->LE . $this->LE;
            } else {
                $mime[] = $this->EncodeFile($path, $encoding);
                if ($this->IsError()) {
                    return '';
                }
                $mime[] = $this->LE . $this->LE;
            }
        }

        $mime[] = sprintf("--%s--%s", $this->boundary[1], $this->LE);

        return join('', $mime);
    }

    /**
     * Encodes attachment in requested format.
     * Returns an empty string on failure.
     * @param string $path The full path to the file
     * @param string $encoding The encoding to use; one of 'base64', '7bit', '8bit', 'binary', 'quoted-printable'
     * @see EncodeFile()
     * @access private
     * @return string
     */
    private function EncodeFile($path, $encoding = 'base64') {
        try {
            if (!is_readable($path)) {
                throw new phpmailerException($this->Lang('file_open') . $path, self::STOP_CONTINUE);
            }
            if (function_exists('get_magic_quotes')) {

                function get_magic_quotes() {
                    return false;
                }

            }
            if (PHP_VERSION < 6) {
                $magic_quotes = get_magic_quotes_runtime();
                set_magic_quotes_runtime(0);
            }
            $file_buffer = file_get_contents($path);
            $file_buffer = $this->EncodeString($file_buffer, $encoding);
            if (PHP_VERSION < 6) {
                set_magic_quotes_runtime($magic_quotes);
            }
            return $file_buffer;
        } catch (Exception $e) {
            $this->SetError($e->getMessage());
            return '';
        }
    }

    /**
     * Encodes string to requested format.
     * Returns an empty string on failure.
     * @param string $str The text to encode
     * @param string $encoding The encoding to use; one of 'base64', '7bit', '8bit', 'binary', 'quoted-printable'
     * @access public
     * @return string
     */
    public function EncodeString($str, $encoding = 'base64') {
        $encoded = '';
        switch (strtolower($encoding)) {
            case 'base64':
                $encoded = chunk_split(base64_encode($str), 76, $this->LE);
                break;
            case '7bit':
            case '8bit':
                $encoded = $this->FixEOL($str);
                //Make sure it ends with a line break
                if (substr($encoded, -(strlen($this->LE))) != $this->LE)
                    $encoded .= $this->LE;
                break;
            case 'binary':
                $encoded = $str;
                break;
            case 'quoted-printable':
                $encoded = $this->EncodeQP($str);
                break;
            default:
                $this->SetError($this->Lang('encoding') . $encoding);
                break;
        }
        return $encoded;
    }

    /**
     * Encode a header string to best (shortest) of Q, B, quoted or none.
     * @access public
     * @return string
     */
    public function EncodeHeader($str, $position = 'text') {
        $x = 0;

        switch (strtolower($position)) {
            case 'phrase':
                if (!preg_match('/[\200-\377]/', $str)) {
                    // Can't use addslashes as we don't know what value has magic_quotes_sybase
                    $encoded = addcslashes($str, "\0..\37\177\\\"");
                    if (($str == $encoded) && !preg_match('/[^A-Za-z0-9!#$%&\'*+\/=?^_`{|}~ -]/', $str)) {
                        return ($encoded);
                    } else {
                        return ("\"$encoded\"");
                    }
                }
                $x = preg_match_all('/[^\040\041\043-\133\135-\176]/', $str, $matches);
                break;
            case 'comment':
                $x = preg_match_all('/[()"]/', $str, $matches);
            // Fall-through
            case 'text':
            default:
                $x += preg_match_all('/[\000-\010\013\014\016-\037\177-\377]/', $str, $matches);
                break;
        }

        if ($x == 0) {
            return ($str);
        }

        $maxlen = 75 - 7 - strlen($this->CharSet);
        // Try to select the encoding which should produce the shortest output
        if (strlen($str) / 3 < $x) {
            $encoding = 'B';
            if (function_exists('mb_strlen') && $this->HasMultiBytes($str)) {
                // Use a custom function which correctly encodes and wraps long
                // multibyte strings without breaking lines within a character
                $encoded = $this->Base64EncodeWrapMB($str);
            } else {
                $encoded = base64_encode($str);
                $maxlen -= $maxlen % 4;
                $encoded = trim(chunk_split($encoded, $maxlen, "\n"));
            }
        } else {
            $encoding = 'Q';
            $encoded = $this->EncodeQ($str, $position);
            $encoded = $this->WrapText($encoded, $maxlen, true);
            $encoded = str_replace('=' . $this->LE, "\n", trim($encoded));
        }

        $encoded = preg_replace('/^(.*)$/m', " =?" . $this->CharSet . "?$encoding?\\1?=", $encoded);
        $encoded = trim(str_replace("\n", $this->LE, $encoded));

        return $encoded;
    }

    /**
     * Checks if a string contains multibyte characters.
     * @access public
     * @param string $str multi-byte text to wrap encode
     * @return bool
     */
    public function HasMultiBytes($str) {
        if (function_exists('mb_strlen')) {
            return (strlen($str) > mb_strlen($str, $this->CharSet));
        } else { // Assume no multibytes (we can't handle without mbstring functions anyway)
            return false;
        }
    }

    /**
     * Correctly encodes and wraps long multibyte strings for mail headers
     * without breaking lines within a character.
     * Adapted from a function by paravoid at http://uk.php.net/manual/en/function.mb-encode-mimeheader.php
     * @access public
     * @param string $str multi-byte text to wrap encode
     * @return string
     */
    public function Base64EncodeWrapMB($str) {
        $start = "=?" . $this->CharSet . "?B?";
        $end = "?=";
        $encoded = "";

        $mb_length = mb_strlen($str, $this->CharSet);
        // Each line must have length <= 75, including $start and $end
        $length = 75 - strlen($start) - strlen($end);
        // Average multi-byte ratio
        $ratio = $mb_length / strlen($str);
        // Base64 has a 4:3 ratio
        $offset = $avgLength = floor($length * $ratio * .75);

        for ($i = 0; $i < $mb_length; $i += $offset) {
            $lookBack = 0;

            do {
                $offset = $avgLength - $lookBack;
                $chunk = mb_substr($str, $i, $offset, $this->CharSet);
                $chunk = base64_encode($chunk);
                $lookBack++;
            } while (strlen($chunk) > $length);

            $encoded .= $chunk . $this->LE;
        }

        // Chomp the last linefeed
        $encoded = substr($encoded, 0, -strlen($this->LE));
        return $encoded;
    }

    /**
     * Encode string to quoted-printable.
     * Only uses standard PHP, slow, but will always work
     * @access public
     * @param string $string the text to encode
     * @param integer $line_max Number of chars allowed on a line before wrapping
     * @return string
     */
    public function EncodeQPphp($input = '', $line_max = 76, $space_conv = false) {
        $hex = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F');
        $lines = preg_split('/(?:\r\n|\r|\n)/', $input);
        $eol = "\r\n";
        $escape = '=';
        $output = '';
        while (list(, $line) = each($lines)) {
            $linlen = strlen($line);
            $newline = '';
            for ($i = 0; $i < $linlen; $i++) {
                $c = substr($line, $i, 1);
                $dec = ord($c);
                if (( $i == 0 ) && ( $dec == 46 )) { // convert first point in the line into =2E
                    $c = '=2E';
                }
                if ($dec == 32) {
                    if ($i == ( $linlen - 1 )) { // convert space at eol only
                        $c = '=20';
                    } else if ($space_conv) {
                        $c = '=20';
                    }
                } elseif (($dec == 61) || ($dec < 32 ) || ($dec > 126)) { // always encode "\t", which is *not* required
                    $h2 = floor($dec / 16);
                    $h1 = floor($dec % 16);
                    $c = $escape . $hex[$h2] . $hex[$h1];
                }
                if ((strlen($newline) + strlen($c)) >= $line_max) { // CRLF is not counted
                    $output .= $newline . $escape . $eol; //  soft line break; " =\r\n" is okay
                    $newline = '';
                    // check if newline first character will be point or not
                    if ($dec == 46) {
                        $c = '=2E';
                    }
                }
                $newline .= $c;
            } // end of for
            $output .= $newline . $eol;
        } // end of while
        return $output;
    }

    /**
     * Encode string to RFC2045 (6.7) quoted-printable format
     * Uses a PHP5 stream filter to do the encoding about 64x faster than the old version
     * Also results in same content as you started with after decoding
     * @see EncodeQPphp()
     * @access public
     * @param string $string the text to encode
     * @param integer $line_max Number of chars allowed on a line before wrapping
     * @param boolean $space_conv Dummy param for compatibility with existing EncodeQP function
     * @return string
     * @author Marcus Bointon
     */
    public function EncodeQP($string, $line_max = 76, $space_conv = false) {
        if (function_exists('quoted_printable_encode')) { //Use native function if it's available (>= PHP5.3)
            return quoted_printable_encode($string);
        }
        $filters = stream_get_filters();
        if (!in_array('convert.*', $filters)) { //Got convert stream filter?
            return $this->EncodeQPphp($string, $line_max, $space_conv); //Fall back to old implementation
        }
        $fp = fopen('php://temp/', 'r+');
        $string = preg_replace('/\r\n?/', $this->LE, $string); //Normalise line breaks
        $params = array('line-length' => $line_max, 'line-break-chars' => $this->LE);
        $s = stream_filter_append($fp, 'convert.quoted-printable-encode', STREAM_FILTER_READ, $params);
        fputs($fp, $string);
        rewind($fp);
        $out = stream_get_contents($fp);
        stream_filter_remove($s);
        $out = preg_replace('/^\./m', '=2E', $out); //Encode . if it is first char on a line, workaround for bug in Exchange
        fclose($fp);
        return $out;
    }

    /**
     * Encode string to q encoding.
     * @link http://tools.ietf.org/html/rfc2047
     * @param string $str the text to encode
     * @param string $position Where the text is going to be used, see the RFC for what that means
     * @access public
     * @return string
     */
    public function EncodeQ($str, $position = 'text') {
        // There should not be any EOL in the string
        $encoded = preg_replace('/[\r\n]*/', '', $str);

        switch (strtolower($position)) {
            case 'phrase':
                $encoded = preg_replace("/([^A-Za-z0-9!*+\/ -])/e", "'='.sprintf('%02X', ord('\\1'))", $encoded);
                break;
            case 'comment':
                $encoded = preg_replace("/([\(\)\"])/e", "'='.sprintf('%02X', ord('\\1'))", $encoded);
            case 'text':
            default:
                // Replace every high ascii, control =, ? and _ characters
                //TODO using /e (equivalent to eval()) is probably not a good idea
                $encoded = preg_replace('/([\000-\011\013\014\016-\037\075\077\137\177-\377])/e', "'='.sprintf('%02X', ord('\\1'))", $encoded);
                break;
        }

        // Replace every spaces to _ (more readable than =20)
        $encoded = str_replace(' ', '_', $encoded);

        return $encoded;
    }

    /**
     * Adds a string or binary attachment (non-filesystem) to the list.
     * This method can be used to attach ascii or binary data,
     * such as a BLOB record from a database.
     * @param string $string String attachment data.
     * @param string $filename Name of the attachment.
     * @param string $encoding File encoding (see $Encoding).
     * @param string $type File extension (MIME) type.
     * @return void
     */
    public function AddStringAttachment($string, $filename, $encoding = 'base64', $type = 'application/octet-stream') {
        // Append to $attachment array
        $this->attachment[] = array(
            0 => $string,
            1 => $filename,
            2 => $filename,
            3 => $encoding,
            4 => $type,
            5 => true, // isStringAttachment
            6 => 'attachment',
            7 => 0
        );
    }

    /**
     * Adds an embedded attachment.  This can include images, sounds, and
     * just about any other document.  Make sure to set the $type to an
     * image type.  For JPEG images use "image/jpeg" and for GIF images
     * use "image/gif".
     * @param string $path Path to the attachment.
     * @param string $cid Content ID of the attachment.  Use this to identify
     *        the Id for accessing the image in an HTML form.
     * @param string $name Overrides the attachment name.
     * @param string $encoding File encoding (see $Encoding).
     * @param string $type File extension (MIME) type.
     * @return bool
     */
    public function AddEmbeddedImage($path, $cid, $name = '', $encoding = 'base64', $type = 'application/octet-stream') {

        if (!@is_file($path)) {
            $this->SetError($this->Lang('file_access') . $path);
            return false;
        }

        $filename = basename($path);
        if ($name == '') {
            $name = $filename;
        }

        // Append to $attachment array
        $this->attachment[] = array(
            0 => $path,
            1 => $filename,
            2 => $name,
            3 => $encoding,
            4 => $type,
            5 => false, // isStringAttachment
            6 => 'inline',
            7 => $cid
        );

        return true;
    }

    /**
     * Returns true if an inline attachment is present.
     * @access public
     * @return bool
     */
    public function InlineImageExists() {
        foreach ($this->attachment as $attachment) {
            if ($attachment[6] == 'inline') {
                return true;
            }
        }
        return false;
    }

    /////////////////////////////////////////////////
    // CLASS METHODS, MESSAGE RESET
    /////////////////////////////////////////////////

    /**
     * Clears all recipients assigned in the TO array.  Returns void.
     * @return void
     */
    public function ClearAddresses() {
        foreach ($this->to as $to) {
            unset($this->all_recipients[strtolower($to[0])]);
        }
        $this->to = array();
    }

    /**
     * Clears all recipients assigned in the CC array.  Returns void.
     * @return void
     */
    public function ClearCCs() {
        foreach ($this->cc as $cc) {
            unset($this->all_recipients[strtolower($cc[0])]);
        }
        $this->cc = array();
    }

    /**
     * Clears all recipients assigned in the BCC array.  Returns void.
     * @return void
     */
    public function ClearBCCs() {
        foreach ($this->bcc as $bcc) {
            unset($this->all_recipients[strtolower($bcc[0])]);
        }
        $this->bcc = array();
    }

    /**
     * Clears all recipients assigned in the ReplyTo array.  Returns void.
     * @return void
     */
    public function ClearReplyTos() {
        $this->ReplyTo = array();
    }

    /**
     * Clears all recipients assigned in the TO, CC and BCC
     * array.  Returns void.
     * @return void
     */
    public function ClearAllRecipients() {
        $this->to = array();
        $this->cc = array();
        $this->bcc = array();
        $this->all_recipients = array();
    }

    /**
     * Clears all previously set filesystem, string, and binary
     * attachments.  Returns void.
     * @return void
     */
    public function ClearAttachments() {
        $this->attachment = array();
    }

    /**
     * Clears all custom headers.  Returns void.
     * @return void
     */
    public function ClearCustomHeaders() {
        $this->CustomHeader = array();
    }

    /////////////////////////////////////////////////
    // CLASS METHODS, MISCELLANEOUS
    /////////////////////////////////////////////////

    /**
     * Adds the error message to the error container.
     * @access protected
     * @return void
     */
    protected function SetError($msg) {
        $this->error_count++;
        if ($this->Mailer == 'smtp' and ! is_null($this->smtp)) {
            $lasterror = $this->smtp->getError();
            if (!empty($lasterror) and array_key_exists('smtp_msg', $lasterror)) {
                $msg .= '<p>' . $this->Lang('smtp_error') . $lasterror['smtp_msg'] . "</p>\n";
            }
        }
        $this->ErrorInfo = $msg;
    }

    /**
     * Returns the proper RFC 822 formatted date.
     * @access public
     * @return string
     * @static
     */
    public static function RFCDate() {
        $tz = date('Z');
        $tzs = ($tz < 0) ? '-' : '+';
        $tz = abs($tz);
        $tz = (int) ($tz / 3600) * 100 + ($tz % 3600) / 60;
        $result = sprintf("%s %s%04d", date('D, j M Y H:i:s'), $tzs, $tz);

        return $result;
    }

    /**
     * Returns the server hostname or 'localhost.localdomain' if unknown.
     * @access private
     * @return string
     */
    private function ServerHostname() {
        if (!empty($this->Hostname)) {
            $result = $this->Hostname;
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $result = $_SERVER['SERVER_NAME'];
        } else {
            $result = 'localhost.localdomain';
        }

        return $result;
    }

    /**
     * Returns a message in the appropriate language.
     * @access private
     * @return string
     */
    private function Lang($key) {
        if (count($this->language) < 1) {
            $this->SetLanguage('en'); // set the default language
        }

        if (isset($this->language[$key])) {
            return $this->language[$key];
        } else {
            return 'Language string failed to load: ' . $key;
        }
    }

    /**
     * Returns true if an error occurred.
     * @access public
     * @return bool
     */
    public function IsError() {
        return ($this->error_count > 0);
    }

    /**
     * Changes every end of line from CR or LF to CRLF.
     * @access private
     * @return string
     */
    private function FixEOL($str) {
        $str = str_replace("\r\n", "\n", $str);
        $str = str_replace("\r", "\n", $str);
        $str = str_replace("\n", $this->LE, $str);
        return $str;
    }

    /**
     * Adds a custom header.
     * @access public
     * @return void
     */
    public function AddCustomHeader($custom_header) {
        $this->CustomHeader[] = explode(':', $custom_header, 2);
    }

    /**
     * Evaluates the message and returns modifications for inline images and backgrounds
     * @access public
     * @return $message
     */
    public function MsgHTML($message, $basedir = '') {
        preg_match_all("/(src|background)=\"(.*)\"/Ui", $message, $images);
        if (isset($images[2])) {
            foreach ($images[2] as $i => $url) {
                // do not change urls for absolute images (thanks to corvuscorax)
                if (!preg_match('#^[A-z]+://#', $url)) {
                    $filename = basename($url);
                    $directory = dirname($url);
                    ($directory == '.') ? $directory = '' : '';
                    $cid = 'cid:' . md5($filename);
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    $mimeType = self::_mime_types($ext);
                    if (strlen($basedir) > 1 && substr($basedir, -1) != '/') {
                        $basedir .= '/';
                    }
                    if (strlen($directory) > 1 && substr($directory, -1) != '/') {
                        $directory .= '/';
                    }
                    if ($this->AddEmbeddedImage($basedir . $directory . $filename, md5($filename), $filename, 'base64', $mimeType)) {
                        $message = preg_replace("/" . $images[1][$i] . "=\"" . preg_quote($url, '/') . "\"/Ui", $images[1][$i] . "=\"" . $cid . "\"", $message);
                    }
                }
            }
        }
        $this->IsHTML(true);
        $this->Body = $message;
        $textMsg = trim(strip_tags(preg_replace('/<(head|title|style|script)[^>]*>.*?<\/\\1>/s', '', $message)));
        if (!empty($textMsg) && empty($this->AltBody)) {
            $this->AltBody = html_entity_decode($textMsg);
        }
        if (empty($this->AltBody)) {
            $this->AltBody = 'To view this email message, open it in a program that understands HTML!' . "\n\n";
        }
    }

    /**
     * Gets the MIME type of the embedded or inline image
     * @param string File extension
     * @access public
     * @return string MIME type of ext
     * @static
     */
    public static function _mime_types($ext = '') {
        $mimes = array(
            'hqx' => 'application/mac-binhex40',
            'cpt' => 'application/mac-compactpro',
            'doc' => 'application/msword',
            'bin' => 'application/macbinary',
            'dms' => 'application/octet-stream',
            'lha' => 'application/octet-stream',
            'lzh' => 'application/octet-stream',
            'exe' => 'application/octet-stream',
            'class' => 'application/octet-stream',
            'psd' => 'application/octet-stream',
            'so' => 'application/octet-stream',
            'sea' => 'application/octet-stream',
            'dll' => 'application/octet-stream',
            'oda' => 'application/oda',
            'pdf' => 'application/pdf',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            'smi' => 'application/smil',
            'smil' => 'application/smil',
            'mif' => 'application/vnd.mif',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
            'wbxml' => 'application/vnd.wap.wbxml',
            'wmlc' => 'application/vnd.wap.wmlc',
            'dcr' => 'application/x-director',
            'dir' => 'application/x-director',
            'dxr' => 'application/x-director',
            'dvi' => 'application/x-dvi',
            'gtar' => 'application/x-gtar',
            'php' => 'application/x-httpd-php',
            'php4' => 'application/x-httpd-php',
            'php3' => 'application/x-httpd-php',
            'phtml' => 'application/x-httpd-php',
            'phps' => 'application/x-httpd-php-source',
            'js' => 'application/x-javascript',
            'swf' => 'application/x-shockwave-flash',
            'sit' => 'application/x-stuffit',
            'tar' => 'application/x-tar',
            'tgz' => 'application/x-tar',
            'xhtml' => 'application/xhtml+xml',
            'xht' => 'application/xhtml+xml',
            'zip' => 'application/zip',
            'mid' => 'audio/midi',
            'midi' => 'audio/midi',
            'mpga' => 'audio/mpeg',
            'mp2' => 'audio/mpeg',
            'mp3' => 'audio/mpeg',
            'aif' => 'audio/x-aiff',
            'aiff' => 'audio/x-aiff',
            'aifc' => 'audio/x-aiff',
            'ram' => 'audio/x-pn-realaudio',
            'rm' => 'audio/x-pn-realaudio',
            'rpm' => 'audio/x-pn-realaudio-plugin',
            'ra' => 'audio/x-realaudio',
            'rv' => 'video/vnd.rn-realvideo',
            'wav' => 'audio/x-wav',
            'bmp' => 'image/bmp',
            'gif' => 'image/gif',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'jpe' => 'image/jpeg',
            'png' => 'image/png',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'css' => 'text/css',
            'html' => 'text/html',
            'htm' => 'text/html',
            'shtml' => 'text/html',
            'txt' => 'text/plain',
            'text' => 'text/plain',
            'log' => 'text/plain',
            'rtx' => 'text/richtext',
            'rtf' => 'text/rtf',
            'xml' => 'text/xml',
            'xsl' => 'text/xml',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'mpe' => 'video/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'movie' => 'video/x-sgi-movie',
            'doc' => 'application/msword',
            'word' => 'application/msword',
            'xl' => 'application/excel',
            'eml' => 'message/rfc822'
        );
        return (!isset($mimes[strtolower($ext)])) ? 'application/octet-stream' : $mimes[strtolower($ext)];
    }

    /**
     * Set (or reset) Class Objects (variables)
     *
     * Usage Example:
     * $page->set('X-Priority', '3');
     *
     * @access public
     * @param string $name Parameter Name
     * @param mixed $value Parameter Value
     * NOTE: will not work with arrays, there are no arrays to set/reset
     * @todo Should this not be using __set() magic function?
     */
    public function set($name, $value = '') {
        try {
            if (isset($this->$name)) {
                $this->$name = $value;
            } else {
                throw new phpmailerException($this->Lang('variable_set') . $name, self::STOP_CRITICAL);
            }
        } catch (Exception $e) {
            $this->SetError($e->getMessage());
            if ($e->getCode() == self::STOP_CRITICAL) {
                return false;
            }
        }
        return true;
    }

    /**
     * Strips newlines to prevent header injection.
     * @access public
     * @param string $str String
     * @return string
     */
    public function SecureHeader($str) {
        $str = str_replace("\r", '', $str);
        $str = str_replace("\n", '', $str);
        return trim($str);
    }

    /**
     * Set the private key file and password to sign the message.
     *
     * @access public
     * @param string $key_filename Parameter File Name
     * @param string $key_pass Password for private key
     */
    public function Sign($cert_filename, $key_filename, $key_pass) {
        $this->sign_cert_file = $cert_filename;
        $this->sign_key_file = $key_filename;
        $this->sign_key_pass = $key_pass;
    }

}

class phpmailerException extends Exception {

    public function errorMessage() {
        $errorMsg = '<strong>' . $this->getMessage() . "</strong><br />\n";
        return $errorMsg;
    }

}

?>
