<?php

/*
 * Shortcode for displaying items, paginated
 */
add_action('init', 'register_itemlist_script');
add_action('wp_footer', 'enqueue_itemlist_script');
function register_itemlist_script() {
    wp_register_script( 'angular', "//ajax.googleapis.com/ajax/libs/angularjs/1.2.26/angular.min.js", 'jquery' );
    wp_register_script( 'angular-animate', "//ajax.googleapis.com/ajax/libs/angularjs/1.2.26/angular-animate.min.js", array( 'angular', 'jquery' ) );
    wp_register_script('itemlist',  plugins_url( 'js/itemlist.js' , __FILE__ ), array('jquery', 'angular') );
    wp_register_script('fileDownload', plugins_url( 'js/fileDownload.js', __FILE__ ), array('jquery') );
    wp_register_style('itemstyle', plugins_url('css/style.css', __FILE__ ) );
}
function enqueue_itemlist_script() {
	global $add_itemlist_script;

	if ( ! $add_itemlist_script )
		return;

        wp_enqueue_script('angular');
        wp_enqueue_script('angular-animate');
	wp_enqueue_script('itemlist');
        wp_enqueue_script('fileDownload');
        wp_enqueue_style('itemstyle' );
}

add_shortcode('otu_itemlist', 'otu_itemlist' );

