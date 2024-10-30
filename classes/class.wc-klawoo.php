<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WC_Klawoo' ) ) {

    class WC_Klawoo {

        private $email_address = '';
        private $password = '';
        private $protocol = 'http:';
        private $brand_id = '';
        private $version = 1.0;
        private $batch_limit = 100;
        public  $text_domain;
        private $api_url;
        private $main_list_id = '';
        private $product_list_ids = array();
        private $category_list_ids = array();
        
        protected static $instance = null;
        
        /**
        * Call this method to get singleton
        *
        * @return WC_Klawoo
        */
        public static function getInstance() {
            if(is_null(self::$instance))
            {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        /**
         * WC_Klawoo Constructor.
         *
         * @access public
         * @return void
         */
        private function __construct() {

            $this->text_domain = 'wc_klawoo';

            $this->protocol = ( is_ssl() ) ? 'https:' : 'http:';
            $this->api_url = $this->protocol.'//app.klawoo.com/customer_happy/';
            
            if (version_compare ( WOOCOMMERCE_VERSION, '2.2.0', '<' )) {
                define ( 'KLAV_IS_WOO22', "false" );
            } else {
                define ( 'KLAV_IS_WOO22', "true" );
            }

            if ( is_admin() ) {
                
                $this->settings_url = admin_url('tools.php?page=wc_klawoo');
                add_action( 'admin_menu', array(&$this, 'add_admin_menu_page') );
                add_action( 'wp_ajax_klawoo_validate_and_get_brands', array (&$this, 'validate_and_get_brands') );
                add_action( 'wp_ajax_klawoo_sign_up', array (&$this, 'validate_and_get_brands') );
            }
            
            add_filter( 'wp_klawoo_bulk_subscribe_get_data', array( &$this, 'bulk_subscribe_get_data' ), 10, 2 );

            $settings = get_option('wc_klawoo_credentials', null);
            
            if( $settings != null ){
                
                $this->password = (!empty($settings['password'])) ? $settings['password'] : null;
                $this->email_address = (!empty($settings['email_address'])) ? $settings['email_address'] : null; 
                
                $brand_list = get_option('wc_klawoo_brand_list', null);
                
                if( $brand_list != null ){
                    
                    $this->brand_lists = $brand_list;
                    
                    if (is_admin() ) {
                        add_action ( 'wp_ajax_klawoo_save_smtp_settings', array (&$this, 'klawoo_save_smtp_settings') );
                        add_action ( 'wp_ajax_klawoo_save_selected_brand', array (&$this, 'klawoo_save_selected_brand') );
                    }
                    
                    $this->brand_id = (get_option('wc_klawoo_selected_brand_id') !== false ) ? get_option('wc_klawoo_selected_brand_id') : null;
                    
                    if( !empty( $this->brand_id ) ){
                        
                        add_action ( 'wp_ajax_klawoo_create_lists', array (&$this, 'klawoo_create_lists') );
                        add_action ( 'wp_ajax_bulk_subscribe', array (&$this, 'bulk_subscribe'), 10, 1 );

                        // Hook to create list when new product is added 
                        add_action ( 'save_post', array (&$this, 'create_update_list_for_product'), 12, 2 );

                        // Hook to create list when new category is added/updated 
                        add_action ( 'created_term', array (&$this, 'create_update_list_for_category'), 10, 3 );
                        add_action ( 'edit_term', array (&$this, 'create_update_list_for_category'), 10, 3 );

                        //Hook to sync data on order staus change
                        add_action( 'woocommerce_order_status_changed', array (&$this, 'user_subscribe_or_unsubscribe'), 10, 3 );
                    }
                }
                
            }            
            
        }
        
        public function add_admin_menu_page() {
            
            // Enque JS file
            wp_enqueue_script( 'wc-klawoo-js', plugins_url( '../assets/wc_klawoo.js', __FILE__) , array( 'jquery', 'jquery-ui-progressbar' ), $this->version, true );            

            $klawoo_params = array ('image_url' => plugins_url('../assets/images/', __FILE__) );
            wp_localize_script( 'wc-klawoo-js', 'klawoo_params', $klawoo_params );
            // Enque CSS file
            wp_enqueue_style( 'wc-klawoo-css', plugins_url( '../assets/wc_klawoo.css', __FILE__) , array( ) );
            
            $smtp_options = get_option( 'wc_klawoo_smtp_settings' );
            
            if( $this->email_address != "" && $this->password != "" && !empty( $smtp_options ) ){
                add_menu_page( __('Klawoo',  $this->text_domain), __('Klawoo',  $this->text_domain), 'manage_options', 'wc_klawoo' , array(&$this,'display_dashboard_page') );
                add_submenu_page( 'wc_klawoo' , __('Klawoo',  $this->text_domain), __('Dashboard',  $this->text_domain), 'manage_options', 'wc_klawoo', array(&$this, 'display_dashboard_page') );
                add_submenu_page( 'wc_klawoo', __('Klawoo',  $this->text_domain), __('Settings',  $this->text_domain), 'manage_options', 'wc_klawoo_settings', array(&$this, 'display_settings_page') );
            } else {
                add_menu_page( __('Klawoo',  $this->text_domain), __('Klawoo',  $this->text_domain), 'manage_options', 'wc_klawoo' , array(&$this,'display_settings_page') );
                add_submenu_page( 'wc_klawoo', __('Klawoo',  $this->text_domain), __('Settings',  $this->text_domain), 'manage_options', 'wc_klawoo', array(&$this, 'display_settings_page') );
            }
            
        }
        
        public function display_dashboard_page() {

            ?>
              <div class="wrap">
                <div id ="klawoo_dashboard">
                  <iframe id= "klawoo_dashboard_iframe" name = "klawoo_dashboard_iframe"  src="<?php echo $this->protocol;?>//app.klawoo.com/" scrolling="auto" frameborder="0" height="100%" width="100%"></iframe>

                  <form method="post" action="<?php echo $this->protocol;?>//app.klawoo.com/includes/login/main.php" name="loginForm" id= "loginformid">
                      <input type="hidden" name="email" value="<?php echo $this->email_address;?>">
                      <input type="hidden" name="password" value="<?php echo $this->password;?>"><br/><br/>
                      <input type="hidden" name="redirect" value=""/>
                  </form>

                      <p align="center">Logging you in...</p>

                      <script type="text/javascript">
                        jQuery("#message, .updated,.error,.update-nag").css('display','none');

                        jQuery('#klawoo_dashboard_iframe').load(function() {
                            this.style.height = document.documentElement.offsetHeight + 'px';
                        }); 

                        document.getElementById('loginformid').target = 'klawoo_dashboard_iframe';
                        document.loginForm.submit();

                      </script>

                </div>
            </div> 

            <?php            
        }
        
        public function display_settings_page() {

            if( $this->email_address != "" && $this->password != "" ){
                $option_name = "existing_cust";
            } else {
                $option_name = "new_account";
            }

            if( version_compare( get_bloginfo( 'version' ), '3.8', '<' ) ) {
                    // $width = '36.8%';
                    $width = '368px';
                } else {
                    $width = '426px';
                    // $width = '42.5%';
                } 

           ?>

           <div class="wrap">
               <div id="wc_klawoo_api_settings">
               <h3><?php _e("Klawoo Settings", $this->text_domain); ?></h3>
               
               <div id="klawoo_message" class="updated fade" style="display:none;"><p></p></div>
               
               <form id="wc_klawoo_settings_form" action="" method="post">
                   <div id="wc_klawoo_account_option">
                       <h3><input type="radio" id="new_account" name="account_option" value="new_account" <?php checked( "new_account", $option_name );?>> &nbsp; <?php _e('Create a new Klawoo Account', $this->text_domain)?> &nbsp;
                        <input type="radio" id="existing_cust" name="account_option" value="existing_cust" <?php checked( "existing_cust", $option_name );?>> &nbsp; <?php _e('Existing Klawoo User', $this->text_domain)?></h3>

                   </div>
                   <table class="form-table" id="klawoo_credentials">
                       <tr><th><label for="klawoo_email_address"><?php _e("Your Klawoo Email Address", $this->text_domain); ?></label></th>
                           <td><input id="klawoo_email_address" type="text" size="50" name="klawoo_email_address" value="<?php echo $this->email_address; ?>"/></td>
                       </tr>
                       <tr><th><label for="klawoo_password"><?php _e("Your Klawoo Password", $this->text_domain); ?></label></th>
                           <td><input id="klawoo_password" type="password" size="50" name="klawoo_password" value="<?php echo $this->password; ?>"/></td>
                       </tr>
                       <tr>
                           <th><label id="klawoo_brand_lbl" for="klawoo_brand"><?php _e("Your Klawoo Brand", $this->text_domain); ?></label></th>
                           <td><input id="klawoo_brand" type="text" size="50" name="klawoo_brand"/></td>
                       </tr>
                       <tr><th>&nbsp;</th>
                           <td><input type="submit" id="wc_klawoo_verify_credentials" class="button-primary" value="<?php _e("Sign Me Up", $this->text_domain); ?>"><br/><br/>       
                           </td>
                       </tr>
                   </table>                    
               </form>
               </div>

               <script type="text/javascript">

                  jQuery("input[name='account_option']").on('change',function() {
                  
                  if (this.value == "existing_cust" ) {
                      jQuery('#klawoo_email_address').val( '<?php echo $this->email_address;?>' );
                      jQuery('#klawoo_password').val( '<?php echo $this->password;?>' );
                  } else {
                      jQuery('#klawoo_email_address').val( '' );
                      jQuery('#klawoo_password').val( '' );
                  }

              });

           </script>

              <?php if( $this->email_address != "" && $this->password != "" ){ ?>

               <div id="wc_klawoo_brands" style="display: none;">
                   <h3><?php _e("Select a Brand", $this->text_domain); ?></h3>
                   <form id="wc_klawoo_save_brand_form" action="" method="post">
                       <div id="wc_klawoo_show_brands">
                           <table class="form-table">
                               <tr><th><label for="klawoo_brand_select_box"><?php _e("Select a brand", $this->text_domain); ?></label></th>
                                   <td><span id="klawoo_brand_select_box" >

                                       <?php
                                       $brand = get_option('wc_klawoo_brand_list');
                                       $selected_brand_id = $this->brand_id;

                                       if( !empty( $brand ) ){
                                           $brand_select = '<select id="klawoo_brand_select" name="klawoo_brand_select">';
                                          $brand_select .= '<option value="0">Select Brand</option>';

                                           foreach( $brand as $brand_id => $brand_name ){

                                               if ( sizeof($brand) > 1) {
                                               $selected = ($brand_id == $selected_brand_id) ? 'selected' : '';
                                               } else {
                                                  $selected = 'selected';
                                               }  
                                               $brand_select .= '<option value='. $brand_id .' '. $selected .' >'. $brand_name .'</option>';
                                           }

                                           $brand_select .= '</select>';
                                           echo $brand_select;
                                       } else {

                                       }

                                       ?>
                                       </span></td>
                               </tr>
                               <tr><th>&nbsp;</th>
                                   <td><input type="submit" id="wc_klawoo_brand_save" class="button-primary" value="<?php _e("Save", $this->text_domain); ?>"><br/><br/>

                                   </td>
                               </tr>
                           </table>
                       </div>
                   </form>
               </div>

           <?php
               
            $smtp_options = get_option( 'wc_klawoo_smtp_settings' );
            
            $email_option_selected = (!empty($smtp_options['type'])) ? $smtp_options['type'] : 'smtp'; //option for handling the email service
            
            ?>
            <div id="wc_klawoo_smtp" style="display: none;">
                <h3><?php _e("Email Service Settings", $this->text_domain); ?></h3>
                <form id="wc_klawoo_smtp" action="" method="post">
                    <h3><input type="radio" id="smtp_settings" name="email_sending_preference" value="smtp_settings" <?php checked( "smtp", $email_option_selected );?>> &nbsp; <?php _e('SMTP', $this->text_domain)?> &nbsp;
                        <input type="radio" id="aws_settings" name="email_sending_preference" value="aws_settings" <?php checked( "aws", $email_option_selected );?>> &nbsp; <?php _e('Amazon SES', $this->text_domain)?></h3>
                    <div id="wc_klawoo_smtp_settings">
                        <table class="form-table" id="smtp_list_option">
                            <tr><th><label for="klawoo_smtp_host"><?php _e("Host", $this->text_domain); ?></label></th>
                                 <td><input id="klawoo_smtp_host" type="text" size="50" name="klawoo_smtp_host" value="<?php echo (!empty($smtp_options['host'])) ? $smtp_options['host'] : ''; ?>"/></td>
                            <td rowspan="4"> 
                                <div id="wc_klawoo_smtp_message" class = "display_left create_smtp_note">
                                  <p style = "margin-left: 5px;">
                                      You can use any SMTP service. <br>
                                      We recommend <a href = "https://mandrill.com/signup/" target="_blank"> Mandrill</a>. It works great and you can send upto 12000 mails using the free plan.
                                  </p>
                              </div>
                            </td>
                            </tr>
                            <tr><th><label for="klawoo_smtp_port"><?php _e("Port", $this->text_domain); ?></label></th>
                                <td>
                                     <select name="smtp_port" id="smtp_ssl">

                                          <?php $smtp_port = (!empty($smtp_options['port'])) ? $smtp_options['port'] : '';
                                                $smtp_ssl = (!empty($smtp_options['ssl'])) ? $smtp_options['ssl'] : '';
                                                $smtp_ses_endpoint = (!empty($smtp_options['ses_endpoint'])) ? $smtp_options['ses_endpoint'] : '';
                                          ?>

                                         <option value="465" id="465" <?php selected( '465', $smtp_port ); ?> >465</option>
                                         <option value="587" id="587" <?php selected( '587', $smtp_port ); ?> >587</option>
                                         <option value="25" id="25" <?php selected( '25', $smtp_port ); ?> >25</option>
                                     </select>
                                 </td>
                            </tr>
                            <tr><th><label for="klawoo_smtp_ssl"><?php _e("SSL/TLS", $this->text_domain); ?></label></th>
                                 <td>
                                     <select name="smtp_ssl" id="smtp_ssl">
                                         <option value="ssl" id="ssl" <?php selected( 'ssl', $smtp_ssl ); ?> >SSL</option>
                                         <option value="tls" id="tls" <?php selected( 'tls', $smtp_ssl ); ?> >TLS</option>
                                     </select>
                                 </td>
                            </tr>
                            <tr><th><label for="klawoo_smtp_username"><?php _e("Username", $this->text_domain); ?></label></th>
                                 <td><input id="klawoo_smtp_username" type="text" size="50" name="klawoo_smtp_username" value="<?php echo (!empty($smtp_options['username'])) ? $smtp_options['username'] : ''; ?>"/></td>
                            </tr>
                            <tr><th><label for="klawoo_smtp_password"><?php _e("Password", $this->text_domain); ?></label></th>
                                 <td><input id="klawoo_smtp_password" type="password" size="50" name="klawoo_smtp_password" value="<?php echo (!empty($smtp_options['password'])) ? $smtp_options['password'] : ''; ?>"/></td>
                            </tr>
                        </table>
                    </div>
                    <div id="wc_klawoo_aws_settings">
                        <table class="form-table" id="aws_settings">
                            <tr><th><label for="klawoo_s3_key"><?php _e("AWS Access Key ID", $this->text_domain); ?></label></th>
                                 <td><input id="klawoo_s3_key" type="text" size="50" name="klawoo_s3_key" value="<?php echo (!empty($smtp_options['s3_key'])) ? $smtp_options['s3_key'] : ''; ?>"/></td>
                            </tr>
                            <tr><th><label for="klawoo_s3_secret"><?php _e("AWS Secret Access Key", $this->text_domain); ?></label></th>
                                 <td><input id="klawoo_s3_secret" type="text" size="50" name="klawoo_s3_secret" value="<?php echo (!empty($smtp_options['s3_secret'])) ? $smtp_options['s3_secret'] : ''; ?>"/></td>
                            </tr>
                            <tr><th><label for="klawoo_ses_region"><?php _e("Amazon SES region", $this->text_domain); ?></label></th>
                                 <td>
                                     <select id="klawoo_ses_region" name="klawoo_ses_region">
                                         <option <?php selected( 'email.us-west-2.amazonaws.com', $smtp_ses_endpoint ); ?> value="email.us-west-2.amazonaws.com"><?php _e("Oregon", $this->text_domain); ?></option> 
                                         <option <?php selected( 'email.us-east-1.amazonaws.com', $smtp_ses_endpoint ); ?> value="email.us-east-1.amazonaws.com"><?php _e("N. Virginia", $this->text_domain); ?></option> 
                                         <option <?php selected( 'email.eu-west-1.amazonaws.com', $smtp_ses_endpoint ); ?> value="email.eu-west-1.amazonaws.com"><?php _e("Ireland", $this->text_domain); ?></option>
                                     </select>
                                 </td>
                            </tr>
                        </table>                       
                    </div>
                     <table class="form-table">
                    <tr><th>&nbsp;</th>
                             <td>
                                 <input type="submit" id="wc_klawoo_save_smtp" class="button-primary" value="<?php _e("Save SMTP Settings", $this->text_domain); ?>"><br/><br/>
                                 <div id="wc_klawoo_save_smtp_msg" style="margin-top: -20px" > </div>
                             </td>
                            </tr>
                    </table>
                </form>
            </div>

           <?php

           $brand_list = get_option('wc_klawoo_brand_list');

           $klawoo_create_list_display = ( !empty($smtp_options) && sizeof($smtp_options) > 1 ) ? '' : 'display: none;';

           if( !empty( $this->brand_id ) ){
             $create_list_options = get_option('wc_klawoo_create_list_options');

             $brand_name = $brand_list[$this->brand_id]; 
             $header = $brand_name . " " ."Lists";

             $create_list_based_on_products = '';
             $create_list_based_on_categories = '';
             $create_list_based_on_prod_variations = '';

             if ( !empty ($create_list_options) ) {
                 $create_list_based_on_products = ( $create_list_options['create_list_based_on_products'] == "true" ) ? 'checked' : '';
                 $create_list_based_on_categories = ( $create_list_options['create_list_based_on_categories'] == "true" ) ? 'checked' : '';
                 $create_list_based_on_prod_variations = ( $create_list_options['create_list_based_on_prod_variations'] == "true" ) ? 'checked' : '';
             }

               ?>
               <div id="wc_klawoo_list_option" style="<?php echo $klawoo_create_list_display;?> width:100%;" class = "display_left">
                   <h3><?php _e("Customer lists based on", $this->text_domain); ?></h3>
                   <form id="wc_klawoo_create_list" action="" method="post">
                       <table class="form-table" id="table_list_option">
                           <tr>
                               <th scope="row" class="titledesc"><?php _e($header, $this->text_domain); ?></th>
                               <td class="forminp forminp-checkbox" style="<?php echo "width:" . $width;?>">
                                   <fieldset>
                                           <label for="list_based_on_products">
                                               <input type="checkbox" id="list_based_on_products" name="list_based_on_products" class="checkbox" value="list_based_on_products" <?php echo $create_list_based_on_products;?> /> All Products 
                                           </label> 									
                                   </fieldset>
                                   <fieldset>
                                           <label for="list_based_on_prod_variations">
                                               <input type="checkbox" id="list_based_on_prod_variations" name="list_based_on_prod_variations" class="checkbox" value="list_based_on_prod_variations" <?php echo $create_list_based_on_prod_variations;?> /> All Product <b>Variations</b>
                                           </label> 									
                                   </fieldset>
                                   <fieldset>
                                           <label for="list_based_on_categories">
                                               <input type="checkbox" id="list_based_on_categories" name="list_based_on_categories" class="checkbox" value="list_based_on_categories" <?php echo $create_list_based_on_categories;?> /> All Product <b>Categories</b>
                                           </label>                                    
                                   </fieldset>
                               </td>

                               <td rowspan="2">
                                    <div id="wc_klawoo_list_option_note" class = "create_list_note" >
                                    <p style ="margin-left:5px">
                                    When a new order arrives the customer will be automatically be subscribed to <br>
                                      - All Customers List
                                      <span id ="create_list_note_append" >
                                    </p>
                                  </div>
                               </td>

                           </tr>
                           <tr>
                               <th>&nbsp;</th>
                               <td>
                                  <fieldset>
                                   <input type="submit" id="wc_klawoo_create_lists" class="button-primary" value="<?php _e("Save & Create Lists", $this->text_domain); ?>"><br/><br/>
                                  </fieldset>
                                  <fieldset>
                                      <div id="wc_klawoo_create_list_msg" style="margin-top: -20px" > </div>
                                  </fieldset>

                               </td>
                           </tr>
                      </table>
                  </form>
               </div>

               <?php  $klawoo_import_display = ( !empty($create_list_options) ) ? '' : 'display: none;'; ?>

               <div id="wc_klawoo_import_option" style="<?php echo $klawoo_import_display; ?>" class = "display_left">
                   <h3> <?php _e("Synchronize", $this->text_domain); ?></h3>
                   <form id="wc_klawoo_import_option" action="" method="post">
                       <table class="form-table">
                           <tr>
                               <th scope="row" class="titledesc"><?php _e('Import existing orders', $this->text_domain); ?></th>
                               <td class="forminp forminp-checkbox">
                                   <fieldset>
                                           <label for="wc_klawoo_import_existing_orders">
                                               <input type="submit" id="wc_klawoo_import_existing_orders" class="button-primary" value="<?php _e("Sync Lists & Subscriptions", $this->text_domain); ?>">
                                           </label> 									
                                   </fieldset>
                                    <fieldset>
                                        <div id="wc_klawoo_import_progressbar" class="wc_klawoo_import_progressbar" style="display:none;"><div id="wc_klawoo_import_progress_label" ><?php _e('Syncing Data...', $this->text_domain );?></div></div>
                                    </fieldset>                                    
                           </tr>
                      </table>
                  </form>
               </div>
               </div>
           <?php
         }
        }
      }
        
        public function validate_and_get_brands () {
            $response = array( 'ACK' => 'Failure', 'code' => '', 'message' => __('Invalid Credentials. Please try again.', $this->text_domain) ) ;

            $brand_name = '';

            if( !empty($_POST['action']) ) {
                $action = (trim($_POST['action']) == "klawoo_sign_up") ? "klawoo_sign_up" : "get_brands";
            }
            
            if ( !empty($_POST['klawoo_password']) && !empty($_POST['klawoo_email_address'])  ) {
                $email = trim($_POST['klawoo_email_address']);
                $password = trim($_POST['klawoo_password']);
            }
            
            if( $action == "klawoo_sign_up" && !empty($_POST['klawoo_brand']) ) {
                $brand_name = $_POST['klawoo_brand'];
            }
            
            if ( !empty($_POST['klawoo_password']) && !empty($_POST['klawoo_email_address']) || ( $action == "klawoo_sign_up" && !empty($_POST['klawoo_brand']) )  ) {
               
                $result = $this->validate_api_info( $action, $password, $email, $brand_name );
                
                if( is_wp_error( $result )){
                    $response ['message'] = $result->get_error_message();
                } else {

                    $response = json_decode( $result['body'],true );

                    if ( empty($response) ) {
                        $response = $result ['response'];
                    }

                    if ($response['ACK'] == "Success") {

                        if ( $action == "klawoo_sign_up" ) {
                            delete_option ('wc_klawoo_smtp_settings');
                            delete_option ('wc_klawoo_create_list_options');
                            delete_option ('wc_klawoo_main_list_id');
                        }

                        // For the time being unsetting the main_list_id
                        if (isset($response['data']['main_list_id'])) {
                            unset($response['data']['main_list_id']);
                        }

                        $klawoo_credentials = array(); 
                        $klawoo_credentials ['email_address'] = $email;
                        $klawoo_credentials ['password'] = $password;

                        update_option ('wc_klawoo_credentials', $klawoo_credentials);
                        update_option ('wc_klawoo_brand_list', $response['data']);

                        if ( sizeof($response['data']) == 1 ) {
                            $brand_id = array_keys($response['data']);
                            update_option ('wc_klawoo_selected_brand_id', $brand_id[0]);
                            $this->brand_id = $brand_id[0];
                    }
                }
            }
            }
            die( json_encode( $response ));
        }
        
        private function validate_api_info( $action, $password, $email, $brand ){
           // Validate with API server
           
           $data = array();
           $data['action'] =  $action;
           if( $action == "klawoo_sign_up" ){
               $data['brand_name'] =  $brand;
           }
           
           $result = wp_remote_post( $this->api_url, 
                       array('headers' => array(
                               'Content-Type' => 'application/json',
                               'Authorization' => 'Basic ' . base64_encode( $email . ':' . $password )
                               ), 
                              'timeout' => 120, 
                              'body' => $data
                           )
                       );
           
           return $result;
           
        }
       
        public function klawoo_save_smtp_settings() {
            
            $smtp_data = array();
            
            $emailing_service_type =  trim($_POST['klawoo_email_sevice_type']);
            if( $emailing_service_type == 'smtp' ){
                $smtp_data['type'] = $emailing_service_type;
                $smtp_data['host'] = trim($_POST['klawoo_smtp_host']);
                $smtp_data['port'] = (!empty($_POST['klawoo_smtp_port'])) ? trim($_POST['klawoo_smtp_port']) : 25;
                $smtp_data['ssl'] = trim($_POST['klawoo_smtp_ssl']);
                $smtp_data['username'] = trim($_POST['klawoo_smtp_username']);
                $smtp_data['password'] = trim($_POST['klawoo_smtp_pwd']);
                
            } elseif ( $emailing_service_type == 'aws' ) {
                $smtp_data['type'] = $emailing_service_type;
                $smtp_data['s3_key'] = trim($_POST['klawoo_s3_key']);
                $smtp_data['s3_secret'] = trim($_POST['klawoo_s3_secret']);
                $smtp_data['ses_endpoint'] = trim($_POST['klawoo_ses_endpoint_name']);
            }
            
            $result = wp_remote_post( $this->api_url, 
             array('headers' => array(
                     'Content-Type' => 'application/json',
                     'Authorization' => 'Basic ' . base64_encode( $this->email_address . ':' . $this->password )
                     ), 
                    'timeout' => 120, 
                    'body' => array('action' => 'save_smtp_settings', 'brand_id' => $this->brand_id, 'smtp_data' => json_encode($smtp_data) )
                 )
             );
            
            if( !is_wp_error( $result )){
                $response = json_decode( $result['body'],true );
                if ($response['ACK'] == "Success") {
                    update_option ('wc_klawoo_smtp_settings', $smtp_data);
                } 
            }
     
           die( $result['body'] );
        }


        public function klawoo_save_selected_brand () {
            update_option ('wc_klawoo_selected_brand_id', $_POST['brand_id']);
            $this->brand_id = $_POST['brand_id'];
        }
        
        private function get_all_list_names( $create_list_based_on_products, $create_list_based_on_category, $create_list_based_on_prod_variations, $id ){
            global $wpdb,$wp_filter;
            
            $klawoo_list_names = array();
            $custom_fields = array( 'fname', 'lname', 'order_date' ); //array for custom fields fname and lname for each list

            if( !empty($wp_filter['sa_bulk_express_login_link']) ) {
                $custom_fields[] = 'express_login_link';
            }

            if( $create_list_based_on_prod_variations == "true" ){

                $query_terms = "SELECT terms.slug as slug, terms.name as term_name
                                          FROM {$wpdb->prefix}terms AS terms
                                            JOIN {$wpdb->prefix}postmeta AS postmeta 
                                                ON ( postmeta.meta_value = terms.slug 
                                                        AND postmeta.meta_key LIKE 'attribute_%' ) 
                                          GROUP BY terms.slug";
                $attributes_terms = $wpdb->get_results( $query_terms, 'ARRAY_A' );

                $attributes = array();
                foreach ( $attributes_terms as $attributes_term ) {
                    $attributes[$attributes_term['slug']] = $attributes_term['term_name'];
                }

                $product_variation_list_query = "SELECT posts.id AS product_id,
                                                posts.post_parent AS post_parent,
                                                posts.post_title AS product_name,
                                                postmeta.meta_value AS meta_value,
                                                postmeta.meta_key AS meta_key
                                        FROM  {$wpdb->prefix}posts AS posts
                                            JOIN {$wpdb->prefix}postmeta AS postmeta ON (posts.id = postmeta.post_id) 
                                        WHERE  posts.post_type IN ('product', 'product_variation')
                                            AND posts.post_status IN ('publish','private') 
                                            AND (postmeta.meta_key LIKE '_product_attributes' 
                                                OR postmeta.meta_key LIKE 'attribute_%')";

                if( !empty( $id ) ) {
                    $product_variation_list_query .= " AND (posts.id IN ( $id ) OR posts.post_parent IN ( $id ) ) ";
                }

                $product_variation_list_query .="GROUP BY product_id, meta_key
                                                    ORDER BY product_id, postmeta.meta_id";

                $product_variation_list_results = $wpdb->get_results( $product_variation_list_query, 'ARRAY_A' );

                $product_variation_list_num_rows = $wpdb->num_rows;

                $parent_ids = array();

                foreach ($product_variation_list_results as $product_variation_list_result) {

                    $prod_id = $product_variation_list_result['product_id'];
                    $parent_id = $product_variation_list_result['post_parent'];

                    if ($parent_id == 0) {
                        $parent_ids [$prod_id] = $product_variation_list_result['product_name'];    
                    } elseif ( isset($parent_ids [$parent_id]) ) {

                        if ( !isset($klawoo_list_names [$prod_id]) ) {
                            $klawoo_list_names [$prod_id] = array();

                            $product_type_parent = wp_get_object_terms($parent_id, 'product_type', array('fields' => 'slugs'));

                            $prefix = '';
                            if( !empty($product_type_parent[0]) && $product_type_parent[0] != "simple" && $product_type_parent[0] != "variable" ) {
                              $prefix = ucwords(str_replace('-', ' ', $product_type_parent[0])) . ' ';
                            }

                            $klawoo_list_names [$prod_id]['list_name'] = '['. $prefix .'Variation] ' . $parent_ids [$parent_id] . ' - ';
                        }
    
                        if ( !empty($product_variation_list_result['meta_value']) && $product_variation_list_result['meta_value'] != "a:0:{}" ) {

                            if (strpos($product_variation_list_result['meta_key'], '_pa_') !== FALSE) {
                                $meta_value = $attributes [$product_variation_list_result['meta_value']];
                            } else {
                                $meta_value = $product_variation_list_result['meta_value'];
                            }
                            
                            if (  substr($klawoo_list_names [$prod_id] ['list_name'], -3) == ' - ' ) {
                                $klawoo_list_names [$product_variation_list_result['product_id']] ['list_name'] .= $meta_value;
                            } else {
                                $klawoo_list_names [$product_variation_list_result['product_id']] ['list_name'] .= ', ' . $meta_value;
                            }
                        }
                        
                        $klawoo_list_names [$product_variation_list_result['product_id']] ['custom_fields'] = $custom_fields;
                    }
                }
            }

            if( $create_list_based_on_products  == "true" ){
                
                $product_list_query = "SELECT id AS product_id,
                                                post_title AS product_name
                                        FROM  {$wpdb->prefix}posts AS posts
                                        WHERE  posts.post_type IN ('product')
                                            AND posts.post_status IN ('publish','private')";
                                            
                
                if( !empty( $id ) ) {
                    $product_list_query .= " AND posts.id IN ( $id ) ";
                }
                
                $product_list_results = $wpdb->get_results( $product_list_query, 'ARRAY_A' );
                
                $product_list_num_rows = $wpdb->num_rows;
                
                if ( $product_list_num_rows > 0 ) {


                          // Code to get the product attributes
                          $product_attributes_query = "SELECT post_id AS product_id,
                                                              meta_value AS product_attributes
                                                        FROM  {$wpdb->prefix}postmeta
                                                        WHERE meta_key = '_product_attributes'
                                                            AND meta_value != 'a:0:{}'
                                                            AND meta_value != '' 
                                                            AND meta_value IS NOT NULL";

                          if( !empty( $id ) ) {
                              $product_attributes_query .= " AND post_id IN ( $id ) ";
                          }

                          $product_attributes_results = $wpdb->get_results( $product_attributes_query, 'ARRAY_A' );
                        
                          $product_attributes_num_rows = $wpdb->num_rows;

                          $product_attributes = array();

                          if( $product_attributes_num_rows > 0 ) {
                              foreach ($product_attributes_results as $value) {
                                $product_attributes[$value['product_id']] = $value['product_attributes'];
                              }    
                          }
                
                        foreach ( $product_list_results as $product_list_result ) {

                                $this->product_list_ids [$product_list_result['product_id']] = '';

                                $klawoo_list_names [$product_list_result['product_id']] = array();

                                $product_type = wp_get_object_terms($product_list_result['product_id'], 'product_type', array('fields' => 'slugs'));

                                $prefix = '';
                                if( !empty($product_type[0]) && $product_type[0] != "simple" && $product_type[0] != "variable" ) {
                                  $prefix = ucwords(str_replace('-', ' ', $product_type[0])) . ' ';
                                }

                                $klawoo_list_names [$product_list_result['product_id']] ['list_name'] = '['. $prefix .'Product] ' . $product_list_result['product_name'];

                                $prod_custom_fields = $klawoo_list_names [$product_list_result['product_id']] ['custom_fields'] = $custom_fields;
                                $prod_att_unserialized = (!empty($product_attributes[$product_list_result['product_id']])) ? maybe_unserialize($product_attributes[$product_list_result['product_id']]) : array();


                                if ( !empty($prod_att_unserialized) ) {

                                    $prod_att_keys = array_keys($prod_att_unserialized);

                                    foreach ( $prod_att_keys as &$prod_att ) {

                                        if (strpos($prod_att, 'pa_') !== FALSE) {
                                            $prod_att = substr( $prod_att, 3);
                                        }

                                        $prod_custom_fields[] = $prod_att;
                                }

                                    $prod_custom_fields = $klawoo_list_names [$product_list_result['product_id']] ['custom_fields'] = $prod_custom_fields;
                      }
                    }
                }
            }
            
            if( $create_list_based_on_category == "true" ){
                
                $query_category_list = "SELECT terms.name AS category_nm,
                                        wt.term_taxonomy_id AS category_id,
                                        wt.parent AS category_parent_id
                                FROM {$wpdb->prefix}term_taxonomy AS wt
                                    JOIN {$wpdb->prefix}terms AS terms ON (wt.term_id = terms.term_id)
                                WHERE wt.taxonomy like 'product_cat'
                                    ";
                                    
                if( !empty( $id ) ) {
                    $query_category_list .= "AND wt.term_taxonomy_id IN ( " .$id. " )" ;
                }
                
                $query_category_list .= " ORDER BY category_id " ;
                
                $category_list_results = $wpdb->get_results( $query_category_list, 'ARRAY_A' );
                

                $category_list_num_rows = $wpdb->num_rows;

                if ( $category_list_num_rows > 0 ) {

                    foreach ( $category_list_results as $category_list_result ) {

                        $this->category_list_ids [$category_list_result['category_id']] = '';

                        $klawoo_list_names [$category_list_result['category_id']] = array();
                        $klawoo_list_names [$category_list_result['category_id']] ['list_name'] = '[Category] ' . $category_list_result['category_nm'];

                        if ( $category_list_result['category_parent_id'] > 0 ) {
                            $klawoo_list_names [$category_list_result['category_id']] ['list_name']= $klawoo_list_names [$category_list_result['category_parent_id']] ['list_name'] . ' - ' . $category_list_result['category_nm'];
                        }

                        $klawoo_list_names [$category_list_result['category_id']] ['custom_fields'] = $custom_fields;
                    }
            
                }
            }  

            return $klawoo_list_names;
        }
        
        public function insert_new_list( $list_data, $list_ids ){
            global $wpdb;
            $values = array();
            $values_inserted = array();

//            =======================================            
            
            // $list_id_stored = array();
            
            // $query_list_ids = "SELECT id, list_id FROM {$wpdb->prefix}wc_klawoo WHERE brand_id =". $this->brand_id;
            // $result_list_ids = $wpdb->get_results( $query_list_ids, 'ARRAY_A' );

            // foreach ($result_list_ids as $result_list_id) {
            //     $list_id_stored [ $result_list_id ['id'] ] = $result_list_id ['list_id'];
            // }
            
//            =======================================
            
            $list_ids = array_unique($list_ids);
            foreach( $list_ids as $prod_id => $list_id ){
                $list_name = $wpdb->_real_escape($list_data[$prod_id]['list_name']);
                $custom_attributes = $list_data[$prod_id]['custom_fields'];

                if( !empty($custom_attributes) ){
                    $custom_attributes = maybe_serialize($custom_attributes);
                }

                // if ( isset( $list_id_stored [$prod_id] ) ) {
                //     $values_updated[] = "WHEN " . $prod_id  . " THEN '" . $list_id . "'";
                // } else {
                    $values_inserted[] = "(" . $prod_id . ", '" . $list_name . "' , '" . $list_id . "' , '" . $this->brand_id . "' , '" . $custom_attributes . "')"; 
                // }
                
                
            }
            
            $result_inserted = true;

            // Insert only if it does not exist
            if ( sizeof( $values_inserted ) > 0 ) {
                $insert_query = " REPLACE INTO {$wpdb->prefix}wc_klawoo (id, list_name, list_id, brand_id, custom_attributes) VALUES";
                $insert_query .= implode( ',', $values_inserted );
                $result_inserted = $wpdb->query( $insert_query );
            }
                        
            // Code for updating the list ids
            // if ( !empty($values_updated) && sizeof( $values_updated ) > 0 ) {
            //     $update_query = " UPDATE {$wpdb->prefix}wc_klawoo
            //                         SET list_id = CASE id ". implode("\n", $values_updated) ."
            //                                 END                                    
            //                         WHERE brand_id = ".$this->brand_id;   
            //     $result_updated = $wpdb->query( $update_query );
            //     $num_updated = $wpdb->num_rows;
            // }

            if ( $result_inserted === false) {
                return false;
            } else {
                return true;
            }
        }
        
        public function klawoo_create_lists () {
            global $wpdb;

            $response = array( 'ACK' => 'Failure' );

            $all_customer_list_id = get_option('wc_klawoo_main_list_id');

            $main_list_id = (!empty($all_customer_list_id[$this->brand_id])) ? $all_customer_list_id[$this->brand_id] : '';

            // Check whether post is empty or not
            if( isset( $_POST['list_based_on_products'] ) || isset( $_POST['list_based_on_categories'] ) || isset( $_POST['list_based_on_prod_variations'] ) ){
                
                $create_list_based_on_products = ( !empty($_POST['list_based_on_products']) ) ? $_POST['list_based_on_products'] : false ;
                $create_list_based_on_categories = ( !empty($_POST['list_based_on_categories']) ) ? $_POST['list_based_on_categories'] : false ;
                $create_list_based_on_prod_variations = ( !empty($_POST['list_based_on_prod_variations']) ) ? $_POST['list_based_on_prod_variations'] : false ;

                // Code for saving the options
                $create_list_options = array();
                $create_list_options ['create_list_based_on_products'] = $create_list_based_on_products;
                $create_list_options ['create_list_based_on_categories'] = $create_list_based_on_categories;
                $create_list_options ['create_list_based_on_prod_variations'] = $create_list_based_on_prod_variations;

                update_option ('wc_klawoo_create_list_options', $create_list_options);
                
            } else {
                
                $create_list_option = get_option( 'wc_klawoo_create_list_options' );
                if( !empty( $create_list_option ) ){
                    $create_list_based_on_products = $create_list_option['create_list_based_on_products'];
                    $create_list_based_on_categories = $create_list_option['create_list_based_on_categories'];
                    $create_list_based_on_prod_variations = $create_list_option['create_list_based_on_prod_variations'];
                }
            }

            if( $create_list_based_on_products || $create_list_based_on_categories|| $create_list_based_on_prod_variations ){
                $klawoo_lists = $this->get_all_list_names( $create_list_based_on_products, $create_list_based_on_categories, $create_list_based_on_prod_variations, null );
            }

            if( !empty( $klawoo_lists ) ){
                
                $result = wp_remote_post( $this->api_url, 
                       array('headers' => array(
                               'Content-Type' => 'application/json',
                               'Authorization' => 'Basic ' . base64_encode( $this->email_address . ':' . $this->password )
                               ), 
                              'timeout' => 120, 
                              'body' => array('action' => 'fetch_and_create_list', 'list_name' => json_encode($klawoo_lists), 'brand_id' => $this->brand_id, 'create_if_not_found' => 1, 'main_list_id' => $main_list_id )
                           )
                       );

                if( !is_wp_error( $result ) ){
                  $response = json_decode( $result['body'], true );

                  if ($response['ACK'] == "Success") {
                      
                      if( is_array( $response['data'] ) && !empty( $response['data'] ) ){
                          
                          $list_ids = $response['data'];

                          $insert_result = $this->insert_new_list( $klawoo_lists, $list_ids );
                          
                          if ($insert_result === false) {
                              $response = array( 'ACK' => 'Failure' );
                              die (json_encode($response));
                          }

                          unset($response['data']);
                      }
                      
                      if( !empty( $response['main_list_id'] ) ){
                          
                          if( !isset( $all_customer_list_id[$this->brand_id] ) ){
                              $all_customer_list_id[$this->brand_id] = $response['main_list_id'] ;
                          } else {
                              $main_list = $all_customer_list_id[$this->brand_id];
                              if( $response['main_list_id']  !== $main_list ){
                                  $all_customer_list_id[$this->brand_id] = $response['main_list_id'] ;
                              }
                          }
                          
                          update_option( 'wc_klawoo_main_list_id', $all_customer_list_id );
                      }
                  }
                }

        } else {

        }
        
        die (json_encode($response));    
    }
    
        public function get_all_existing_orders_data( &$list_ids, &$order_ids, &$order_dates, &$term_names, &$cat_ids, &$list_creation, &$variation_nm, $product_nm ) {
        global $wpdb;

        $create_list_options = get_option('wc_klawoo_create_list_options');

        $sync_data = array(); // main array containing the sync data

        //code for getting the order details

        $query_order_details = "SELECT post_id as order_id,
                                    meta_key,
                                    meta_value
                                FROM {$wpdb->prefix}postmeta
                                    WHERE meta_key IN ('_billing_first_name','_billing_last_name','_billing_email')
                                    AND post_id IN (". implode(",", $order_ids) .")
                                GROUP BY post_id, meta_key
                                ORDER BY post_id";

        $result_order_details = $wpdb->get_results($query_order_details, 'ARRAY_A');
        $rows_order_details =  $wpdb->num_rows;

        if ($rows_order_details = 0) {
            //no order details found
            return;
        }

            // $skip_flag = 0;

        foreach ($result_order_details as $result_order_detail) {

            $meta_key = $result_order_detail['meta_key'];
            $meta_value = $result_order_detail['meta_value'];
            $order_id = $result_order_detail['order_id'];

            if ( !isset($sync_data[$order_id]) ) {
                $sync_data[$order_id] = array();
                $sync_data[$order_id] ['subscription_date'] = $order_dates [$order_id];
                    //$skip_flag = 0;
            }

            if ( $meta_key == '_customer_user' ) {

            } else {

                if ($meta_key == "_billing_email") {
                    $meta_key = 'subscriber_email';
                } elseif ($meta_key == "_billing_first_name") {
                    $meta_key = 'subscriber_fname';
                } elseif ($meta_key == "_billing_last_name") {
                    $meta_key = 'subscriber_lname';
                }
                $sync_data[$order_id] [$meta_key] = $meta_value;
            }
        }

        // Code for removing the orders with no email
        foreach ($sync_data as $key => $data) {
            if ( empty($data['subscriber_email']) ) {
                unset($sync_data[$key]);
                unset($order_ids[array_search($key, $order_ids)]);
            }
        }
        
        //query to get all the prod_ids along with the order ids

        $query_get_prod_order_ids = "SELECT items.order_id as order_id,
                                    itemmeta.order_item_id as order_item_id,
                                    items.order_item_name as prod_nm,
                                    itemmeta.meta_value as meta_value,
                                    itemmeta.meta_key as meta_key
                                FROM {$wpdb->prefix}woocommerce_order_items AS items
                                    JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS itemmeta
                                        ON (itemmeta.order_item_id = items.order_item_id)
                                WHERE items.order_item_type LIKE 'line_item'
                                    AND (itemmeta.meta_key IN ('_product_id', '_variation_id')
                                            OR itemmeta.meta_key NOT LIKE '\_%')
                                    AND order_id IN (". implode(",", $order_ids) .")
                                GROUP BY order_id, meta_value
                                ORDER BY order_item_id";

        $result_get_prod_order_ids = $wpdb->get_results($query_get_prod_order_ids, 'ARRAY_A');
        $rows_get_prod_order_ids =  $wpdb->num_rows;

        if ($rows_get_prod_order_ids == 0) {
            //no order details found
            return;
        }


        //query to get all the chained_product_ids along with the order ids

        $query_get_chained_prod_om_ids = "SELECT itemmeta.order_item_id as order_item_id
                                            FROM {$wpdb->prefix}woocommerce_order_items AS items
                                                JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS itemmeta
                                                    ON (itemmeta.order_item_id = items.order_item_id
                                                        AND itemmeta.meta_key = '_chained_product_of')
                                            WHERE items.order_item_type LIKE 'line_item'
                                                AND order_id IN (". implode(",", $order_ids) .")";

        $result_get_chained_prod_om_ids = $wpdb->get_col($query_get_chained_prod_om_ids);

        // Code for removing the chained product ids
        if( count($result_get_chained_prod_om_ids) > 0 ) {
            foreach ($result_get_prod_order_ids as $key => $value) {
                $chained = array_search($value['order_item_id'], $result_get_chained_prod_om_ids);
                if( $chained !== FALSE ) {
                    unset($result_get_prod_order_ids[$key]);
                }
            }
        }

        // $variation_nm[$variation_id]['list_name']

        $prod_cat_list_creation_ids = array();

        //code for creating an array if crete lists based on products
        if ($create_list_options ['create_list_based_on_products'] == "true") {

          //Code to make the prod array

            $valid_order = 0;
            
            foreach( $result_get_prod_order_ids as $result_get_prod_order_id ) {

                $order_id = $result_get_prod_order_id['order_id'];
                $order_item_id = $result_get_prod_order_id['order_item_id'];
                $meta_key = $result_get_prod_order_id['meta_key'];
                $meta_value = $result_get_prod_order_id['meta_value'];

                if ( $meta_key == '_variation_id' ) {
                    continue;
                }

                if ( !isset($sync_data[ $order_id ]['list_details']) ) {
                    $sync_data[ $order_id ]['list_details'] = array();
                }

                if ( !isset($sync_data[ $order_id ]['list_creation_details']) && $list_creation == 1 ) {
                    $sync_data[ $order_id ]['list_creation_details'] = array();
                }

                if ( !isset($sync_data[ $order_id ]['list_details'][$order_item_id]) ) {
                    $sync_data[ $order_id ]['list_details'][$order_item_id] = array();
                    $sync_data[ $order_id ]['list_details'][$order_item_id]['custom_fields'] = array();
                }

                if ( $meta_key == '_product_id' ) {

                    if ( isset($list_ids [ $meta_value ]) ) {
                        $sync_data[ $order_id ]['list_details'][$order_item_id]['list_id'] = $list_ids [ $meta_value ];
                        $valid_order = 0;
                    } else {
                        if ($list_creation == 1) {

                            if ( !isset($sync_data[ $order_id ]['list_creation_details'][$order_item_id]) ) {
                                $sync_data[ $order_id ]['list_creation_details'][$order_item_id] = array();
                                $sync_data[ $order_id ]['list_creation_details'][$order_item_id]['custom_fields'] = array();
                            }

                            $sync_data[ $order_id ]['list_creation_details'][$order_item_id]['list_name'] = $product_nm[$meta_value]['list_name'];
                            $sync_data[ $order_id ]['list_details'][$order_item_id]['list_id'] = $meta_value;
                            $prod_cat_list_creation_ids [$meta_value] = array();
                            $prod_cat_list_creation_ids [$meta_value]['list_name'] = $result_get_prod_order_id['prod_nm'];
                            $valid_order = 1;
                        } else {
                          unset ($sync_data[ $order_id ]['list_details'][$order_item_id]);
                    }
                    }

                } else {
                        if (strpos($meta_key, 'pa_') !== FALSE) {
                            $meta_key = substr( $meta_key, 3);
                            $meta_value = $term_names [$meta_value];
                        }

                        if ($valid_order == 1) {
                            $sync_data[ $order_id ]['list_creation_details'][$order_item_id]['custom_fields'] [] = $meta_key;
                        } 
                    
                        if ( isset($sync_data[ $order_id ]['list_details'][$order_item_id]['custom_fields']) ) {
                            $sync_data[ $order_id ]['list_details'][$order_item_id]['custom_fields'] [$meta_key] = $meta_value;  
                        }
                    }
                    
                }
        }

        // Code for category list option
        if ($create_list_options ['create_list_based_on_categories'] == "true") {

            //Code to form the order ids and prod ids array

            $prod_order_ids = array();

            foreach( $result_get_prod_order_ids as $result_get_prod_order_id ) {

                $order_id = $result_get_prod_order_id['order_id'];
                $meta_key = $result_get_prod_order_id['meta_key'];
                $meta_value = $result_get_prod_order_id['meta_value'];

                if ( $meta_key == "_product_id" ) {

                    if ( !isset($prod_order_ids [$order_id]) ) {
                        $prod_order_ids [$order_id] = array();
                    }

                    // $prod_order_ids [$order_id]['order_id'] = $order_id;
                    $prod_order_ids [$order_id][] = $meta_value;

                } else {
                    continue;
                }

            }

            foreach ( $prod_order_ids as $order_id => $prod_ids ) {

                $i = 0;

                foreach ( $prod_ids as $prod_id ) { 
                    if ( isset($sync_data[$order_id]) ) {

                        if ( !isset($sync_data[$order_id]['list_details']) ) {
                            $sync_data[$order_id]['list_details'] = array();
                        }

                        if ( !isset($sync_data[$order_id]['list_creation_details']) && $list_creation == 1) {
                            $sync_data[$order_id]['list_creation_details'] = array();
                        }

                        if ( isset($cat_ids[$prod_id]['list_ids']) ) {
                            foreach ($cat_ids[$prod_id]['list_ids'] as $cat_id) {
                                $sync_data[$order_id]['list_details'][] = array( 'list_id' => $cat_id );
                            }
                        }

                        if ( isset($cat_ids[$prod_id]['list_creation_details']) && $list_creation == 1) {
                            foreach ($cat_ids[$prod_id]['list_creation_details'] as $cat_id => $cat_nm ) {
                                $sync_data[$order_id]['list_creation_details'][ 'cat'.$i ] = array( 'list_name' => $cat_nm );
                                $sync_data[$order_id]['list_details'][ 'cat'.$i ] = array( 'list_id' => $cat_id );

                                $prod_cat_list_creation_ids [$cat_id] = array();
                                $prod_cat_list_creation_ids [$cat_id]['list_name'] = $cat_nm;
                                $i++;
                            }
                        }
                    }
                }
            }
        }

        // Code for Variation list option
        if ($create_list_options ['create_list_based_on_prod_variations'] == "true") {

            $variation_order_ids = array();

            foreach( $result_get_prod_order_ids as $result_get_prod_order_id ) {

                $order_id = $result_get_prod_order_id['order_id'];
                $meta_key = $result_get_prod_order_id['meta_key'];
                $meta_value = $result_get_prod_order_id['meta_value'];

                if ( $meta_key == "_variation_id" && !empty($meta_value) ) {

                    if ( !isset($variation_order_ids [$order_id]) ) {
                        $variation_order_ids [$order_id] = array();
                    }
                    $variation_order_ids [$order_id][] = $meta_value;

                } else {
                    continue;
                }

            }

            foreach ( $variation_order_ids as $order_id => $variation_ids ) {

                $i=0;

                foreach ( $variation_ids as $variation_id ) { 
                    if ( isset($sync_data[$order_id]) ) {

                        if ( !isset($sync_data[$order_id]['list_details']) ) {
                            $sync_data[$order_id]['list_details'] = array();
                        }

                        if ( !isset($sync_data[$order_id]['list_creation_details']) && $list_creation == 1 ) {
                            $sync_data[$order_id]['list_creation_details'] = array();
                        }

                        if ( isset($list_ids[$variation_id]) ) {
                            $sync_data[$order_id]['list_details'][] = array( 'list_id' => $list_ids[$variation_id] );
                        } else {
                            if ($list_creation == 1) {
                                $sync_data[$order_id]['list_creation_details'][ 'variation'.$i ] = array( 'list_name' => $variation_nm[$variation_id]['list_name'] );
                                $sync_data[$order_id]['list_details'][ 'variation'.$i ] = array( 'list_id' => $variation_id );
                                
                                $prod_cat_list_creation_ids [$variation_id] = array();
                                $prod_cat_list_creation_ids [$variation_id]['list_name'] = $variation_nm[$variation_id]['list_name'];
                                $i++;
                            }
                        }
                    }
                }
            }
        }

        if ($list_creation == 0) {
          foreach ($sync_data as $key => $data) {
              if ( empty($data['list_details']) || empty($data['subscription_date']) || empty($data['subscriber_email']) ) {
                  unset($sync_data[$key]);
              }
          }
        }

        $response = array();
        $response ['sync_data'] = $sync_data;
        $response ['prod_cat_list_creation_ids'] = $prod_cat_list_creation_ids;

        return $response;

    }
    
    //Function to get data for Bulk Subscribe
    public function bulk_subscribe_get_data ( $existing_order_data = array(), $order_id = '' ) {

        global $wpdb;

        $response = array( 'ACK' => 'Failure' ) ;
        $list_creation = (!empty($order_id)) ? 1 : 0;

        //Query to get all the list ids

        $list_ids = array(); // array containing all the list ids

        $query_list_ids = "SELECT id, list_id FROM {$wpdb->prefix}wc_klawoo WHERE brand_id = ".$this->brand_id;
        $result_list_ids = $wpdb->get_results( $query_list_ids, 'ARRAY_A' );
        $result_list_ids_count = $wpdb->num_rows;

        if ($result_list_ids_count == 0) {

            //return error msg saying no list present
                // $response ['MSG'] = __('No Lists Found!', $this->text_domain);
                // die(json_encode($response));
        }

        foreach ($result_list_ids as $result_list_id) {
            $list_ids [ $result_list_id ['id'] ] = $result_list_id ['list_id'];
        }

        //Query to get the term and the slugs name

        $query_term_names = "SELECT terms.slug as slug,
                                terms.name as name
                            FROM  {$wpdb->prefix}term_taxonomy AS term_taxonomy 
                                    JOIN {$wpdb->prefix}terms AS terms
                                    ON term_taxonomy.term_id = terms.term_id
                            WHERE term_taxonomy.taxonomy LIKE 'product_cat'
                                OR term_taxonomy.taxonomy LIKE 'pa_%'";

        $result_term_names = $wpdb->get_results($query_term_names, 'ARRAY_A');
        $rows_term_names =  $wpdb->num_rows;

        $term_names = array();

        if ($rows_term_names > 0) {
            foreach ($result_term_names as $result_term_name) {
                $term_names [$result_term_name['slug']] = $result_term_name['name'];
            }
        }


        //Fix for woo2.2+
        if (defined('KLAV_IS_WOO22') && KLAV_IS_WOO22 == 'true') {
            $terms_post_join = '';
            $terms_post_cond = " posts.post_status IN ('wc-completed','wc-processing')";
        } else {
            //Query to get the order status
            $query_order_status = "SELECT term_taxonomy_id 
                                    FROM {$wpdb->prefix}term_taxonomy AS term_taxonomy 
                                        JOIN {$wpdb->prefix}terms AS terms 
                                            ON term_taxonomy.term_id = terms.term_id
                                    WHERE terms.name IN ('completed','processing')";

            $result_order_status = $wpdb->get_col($query_order_status);
            $rows_order_status =  $wpdb->num_rows;

            if ($rows_order_status > 0) {
                $order_status = implode(",",$result_order_status);
            }

            $terms_post_join = "JOIN ". $wpdb->prefix ."term_relationships as tr ON (tr.object_id = posts.id AND posts.post_status IN ('publish'))";
            $terms_post_cond = " tr.term_taxonomy_id IN ($order_status)";
        }

        //Query to get the count and the order ids
        $order_id_cond = (!empty($order_id)) ? "AND posts.id IN ($order_id)" : '';

        $limit_cond = (isset($_POST['start_limit']) && $_POST['start_limit'] != "initial") ? "LIMIT ".$_POST['start_limit'] .",".$this->batch_limit : '';

        $query_order_ids = "SELECT posts.id as order_id,
                                posts.post_date_gmt as order_date
                            FROM {$wpdb->prefix}posts as posts
                                $terms_post_join
                            WHERE $terms_post_cond
                                    $order_id_cond
                                    $limit_cond";

        $result_order_ids = $wpdb->get_results($query_order_ids, 'ARRAY_A');
        $rows_order_ids =  $wpdb->num_rows;

        if ($rows_order_ids == 0) {
            //msg that no valid orders present
                $response ['MSG'] = __('No Data to Sync!', $this->text_domain);
                die(json_encode($response));
        }   

        if ( $_POST['start_limit'] == "initial" ) {
            $response ['ACK'] = 'Success';
            $response ['order_total_count'] = sizeof($result_order_ids);
            $response ['batch_limit'] = $this->batch_limit;

            die(json_encode($response));
        }

        $order_dates = array();

        foreach ($result_order_ids as $result_order_id) {
            $order_dates [$result_order_id['order_id']] = $result_order_id['order_date'];
        }

        $order_ids = array_keys($order_dates);

        $cat_ids = array();

        $create_list_options = get_option('wc_klawoo_create_list_options');

        if ($create_list_options ['create_list_based_on_categories'] == "true") {
          
            //code to get all the category names
            $category_nm = ($list_creation == 1) ? $this->get_all_list_names( false, true, false, null ) : '';

            //query to fetch all the categories and the related product ids

                $query_cat_prod_ids = "SELECT posts.id as prod_id,
                                            tr.term_taxonomy_id as cat_id
                                        FROM {$wpdb->prefix}posts as posts
                                            JOIN {$wpdb->prefix}term_relationships as tr
                                                ON (tr.object_id = posts.id)
                                            JOIN {$wpdb->prefix}term_taxonomy as taxonomy
                                                ON (taxonomy.term_taxonomy_id = tr.term_taxonomy_id)
                                        WHERE taxonomy.taxonomy LIKE 'product_cat'
                                        GROUP BY cat_id, prod_id
                                        ORDER BY cat_id";

                $result_cat_prod_ids = $wpdb->get_results($query_cat_prod_ids, 'ARRAY_A');
                $rows_cat_prod_ids =  $wpdb->num_rows;

                if ($rows_cat_prod_ids > 0) {


                    foreach ($result_cat_prod_ids as $result_cat_prod_id) {

                        $cat_id = $result_cat_prod_id ['cat_id'];
                        $prod_id = $result_cat_prod_id ['prod_id'];

                        if ( !isset($cat_ids[$prod_id]['list_ids']) ) {
                            $cat_ids[$prod_id]['list_ids'] = array();
                        }

                        if ( isset($list_ids [ $cat_id ]) ) {
                            $cat_ids[$prod_id]['list_ids'][] = $list_ids [ $cat_id ];
                        } else {
                            if ( !isset($cat_ids[$prod_id]['list_creation_details']) ) {
                                $cat_ids[$prod_id]['list_creation_details'] = array();
                        }

                            $cat_ids[$prod_id]['list_creation_details'][$cat_id] = $category_nm [ $cat_id ]['list_name'];
                    }
                }
            }            
        }
        
        //code to get all the variation names
        $variation_nm = ($list_creation == 1) ? $this->get_all_list_names( false, false, true, null ) : '';
        $product_nm = ($list_creation == 1) ? $this->get_all_list_names( true, false, false, null ) : '';

        // $sync_data = $this->get_all_existing_orders_data( $list_ids, $order_ids, $order_dates, $term_names, $user_details, $cat_ids );
        $existing_order_data = $this->get_all_existing_orders_data( $list_ids, $order_ids, $order_dates, $term_names, $cat_ids, $list_creation, $variation_nm, $product_nm );

        return $existing_order_data;
    }


    //Function to bulk subscribe
    public function bulk_subscribe( $order_id ) {
        global $wpdb, $wp_filter;

        $existing_order_data = array();

        $existing_order_data = apply_filters('wp_klawoo_bulk_subscribe_get_data', array(), $order_id );

        $sync_data = $existing_order_data ['sync_data'];
        $prod_cat_list_creation_ids = $existing_order_data ['prod_cat_list_creation_ids'];

        if( !empty($wp_filter['sa_bulk_express_login_link']) && !empty($sync_data) ) {

            $email_ids = array();
            $email_ids_temp = array();

            //Code for getting the my-account page link
            $myaccount_page_url = '';
            $myaccount_page_id = get_option( 'woocommerce_myaccount_page_id' );
            if ( $myaccount_page_id ) {
              $myaccount_page_url = get_permalink( $myaccount_page_id );
            }

            foreach ($sync_data as $order_id => $sync_data_temp ) {
                $email_ids [$order_id] = $sync_data_temp['subscriber_email'];
                $email_ids_temp [$sync_data_temp['subscriber_email']] = $order_id;
            }

            $generated_links = apply_filters('sa_bulk_express_login_link', array(), $myaccount_page_url , array_unique($email_ids), '', '' );

            if (!empty($generated_links)) {
              foreach ($sync_data as $key => $sync_data1) {
                  $email = $sync_data1['subscriber_email'];
                  $generated_link = (!empty($generated_links [$email])) ? $generated_links[$email] : '';
                  foreach ($sync_data[$key]['list_details'] as $list_key => $list_detail) {
                      $sync_data[$key]['list_details'][$list_key]['custom_fields']['express_login_link'] = $generated_link;
                  }
              }  
            }
        }

        // $brand_id = get_option('wc_klawoo_selected_brand_id', true);
        $all_customer_list_id = get_option('wc_klawoo_main_list_id');
        $main_list_id = $all_customer_list_id[$this->brand_id];
        $data = array('action' => 'bulk_subscribe', 'subscriber_details' => json_encode($sync_data), 'brand_id' => $this->brand_id, 'main_list_id' => $main_list_id );
        
        $result = wp_remote_post( $this->api_url, 
              array('headers' => array(
                      'Content-Type' => 'application/json',
                      'Authorization' => 'Basic ' . base64_encode( $this->email_address . ':' . $this->password )
                      ), 
                     'timeout' => 120, 
                     'body' => $data
                  )
              );

        if( !is_wp_error( $result ) ){
          $response = json_decode($result['body'], true);

          if ( !empty($response['created_list_ids']) ) {
              $this->insert_new_list( $prod_cat_list_creation_ids, $response['created_list_ids'] );
              unset($response['created_list_ids']);
          }
        }

        if ( empty($order_id) ) {
          die(json_encode($response));  
        }
    }
     
    public function create_update_list_for_product( $post_id, $post ) {
        global $wpdb;
       // Have to add additional condition to check wheher to create list or not
       
        $data = array();

        if ($post->post_type != 'product') return;
        if ($post->post_status != 'publish') return;

        $create_list_option = get_option( 'wc_klawoo_create_list_options' );
        
        if( !empty( $create_list_option ) ){
            $create_list_based_on_products = $create_list_option['create_list_based_on_products'];
            $create_list_based_on_categories = $create_list_option['create_list_based_on_categories'];
            $create_list_based_on_prod_variations = $create_list_option['create_list_based_on_prod_variations'];
        }
        
        if( $create_list_based_on_products == true || $create_list_based_on_prod_variations == true ){
            
            $all_customer_list_id = get_option('wc_klawoo_main_list_id');
            $main_list_id = $all_customer_list_id[$this->brand_id];
            
            if( $post->post_date == $post->post_modified ){
                
                $get_list_data = $this->get_all_list_names( $create_list_based_on_products, $create_list_based_on_categories, $create_list_based_on_prod_variations, $post_id );
                $action = 'fetch_and_create_list';
                $data = array('action' => $action, 'list_name' => json_encode($get_list_data), 'brand_id' => $this->brand_id, 'create_if_not_found' => 1, 'main_list_id' => $main_list_id );
                
            }
            
            if( $post->post_date != $post->post_modified ) {
                
                $prod_ids = array();
                
                $prod_ids[] = $post_id;
                
                if( $_POST['product-type'] == 'variable' ){
                    
                    $variable_post_id = (!empty($_POST['variable_post_id']) && is_array($_POST['variable_post_id'])) ? array_values( $_POST['variable_post_id'] ) : array();
                    $prod_ids = array_merge($prod_ids, $variable_post_id );
                }
                
                $prod_ids = array_unique($prod_ids);

                $result_to_get_prod_name = $product_based_list_id = array();

                // Query to get all existing lists
                if( !empty($prod_ids) ) {
                    $query_to_get_prod_name = "SELECT wcsc.id, wcsc.list_name, wcsc.list_id, wcsc.custom_attributes from {$wpdb->prefix}wc_klawoo AS wcsc
                                              WHERE wcsc.id IN (". implode( ',', $prod_ids ) .")";

                    $result_to_get_prod_name = $wpdb->get_results ( $query_to_get_prod_name, 'ARRAY_A' );  
                }
                
                if( count($result_to_get_prod_name) > 0 ){
                    
                    $sc_prod_attributes = array();

                    $product_updated_data = array();
                    if( !empty( $_POST['attribute_names'] ) ){
                        $product_attributes = $_POST['attribute_names'];
                    }
                    
                    foreach( $result_to_get_prod_name as $prod_data ){
                        $product_id = $prod_data['id'];
                        $product_based_list_id[ $prod_data['id'] ] = $prod_data['list_id'];
                        if( $post_id == $product_id ){
                            
                            $product_name = $prod_data['list_name'];
                            if( !empty( $prod_data['custom_attributes'] ) ){
                                $sc_prod_attributes = maybe_unserialize($prod_data['custom_attributes']);
                            }
                        }
                    }

                    $attribute_updated = $product_name_updated = false;
                    $product_name_updated = ( $product_name != $post->post_title ) ? true : false ;
                   
                    foreach( $product_attributes as $attr_name ){
                        
                        if (strpos($attr_name, 'pa_') !== FALSE) {
                            $attr_name = substr( $attr_name, 3);
                        } 
                        
                        if( is_array($sc_prod_attributes) && !in_array( $attr_name, $sc_prod_attributes )) {
                            $attribute_updated = true;
                            break;
                        }
                    }
                    
                    if( $product_name_updated == true || $attribute_updated == true ){
                        
                        $get_list_data = $this->get_all_list_names( $create_list_based_on_products, $create_list_based_on_categories, $create_list_based_on_prod_variations, $post_id );

                        foreach( $get_list_data as $p_id => $list_data ){
//                            if( !isset( $get_list_data[$p_id]['list_id'] ) ){
                                $get_list_data[$p_id]['list_id'] = (!empty($product_based_list_id[$p_id])) ? $product_based_list_id[$p_id] : '';
//                            }
                        }
                        
                        $action = 'update_list';
                        $data = array('action' => $action, 'list_details' => json_encode($get_list_data), 'brand_id' => $this->brand_id );
                    }
                }
            }

            if( !empty($data) ) {
                // create list api 
                $result = wp_remote_post( $this->api_url, 
                   array('headers' => array(
                           'Content-Type' => 'application/json',
                           'Authorization' => 'Basic ' . base64_encode( $this->email_address . ':' . $this->password )
                           ), 
                          'timeout' => 120, 
                          'body' => $data
                       )
                   );

               if( !is_wp_error( $result ) ){ 
                 $response = json_decode( $result['body'], true );
                
                  if ($response['ACK'] == "Success") {
                      
                      if( is_array( $response['data'] ) && !empty( $response['data'] ) ){
                          
                          if( $action == 'fetch_and_create_list' ){

                              $list_ids = $response['data'];
                              $this->insert_new_list( $get_list_data, $list_ids );

                          } elseif( $action == 'update_list' ){
                              
                              $list_ids = $query_case = $attribute_query_case = array();

                              foreach ( $get_list_data as $id => $list_data ) {

                                  $list_ids[] = $id;
                                  $list_nm = $list_data['list_name'];

                                  $query_case[] = "WHEN " . $id  . " THEN '" . $list_nm . "'";

                                  if ( !empty($list_data['custom_fields']) ) {
                                      $custom_fields = implode(':Text%s%', $list_data['custom_fields']) . ':Text';
                                      $attribute_query_case[] = "WHEN " . $id  . " THEN '" . $custom_fields . "'";
                                  }

                              } 

                              if ( !empty($attribute_query_case) ) {
                                  $custom_fields_query  = ",custom_attributes = CASE id ". implode("\n", $attribute_query_case) ." END";
                              }

                              $query_list_update = " UPDATE {$wpdb->prefix}wc_klawoo
                                                      SET list_name = CASE id ". implode("\n", $query_case) ."
                                                              END
                                                      $custom_fields_query                    
                                                      WHERE brand_id = ".$this->brand_id."
                                                           AND id IN (". implode(",", $list_ids) .")";

                              $wpdb->query( $query_list_update );

                          }
                      }
                  }

              }
            }
        }
    }
        
    public function create_update_list_for_category( $term_id, $tt_id, $taxonomy ) {
        global $wpdb;

        if( !empty( $_POST['action']  ) ){

            $create_list_option = get_option( 'wc_klawoo_create_list_options' );
            if( !empty( $create_list_option ) ){
                $create_list_based_on_products = $create_list_option['create_list_based_on_products'];
                $create_list_based_on_category = $create_list_option['create_list_based_on_categories'];
                $create_list_based_on_prod_variations = $create_list_option['create_list_based_on_prod_variations'];
            } 

            if( $_POST['action'] == 'add-tag' ) {
                $category_name = $_POST['tag-name'];

                $action = 'fetch_and_create_list';
                $get_list_data = $this->get_all_list_names( false, $create_list_based_on_category, $tt_id  );
                $data = array('action' => $action, 'list_name' => json_encode($get_list_data), 'brand_id' => $this->brand_id, 'create_if_not_found' => 1 );

            } elseif( $_POST['action'] == 'editedtag' ) {

                $category_name = $_POST['name'];
                //update list api if list id exists
                // Query to get list detailsof the post_id
                $query_to_get_cat_name = "SELECT wcsc.list_name, wcsc.list_id FROM {$wpdb->prefix}wc_klawoo AS wcsc
                                            WHERE wcsc.id IN (". $tt_id .")";

                $result_to_get_cat_name = $wpdb->get_results ( $query_to_get_cat_name, 'ARRAY_A' );

                if( count( $result_to_get_cat_name ) > 0 ){

                    $list_name = $result_to_get_cat_name[0]['list_name'];

                    if (strrpos($list_name, '-') !== false) {
                        $list_name = substr($list_name, strrpos($list_name, '-')+1);
                    }

                    if (strpos($list_name,'[Categories]') !== false) {
                        $list_name = substr($list_name,strlen('[Categories]')+1);
                    }

                    $list_id = $result_to_get_cat_name[0]['list_id'];

                    $related_cat_ids = array();
                    $related_cat_ids[0] = $cat_id = $_POST['tag_ID'];

                    if( $category_name != $list_name ){

                        do {

                            $query_get_related_cat_ids = "SELECT term_taxonomy_id
                                                            FROM {$wpdb->prefix}term_taxonomy
                                                            WHERE parent =" . $cat_id;
                            $result_related_cat_ids = $wpdb->get_col ( $query_get_related_cat_ids );
                            $related_cat_ids_count = $wpdb->num_rows;

                            if ($related_cat_ids_count > 0) {

                                $related_cat_ids [] = $result_related_cat_ids [0];
                                $cat_id = $result_related_cat_ids [0];
                            } else {
                                $cat_id = '';
                            }

                        } while ($cat_id);

                        $list_details = array();

                        if ( sizeof($related_cat_ids) > 0 ) {

                            $query_list_ids = " SELECT id, list_name, list_id
                                                FROM {$wpdb->prefix}wc_klawoo
                                                WHERE id IN (". implode(",", $related_cat_ids) .")
                                                GROUP BY id";

                            $result_list_ids = $wpdb->get_results ( $query_list_ids, 'ARRAY_A' );

                            foreach ($result_list_ids as $result_list_id) {
                                $list_details [ $result_list_id['list_id'] ] = str_replace( $list_name , $category_name, $result_list_id['list_name'] );
                                $category_list_details [ $result_list_id['id'] ] = str_replace( $list_name , $category_name, $result_list_id['list_name'] );
                            }
                        } 

                        $action = 'update_list';
                        $data = array('action' => $action, 'list_details' => json_encode($list_details), 'brand_id' => $this->brand_id);
                    }
                }
            }

            if( $data ) {
                // create list api 
                $result = wp_remote_post( $this->api_url, 
                   array('headers' => array(
                           'Content-Type' => 'application/json',
                           'Authorization' => 'Basic ' . base64_encode( $this->email_address . ':' . $this->password )
                           ), 
                          'timeout' => 120, 
                          'body' => $data
                       )
                   );

                if( !is_wp_error( $result ) ){
                   $response = json_decode( $result['body'], true );

                    if ($response['ACK'] == "Success") {

                        if( is_array( $response['data'] ) && !empty( $response['data'] ) ){

                            $response_data = $response['data'];
                            if( $action == 'fetch_and_create_list' ){
                                
                                // Insert only if it does not exist
                                $insert_query = " INSERT IGNORE into {$wpdb->prefix}wc_klawoo (id, list_name, list_id) VALUES ( $tt_id, '$category_name', '$response_data[$tt_id]' )";
                                $wpdb->query( $insert_query );

                            } elseif( $action == 'update_list' ){

                                foreach ( $category_list_details as $list_id => $list_nm ) {
                                    $list_ids[] = $list_id;
                                    $query_case[] = "WHEN " . $list_id  . " THEN '" . $list_nm . "'";
                                }

                                $query_list_update = " UPDATE {$wpdb->prefix}wc_klawoo
                                                        SET list_name = CASE id ". implode("\n", $query_case) ."
                                                                END
                                                        WHERE id IN (". implode(",", $list_ids) .")";

                                $wpdb->query( $query_list_update );
                            }
                        }
                    }
              }
            }
        }
    }

    function unsubscribe_user( $order_id ){
            global $wpdb;
            
            $subscriber_data = $subscriber_data['list_ids'] = $subscriber_data['unsubscriber_data'] = array();
            
            // Fetch all lists of a brand
            $query_to_get_list_ids = " SELECT wcsc.id, wcsc.list_id 
                                       FROM {$wpdb->prefix}wc_klawoo as wcsc
                                           WHERE wcsc.brand_id = ".$this->brand_id."
                                       ORDER BY wcsc.id 
                                    ";

            $result_to_get_list_ids = $wpdb->get_results( $query_to_get_list_ids, 'ARRAY_A');
           
            $all_list_ids = array();
            
            if( count( $result_to_get_list_ids ) > 0){
                foreach( $result_to_get_list_ids as $list_data ){
                    $all_list_ids[$list_data['id']] = $list_data['list_id'];
                }
            }
            
            // Create an array containing current order data i.e date of purchase, emaila and contains products
            $current_order_details = $current_order_details['order_contains'] = array();
            
            $get_customer_data = "SELECT date_format(posts.post_modified_gmt,'%Y-%m-%d %T') AS date,
                                            postmeta.meta_key as meta_key,
                                            postmeta.meta_value as meta_value
                                            FROM {$wpdb->prefix}posts as posts
                                               JOIN {$wpdb->prefix}postmeta as postmeta
                                                   ON ( posts.ID = postmeta.post_id ) 
                                            WHERE posts.ID IN (" . $order_id . ")
                                            AND postmeta.meta_key IN ('_billing_email')
                                            GROUP BY posts.ID,meta_key";
                       
            $result_get_customer_data = $wpdb->get_results( $get_customer_data, 'ARRAY_A' );
            
            if( $result_get_customer_data > 0 ) {
                
                foreach( $result_get_customer_data as $data ){
                    
                    $unsubscribe_date = $data['date'];
                    $meta_value = $data['meta_value'];
                    $current_order_details['purchase_date'] = $unsubscribe_date;
                    $current_order_details['customer_email'] = $meta_value;
                    $customer_email = $meta_value;
                }
                
                //Fetch products in this order
                
                $query_get_order_meta = "SELECT itemmeta.order_item_id AS item_id,
                                            itemmeta.meta_value AS meta_value,
                                                itemmeta.meta_key AS meta_key
                                        FROM {$wpdb->prefix}woocommerce_order_items AS orderitems 
                                                JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS itemmeta 
                                                    ON (orderitems.order_item_id = itemmeta.order_item_id)
                                        WHERE orderitems.order_item_type LIKE 'line_item'
                                            AND (itemmeta.meta_key LIKE '_product_id'
                                                  OR itemmeta.meta_key LIKE '_variation_id' )
                                            AND orderitems.order_id IN (". $order_id .")
                                        GROUP BY orderitems.order_id, orderitems.order_item_id, meta_key";
                                                
                $result_get_order_meta = $wpdb->get_results( $query_get_order_meta, 'ARRAY_A' );
                
                if( count( $result_get_order_meta ) > 0 ){
                    foreach( $result_get_order_meta as $order_meta ){
                        if( !empty( $order_meta['meta_value'] ) ) {
                            $current_order_details['order_contains'][] = $order_meta['meta_value'];
                        }
                    }
                }
            
                
            }
            
            $product_ids_to_unsub = array();
            
            //Fix for woo2.2+
            $post_status_cond = '';
            if (!(defined('KLAV_IS_WOO22') && KLAV_IS_WOO22 == 'true')) {
              $post_status_cond = " AND posts.post_status = 'publish'";
            }

            // Fetch all prev orders placed by customer_email 
            $query_to_get_all_orders_id_of_customers = "SELECT posts.ID 
                                                        FROM {$wpdb->prefix}posts as posts 
                                                          JOIN {$wpdb->prefix}postmeta as postmeta ON (posts.ID = postmeta.post_id)
                                                        WHERE posts.post_type = 'shop_order'
                                                          $post_status_cond
                                                          AND posts.ID NOT IN ( $order_id )
                                                          AND postmeta.meta_key = '_billing_email'
                                                          AND postmeta.meta_value = '$customer_email' "; 
            
            $get_all_orders_id_of_customers = $wpdb->get_col( $query_to_get_all_orders_id_of_customers );

            if( count( $get_all_orders_id_of_customers ) > 0 ){
            
                //Fix for woo2.2+
                if (defined('KLAV_IS_WOO22') && KLAV_IS_WOO22 == 'true') {
                    $query_to_get_valid_orders = $wpdb->get_col( "SELECT id
                                                                  FROM {$wpdb->prefix}posts
                                                                  WHERE post_type = 'shop_order'
                                                                    AND post_status IN ('wc-completed','wc-processing')
                                                                    AND id IN (" . implode( ',', $get_all_orders_id_of_customers ) . ")");
                } else {
                    $query_to_get_valid_orders = $wpdb->get_col( "SELECT tr.object_id 
                                                                  FROM {$wpdb->prefix}term_relationships as tr 
                                                                    JOIN {$wpdb->prefix}term_taxonomy as tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
                                                                     WHERE tt.taxonomy = 'shop_order_status'
                                                                      AND tt.term_id IN ( SELECT t.term_id from {$wpdb->prefix}terms as t
                                                                                           WHERE t.slug IN ('completed','processing'))
                                                                      AND tr.object_id IN (" . implode( ',', $get_all_orders_id_of_customers ) . ");
                                                                    " );  
                }
                     
                if( count( $query_to_get_valid_orders ) > 0 ){
                    
                    $order_count = count( $query_to_get_valid_orders );
                    $query_get_prev_order_meta = "SELECT itemmeta.order_item_id AS item_id,
                                            itemmeta.meta_value AS meta_value,
                                                itemmeta.meta_key AS meta_key
                                        FROM {$wpdb->prefix}woocommerce_order_items AS orderitems 
                                                JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS itemmeta 
                                                    ON (orderitems.order_item_id = itemmeta.order_item_id)
                                        WHERE orderitems.order_item_type LIKE 'line_item'
                                            AND (itemmeta.meta_key LIKE '_product_id'
                                                  OR itemmeta.meta_key LIKE '_variation_id' )
                                            AND orderitems.order_id IN (" . implode( ',', $query_to_get_valid_orders ) . ")
                                        GROUP BY orderitems.order_id, orderitems.order_item_id, meta_key";
                                                
                    $result_get_prev_order_meta = $wpdb->get_results( $query_get_prev_order_meta, 'ARRAY_A' );
                    
                    $prev_order_contains = array();
                    
                    if( count( $result_get_order_meta ) > 0 ){
                        foreach( $result_get_prev_order_meta as $order_meta ){
                            if( !empty( $order_meta['meta_value'] ) ) {
                                $prev_order_contains[] = $order_meta['meta_value'];
                            }
                        }
                        
                        $prev_order_contains = array_unique($prev_order_contains);
                    }
                                        
                    $product_ids_to_unsub = array_diff($current_order_details['order_contains'], $prev_order_contains);
                    
                } else {
                    // unsubscribe from list containing prods of order_id 
                    $product_ids_to_unsub = $current_order_details['order_contains'];
                    $order_count = 0;
                }
                
                
            } else {
                // unsubscribe from list containing prods of order_id 
                $product_ids_to_unsub = $current_order_details['order_contains'];
                $order_count = 0;
            }
            
            if( !empty($product_ids_to_unsub) ){
                
                $subscriber_data['unsubscriber_data']['unsubscribe_date'] = $current_order_details['purchase_date'];
                $subscriber_data['unsubscriber_data']['cust_email'] = $current_order_details['customer_email'];
                $subscriber_data['unsubscriber_data']['user_order_count'] = $order_count;
                
                $create_list_option = get_option( 'wc_klawoo_create_list_options' );
            
                if( !empty( $create_list_option ) ){
    //                $create_list_based_on_products = $create_list_option['create_list_based_on_products'];
                    $create_list_based_on_categories = $create_list_option['create_list_based_on_categories'];
    //                $create_list_based_on_prod_variations = $create_list_option['create_list_based_on_prod_variations'];
                }
                
                if( $create_list_based_on_categories ){
                    $query_to_get_category_ids = "SELECT tt.term_taxonomy_id, tr.object_id  
                                            FROM {$wpdb->prefix}term_taxonomy AS tt
                                            JOIN {$wpdb->prefix}term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id 
                                            WHERE tt.taxonomy IN ( 'product_cat' ) 
                                            AND tr.object_id IN (" . implode( ',', $product_ids_to_unsub ) .") 
                                            ORDER BY tt.term_taxonomy_id ASC";
                    $results_to_get_category_ids = $wpdb->get_results ( $query_to_get_category_ids, 'ARRAY_A' );

                    $product_categories = array();

                    if( count( $results_to_get_category_ids ) > 0 )  {
                        foreach( $results_to_get_category_ids as $prod_cat_details ){
                            $product_categories[] = $prod_cat_details['term_taxonomy_id'];
                        }
                    }
                }
                
                if( $product_categories ){
                    $product_ids_to_unsub = array_merge($product_ids_to_unsub,$product_categories);
                }
                
                $valid_list_ids['list_ids'] = array();
                
                foreach( $product_ids_to_unsub as $p_id ){
                    if( isset($all_list_ids[$p_id] ) ){
                        $valid_list_ids['list_ids'][] = $all_list_ids[$p_id];
                   }
                }
                
                $subscriber_data['list_ids'] = $valid_list_ids['list_ids'];
                
            }

            return $subscriber_data;
            
        }
    
    public function user_subscribe_or_unsubscribe( $order_id, $old_status, $new_status ){

        // if( $old_status != $new_status ){

            if( $new_status == 'processing' || $new_status == 'completed' ){

                $this->bulk_subscribe( $order_id );

            } elseif ( ( $new_status == 'refunded' || $new_status == 'cancelled' ) && ( $old_status !== 'refunded' || $old_status !== 'cancelled' )  ) {

                $get_cust_dat = $this->unsubscribe_user( $order_id );
                
                if( !empty( $get_cust_dat ) ){
                    
                    $all_customer_list_id = get_option('wc_klawoo_main_list_id');
                    $main_list_id = $all_customer_list_id[$this->brand_id];

                    if( !isset( $get_cust_dat['unsubscriber_data']['main_list_id']) ){
                        $get_cust_dat['unsubscriber_data']['main_list_id'] = $main_list_id;
                    }
                    
                    $data = array('action' => 'bulk_unsubscribe', 'unsubscriber_details' => json_encode($get_cust_dat), 'brand_id' => $this->brand_id );
                    
                    $result = wp_remote_post( $this->api_url, 
                       array('headers' => array(
                               'Content-Type' => 'application/json',
                               'Authorization' => 'Basic ' . base64_encode( $this->email_address . ':' . $this->password )
                               ), 
                              'timeout' => 120, 
                              'body' => $data
                           )
                       );
                }
            }
        // }   
    }
    
    }
}
