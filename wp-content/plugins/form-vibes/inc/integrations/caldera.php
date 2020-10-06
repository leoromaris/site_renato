<?php

namespace FormVibes\Integrations;

use FormVibes\Classes\ApiEndpoint;
use FormVibes\Classes\DbManager;
use FormVibes\Classes\Utils;
use FormVibes\Pro\Classes\Settings;
use function GuzzleHttp\Promise\all;

class Caldera extends Base {

	private static $_instance = null;

	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Cf7 constructor.
	 */
	public function __construct() {
		$this->plugin_name = 'caldera';
		add_filter('fv_forms', [ $this, 'register_form' ]);
		//add_action('caldera_forms_submit_complete', [$this, 'insert_google_sheet'], 10);
	}

	public function get_analytics ( $params ) {
		// TODO:: Get time zone from utils
		$gmt_offset =  get_option('gmt_offset');
		$hours   = (int) $gmt_offset;
		$minutes = ( $gmt_offset - floor( $gmt_offset ) ) * 60;

		if($hours >= 0 ){
			$time_zone = $hours.':'.$minutes;
		}
		else{
			$time_zone = $hours.':'.$minutes;
		}

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
	        $label = "MAKEDATE(DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y'), DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%j'))";
        } else if($filterType == 'month'){
            $default_data = self::getMonthRange($fromDate,$toDate);
            $filter = '%b';
	        $label = "concat(DATE_FORMAT(`ADDTIME(datestamp,'".$time_zone."' )`, '%b'),'(',DATE_FORMAT(`ADDTIME(datestamp,'".$time_zone."' )`, '%y'),')')";
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
	        $label = "STR_TO_DATE(CONCAT(DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y'),' ', DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '".$filter."')".$weekNumber.",' ', '".$dayStart."'), '%X %V %W')";
        }
        if($filter == '%b'){
            $orderby = '%m';
        }
        else{
            $orderby = $filter;
        }
        global $wpdb;
        $query_param .= " Where DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y-%m-%d') >= '". $fromDate . "'";
        $query_param .= " and DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y-%m-%d') <= '". $toDate . "'";
        $query_param .= " and form_id='".$formid."'";
		$data_query = "SELECT ".$label." as Label, CONCAT(DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '".$filter."'),'(',DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%y'),')') as week, count(*) as count,CONCAT(DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%y'),'-',DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '".$orderby."')) as ordering from {$wpdb->prefix}cf_form_entries ".$query_param." GROUP BY DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '".$orderby."'),ordering ORDER BY ordering";
        $res['data'] = $wpdb->get_results($data_query, OBJECT_K);;
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
        if ( array_key_exists( 'dashboard_data', $params) && $params['dashboard_data'] ) {
            $dashboard_data = $this->prepare_data_for_dashboard_widget( $params, $res);
            $data['dashboard_data'] = $dashboard_data;
        }
        return $data;
    }

    private function prepare_data_for_dashboard_widget ( $params , $res) {
	    // TODO:: Get time zone from utils
	    $gmt_offset =  get_option('gmt_offset');
	    $hours   = (int) $gmt_offset;
	    $minutes = ( $gmt_offset - floor( $gmt_offset ) ) * 60;

	    if($hours >= 0 ){
		    $time_zone = $hours.':'.$minutes;
	    }
	    else{
		    $time_zone = $hours.':'.$minutes;
	    }

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
        $preParam = " where form_id='".$params['formid']."' and DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y-%m-%d') >= '". $preFromDate . "'";
        $preParam .= " and DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y-%m-%d') <= '". $preToDate . "'";
        $preDataCount = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cf_form_entries ".$preParam );
        // get all forms data count.
        $param = '';
        foreach ( $allForms as $formKey => $formValue ) {
            if ( $formValue['plugin'] == 'Caldera' || $formValue['plugin'] == 'caldera' ) {
                $param = " where form_id='".$formKey."' and DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y-%m-%d') >= '". $params['fromDate'] . "'";
                $param .= " and DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y-%m-%d') <= '". $params['toDate'] . "'";
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

    public function get_submissions($params)
    {

	    // TODO:: Get time zone from utils
	    $gmt_offset =  get_option('gmt_offset');
	    $hours   = (int) $gmt_offset;
	    $minutes = ( $gmt_offset - floor( $gmt_offset ) ) * 60;

	    if($hours >= 0 ){
		    $time_zone = $hours.':'.$minutes;
	    }
	    else{
		    $time_zone = $hours.':'.$minutes;
	    }
        global $wpdb;
        $param_count_query = '';
        $param_where = [];
        $paramcount_where = [];
        if($params['plugin'] !== '' && $params['plugin'] !== null){
            if(trim($params['formid']) !== '' || $params['formid'] !== null){
                $param_where[] = "form_id='".$params['formid']."' ";
                $paramcount_where[] = "form_id='".$params['formid']."'";
            }
        }
        if($params['fromDate'] !== '' && $params['fromDate'] !== null){
            $param_where[] = "DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y-%m-%d') >= '".$params['fromDate']."'";
            $paramcount_where[] = "DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y-%m-%d') >= '".$params['fromDate']."'";
        }
        if($params['toDate'] !== '' && $params['toDate'] !== null){
            if($params['fromDate'] !== ''){
                $param_where[] = "DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y-%m-%d') <= '".$params['toDate']."'";
                $paramcount_where[] = "DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y-%m-%d') <= '".$params['toDate']."'";
            }
            else{
                $param_where[] = "DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y-%m-%d') <= '".$params['toDate']."'";
                $paramcount_where[] = "DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y-%m-%d') <= '".$params['toDate']."'";
            }
        }

        $filter_param[] = "slug like'%%'";
        $filter_param[] = "value like'%%'";
        $orderby[] = "datestamp desc";
        $orderby_count[] = "DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y-%m-%d') desc";
        $limit = '';

        if ( $params['page_num'] > 1 ) {
            $limit = ' limit ' . $params['per_page'] * ( $params['page_num'] - 1 ) . ',' . $params['per_page'];
        } else {
            $limit = ' limit ' . $params['per_page'];
        }

        // TODO:: Query Where Filter
	    $param_where = apply_filters('fv_query_param', $param_where);
        $query_cols = ["DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y/%m/%d %H:%i:%S') as datestamp","e.id","form_id","user_id","status"];
        $entry_query = "SELECT distinct ". implode(",", $query_cols) ." FROM {$wpdb->prefix}cf_form_entries e left join {$wpdb->prefix}cf_form_entry_values ev on e.id=ev.entry_id where ". implode(' and ',$param_where) ." and ". implode(' and ',$filter_param) ." order by ".implode(' ',$orderby) . $limit;
        if ( array_key_exists('export', $params) && $params['export'] ) {
            $entry_query = "SELECT distinct ". implode(",", $query_cols) ." FROM {$wpdb->prefix}cf_form_entries e left join {$wpdb->prefix}cf_form_entry_values ev on e.id=ev.entry_id where ". implode(' and ',$param_where) ." and ". implode(' and ',$filter_param) ." order by ".implode(' ',$orderby);
        }

        $entry_result = $wpdb->get_results($entry_query, ARRAY_A);


        $param_id = [];
        foreach ($entry_result as $key => $value){
            $param_id[] = $value['id'];
        }
        $res = [];
        $entry_count = 0;
        if(count($param_id) > 0){
            $entry_meta_query = "SELECT * FROM {$wpdb->prefix}cf_form_entry_values where entry_id IN (". implode(",",$param_id) . ") and slug not like '%.%'" ;
            $entry_metas = $wpdb->get_results($entry_meta_query, ARRAY_A);
            $entry_count_query = "SELECT count(*) FROM {$wpdb->prefix}cf_form_entries where ". implode(' and ',$paramcount_where) ." and status='active' order by ".implode(' ',$orderby);
            $entry_count = $wpdb->get_var($entry_count_query);
            $meta_data = [];
            foreach ($entry_metas as $entry_meta) {
                $meta_data[ $entry_meta['entry_id'] ][ str_replace('.','_',$entry_meta['slug']) ] = stripslashes($entry_meta['value']);
            }
            $i = 0;
            foreach ($entry_result as $key => $value){
                $i++;
                array_push($res, $meta_data[$value['id']] + $value);
            }
            $i=0;
	        if(array_key_exists('export', $params) && $params['export']){
                $temp_res = [];
                foreach ($res as $key => $val) {
                    $temp_res[$i] = $val;
	                $i++;
                }
                $res = $temp_res;
            }
        }
	    $distinct_cols_query = "select distinct REPLACE(slug,'.','_') from {$wpdb->prefix}cf_form_entry_values ev join {$wpdb->prefix}cf_form_entries e on ev.entry_id=e.id where form_id='".$params['formid']."' and slug not like '%.%'";
	    $distinct_cols_res = $wpdb->get_col($distinct_cols_query);
	    if(array_key_exists( 'export', $params) && $params['export'] == true){
		    //array_push($distinct_cols_res, '');
	    }else{
		    if($entry_count > 0){
			    array_push($distinct_cols_res, 'datestamp');
		    }
	    }
        if ( empty( $res ) ) {
            $data['lead_count'] = 0;
            $data['leads'] = [];
            $data['columns'] = $distinct_cols_res;
        }
        else{
            $data['lead_count'] = $entry_count;
            $data['leads'] = $res;
            $data['columns'] = $distinct_cols_res;
        }
        $savedColumns = get_option('fv-keys');
        if ( $savedColumns == '' ) {
            $data['isColumnsSaved'] = false;
            return $data;
        } else {
	        if(array_key_exists(lcfirst($params['plugin']).'_'.$params['formid'], $savedColumns)) {
		        if ( count( $savedColumns[ lcfirst( $params['plugin'] ) . '_' . $params['formid'] ] ) > 0 ) {
			        $data['isColumnsSaved'] = true;
			        $data['savedColumns']   = $savedColumns[ lcfirst( $params['plugin'] ) . '_' . $params['formid'] ];
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
			        return $data;
		        }
	        }
        }
        return $data;
    }

    function export_data ( $data ) {
	    return $data;
    }
    function save_options ( $params ) {
        $forms_data = $params['columns'];
        $formName = $params['form'];
        $pluginName = lcfirst($params['plugin']);
        $key = $params['key'];
        $saved_data = get_option('fv-keys');
        $data = $saved_data;
        $data[$pluginName.'_'.$formName] = $forms_data;
        update_option( $key , $data, false );
        wp_send_json('saved');
    }

    public function register_form($forms){
		$forms[$this->plugin_name] = 'Caldera';
		return $forms;
	}

	public function insert_google_sheet($form){
		//echo 'insert form data ';
		$data= array();
		foreach( $form[ 'fields' ] as $field_id => $field){
			$data[ $field['slug'] ] = \Caldera_Forms::get_field_data( $field_id, $form );
		}

		$formId = $form['id'];
		$data['fv_plugin']      =   $this->plugin_name;

		if(get_option('fv_gdpr_settings') !== false){
			$gdpr = get_option( 'fv_gdpr_settings');
			$saveIp = $gdpr['ip'];
		}
		else{
			$saveIp = get_option( 'fv-ip-save');
		}

		if($saveIp == 'yes'){
			$data['IP'] = $this->set_user_ip();
		}

		if(WPV_FV_PLAN == 'PRO') {
			$settngs = new Settings();

			$settngs->check_gsheet_connector( $this->plugin_name, $formId, $data );
		}
	}
	static function get_forms($param) {
		$post_type = $param;

		global $wpdb;

		$forms_query = "select * from {$wpdb->prefix}cf_forms where type='primary';";

		$form_result = $wpdb->get_results($forms_query);
		$data = [];
		foreach ( $form_result as $form ) {
			$form_name = unserialize($form->config);
			$data[$form_name['ID']] = [
				'id' => $form_name['ID'],
				'name' => $form_name['name']
			];
		}
		return $data;
	}

	static function get_submission_data($param){

        print_r($param);
        die();

        $slug = [];
        $cols = [];
        $filter_param = [];

        $gmt_offset =  get_option('gmt_offset');
        $hours   = (int) $gmt_offset;
        $minutes = ( $gmt_offset - floor( $gmt_offset ) ) * 60;

        if($hours >= 0 ){
            $time_zone = '+'.$hours.':'.$minutes;
        }
        else{
            $time_zone = $hours.':'.$minutes;
        }


        if($param['queryType'] !== 'Custom'){
            $dates = Utils::get_dates($param['queryType']);

            $tz = new \DateTimeZone($time_zone);

            $fromDate = new \DateTime($dates['fromDate']);
            $fromDate->setTimezone($tz);
            $toDate = new \DateTime($dates['endDate']);
            $toDate->setTimezone($tz);

            $fromDate = $fromDate->format('Y-m-d');
            $toDate = $toDate->format('Y-m-d');
        }
        else{
            $tz = new \DateTimeZone($time_zone);

            $fromDate = new \DateTime($param['fromDate']);
            $fromDate->setTimezone($tz);
            $toDate = new \DateTime($param['toDate' ]);
            $toDate->setTimezone($tz);

            $fromDate = $fromDate->format('Y-m-d');
            $toDate = $toDate->format('Y-m-d');
        }

        //print_r($fromDate);
        //print_r($toDate);

        if($fromDate !== '' && $fromDate !== null){
            $param_where[] = "DATE_FORMAT(datestamp,GET_FORMAT(DATE,'JIS')) >= '".$fromDate."'";
            $paramcount_where[] = "DATE_FORMAT(datestamp,GET_FORMAT(DATE,'JIS')) >= '".$fromDate."'";
        }
        if($toDate !== '' && $toDate !== null){
            if($fromDate !== ''){
                $param_where[] = "DATE_FORMAT(datestamp,GET_FORMAT(DATE,'JIS')) <= '".$toDate."'";
                $paramcount_where[] = "DATE_FORMAT(datestamp,GET_FORMAT(DATE,'JIS')) <= '".$toDate."'";
            }
            else{
                $param_where[] = "DATE_FORMAT(datestamp,GET_FORMAT(DATE,'JIS')) <= '".$toDate."'";
                $paramcount_where[] = "DATE_FORMAT(datestamp,GET_FORMAT(DATE,'JIS')) <= '".$toDate."'";
            }
        }

        if($param['selectedFilter'] == 'undefined' || $param['selectedFilter'] == '' || $param['filterValue'] == 'undefined' || $param['filterValue'] == ''){
            $filter_param[] = "slug like'%%'";
        }
        else{
            $filter_param[] = "slug='".$param['selectedFilter']."'";
        }
        if($param['filterValue'] == 'undefined' || $param['filterValue'] == ''){
            $filter_param[] = "value like'%%'";
        }
        else{
            if($param['filterOperator'] == 'equal'){
                $filter_param[] = "value='".$param['filterValue']."'";
            }
            else if($param['filterOperator'] == 'not_equal'){
                $filter_param[] = "value != '".$param['filterValue']."'";
            }
            else if($param['filterOperator'] == 'contain'){
                $filter_param[] = "value LIKE '%".$param['filterValue']."%'";
            }
            else if($param['filterOperator'] == 'not_contain'){
                $filter_param[] = "value NOT LIKE '%".$param['filterValue']."%'";
            }
        }

        foreach ($param['columns'] as $key => $val) {
            if($val->visible == 0 || $val->visible == ''){
                $slug[] = $val->colKey;
            }
            $cols[] = $val->colKey;
	    }

        global $wpdb;

        $filter_col_id = "select entry_id from {$wpdb->prefix}cf_form_entries e 
        left JOIN {$wpdb->prefix}cf_form_entry_values ev ON e.id=ev.entry_id where ". implode(' and ',$filter_param) ." and form_id = '".$param['form']."'";
        $filter_col_id_res = $wpdb->get_results($filter_col_id, ARRAY_A);
        $entry_id = [];
        foreach ($filter_col_id_res as $entryId) {
            $entry_id[] = $entryId['entry_id'];
        }

        $gmt_offset =  get_option('gmt_offset');
		$hours   = (int) $gmt_offset;
		$minutes = ( $gmt_offset - floor( $gmt_offset ) ) * 60;

		if($hours >= 0 ){
			$time_zone = $hours.':'.$minutes;
		}
		else{
			$time_zone = $hours.':'.$minutes;
		}

        $entry_query = "select *,DATE_FORMAT(ADDTIME(datestamp,'".$time_zone."' ), '%Y/%m/%d %H:%i:%S') as datestamp from {$wpdb->prefix}cf_form_entries e 
        left JOIN {$wpdb->prefix}cf_form_entry_values ev ON e.id=ev.entry_id
        where ". implode(' and ',$param_where) ." and ev.slug NOT IN ('".implode("','",$slug)."') and entry_id IN ('".implode("','",$entry_id)."') and form_id = '".$param['form']."' order by datestamp desc";
	    $entry_res = $wpdb->get_results($entry_query, ARRAY_A);

        $meta_data = [];
        foreach ($entry_res as $entry_meta) {
            $meta_data[ $entry_meta['entry_id'] ][ $entry_meta['slug'] ] = stripslashes($entry_meta['value']);
        }

        if (!in_array('datestamp', $slug)) {
            foreach ($entry_res as $entry_meta) {
                $meta_data[ $entry_meta['entry_id'] ][ 'datestamp' ] = stripslashes($entry_meta['datestamp']);
            }
        }

        $res = [];
        foreach ($meta_data as $key => $val) {
            $res[] = $val;
        }

        $final_array = [];
		$final_cols = array_flip(array_diff($cols, $slug));
		foreach ( $final_cols as $key => $value ) {
			$final_cols[$key] = '';
		}
        for($i = 0; $i<count($meta_data); $i++){
            $final_array[] = array_merge($final_cols, $res[$i]);
        }

	    return $final_array;
    }
}