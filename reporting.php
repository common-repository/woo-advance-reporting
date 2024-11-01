<?php
add_action( 'admin_enqueue_scripts', 'wnw_enqueue_scripts' );
function wnw_enqueue_scripts(){
	wp_enqueue_style('wnw-war-style', WAR__PLUGIN_URL.'css/style.css','','',false);
	wp_enqueue_script('wnw-war-google-chart','https://www.gstatic.com/charts/loader.js','','',false);
}
add_action('admin_menu','add_war_submenu',1000,1);
function add_war_submenu(){
	add_submenu_page('woocommerce', 'Advance Reporting', 'Advance Reporting', 'manage_options', 'advance-reporting', 'advance_reporting_callback' );
}
function advance_reporting_callback(){
	global $wpdb;
	$currency = get_woocommerce_currency();
	$sales_period = '';
	$year = '';
	$month = '';
	$day = '';
	$select = 'count(ID) as orders, sum(woo_oim.meta_value) as sales';
	$group_by = '';
	$order_by = '';
	$where = "post_type='shop_order'";
	$first_order_select = '';
	$join = $wpdb->prefix."posts as posts INNER JOIN ".$wpdb->prefix."woocommerce_order_items as woo_oi ON posts.ID = woo_oi.order_id AND woo_oi.order_item_type='line_item' INNER JOIN ".$wpdb->prefix."woocommerce_order_itemmeta as woo_oim ON woo_oim.order_item_id = woo_oi.order_item_id AND woo_oim.meta_key='_line_subtotal'";
	if(!empty($_REQUEST['product_id']) &&!empty($_REQUEST['day']) && !empty($_REQUEST['month']) && !empty($_REQUEST['year'])){
		$first_order_select .= 'woo_oim1.meta_value as sales_key';
		$select .= $first_order_select.', '.$select;
		$group_by .= 'GROUP BY sales_key';
		$where .= $wpdb->prepare(' AND year(post_date)=%d AND month(post_date)=%d AND day(post_date)=%d',$_REQUEST['year'],$_REQUEST['month'],$_REQUEST['day']);
		$join .= " INNER JOIN ".$wpdb->prefix."woocommerce_order_itemmeta as woo_oim1 ON woo_oim1.order_item_id = woo_oi.order_item_id AND woo_oim1.meta_key='_variation_id'";
		$sales_period = 'Variation';

	}elseif(!empty($_REQUEST['day']) && !empty($_REQUEST['month']) && !empty($_REQUEST['year'])){
		$first_order_select .= 'woo_oim1.meta_value as sales_key';
		$select = $first_order_select.', '.$select;
		$group_by .= 'GROUP BY sales_key';
		$where .= $wpdb->prepare(' AND year(post_date)=%d AND month(post_date)=%d AND day(post_date)=%d',$_REQUEST['year'],$_REQUEST['month'],$_REQUEST['day']);
		$join .= " INNER JOIN ".$wpdb->prefix."woocommerce_order_itemmeta as woo_oim1 ON woo_oim1.order_item_id = woo_oi.order_item_id AND woo_oim1.meta_key='_product_id'";
		$sales_period = 'Product';
		$year = $_REQUEST['year'];
		$month = sprintf("%02d", $_REQUEST['month']);
		$day = sprintf("%02d", $_REQUEST['day']);
	
	}elseif(!empty($_REQUEST['month']) && !empty($_REQUEST['year'])){
		$first_order_select .= 'day(post_date) as sales_key';
		$select = $first_order_select.', '.$select;
		$group_by .= 'GROUP BY sales_key';
		$where .= $wpdb->prepare(' AND year(post_date)=%d AND month(post_date)=%d',$_REQUEST['year'],$_REQUEST['month']);
		$sales_period = 'Day';
		$year = $_REQUEST['year'];
		$month = sprintf("%02d", $_REQUEST['month']);

	}elseif(!empty($_REQUEST['year'])){
		$first_order_select .= 'MONTHNAME(STR_TO_DATE(month(post_date), "%m"))  as sales_key, month(post_date) as sales_sort';
		$select = $first_order_select.', '.$select;
		$group_by .= 'GROUP BY sales_key';
		$where .= $wpdb->prepare(' AND year(post_date)=%d',$_REQUEST['year']);
		$order_by = ' ORDER BY sales_sort ASC';
		$sales_period = 'Month';
		$year = $_REQUEST['year'];
	}else{
		$first_order_select = 'year(post_date) as sales_key';
		$select = $first_order_select.', '.$select;
		$group_by .= 'GROUP BY sales_key';
		$sales_period = 'Year';
	}
	$first_order_query = "SELECT $first_order_select , SUM(sales) as sales, count(ID) as orders FROM (SELECT distinct(post_author), post_type, posts.ID, woo_oim.meta_value as sales, post_date FROM ".$wpdb->prefix."posts as posts INNER JOIN ".$wpdb->prefix."woocommerce_order_items as woo_oi ON posts.ID = woo_oi.order_id AND woo_oi.order_item_type='line_item' INNER JOIN ".$wpdb->prefix."woocommerce_order_itemmeta as woo_oim ON woo_oim.order_item_id = woo_oi.order_item_id AND woo_oim.meta_key='_line_subtotal' WHERE post_type='shop_order' GROUP BY post_author) as first_orders WHERE $where $group_by $order_by";
	$first_order_results = $wpdb->get_results($first_order_query,'OBJECT_K');
	$query = "SELECT $select FROM $join WHERE $where $group_by $order_by";
	$results = $wpdb->get_results($query,'OBJECT_K');
	$refunded_query = "SELECT $select FROM $join WHERE $where AND post_status = 'wc-refunded' $group_by $order_by";
	$refunded_results = $wpdb->get_results($refunded_query,'OBJECT_K');
	if(!empty($_POST['graph_type'])){
		$graph_type = $_POST['graph_type'] == 'v' ? 'v' : 'h';
		update_option('war_graph_type',$graph_type);
	}else{
		$graph_type = get_option('war_graph_type');
		if(empty($graph_type)){
			$graph_type = 'h';
		}
	}
	$loop_array = array();
	if($sales_period == 'Year'){
		if(!empty($results)){
			for($i = min(array_keys($results)); $i <= max(array_keys($results)); $i++){
				$loop_array[$i] = $i;
			}
		}
		$total_report_days = count($loop_array)*365;
	}elseif($sales_period == 'Month'){
		$loop_array = array(1=>'January', 2=>'Feburary', 3=>'March', 4=>'April', 5=>'May', 6=>'June', 7=>'July', 8=>'August', 9=>'September', 10=>'October', 11=>'November', 12=>'December');
		$total_report_days = 365;
	}elseif($sales_period == 'Day'){
		$last_day_this_month  = date('t', strtotime($_REQUEST['year'].'-'.$_REQUEST['month'].'-01'));
		for($i = 1; $i <= $last_day_this_month; $i++){
			$loop_array[$i] = $i;
		}
		$total_report_days = count($loop_array);
	}
	$last_date_for_projected = strtotime( (!empty($_REQUEST['year']) ? (int)$_REQUEST['year'] : date('Y')).'-'.(!empty($_REQUEST['month']) ? (int)$_REQUEST['month'] : 12).'-'.(!empty($_REQUEST['day']) ? (int)$_REQUEST['day'] : 31));
	$current_date = strtotime(date("Y-m-d"));
	$difference_in_dates = ($last_date_for_projected - $current_date)/(60*60*24);
	?>
    
      
    <div class="table-responsive">
    	<h2>Report for the period- <?php echo (!empty($day) ? $day.'-' : '').(!empty($month) ? $month.'-' : '').(!empty($year) ? $year : 'ALL'); ?></h2>
       <table>
           <thead>
               <tr>
                   <th></th>
                    <?php foreach($loop_array as $key=>$value){?>
                        <th><a href="<?php echo $sales_period != 'Product' ? add_query_arg(strtolower($sales_period),$key,"//$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]") : add_query_arg(array('post_status'=>'all', 'post_type'=>'shop_order','m'=>$year.$month.sprintf("%02d", $key)),admin_url().'edit.php') ; ?>"><?php echo $value;?></a></th>
                   <?php } ?>
                   <th class="totals"><a href="#"><strong>Total</strong></a></th>
                   <th class="totals"><a href="#"><strong>Projected</strong></a></th>
               </tr>
            </thead>
            
            <tbody>
            <tr>
                <td>Sales</td>
                <?php 
                  $sales_total = 0;
                  foreach($loop_array as $key=>$value){
                       echo '<td><a href="'.add_query_arg(array('post_status'=>'all', 'post_type'=>'shop_order','m'=>$year.$month.sprintf("%02d", $key)),admin_url().'edit.php').'">'.wc_price($results[$value]->sales + $refunded_results[$value]->sales).'</a></td>';
                        $sales_total +=$results[$value]->sales + $refunded_results[$value]->sales;
                  }
                  echo '<td class="totals"><a href="'.add_query_arg(array('post_status'=>'all', 'post_type'=>'shop_order','m'=>$year.$month),admin_url().'edit.php').'"'.wc_price($sales_total).'</a></td>';
                  ?>
                  <td class="totals"><?php if( $difference_in_dates > 0){
                        echo wc_price((($sales_total/($total_report_days - $difference_in_dates))*$difference_in_dates) + $sales_total,2);
                  }else{
                        echo wc_price($sales_total);
                  }?></td>
              </tr>
            
             <tr>
                <td>New Sales</td>
                    <?php 
                    $new_sales_total = 0;
                    foreach($loop_array as $key=>$value){
                            echo '<td><a href="'.add_query_arg(array('post_status'=>'all', 'post_type'=>'shop_order','m'=>$year.$month.sprintf("%02d", $key),'sales_type'=>'new_sales'),admin_url().'edit.php').'">'.wc_price($first_order_results[$value]->sales).'</a></td>';
                            $new_sales_total += $first_order_results[$value]->sales;
                    }
                    echo '<td class="totals"><a href="'.add_query_arg(array('post_status'=>'all', 'post_type'=>'shop_order','m'=>$year.$month,'sales_type'=>'new_sales'),admin_url().'edit.php').'">'.wc_price($new_sales_total).'</a></td>';
                    ?>
                    <td class="totals"><?php if( $difference_in_dates > 0){
                            echo wc_price((($new_sales_total/($total_report_days - $difference_in_dates))*$difference_in_dates) + $new_sales_total,2);
                      }else{
                            echo wc_price($new_sales_total);
                      }?></a>
                    </td>
              </tr>
              <tr>
                <td>Returning Sales</td>
                <?php 
                $returning_sales_total = 0;
                foreach($loop_array as $key=>$value){
                        echo '<td><a href="'.add_query_arg(array('post_status'=>'all', 'post_type'=>'shop_order','m'=>$year.$month.sprintf("%02d", $key),'sales_type'=>'returning_sales'),admin_url().'edit.php').'">'.wc_price($results[$value]->sales - $first_order_results[$value]->sales).'</a></td>';
                        $returning_sales_total += $results[$value]->sales - $first_order_results[$value]->sales;
                }
                echo '<td class="totals"><a href="'.add_query_arg(array('post_status'=>'all', 'post_type'=>'shop_order','m'=>$year.$month,'sales_type'=>'returning_sales'),admin_url().'edit.php').'">'.wc_price($returning_sales_total).'</a></td>';
                ?>
                <td class="totals"><?php if( $difference_in_dates > 0){
                        echo wc_price((($returning_sales_total/($total_report_days - $difference_in_dates))*$difference_in_dates) + $returning_sales_total,2);
                  }else{
                        echo wc_price($returning_sales_total);
                  }?>
                </td>
              </tr>
              <tr>
                <td>Refunds</td>
                <?php 
                $refunds_total = 0;
                foreach($loop_array as $key=>$value){
                        echo '<td><a href="'.add_query_arg(array('post_status'=>'wc-refunded', 'post_type'=>'shop_order','m'=>$year.$month.sprintf("%02d", $key)),admin_url().'edit.php').'">'.wc_price(-1*$refunded_results[$value]->sales).'</a></td>';
                        $refunds_total += $refunded_results[$value]->sales;
                }
                echo '<td class="totals"><a href="'.add_query_arg(array('post_status'=>'wc-refunded', 'post_type'=>'shop_order','m'=>$year.$month),admin_url().'edit.php').'">'.wc_price($refunds_total*-1).'</a></td>';
                ?>
                <td class="totals"><?php if( $difference_in_dates > 0){
                        echo wc_price(((-1*$refunds_total/($total_report_days - $difference_in_dates))*$difference_in_dates) - $refunds_total,2);
                  }else{
                        echo wc_price($refunds_total*-1);
                  }?>
                </td>
              </tr>
              <tr>
                <td class="totals"><b>Net sales</b></td>
                <?php 
                 $sales_total = 0;
                  foreach($loop_array as $key=>$value){
                       echo '<td class="totals"><b>'.wc_price($results[$value]->sales).'</b></td>';
                        $sales_total +=$results[$value]->sales;
                  }
                  echo '<td class="totals"><b>'.wc_price($sales_total).'</b></td>';
                  ?>
                  <td class="totals"><b><?php if( $difference_in_dates > 0){
                        echo wc_price((($sales_total/($total_report_days - $difference_in_dates))*$difference_in_dates) + $sales_total,2);
                  }else{
                        echo wc_price($sales_total);
                  }?>
                  </b>
                </td>
              </tr>
             
            </tbody>
         
      
       </table>
    </div>
    <div id="sales_chart_div"></div>
    <div id="orders_chart_div"></div>
    <form method="post" name="graph">
      	<input type="radio" name="graph_type" value="h" onclick="jQuery(this).closest('form').submit();" <?php echo $graph_type == 'h' ? 'checked' : '' ;?>/> Horizontal
        <input type="radio" name="graph_type" value="v" onclick="jQuery(this).closest('form').submit();" <?php echo $graph_type == 'v' ? 'checked' : '' ;?>/> Vertical
	</form>
	<script type="text/javascript">
	google.charts.load('current', {packages: ['corechart', 'bar']});
	google.charts.setOnLoadCallback(drawColColors);
	
	function drawColColors() {
		  var sales = new google.visualization.DataTable();
		  sales.addColumn('string', '<?php echo $sales_period; ?>');
		  //sales.addColumn('number', 'Sales in USD');
		  sales.addColumn('number', 'New Sales in <?php echo $currency;?>');
		  sales.addColumn('number', 'Returning Sales in <?php echo $currency;?>');
		  sales.addColumn('number', 'Refunds in <?php echo $currency;?>');
		  sales.addRows([
		  <?php foreach($loop_array as $key=>$value){
		  			echo '["'.$value.'",'.@$first_order_results[$value]->sales.','.($results[$value]->sales - @$first_order_results[$value]->sales).','.(!empty($refunded_results[$value]) ? $refunded_results[$value]->sales : 0).'],';	
				}
		  ?>
		  ]);
		 
	
		   var options = {
			title: 'Sales for the selected period',
			colors: ['#9575cd', '#33ac71','red'],
			<?php if($graph_type == 'h'){?>
			vAxis: {
			  title: 'Sales in <?php echo $currency;?>',
			},
			hAxis: {
			  title: '<?php echo $sales_period; ?>'
			},<?php }else{?>
			hAxis: {
			  title: 'Sales in <?php echo $currency;?>',
			},
			vAxis: {
			  title: '<?php echo $sales_period; ?>'
			},<?php }?>
			height:500,
			isStacked:true
		  };
		   var orders = new google.visualization.DataTable();
		  orders.addColumn('string', '<?php echo $sales_period; ?>');
		  orders.addColumn('number', 'New Orders');
		  orders.addColumn('number', 'Returning Orders');
		  orders.addColumn('number', 'Refunded Orders');
		 orders.addRows([
		  <?php foreach($loop_array as $key=>$value){
		  			echo '["'.$value.'",'.@$first_order_results[$value]->orders.','.($results[$value]->orders - @$first_order_results[$value]->orders).','.(!empty($refunded_results[$value]) ? $refunded_results[$value]->orders : 0).'],';	
				}
		  ?>
		  ]);
		 var order_options = {
			title: 'Orders for the selected period',
			colors: ['#9575cd', '#33ac71','red'],
			<?php if($graph_type == 'h'){?>
			vAxis: {
			  title: 'Orders',
			},
			hAxis: {
			  title: '<?php echo $sales_period; ?>'
			},<?php }else{?>
			hAxis: {
			  title: 'Orders',
			},
			vAxis: {
			  title: '<?php echo $sales_period; ?>'
			},<?php }?>
			height:500,
			isStacked:true
		  };
		  <?php if($graph_type == 'h'){?>
		  	var chart = new google.visualization.ColumnChart(document.getElementById('sales_chart_div'));
		  	chart.draw(sales, options);
		  <?php }else{?>
		 	 var chart = new google.visualization.BarChart(document.getElementById("sales_chart_div"));
		  	chart.draw(sales, options);
		  <?php }?>
		  <?php if($graph_type == 'h'){?>
		  	var order_chart = new google.visualization.ColumnChart(document.getElementById('orders_chart_div'));
		  	order_chart.draw(orders, order_options);
		  <?php }else{?>
		 	 var order_chart = new google.visualization.BarChart(document.getElementById("orders_chart_div"));
		  	order_chart.draw(orders, order_options);
		  <?php }?>
		}
  </script>

  <?php 
}
function war_print_r($data){
	echo '<pre>';
	print_r($data);
	echo '</pre>';
}
add_action( 'parse_query', 'wnw_get_new_and_returning_orders');
function wnw_get_new_and_returning_orders($wp_query){
	if(!empty($_REQUEST['sales_type']) && $_REQUEST['sales_type'] == 'new_sales'){
		add_filter('posts_where','wnw_where_filter_for_new_sales');
		add_filter('post_limits','wnw_limit_filter_for_new_sales');
		add_filter('posts_request','wnw_request_filter_for_new_sales',10000);
	}elseif(!empty($_REQUEST['sales_type']) && $_REQUEST['sales_type'] == 'returning_sales'){
		add_filter('posts_where','wnw_where_filter_for_returning_sales');
	}
}
function wnw_request_filter_for_returning_sales($request){
	echo $request;
	return $request;
}
function wnw_where_filter_for_returning_sales($where){
	global $wpdb;
	$request = 'SELECT ID '." FROM (SELECT distinct(post_author) as unique_post_author, posts.* FROM ".$wpdb->prefix."posts as posts INNER JOIN ".$wpdb->prefix."woocommerce_order_items as woo_oi ON posts.ID = woo_oi.order_id AND woo_oi.order_item_type='line_item' INNER JOIN ".$wpdb->prefix."woocommerce_order_itemmeta as woo_oim ON woo_oim.order_item_id = woo_oi.order_item_id AND woo_oim.meta_key='_line_subtotal' WHERE post_type='shop_order' GROUP BY post_author) as ".$wpdb->posts.' WHERE 1=1 '.$where;
	$post_ids = $wpdb->get_col($request);
	if(!empty($post_ids)){
		$where .= ' AND ID NOT IN('.implode(',',$post_ids).')';
	}
	return $where;
}
function wnw_limit_filter_for_new_sales($limit){
	global $wnw_limit;
	$wnw_limit = $limit;
	return $limit;
}
function wnw_where_filter_for_new_sales($where){
	global $wnw_where;
	$wnw_where = $where;
	return $where;
}
function wnw_request_filter_for_new_sales($request){
	global $wnw_where, $wnw_limit, $wpdb;
	$request = 'SELECT SQL_CALC_FOUND_ROWS '.$wpdb->posts.'.* '." FROM (SELECT distinct(post_author) as unique_post_author, posts.* FROM ".$wpdb->prefix."posts as posts INNER JOIN ".$wpdb->prefix."woocommerce_order_items as woo_oi ON posts.ID = woo_oi.order_id AND woo_oi.order_item_type='line_item' INNER JOIN ".$wpdb->prefix."woocommerce_order_itemmeta as woo_oim ON woo_oim.order_item_id = woo_oi.order_item_id AND woo_oim.meta_key='_line_subtotal' WHERE post_type='shop_order' GROUP BY post_author) as ".$wpdb->posts.' WHERE 1=1 '.$wnw_where.' '.$wnw_limit;
	return $request;
}