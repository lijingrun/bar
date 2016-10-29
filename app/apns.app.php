<?php

/**
 *	APNS base
 *
 *    @author    Garbin
 *    @usage    none
 */
class ApnsApp extends MallbaseApp {

	// for apns testing.
	function send() {
		$apns_channel = new Apns();
		$apns_channel->send( $_GET['device_id'], $_GET['msg'] );
	}

	function test_json_send() {
		$apns_channel = new Apns();
		$array = array(
			"title" => "Mall Test",
			"content" => "hello, world", 
			"redirect_url" => "http://126wallpaper.com/"
		);
		$apns_channel->send( 'd5dc4bf71b3ca91c53245907482ee3d96e29e5c22ecb5f5cd86eb8808114ec1b', json_encode($array) ); 
	}
}

?>
