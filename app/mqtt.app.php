<?php

/**
 *	Mqtt base
 *
 *    @author    Garbin
 *    @usage    none
 */
class MqttApp extends MallbaseApp {

	// for mqtt testing.
	function test_mqtt_send() {
		$mqtt_channel = new Mqtt();
		$mqtt_channel->send( $_GET['device_id'], $_GET['msg'] );
	}

	function test_json_send() {
		$mqtt_channel = new Mqtt();
		$array = array(
			"title" => "Mall Test",
			"content" => "hello, world", 
			"redirect_url" => "http://126wallpaper.com/"
		);
		$mqtt_channel->send( '5b1bcbb27faed14f', json_encode($array) ); 
	}

	// device log
	function device_login() {
		// js: ajax loading sample
		/*
			$.ajax( { 
				type: 'POST',
				data: {
					device_type: 'mqtt',
					serial_id: '5b1bcbb27faed14f',
					device_model: 'LG Nexus 5',
				},
				url: '?app=mqtt&act=device_login', 
				dataType: 'text',
				success: 
					function(data) { 
						console.log(data); 
					}, 
			});
		 */
		if ($this->visitor->has_login) 
		{
			if (IS_POST) {
				// get posts

				// device type: 'mqtt' or 'apns' ?
				$device_type = $_POST['device_type'];
				$serial_id = $_POST['serial_id'];
				$device_model = $_POST['device_model'];

				// info
				$last_login = gmtime();
				$user_id = $this->visitor->get('user_id');

				// database model
				$dm = & m('devices');

				$device = $dm->get(
					array(
						'conditions' => 'device_type = "'.$device_type.'" AND serial_id = "'.$serial_id.'" ',
						'order' => 'last_login DESC'
				));

				if( !empty($device) ) {
					// have already logged in
					// update last_login
					$dm->edit( $device['id'], array('last_login' => $last_login) ); 
				}
				else {
					$new_device = array(
						'device_type' => $device_type,
						'serial_id' => $serial_id,
						'device_model' => $device_model,
						'last_login' => $last_login,
						'user_id' => $user_id
					);
					if (!$device_id = $dm->add($new_device)) {
						echo false;
						return false;
					}
				}
				echo true;
				return true;
			}
		}
	}
}

?>
