<?php

namespace FormVibes\Integrations;

use FormVibes\Classes\Utils;

abstract class Base {

    protected $plugin_name;
    protected  $ip;
    private $params = [];

    /**
     * @param $args
     */
    public function make_entry( $data ){

        $args = [
            'post_type'   =>  'fv_leads',
            'post_status' =>  'publish'
        ];

        // Insert Post
        $post_id = wp_insert_post( $args );

        // Add Meta Data
        $this->add_meta_entries($post_id, $data );

    }

    public function get_submissions ( $params ) {
	    $forms = [];
        $data['forms_plugin'] = apply_filters('fv_forms', $forms);
        $export = false;
	    if(array_key_exists('export', $params)){
		    $export = $params['export'];
        }
        $temp_params = [
            'plugin' => $params['plugin'],
            'per_page' => $params['per_page'],
            'page_num' => $params['page_num'] == '' ? 1 : $params['page_num'],
            'form_id' =>  $params['formid'],
            'queryType' => $params['query_type'],
            'fromDate' => $params['fromDate'],
            'toDate' => $params['toDate'],
            'export' => $export
        ];
        $data = self::get_data($temp_params);
        $savedColumns = get_option('fv-keys');
        if ( $savedColumns == '' ) {
            $data['isColumnsSaved'] = false;
            return $data;
        } else {
        	if(array_key_exists(lcfirst($params['plugin']).'_'.$params['formid'], $savedColumns)){
		        if ( count($savedColumns[lcfirst($params['plugin']).'_'.$params['formid']]) > 0 ) {
			        $data['isColumnsSaved'] = true;
			        $data['savedColumns'] = $savedColumns[lcfirst($params['plugin']).'_'.$params['formid']];
                    $tempColumns = $data['columns'];
                    $tempSavedColumns = $data['savedColumns'];
                    if ( count($tempColumns) > count($tempSavedColumns) ) {
                        for ( $i = 0; $i < count($tempColumns); ++$i ) {
                            if ( array_search($tempColumns[$i], array_column($tempSavedColumns, 'colKey')) === FALSE) {
                                array_push($data['savedColumns'], array(
                                    'colKey' => $tempColumns[$i],
                                    'alias' => $tempColumns[$i],
                                    'visible' => true
                                ));
                            }
                        }
                    }
                    $gdpr_setting = get_option( 'fv_gdpr_settings');
                    $saveUserAgent = '';
                    $saveIp = '';
                    if($gdpr_setting == false){
                        $saveIp = get_option('fv-ip-save');
                    }
                    else{
                        $saveIp = $gdpr_setting['ip'];
                        $saveUserAgent = $gdpr_setting['ua'];
                    }
                    if ( !$saveUserAgent ) {
                        foreach ( $tempSavedColumns as $key => $value ) {
                            if ( $value['colKey'] == 'user_agent' ) {
                                unset($data['savedColumns'][$key]);
                            }
                        }
                    }
                    if ( !$saveIp ) {
                        foreach ( $tempSavedColumns as $key => $value ) {
                            if ( $value['colKey'] == 'IP' ) {
                                unset($data['savedColumns'][$key]);
                            }
                        }
                    }
                    $data['savedColumns'] = array_values($data['savedColumns']);
			        return $data;
		        }
	        }
        }
        return $data;
    }
    static function get_data( $params ){
        $data = self::get_tbl_data($params);
        return $data;
    }

