<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of mobile_edition
 *
 * @author lijingrun
 */
class Mobile_editionApp  extends StorebaseApp {
    
    
    function index(){
        $edition_model = & m('edition');
        $store_id = $_GET['store_id'];
        if(empty($store_id)){
            $this->show_warning('页面错误！');
        }
        if(empty($store_id)){
            $this->show_warning('该店铺没有产品展示！');
            return;
        }
        $conditions = "store_id =".$store_id;
        $keyword = $_GET['keyword'];
        if(!empty($keyword)){
            $conditions .= " AND edition_with like '%$keyword%'";
        }
        $pagesize = 10;
        $page = $_GET['page'];
        if (empty($page)) {
            $page = 1;
        }
        $limit = ($page - 1) * $pagesize . "," . $pagesize;
        if ($page <= 1) {
            $prev_page = 1;
        } else {
            $prev_page = $page - 1;
        }
        $all_editions = $edition_model->find(array(
            'conditions' => $conditions,
            'fields' => 'edition_id',
        ));

        $totalcount = count($all_editions);
        $totalpage = ceil($totalcount / $pagesize);
        if ($page >= $totalpage) {
            $next_page = $totalpage;
        } else {
            $next_page = $page + 1;
        }
        $editions = $edition_model->find(array(
            'conditions' => $conditions,
            'limit' => $limit,
            'order' => "orders",
        ));
        $page_info = array(
            'page' => $page,
            'totalpage' => $totalpage,
            'totalcount' => $totalcount,
            'next_page' => $next_page,
            'prev_page' => $prev_page,
        );
        if(empty($editions)){
            $this->show_warning('商家还未设置任何产品展示内容');
            return;
        }
        $this->assign('store_id', $store_id);
        $this->assign('store_id', $store_id);
        $this->assign('keyword', $keyword);
        $this->assign('page_info', $page_info);
        $this->assign('editions', $editions);
        $this->display('edition_list.html');
    }
    
        //手机页面详细情况页面
    function details() {
        $edition_model = & m('edition');
        $edition_images_model = & m('edition_images');
        $id = $_GET['id'];
        $edition = $edition_model->get('edition_id =' . $id);
        $images = $edition_images_model->find('edition_id =' . $id . " AND status=1");
        $effect_imgs = $edition_images_model->find('edition_id =' . $id . " AND status=2");
        $this->assign('store_id', $edition['store_id']);
        $this->assign('effect_imgs', $effect_imgs);
        $this->assign('images', $images);
        $this->assign('id', $id);
        $this->assign('edition', $edition);
        $this->display('edition_details.html');
    }
    
    //手机详细产品样式
    function img_detail(){
        $id = $_GET['id'];  //版本id
        $img_id = $_GET['img_id'];  //图片id
        $edition_img_model = & m('edition_images');
        //如果有图片的id，直接就查该图片，无需其他动作
        if(!empty($img_id)){
            $edition_img = $edition_img_model->get("id = ".$img_id);
            $next_img = $edition_img_model->get("edition_id =".$id." AND status = 1 AND id > ".$img_id." order by id");
            $prev_img = $edition_img_model->get("edition_id =".$id." AND status = 1 AND id < ".$img_id." order by id desc");
        }else{
            $this->show_warning("找不到该图片！");
        }
        $this->assign('id', $id);
        if(empty($prev_img)){
            $prev_img = $edition_img;
        }
        if(empty($next_img)){
            $next_img = $edition_img;
        }
        $this->assign('prev_img', $prev_img);
        $this->assign("edition_img", $edition_img);
        $this->assign("next_img", $next_img);
        $this->display("edition_img.html");
    }
    
}
