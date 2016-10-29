<?php

class ImportApp extends BackendApp {

    function index() {
        $redeem_orders_model = & m('redeem_orders');
        if (!IS_POST) {

            $this->display('import.html');
        } else {
            $file = $_FILES['excel_file'];
            $filesuffix = substr(strchr($file['name'], "."), 1);   //1.1.xls 的文件为 1.xls
            //判断导入文件格式
            if (!empty($filesuffix) && ($filesuffix == 'xls' || $filesuffix == 'xlsx')) {
                //导入各种excel工具
                import('PHPExcel');
                import('PHPExcel/Reader/Excel2007');
                import('PHPExcel/Reader/Excel5');

                //新建Excel类
                $PHPExcel = new PHPExcel();
                //新建读取类
                if ($filesuffix == 'xls') {
                    $phpRead = new PHPExcel_Reader_Excel5();
                } else {
                    $phpRead = new PHPExcel_Reader_Excel2007();
                }
                //读取文件
                $PHPExcel = $phpRead->load($file['tmp_name']);
                //获取第一个表
                $sheetExcel = $PHPExcel->getSheet(0);

                // 取得一共有多少列 
                $allColumn = $sheetExcel->getHighestColumn();

                //取得一共有多少行 
                $allRow = $sheetExcel->getHighestRow();

                $allColumn++;
                //由第二行开始获取数据
                $all_data = array(); //整个表的数据
                for ($startRow = 2; $startRow <= $allRow; $startRow++) {
                    $one_data = array(); //一行数据
                    for ($startCol = 'A'; $startCol != $allColumn; $startCol++) {
                        $name = trim($sheetExcel->getCell($startCol . $startRow)->getValue()); //获取内容，2边去空格
                        $one_data[] = $name;
                    }
                    $all_data[] = $one_data;
                }
                $success_nums = 0; //成功条数
                $failure_nums = 0; //失败条数
                //获取到所有的数据之后，针对这些数据做一个循环，查找对应订单号未发货的所有订单记录，添加发货信息
//                foreach ($all_data as $val):
//                    //凭订单id找出待发货订单
//                    $good = $redeem_orders_model->find('order_id =' . $val[4] . ' AND status = 20');
//                    if (!empty($good)) {
//                        $good = current($good);
//                        $good['logistics_num'] = $val[5];  //物流单号
//                        $good['out_man_k3'] = $val[6];  //发货人k3码
//                        $good['out_phone'] = $val[7];   //发货人电话
//                        $good['out_man'] = $val[8];  //发货人
//                        $good['logistics_name'] = $val[9];  //物流公司
//                        $good['logistics_phone'] = $val[10]; //物流电话
//                        $good['out_time'] = time();
//
//                        $good['get_time'] = $val[12];    //预达时间
//                        $good['status'] = 30;   //已经发货就改变发货噶状态
//                        //保存发货信息
//                        $redeem_orders_model->edit($val[4], $good);
//                        $success_nums++;
//                    } else {
//                        $failure_nums++;
//                    }
//                endforeach;
//                $this->show_message($success_nums . '个订单成功发货', '返回订单页面', 'index.php?app=redeem&act=orders');
//                print_r($all_data);exit;
                $member_model = & m('member');
                foreach($all_data as $val):
                    if(!empty($val[0])){
                        $member_model->edit($val[0],'point ='.$val[3]);
                    }
                endforeach;
                echo "搞掂！";exit;
            } else {
                $this->show_warning('导入文件格式错误，请导入.xls或者.xlsx文件');
                return;
            }
        }
    }

    function redeem() {
        if (!IS_POST) {
            $this->display('import_redeem.html');
        } else {
            $file = $_FILES['import_file'];
            $filesuffix = substr(strchr($file['name'], "."), 1);   //1.1.xls 的文件为 1.xls
            //判断导入文件格式
            if (!empty($filesuffix) && ($filesuffix == 'xls' || $filesuffix == 'xlsx')) {
                //导入各种excel工具
                import('PHPExcel');
                import('PHPExcel/Reader/Excel2007');
                import('PHPExcel/Reader/Excel5');

                //新建Excel类
                $PHPExcel = new PHPExcel();
                //新建读取类
                if ($filesuffix == 'xls') {
                    $phpRead = new PHPExcel_Reader_Excel5();
                } else {
                    $phpRead = new PHPExcel_Reader_Excel2007();
                }
                //读取文件
                $PHPExcel = $phpRead->load($file['tmp_name']);
                //获取第一个表
                $sheetExcel = $PHPExcel->getSheet(0);

                // 取得一共有多少列 
                $allColumn = $sheetExcel->getHighestColumn();

                //取得一共有多少行 
                $allRow = $sheetExcel->getHighestRow();

                $allColumn++;
                //由第二行开始获取数据
                $all_data = array(); //整个表的数据
                for ($startRow = 2; $startRow <= $allRow; $startRow++) {
                    $one_data = array(); //一行数据
                    for ($startCol = 'A'; $startCol != $allColumn; $startCol++) {
                        $name = trim($sheetExcel->getCell($startCol . $startRow)->getValue()); //获取内容，2边去空格
                        $one_data[] = $name;
                    }
                    $all_data[] = $one_data;
                }
                
                $categort_goods_model = & m('category_goods');
                foreach($all_data as $data):
                    $id =$data[0];
                    if(!empty($data[1])){
                        $categort = array();
                        $categort['goods_id'] = $id;
                        $categort['cate_id'] = $data[1];
                        $categort_goods_model->add($categort);
                    }
                endforeach;
                echo "1111";exit;
                
                
            }
        }
    }

}