	static function get_tbl_data( $params ){
		global $wpdb;
		$param = '';
		$param_count_query = '';
		$status_label = get_option('fv_data');
		$gdpr_setting = get_option( 'fv_gdpr_settings');
		$saveUserAgent = '';
		if($gdpr_setting == false){
			$saveIp = get_option('fv-ip-save');
		}
		else{
			$saveIp = $gdpr_setting['ip'];
			$saveUserAgent = $gdpr_setting['ua'];
		}
        $filter_param[] = "meta_key like'%%'";
        $filter_param[] = "meta_value like'%%'";
		if($params['plugin'] !== '' && $params['plugin'] !== null){
			$param_where[] = "form_plugin='".$params['plugin']."'";
			$paramcount_where[] = "form_plugin='".$params['plugin']."'";
			if($params['form_id'] !== '' && $params['form_id'] !== null){
				$param_where[] = "form_id='".$params['form_id']."'";
				$paramcount_where[] = "form_id='".$params['form_id']."'";
			}
		}
		else{
			$res = '';
			$param_where[] = "form_plugin='".$res['plugin']."'";
			$paramcount_where[] = "form_plugin='".$res['plugin']."'";
			if($params['form_id'] == '' || $params['form_id'] !== null){
				$form_id=$res['formid'];
				$param_where[] = "form_id='".$res['formid']."'";
				$paramcount_where[] = "form_id='".$res['formid']."'";
			}
		}

		if($params['fromDate'] !== '' && $params['fromDate'] !== null){
			$param_where[] = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '". $params['fromDate'] . "'";
			$paramcount_where[] = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '". $params['fromDate'] . "'";
		}
		if($params['toDate'] !== '' && $params['toDate'] !== null){
			if($params['fromDate'] !== ''){
				$param_where[] = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '". $params['toDate'] . "'";
				$paramcount_where[] = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '". $params['toDate'] . "'";
			}
			else{
				$param_where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '". $params['toDate'] . "'";
				$paramcount_where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '". $params['toDate'] . "'";
			}
		}
		$orderby[] = " order by captured desc";
		$orderby_count[] = " order by DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) desc";
		$limit = '';
		if($params['export'] == false) {
			if ( $params['page_num'] > 1 ) {
				$limit = ' limit ' . $params['per_page'] * ( $params['page_num'] - 1 ) . ',' . $params['per_page'];
			} else {
				$limit = ' limit ' . $params['per_page'];
			}
		}
		// TODO:: Where Filter -- filter for filter
		$param_where = apply_filters('fv_query_param', $param_where);
		$query_cols = ["e.id","e.url","DATE_FORMAT(captured, '%Y/%m/%d %H:%i:%S') as captured,form_id,form_plugin"];
		if( $saveUserAgent ){
			$query_cols[] = 'e.user_agent';
		}
		// TODO:: Query Columns Filter
		// FIXME:: Query Updated
		$entry_query = "SELECT distinct ". implode(",", $query_cols) ." FROM {$wpdb->prefix}fv_enteries e left join {$wpdb->prefix}fv_entry_meta em on e.id=em.data_id where ". implode(' and ',$param_where) .' and ' . implode(' and ', $filter_param) . implode(' ',$orderby) . $limit;
        if($params['export'] == true) {
            $entry_query = "SELECT distinct ". implode(",", $query_cols) ." FROM {$wpdb->prefix}fv_enteries e left join {$wpdb->prefix}fv_entry_meta em on e.id=em.data_id where ". implode(' and ',$param_where) .' and ' . implode(' and ', $filter_param) . implode(' ',$orderby);
        }
		$entry_result = $wpdb->get_results($entry_query, ARRAY_A);
		$param_id = [];
		foreach ($entry_result as $key => $value){
			$param_id[] = $value['id'];
		}
		$res = [];
		$entry_count = 0;
		if(count($param_id) > 0){
			$entry_meta_query = "SELECT * FROM {$wpdb->prefix}fv_entry_meta where data_id IN (". implode(",",$param_id) . ") AND meta_key != 'fv_form_id' AND meta_key != 'fv_plugin'";
			if( $saveIp == false ){
				$entry_meta_query .= " AND meta_key != 'fv_ip' AND meta_key != 'IP'";
			}
			$entry_metas = $wpdb->get_results($entry_meta_query, ARRAY_A);
			$entry_count_query = "SELECT count(*) FROM {$wpdb->prefix}fv_enteries where ". implode(' and ',$paramcount_where). implode(' ',$orderby_count) ;
			$entry_count = $wpdb->get_var($entry_count_query);
			$meta_data = [];
			foreach ($entry_metas as $entry_meta) {
				$meta_data[ $entry_meta['data_id'] ][ $entry_meta['meta_key'] ] = stripslashes($entry_meta['meta_value']);
			}
			$i = 0;
			foreach ($entry_result as $key => $value){
				$i++;
				array_push($res, $meta_data[$value['id']] + $value);
			}
			$i=0;
			if($params['export']){
				$temp_res = [];
				foreach ($res as $key => $val) {
					if(array_key_exists('fv_status', $val) == true){
						$val['fv_status'] = $status_label['lead_status'][$val['fv_status']]['label'];
					}
					$temp_res[$i] = $val;
					$i++;
				}
				$res = $temp_res;
			}
		}
		$distinct_cols_query = "select distinct meta_key from {$wpdb->prefix}fv_entry_meta em join {$wpdb->prefix}fv_enteries e on em.data_id=e.id where form_id='".$params['form_id']."' AND meta_key != 'fv_form_id' AND meta_key != 'fv_plugin'";
		if($saveIp == false){
			$distinct_cols_query .= " AND meta_key != 'fv_ip' AND meta_key != 'IP'";
		}
		$distinct_cols_res = $wpdb->get_col($distinct_cols_query);
		if($params['export'] == true){
			//array_push($distinct_cols_res, '');
		}else{
			if($entry_count > 0){
				array_push($distinct_cols_res, 'captured');
				array_push($distinct_cols_res, 'url');
				if( $saveUserAgent ){
					array_push($distinct_cols_res, 'user_agent');
				}
			}
		}
		if ( empty( $res ) ) {
            $options = get_option('fv_dashboard_widget_settings');
            if ( $options['formid'] == $params['form_id'] ) {
                $dashboard_widget_setting = [];
                $dashboard_widget_setting['query_type'] = '';
                $dashboard_widget_setting['plugin'] = '';
                $dashboard_widget_setting['formid'] = '';
                update_option('fv_dashboard_widget_settings', $dashboard_widget_setting);
            }
			$data['lead_count'] = 0;
			$data['leads'] = [];
			$data['columns'] = $distinct_cols_res;
		}
		else{
			$data['lead_count'] = $entry_count;
			$data['leads'] = $res;

			$data['columns'] = $distinct_cols_res;
		}
		return $data;
	}