function otu_itemlist (  ) {
    global $add_itemlist_script;
    $add_itemlist_script = true;
    
    $rows_per_page = 10;
    $data = get_items( 0, $rows_per_page ); // first lot of items are loaded with the page
    $data['ajaxurl'] = admin_url( 'admin-ajax.php' );
    $data['rows_per_page'] = $rows_per_page;
    global $wpdb;
    $query = "SELECT id, name FROM $wpdb->pmpro_membership_levels";
    $membertypes = $wpdb->get_results ( $query, OBJECT );
    $data['membertypes'] = array();
    foreach($membertypes as $membertype ) {
        $data['membertypes'][] = array('id'=>$membertype->id, 'name'=>$membertype->name );
    }
    $query = "SELECT IF(meta_value=\"Overseas\", \"ZZ\", meta_value) as mv FROM wp_my0ord_usermeta WHERE meta_key=\"pmpro_bstate\" and meta_value!=\"\" GROUP BY meta_value ORDER BY mv";
    $results = $wpdb->get_results ( $query, OBJECT );
    $states = array();
    foreach($results as $result) {
        $states[] = ($result->mv==="ZZ" ? "Overseas" : $result->mv);
    }
    $data['states'] = $states;
    ob_start();
    ?>
    <div class="row" ng-app="itemsApp" ng-controller="itemsCtrl">
        <script type="text/javascript">
            _data = <?=json_encode($data)?>;
        </script>
        <div id='states'>
            <div class='state' ng-class='{selected: isState("")}' ng-click='togglestate("")'>Unknown</div>
            <?php
            foreach ( $states as $state ) {
                echo "<div class='state' ng-class='{selected: isState(\"" . $state . "\")}' ng-click='togglestate(\"" . $state . "\")'>" . $state . "</div>";
            }
            ?>
        </div>
        <div id='membertypes'>
            <div class='membertype' ng-class='{selected: isMemberType("")}' ng-click='togglemembertype("")'>Unfinancial</div>
            <?php
            foreach ( $membertypes as $membertype ) {
                echo "<div class='membertype' ng-class='{selected: isMemberType(\"" . $membertype->id . "\")}' ng-click='togglemembertype(\"" . $membertype->id . "\")'>" . $membertype->name . "</div>";
            }
            ?>
        </div>
        <div id="letters">
            <div class='letter wider' ng-class='{selected: (letter=="")}' ng-click='setletter("")'>ALL</div>
            <?php
            foreach ( range("A", "Z") as $char) {
                echo "<div class='letter' ng-class='{selected:(letter==\"" . $char . "\")}' ng-click='setletter(\"" . $char . "\")'>" . $char . "</div>";
            }
            ?>
        </div>
        <div id='items'>
            <table border="0" class="listitems" width="90%" ng-cloak>
                <tbody>
                    <tr><th width="120">Name</th><th width="100">Class</th><th>email</th><th>Home phone</th><th>Mobile phone</th><th>Business phone</th><th>&nbsp;</th><th>&nbsp;</th></tr>
                    <tr ng-repeat="item in data.items">
                        <td><span class='hand' ng-click='show(item)'>{{item.name}}</span></td>
                        <td>{{item.class}}</td>
                        <td><a href="mailto:{{item.email}}" target="_blank">{{item.email}}</a></td>
                        <td>{{item.homephone}}</td>
                        <td>{{item.mobilephone}}</td>
                        <td>{{item.businessphone}}</td>
                        <td>{{etc(item)}}</td>
                        <td class='hand'><i class="fa fa-folder-open-o pull-left" ng-click="show(item)"></i></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div id="item">
            <table border="0" width="90%" ng-cloak>
                <tbody>
                    <tr>
                        <td colspan="6">&nbsp;</td>
                        <td class="alignright"><i class="fa fa-times" ng-click="hide()"></i></td>
                    </tr>
                    <tr>
                        <td>Name</td><td width="100">Class</td><td>email</td><td>Home phone</td><td>Mobile phone</td><td>Business phone</td><td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td>{{item.name}}</td>
                        <td>{{item.class}}</td>
                        <td><a href="mailto:{{item.email}}" target="_blank">{{item.email}}</a></td>
                        <td>{{item.homephone}}</td>
                        <td>{{item.mobilephone}}</td>
                        <td>{{item.businessphone}}</td>
                        <td>{{etc(item)}}</td>
                    </tr>
                    <tr>
                        <td colspan="2">Address</td>
                        <td>City</td>
                        <td>State</td>
                        <td colspan="3">Postcode</td>
                    </tr>
                    <tr>
                        <td>{{item.address1}}</td>
                        <td>{{item.address2}}</td>
                        <td>{{item.city}}</td>
                        <td>{{item.state}}</td>
                        <td colspan="3">{{item.postcode}}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div id='download' ng-click='download()' ng-hide='membertype.length===0 || state.length===0'>
            download
        </div>
        <div id="ajax-loading" ng-class="{'farleft':!showLoading}"><img src="<?php echo get_site_url();?>/wp-includes/js/thickbox/loadingAnimation.gif" ng-cloak></div>
        <?php
        // pagination adapted from http://sgwordpress.com/teaches/how-to-add-wordpress-pagination-without-a-plugin/                    
        ?>
        <div ng-hide="data.pages===1" class="pagination listitems" ng-cloak>
            <span>Page {{paged}} of {{data.pages}}</span>
            <a ng-show="paged>2 && paged > range+1 && showitems<data.pages" ng-click="gotoPage(1)">&laquo; First</a>
            <a ng-show="paged>1 && showitems<data.pages" ng-click='gotoPage(paged-1)'>&lsaquo; Previous</a>

            <span ng-show='data.pages!==1' ng-repeat='i in pagearray'>
                <span ng-show='paged===i' class="current">{{i}}</span>
                <a ng-hide="paged===i" ng-click="gotoPage(i)" class="inactive">{{i}}</a>
            </span>

            <a ng-show='paged<data.pages && showitems<data.pages' ng-click='gotoPage(paged+1)'>Next &rsaquo;</a>
            <a ng-show='paged<data.pages-1 && paged+range-1<data.pages && showitems < data.pages' ng-click='gotoPage(data.pages)'>Last &raquo;</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
/* 
 * showing items to members - called from ajax wrapper and also when loading page initially
 */
function get_items( $first_item, $rows_per_page, $letter='', $membertypes=array(), $states=array() ){
    global $wpdb;
    $params = array();
    if( $letter != '' ) $params[] = $letter;
    if ( Count($membertypes)>0) {
        $membertypearr = array();
        foreach ( $membertypes as $membertype ) {
            $membertypearr[] = "%d";
            $params[] = $membertype;
        }
        $membertypestr = join(",", $membertypearr );
    }
    if ( Count($states)>0 ) {
        $statearr = array();
        foreach ( $states as $state ) {
            $statearr[] = "%s";
            $params[] = $state;
        }
        $statestr = join(",", $statearr );
    }
    if ( $first_item >= 0 ) { // if negative, get all rows
        $params[] = $first_item;
        $params[] = $rows_per_page;
    }
    $query = 
        "SELECT SQL_CALC_FOUND_ROWS" .
        " IF(p.membership_id IS NULL, 0, p.membership_id) as ml," .
        " u.user_email as email, u.display_name as name, u.ID FROM " . $wpdb->users . " u" .
        " LEFT JOIN $wpdb->usermeta m ON m.user_id=u.ID AND m.meta_key='" . $wpdb->base_prefix . "user_level' " .
        " LEFT JOIN $wpdb->usermeta d ON d.user_id=u.ID AND d.meta_key='pmpro_do_not_contact'" .
        " LEFT JOIN $wpdb->usermeta l ON l.user_id=u.ID AND l.meta_key='pmpro_blastname'" .
        ( Count($states) == 0 ? "" : " LEFT JOIN $wpdb->usermeta s ON s.user_id=u.ID AND s.meta_key='pmpro_bstate'" ) .
        " LEFT JOIN $wpdb->pmpro_memberships_users p ON p.user_id=u.ID" .
        " WHERE m.meta_value=0 AND d.meta_value=0" .
        ( $letter == '' ? "" : " AND SUBSTRING(l.meta_value, 1, 1)=%s" ) .
        ( Count($membertypes)==0 ? "" : " AND IF(p.membership_id IS NULL, 0, p.membership_id) IN (" . $membertypestr . ")" ) .
        ( Count($states)==0 ? "" : " AND s.meta_value IN (" . $statestr . ")" ) .
        " ORDER BY l.meta_value, name" .
        ( $first_item >= 0 ? " LIMIT %d,%d" : "" );
    if ( Count ( $params ) > 0 ) {
        $query = $wpdb->prepare ( $query, $params );
    }
    //echo $query . "<br/>";
    $rows = $wpdb->get_results ( $query );
    $nitems = $wpdb->get_var('SELECT FOUND_ROWS();');
    $items = array();
    foreach ( $rows as $row ) {
        $custom = get_user_meta( $row->ID );
        $item = array (
            'email'=>$row->email,
            'name'=>$row->name,
            'membershiplevel'=>$row->ml,
            'class'=>$custom['pmpro_class'][0],
            'homephone'=>$custom['pmpro_bphone'][0],
            'mobilephone'=>$custom['pmpro_bmobile'][0],
            'businessphone'=>$custom['pmpro_bbusiness'][0],
            'deceased'=>$custom['pmpro_deceased'][0],
            'ID'=>$row->ID,
            'address1'=>$custom['pmpro_baddress1'][0],
            'address2'=>isset ( $custom['pmpro_baddress2'] ) ? $custom['pmpro_baddress2'][0] : "",
            'state'=>$custom['pmpro_bstate'][0],
            'city'=>$custom['pmpro_bcity'][0],
            'postcode'=>$custom['pmpro_bzipcode'][0],
        );
        $items[] = $item;
    }
    $data = array ( 'items'=>$items );
    if ( $first_item >= 0 ) {
        $pages = floor ( ($nitems - 0.9999) / $rows_per_page ) + 1;
        if(!$pages) $pages = 1;
        $data['pages'] = $pages;
    }
    return $data;
}
/*
 * AJAX wrapper to get items
 */
add_action( 'wp_ajax_CBDWeb_get_items', 'CBDWeb_get_items' );
add_action( 'wp_ajax_nopriv_CBDWeb_get_items', 'CBDWeb_get_items' );

function CBDWeb_get_items() {
    $rows_per_page = $_POST['rows_per_page'];
    $page = $_POST['page'];
    $letter = $_POST['letter'];
    $membertype = isset( $_POST['membertype'] ) ? $_POST['membertype'] : array();
    $state = isset ( $_POST['state'] ) ? $_POST['state'] : array();
    $first_item = ( $page - 1 ) * $rows_per_page;
    $data = get_items( $first_item, $rows_per_page, $letter, $membertype, $state );
    echo json_encode( $data );
    die;
}

add_action( 'wp_ajax_CBDWeb_download_items', 'CBDWeb_download_items' );
add_action( 'wp_ajax_nopriv_CBDWeb_download_items', 'CBDWeb_download_items' );

function CBDWeb_download_items() {
    $letter = $_GET['letter'];
    $membertype = isset( $_GET['membertype'] ) ? $_GET['membertype'] : array();
    $state = isset ( $_GET['state'] ) ? $_GET['state'] : array();
    $data = get_items( -1, 0, $letter, $membertype, $state );
    download_send_headers("OTU_members_" . $letter . date("Y-m-d") . ".csv");
    echo array2csv( $data['items'] );
    die;
}

function array2csv(array &$array)
{
   if (count($array) == 0) {
     return null;
   }
   ob_start();
   $df = fopen("php://output", 'w');
   fputcsv($df, array_keys(reset($array)));
   foreach ($array as $row) {
      fputcsv($df, $row);
   }
   fclose($df);
   return ob_get_clean();
}
function download_send_headers($filename) {
    // disable caching
    $now = gmdate("D, d M Y H:i:s");
    header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
    header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
    header("Last-Modified: {$now} GMT");

    // force download  
    header("Content-Type: application/force-download");
    header("Content-Type: application/octet-stream");
    header("Content-Type: application/download");

    // disposition / encoding on response body
    header("Content-Disposition: attachment;filename={$filename}");
    header("Content-Transfer-Encoding: binary");
    header("Set-Cookie: fileDownload=true; path=/");
}