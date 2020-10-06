<?php

namespace FormVibes\Classes;

use FormVibes\Classes\DbManager;
use FormVibes\Classes\Utils; 

class Settings{

	private static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		add_action( 'wp_ajax_fv_save_config', [ $this, 'fv_save_config' ] );
        add_action( 'wp_ajax_fv_save_status', [ $this, 'fv_save_status' ] );
		add_action( 'wp_ajax_fv_save_role_config', [ $this, 'fv_save_role_config' ] );
		add_action( 'wp_ajax_fv_save_exclude_forms', [ $this, 'fv_save_exclude_forms' ] );
		add_action( 'wp_ajax_nopriv_fv_save_exclude_forms', [ $this, 'fv_save_exclude_forms' ] );

		add_action( 'wp_ajax_fv_delete_forms', [ $this, 'fv_delete_forms' ] );
		add_action( 'wp_ajax_nopriv_fv_delete_forms', [ $this, 'fv_delete_forms' ] );

        //add_action( 'admin_init', [$this, 'license_update']);

        //register_activation_hook(WPV_FV_FILE, [ $this, 'plugin_activated']);
    }

	public function fv_save_status(){
        global $wpdb;
		if(current_user_can( 'manage_options' ) && check_ajax_referer('fv_ajax_nonce','ajax_nonce')) {
			$wpdb->update( $wpdb->prefix . 'fv_enteries', array( 'fv_status' => sanitize_text_field( $_REQUEST['value'] ) ), array( 'id' => sanitize_text_field( $_REQUEST['id'] ) ) );
		}
    }

    public function fv_save_configuration( $params ) {
        $gdpr['ip'] = $params['ip'];
        $gdpr['ua'] = $params['userAgent'];
        $gdpr['debug_mode'] = $params['debugMode'];
        $gdpr['export_reason'] = $params['csvExportReason'];
        $gdpr['auto_refresh'] = $params['autoRefresh'];
        $gdpr['auto_refresh_value'] = $params['autoRefreshValue'];
        $widgetData['widget'] = $params['widget'];
        if( current_user_can( 'manage_options') ) {
            update_option( 'fv-db-settings', $widgetData,false);
            update_option( 'fv_gdpr_settings', $gdpr,false);
            return 'saved';
        }
    }

	public function fv_save_config(){
		
		$data = $_REQUEST['data'];

		$widget = $data['widget'];
		$panelData = get_option( 'fv-db-settings');
		
		if($widget === 'yes'){
			$widget = 1;
		}
		
		$defaultForms = Utils::get_first_plugin_form();
		
		for($i = 0; $i < $widget; $i++){
			if(! array_key_exists($i, $panelData['panelData'])) {
				$panelData[ $i ] = [
					'queryType'      => 'Last_30_Days',
					'formName'       => $defaultForms['formName'],
					'selectedPlugin' => $defaultForms['selectedPlugin'],
					'selectedForm'   => $defaultForms['selectedForm']
				];
			}
		}

		$widgetData['panelNumber'] = $widget;
		$widgetData['panelData'] = $panelData;
		
		if(current_user_can( 'manage_options' ) && check_ajax_referer('fv_ajax_nonce','ajax_nonce')){
			update_option( 'fv-db-settings', $widgetData,false);
		}
		
		$saveIp = sanitize_text_field($_REQUEST['data']['ip']);
		$saveUA = sanitize_text_field($_REQUEST['data']['ua']);
		$debug_mode = sanitize_text_field($_REQUEST['data']['debugMode']);
		$export_reason = sanitize_text_field($_REQUEST['data']['exportReason']);
		//update_option( 'fv-ip-save', $saveIp,false);
		$gdpr['ip'] = $saveIp;
		$gdpr['ua'] = $saveUA;
		$gdpr['debug_mode'] = $debug_mode;
		$gdpr['export_reason'] = $export_reason;

		if(current_user_can( 'manage_options' ) && check_ajax_referer('fv_ajax_nonce','ajax_nonce')){
			update_option( 'fv_gdpr_settings', $gdpr,false);
		}
	}

	public function fv_save_exclude_forms(){
		//$forms = sanitize_text_field($_REQUEST['myForms']);
		$forms = $_REQUEST['myForms'];
		if(array_key_exists('cf7',$_REQUEST['myForms'])){
			$forms['cf7'] = array_map('sanitize_text_field',$_REQUEST['myForms']['cf7']);
		}
		if(array_key_exists('elementor',$_REQUEST['myForms'])){
			$forms['elementor'] = array_map('sanitize_text_field',$_REQUEST['myForms']['elementor']);
		}
		if(array_key_exists('beaverBuilder',$_REQUEST['myForms'])){
			$forms['beaverBuilder'] = array_map('sanitize_text_field',$_REQUEST['myForms']['beaverBuilder']);
		}
		if(array_key_exists('wpforms',$_REQUEST['myForms'])){
			$forms['wpforms'] = array_map('sanitize_text_field',$_REQUEST['myForms']['wpforms']);
		}

		if(current_user_can( 'manage_options' ) && check_ajax_referer('fv_ajax_nonce','ajax_nonce')){
			update_option( 'fv_exclude_forms', $forms,false);
		}


	}

	public function fv_save_role_config(){
		//$data = sanitize_text_field($_REQUEST['role_data']);
		if(array_key_exists('editor',$_REQUEST['role_data'])){
			$data['editor'] = array_map('sanitize_text_field',$_REQUEST['role_data']['editor']);
		}
		if(array_key_exists('author',$_REQUEST['role_data'])){
			$data['author'] = array_map('sanitize_text_field',$_REQUEST['role_data']['author']);
		}

		if(current_user_can( 'manage_options' ) && check_ajax_referer('fv_ajax_nonce','ajax_nonce')){
			update_option( 'fv_user_role', $data,false);
		}

	}

	public function fv_delete_forms(){
		$formID = sanitize_text_field($_REQUEST['formId']);
		$plugin = sanitize_text_field( $_REQUEST['plugin']);

		$inserted_forms = get_option('fv_forms');
		unset($inserted_forms[$plugin][$formID]);

		if(current_user_can( 'manage_options' ) && check_ajax_referer('fv_ajax_nonce','ajax_nonce')){
			update_option('fv_forms',$inserted_forms);
		}

	}

}