    private function add_meta_entries( $post_id, $data ){
        foreach ( $data['posted_data'] as $key => $value ){
            update_post_meta( $post_id, $key, $value );
        }
    }

    public function set_user_ip(){
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            //check ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            //to check ip is pass from proxy
            $temp_ip = explode(',',$_SERVER['HTTP_X_FORWARDED_FOR']);;
            $ip = $temp_ip[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    function insert_enteries($enteries){

    	//TODO :: Check exclude form
        /*$excludeForms = get_option('fv_exclude_forms');

        if($excludeForms !== false && $excludeForms !== ''){
            if(array_key_exists($enteries['plugin_name'],$excludeForms)){
                if(array_key_exists($enteries['id'],$excludeForms[$enteries['plugin_name']])){

                    return;
                }
            }
        }

        if($excludeForms == false){
            $excludeForms = [];
        }*/

        $inserted_forms = get_option('fv_forms');

        if($inserted_forms == false){
            $inserted_forms = [];
        }
        $forms = [];

        if(array_key_exists($enteries['plugin_name'],$inserted_forms)){
            $forms = $inserted_forms[$enteries['plugin_name']];

            $forms[$enteries['id']] = [
                'id' => $enteries['id'],
                'name' => $enteries['title']
            ];
        }
        else{
            $forms[$enteries['id']] = [
                'id' => $enteries['id'],
                'name' => $enteries['title']
            ];
        }
        $inserted_forms[$enteries['plugin_name']] = $forms;

        update_option('fv_forms',$inserted_forms);

        if ( ! function_exists('write_log')) {
            function write_log ( $log )  {
                if ( is_array( $log ) || is_object( $log ) ) {
                    error_log( print_r( $log, true ) );
                } else {
                    error_log( $log );
                }
            }
        }

        global $wpdb;
        $entry_data = array(
            'form_plugin' => $enteries['plugin_name'],
            'form_id' => $enteries['id'],
            'captured' => $enteries['captured'],
            'captured_gmt' => $enteries['captured_gmt'],
            'url' => $enteries['url'],
        );
        if(get_option('fv_gdpr_settings') !== false){
            $gdpr = Utils::get_gdpr_settings();
            $saveUA = $gdpr['ua'];

            if($saveUA == 'yes' || $saveUA){
                $entry_data['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                $enteries['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            }
            else{
                $entry_data['user_agent'] = '';
            }
        }

        write_log("=============Form Vibes Log===================");

        write_log("=============Captured DATA===================");
        write_log( $enteries );
        write_log("-----Result----------");
        write_log("-----Inserting Data In Entry Table----------");
        $wpdb->insert(
            $wpdb->prefix.'fv_enteries',
            $entry_data
        );
        $insert_id = $wpdb->insert_id;
        if($insert_id != 0){
            $this->insert_fv_entry_meta($insert_id, $enteries['posted_data']);
        }
    }

    function insert_fv_entry_meta($insert_id, $enteries){
        global $wpdb;
        write_log("-----Entry Id ".$insert_id."----------");
        write_log("-----Inserting Data In Entry Meta Table----------");

        foreach ( $enteries as $key => $value){
            $wpdb->insert(
                $wpdb->prefix.'fv_entry_meta',
                array(
                    'data_id' => $insert_id,
                    'meta_key' => $key,
                    'meta_value' => $value
                )
            );
        }
        $insert_id_meta = $wpdb->insert_id;
        if($insert_id_meta > 1){
            write_log("==============Entry Saved Successfully===============");
        }
        else{
            write_log("==============Entry Failed===============");
        }

    }

    static function get_forms($param){

    }

    static function delete_entries ( $ids ) {
        global $wpdb;
        $deleteRowQuery1 = "Delete from {$wpdb->prefix}fv_enteries where id IN (".implode(",",$ids).")";
        $deleteRowQuery2 = "Delete from {$wpdb->prefix}fv_entry_meta where data_id IN (".implode(",",$ids).")";

        $wpdb->get_results($deleteRowQuery1, ARRAY_A);
        $wpdb->get_results($deleteRowQuery2, ARRAY_A);

        wp_send_json('deleted');
    }

    function save_options ( $params ) {
        $forms_data = $params['columns'];
        $formName = $params['form'];
        $pluginName = $params['plugin'];
        $key = $params['key'];
        $saved_data = get_option('fv-keys');
        $data = $saved_data;
        $data[$pluginName.'_'.$formName] = $forms_data;
        update_option( $key , $data, false );
        wp_send_json('saved');
    }

    public function get_analytics ( $params ) {
        $filterType = $params['filter_type'];
        $pluginName = $params['plugin'];
        $fromDate = $params['fromDate'];
        $toDate = $params['toDate'];
        $filter = '';
        $formid = $params['formid'];
        $label = "";
        $query_param = "";
        if($filterType == 'day'){
            $default_data = self::getDatesFromRange($fromDate, $toDate);
            $filter = '%j';
            $label = "MAKEDATE(DATE_FORMAT(`captured`, '%Y'), DATE_FORMAT(`captured`, '%j'))";
        } else if($filterType == 'month'){
            $default_data = self::getMonthRange($fromDate,$toDate);
            $filter = '%b';
            $label = "concat(DATE_FORMAT(`captured`, '%b'),'(',DATE_FORMAT(`captured`, '%y'),')')";
        } else {
            $default_data = self::getDateRangeForAllWeeks($fromDate,$toDate);
            $start_week = get_option('start_of_week');
            if($start_week == 0){
                $filter = '%U';
                $dayStart = 'Sunday';
                $weekNumber = '';
            }
            else{
                $filter = '%u';
                $dayStart = 'Monday';
                $weekNumber = '-1';
            }
            $label = "STR_TO_DATE(CONCAT(DATE_FORMAT(`captured`, '%Y'),' ', DATE_FORMAT(`captured`, '".$filter."')".$weekNumber.",' ', '".$dayStart."'), '%X %V %W')";
        }
        if($filter == '%b'){
            $orderby = '%m';
        }
        else{
            $orderby = $filter;
        }
        global $wpdb;
	    $param_where = [];
	    $param_where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '". $fromDate . "'";;
	    $param_where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '". $toDate . "'";
	    $param_where[] = "form_plugin='".$pluginName."'";
	    $param_where[] = "form_id='".$formid."'";
	    $query_param = " Where ". implode(' and ', $param_where);
        $data_query = "SELECT ".$label." as Label,CONCAT(DATE_FORMAT(`captured`, '".$filter."'),'(',DATE_FORMAT(`captured`, '%y'),')') as week, count(*) as count,CONCAT(DATE_FORMAT(`captured`, '%y'),'-',DATE_FORMAT(`captured`, '".$orderby."')) as ordering from {$wpdb->prefix}fv_enteries ".$query_param." GROUP BY DATE_FORMAT(`captured`, '".$orderby."'),ordering ORDER BY ordering";
        $res['data'] = $wpdb->get_results($data_query, OBJECT_K);
        if(count((array)$res['data']) > 0){
            $key = array_keys($res['data'])[0];
            if($res['data'][$key]->Label == null || $res['data'][$key]->Label == ''){
                $abc[array_keys($default_data)[0]] = (object) $res['data'][""];
                $res['data'] = $abc + $res['data'];
                $res['data'][array_keys($default_data)[0]]->Label = array_keys($default_data)[0];
                unset($res['data'][""]);
            }
        }
        $data = array_replace( $default_data,$res['data']);
	    if ( array_key_exists('dashboard_data', $params) && $params['dashboard_data'] ) {
            $dashboard_data = $this->prepare_data_for_dashboard_widget( $params, $res);
            $data['dashboard_data'] = $dashboard_data;
        }
        return $data;
    }

    private function prepare_data_for_dashboard_widget ( $params , $res) {
        $allForms = [];
        $dashboard_data = [];
        for ( $i=0; $i < count($params['allForms']); ++$i ) {
            $plugin = $params['allForms'][$i]['label'];
            for ( $j = 0 ; $j < count($params['allForms'][$i]['options']); ++$j ) {
                $id = $params['allForms'][$i]['options'][$j]['value'];
                $formName = $params['allForms'][$i]['options'][$j]['label'];
                $allForms[$id] = array(
                    'id'  => $id,
                    'plugin' => $plugin,
                    'formName' => $formName
                );
            }
        }
        if($params['query_type'] == 'Last_7_Days' || $params['query_type'] == 'This_Week'){
            $preFromDate = date('Y-m-d', strtotime( $params['fromDate']."-7 days"));
            $preToDate = date('Y-m-d', strtotime( $params['fromDate']."-1 days"));
        }
        else if($params['query_type'] == 'Last_30_Days'){
            $preFromDate = date('Y-m-d', strtotime( $params['fromDate']."-30 days"));
            $preToDate = date('Y-m-d', strtotime( $params['fromDate']."-1 days"));
        }
        else{
            $preFromDate = date('Y-m-01',strtotime("first day of last month"));
            $preToDate = date('Y-m-t',strtotime("last day of last month"));
        }
        global $wpdb;
        $preParam = " where form_id='".$params['formid']."' and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '". $preFromDate . "'";
        $preParam .= " and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '". $preToDate . "'";
        $preDataCount = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}fv_enteries ".$preParam );
        foreach ( $allForms as $formKey => $formValue ) {
            if ( $formValue['plugin'] == 'Caldera' ) {
                $param = " where form_id='".$formKey."' and DATE_FORMAT(datestamp,GET_FORMAT(DATE,'JIS')) >= '". $params['fromDate'] . "'";
                $param .= " and DATE_FORMAT(datestamp,GET_FORMAT(DATE,'JIS')) <= '". $params['toDate'] . "'";
                $data_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cf_form_entries ".$param );

                $dashboard_data['allFormsDataCount'][$formKey] = [
                    'plugin' =>  $formValue['plugin'],
                    'count' =>  $data_count,
                    'formName' =>  $formValue['formName'],
                ];
            } else {
                $param = " where form_id='".$formKey."' and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '". $params['fromDate'] . "'";
                $param .= " and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '". $params['toDate'] . "'";
                $data_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}fv_enteries ".$param );

                $dashboard_data['allFormsDataCount'][$formKey] = [
                    'plugin' =>  $formValue['plugin'],
                    'count' =>  $data_count,
                    'formName' =>  $formValue['formName'],
                ];
            }
        }
        $totalEntries = 0;
        foreach ( $res['data'] as $key => $val ) {
            $totalEntries += $val->count;
        }
        $dashboard_widget_setting = [];
        $dashboard_widget_setting['query_type'] = $params['query_type'];
        $dashboard_widget_setting['plugin'] = $params['plugin'];
        $dashboard_widget_setting['formid'] = $params['formid'];
        update_option('fv_dashboard_widget_settings', $dashboard_widget_setting);
        $dashboard_data['totalEntries'] = $totalEntries;
        $dashboard_data['previousDateRangeDataCount'] = $preDataCount;
        return $dashboard_data;
    }

    // TODO :: move it to utils.
    static function getDatesFromRange($start, $end, $format = 'Y-m-d') {
        //$Date1 = '05-10-2010';
        $Date1 = $start;
        $Date2 = $end;

        // Declare an empty array
        $array = array();

        // Use strtotime function
        $Variable1 = strtotime($Date1);
        $Variable2 = strtotime($Date2);

        // Use for loop to store dates into array
        // 86400 sec = 24 hrs = 60*60*24 = 1 day
        for ($currentDate = $Variable1; $currentDate <= $Variable2;
             $currentDate += (86400)) {

            $Store = date('Y-m-d', $currentDate);

            $array[$Store] = (object)[
                'Label' => $Store,
                'week' => (date('z', $currentDate)+1).'('.date('y', $currentDate).')',
                'count' => 0,
                'ordering' => date('y', $currentDate).'-'.(date('z', $currentDate)+1),
            ];
        }
        $array[] = new \stdClass;
        unset($array[0]);
        return $array;
    }
    static function getMonthRange($startDate,$endDate){
        $start    = new \DateTime($startDate);
        $start->modify('first day of this month');
        $end      = new \DateTime($endDate);
        $end->modify('first day of next month');
        $interval = \DateInterval::createFromDateString('1 month');
        $period   = new \DatePeriod($start, $interval, $end);

        $months = [];
        foreach ($period as $dt) {
            $months[$dt->format("M").'('.$dt->format("y").')'] = (object)[
                'Label' => $dt->format("M").'('.$dt->format("y").')',
                'week' => '',
                'count' => 0,
                'ordering' => '',
            ];
        }

        return $months;
    }
    static function getDateRangeForAllWeeks($start, $end){
        $fweek = self::getDateRangeForWeek($start);
        $lweek = self::getDateRangeForWeek($end);

        $week_dates = [];

        while($fweek['sunday'] < $lweek['sunday']){
            $week_dates [$fweek['monday']] = (object)[
                'Label' => $fweek['monday'],
                'week' => '',
                'count' => 0,
                'ordering' => '',
            ];;
            $date = new \DateTime($fweek['sunday']);
            $date->modify('next day');

            $fweek = self::getDateRangeForWeek($date->format("Y-m-d"));
        }
        $week_dates [$lweek['monday']] = (object)[
            'Label' => $lweek['monday'],
            'week' => '',
            'count' => 0,
            'ordering' => '',
        ];

        //print_r($week_dates);
        return $week_dates;
    }
    static function getDateRangeForWeek($date){
        $dateTime = new \DateTime($date);

        if('Monday' == $dateTime->format('l')){
            $monday = date('Y-m-d',  strtotime($date));
        }
        else{
            $monday = date('Y-m-d', strtotime('last monday', strtotime($date)));
        }

        $sunday = 'Sunday' == $dateTime->format('l') ? date('Y-m-d', strtotime($date)) : date('Y-m-d', strtotime('next sunday', strtotime($date)));

        return ['monday'=>$monday, 'sunday'=>$sunday];
    }
}