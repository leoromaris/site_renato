<?php
namespace FormVibes\Classes;

class Export {
    function __construct( $params ){
        $this->exportToCsv( $params );
    }

    private function exportToCsv ( $params ) {
        $gdprSettings = get_option( 'fv_gdpr_settings' );
        global $wpdb;
        if ($gdprSettings['export_reason']) {
            $wpdb->insert(
                $wpdb->prefix.'fv_logs',
                array(
                    'user_id' => get_current_user_id(),
                    'event' => 'export',
                    'description' => sanitize_text_field($params['description']),
                    'export_time' => current_time( 'mysql', $gmt = 0 ),
                    'export_time_gmt' => current_time( 'mysql', $gmt = 1 )
                )
            );
        }
	    $plugin =  lcfirst($params['plugin']);
		$form_id =  $params['formid'] ;
        $name = $plugin .'-'. $form_id. '-'. date("Y/m/d");
        $columns = $params['columns'];
        $remove_columns = ['form_plugin', 'form_id'];
        $columns = array_diff($columns, $remove_columns);
        unset($params['columns']);
        $submissions = new Submissions($params['plugin']);
        $data = $submissions->get_submissions($params)['leads'];
        $final_leads = [];
	    $default_columns_vals = [];

	    foreach ($columns as $key => $value){
		    $default_columns_vals[$value] = '';
	    }

	    $plugin_checker = 0;
	    if($plugin == 'ninja'){
		    $plugin_checker = 0;
		    $loop_out = count($data);
	    }
	    else{
		    $loop_out = count($data);
	    }

	    for($i = $plugin_checker; $i < $loop_out; $i++){
	        unset($data[$i]['form_id']);
            unset($data[$i]['form_plugin']);
	        //print_r($data[$i]);
		    $final_leads[] = array_merge($columns, $data[$i]);
	    }
	    $saved_keys_data = get_option('fv-keys');
	    if($saved_keys_data == false){
		    $saved_keys_data = [];
	    }

	    $key_exist = [];
	    if($form_id !== ''){
		    if(array_key_exists($plugin.'_'.$form_id,$saved_keys_data)){
			    $key_exist = $saved_keys_data[$plugin.'_'.$form_id];
		    }
	    }

	    $saved_keys = [];
	    if(count($key_exist)>0){
		    foreach ($key_exist as $key => $value){
			    $saved_keys[$value['colKey']] = $value['alias'];
		    }
		    $o_keys = array_values($columns);
		    $label = [];

		    foreach ($o_keys as $key => $value){
			    if(array_key_exists($value,$saved_keys)){
				    $label[$value] = $saved_keys[$value];
			    }
			    else{
				    $label[$value] = $value;
			    }
		    }
	    }
	    else{
	        $label = array_values($columns);
	    }
	    foreach ( $data as $key => $value ) {
		    $data[$key] = wp_parse_args($value,$default_columns_vals);
	    }

	    if(count($key_exist) <= 0){
		    for ($i=0; $i<count($label); $i++){
			    if($label[$i] == "captured"  || $label[$i] === "datestamp"){
				    $label[$i] = "Submission Date";
			    }
		    }
	    }

        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private', false);
        header('Content-Type: text/csv;charset=utf-8');
        header('Content-Disposition: attachment;filename='.$name.'.csv');

        $fp = fopen( 'php://output', 'w' );
	    if(isset($final_leads[0])) {
		    fwrite($fp, "\xEF\xBB\xBF");
		    fputcsv( $fp, array_values( $label ) );
		    foreach ( $data as $value ) {
			    $value = wp_parse_args($value,$default_columns_vals);
			    fputcsv( $fp, $value,',', '"');
		    }
	    }
	    else{
		    fwrite($fp, "\xEF\xBB\xBF");
		    fputcsv( $fp, array_values( $label ) );

		    fclose( $fp );
	    }

        fclose( $fp );
        $exported_data = ob_get_contents();
        die();
    }
}