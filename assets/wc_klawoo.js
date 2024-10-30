;jQuery(function( $ ) {
    
    
    function klawoo_settings_display () {
        if ( $('#klawoo_brand_select option').length > 2 ) {
            $('#wc_klawoo_brands').show();
        }

        if ( $('#klawoo_brand_select option:selected').index() != 0 ) {
            $('#wc_klawoo_smtp').show();
        }

        if( $('#klawoo_smtp_host').val() != "") {
            $('#wc_klawoo_list_option').show();
        }

       if( $('#list_based_on_products').is(':checked') || $('#list_based_on_prod_variations').is(':checked') || $('#list_based_on_categories').is(':checked') ) {
           $('#wc_klawoo_import_option').show();
       }
    }
    
    $(document).ready(function(){
        
        if ( $('#klawoo_email_address').val() != "" && $('#klawoo_password').val() != "" ) {
            klawoo_account_display ( "existing_cust" );
        } else {
            klawoo_account_display ( "new_account" );
        }
       
        klawoo_email_send_settings();
        
        $('#wc_klawoo_create_list_msg').hide();

        display_note = get_create_list_display_note ();

        if (display_note != "") {
            $('#create_list_note_append').html(display_note);
        }

    });
    
    function klawoo_account_display ( option_selected ) {
        if ( option_selected == "existing_cust" ) {
            $('#wc_klawoo_verify_credentials').val('Verify Credentials');
            $('#klawoo_brand').hide();
            $('#klawoo_brand_lbl').hide();
            klawoo_settings_display ();
        } else if ( option_selected == "new_account" ) {
            $('#wc_klawoo_verify_credentials').val('Sign Me Up');
            $('#klawoo_brand').show();
            $('#klawoo_brand_lbl').show();
            $('#wc_klawoo_smtp').hide();
            $('#wc_klawoo_brands').hide();
            $('#wc_klawoo_list_option').hide();
            $('#wc_klawoo_import_option').hide();
            
        }
    }
    
    function klawoo_email_send_settings(){
     
        var option_selected = $("input[name='email_sending_preference']:checked").val();
        
        if( option_selected == "smtp_settings" ){
            $('#wc_klawoo_smtp_settings').show();
            $('#wc_klawoo_aws_settings').hide();
        } else if( option_selected == "aws_settings" ) {
            $('#wc_klawoo_smtp_settings').hide();
            $('#wc_klawoo_aws_settings').show();
        }
     
    }
    
    // 
    $("input[name='email_sending_preference']").on('change',function() {
        klawoo_email_send_settings ();
    });

    // would be function so to show option on document ready
    $("input[name='account_option']").on('change',function() {
        klawoo_account_display ( this.value );
    });
    
    
    $('form#wc_klawoo_settings_form').submit( function() {
        
        var form = $(this), inputs = form.find("input, select, button, textarea");
        var email_address = $('#klawoo_email_address', form).val();
        var password = $('#klawoo_password', form).val();
        var action, brand = '' ;
        
        if( $("input:radio[id=new_account]").is(':checked') ){
            action = 'klawoo_sign_up';
            brand = $('#klawoo_brand', form).val();
        } else if( $("input:radio[id=existing_cust]").is(':checked') ) {
            action = 'klawoo_validate_and_get_brands';
        }
        
        var data = {
            action: action,
            klawoo_email_address: email_address,
            klawoo_password: password
        };
        
        if( brand != '' && $("input:radio[id=new_account]").is(':checked') ){
            data.klawoo_brand = brand;
        }
        
        if( email_address == '' || password == '' || ( brand == '' && $("input:radio[id=new_account]").is(':checked') ) ) {
            var msg = 'Credentials cannot be empty.';
            show_message ( msg );
        } else {
        
                var request = $.ajax({
                                type: 		'POST',
                                url: 		ajaxurl,
                                data: 		data
                            });
                            
                request.done(function ( response ){
                    
                    response = JSON.parse(response);

                        if( response.ACK == "Success" ){

                            if ( $("input:radio[id=new_account]").is(':checked') ) {
                                show_message ( 'Account Created Successfully!' );
                                $("input:radio[id=existing_cust]").attr('checked', true);
                                klawoo_account_display ( "existing_cust" );
                                
                            } else {
                                show_message ( 'Credentials Verified' );
                            }
                            
                            location.reload();

                            var len = Object.keys(response.data).length;
                            
                            if (len == 1) {
                                $('#wc_klawoo_brands').hide();
                                $('#wc_klawoo_smtp').show();
                            } else {
                                location.reload();
                            }


                        } else {
                           $('#wc_klawoo_smtp').hide();
                           $('#wc_klawoo_brands').hide();
                           $('#wc_klawoo_list_option').hide();
                           $('#wc_klawoo_import_option').hide();

                           var msg = '';

                           if (response.code) {
                                msg = response.code + ':' + response.message;
                           } else {
                                msg = response.message;
                           }

                           show_message ( msg );

                        }
                });
        }
        
        return false;
    });
        
    $('form#wc_klawoo_smtp').submit( function() {
        
        var form = $(this), inputs = form.find("input, select, button, textarea");
        
        if( $("#smtp_settings").is(":checked") ){
            
            var smtp_host = $('#klawoo_smtp_host', form).val();
            var smtp_port = $('#smtp_port', form).val();
            var smtp_ssl = $('#smtp_ssl', form).val();
            var smtp_username = $('#klawoo_smtp_username', form).val();
            var smtp_password = $('#klawoo_smtp_password', form).val();
           
            if( smtp_host == '' || smtp_port == '' || smtp_username == '' ){
                var msg = 'Host/Port/Username cannot be empty';
                show_message ( msg );
                return false;
            } else {
                var data = {
                    action: 'klawoo_save_smtp_settings',
                    klawoo_email_sevice_type: 'smtp',
                    klawoo_smtp_host: smtp_host,
                    klawoo_smtp_port: smtp_port,
                    klawoo_smtp_ssl: smtp_ssl,
                    klawoo_smtp_username: smtp_username,
                    klawoo_smtp_pwd: smtp_password
                };
            }
            
        
        } else if($("#aws_settings").is(":checked") ){
            
            var aws_s3_key = $('#klawoo_s3_key', form).val();
            var aws_s3_secret = $('#klawoo_s3_secret', form).val();
            var aws_s3_ses = $('#klawoo_ses_region', form).val();
            
            if( aws_s3_key == '' || aws_s3_secret == '' ){
                var msg = 'Amazon Key and Secret Key cannot be empty';
                show_message ( msg );
                return false;
            }
            var data = {
                action: 'klawoo_save_smtp_settings',
                klawoo_email_sevice_type: 'aws',
                klawoo_s3_key: aws_s3_key,
                klawoo_s3_secret: aws_s3_secret,
                klawoo_ses_endpoint_name: aws_s3_ses
            };
            
        }
        
        if( data !== '' ){
            var request = $.ajax({
                                type: 		'POST',
                                url: 		ajaxurl,
                                data: 		data
                            });
                
            request.done(function ( response ){
                
                response = JSON.parse(response);
                
                if( response.ACK == "Success" ){

                    var img_url = klawoo_params.image_url + 'green-tick.png';

                    $('#wc_klawoo_save_smtp_msg').html('<img src="' + img_url + '"alt="Complete" height="16" width="16" class="alignleft"> <h3 style = "margin-left:20px;"> Settings saved successfully! </h3>');
                    $('#wc_klawoo_save_smtp_msg').show();
                    $('#wc_klawoo_list_option').show();

                    location.reload();
                } 
                
            });
        }
        
//        if( smtp_host == '' || smtp_port == '' || smtp_username == '' ){
//            var msg = 'Host/Port/Username cannot be empty';
//            show_message ( msg );
//        } else {
//            
//            var request = $.ajax({
//                                type: 		'POST',
//                                url: 		ajaxurl,
//                                data: 		data
//                            });
//                
//            request.done(function ( response ){
//                
//                response = JSON.parse(response);
//                
//                if( response.ACK == "Success" ){
//
//                    var img_url = klawoo_params.image_url + 'green-tick.png';
//
//                    $('#wc_klawoo_save_smtp_msg').html('<img src="' + img_url + '"alt="Complete" height="16" width="16" class="alignleft"> <h3 style = "margin-left:20px;"> Settings saved successfully! </h3>');
//                    $('#wc_klawoo_save_smtp_msg').show();
//                    $('#wc_klawoo_list_option').show();
//
//                    location.reload();
//                } 
//                
//            });
//        }
        
        return false;
    });
        
        
    function show_message( msg ){
        $("#klawoo_message").show();
        $("#klawoo_message p").text( msg );
    }

        
    $("#wc_klawoo_save_brand_form").submit( function() {
        
        var data = {
            action: 'klawoo_save_selected_brand',
            brand_id: $('#klawoo_brand_select').val()
        };

        request = $.ajax({
            url: ajaxurl,
            type: "post",
            data: data
        });
        
        request.done(function ( response ){

            location.reload();
        });

        return false;
    });
    
    function get_create_list_display_note () {

        var display_note = '';

        if( $("#list_based_on_products").is(':checked') ){
            display_note += "<br>- All Products Lists (Only Parent Products in case of variations)";
        }
        
        if( $("#list_based_on_prod_variations").is(':checked') ){
            display_note += "<br>- All Product Variations Lists";
        }
        
        if( $("#list_based_on_categories").is(':checked') ){
            display_note += "<br>- All Product Categories Lists to which the product belongs to";
        }

        return display_note;
    }

    $("#wc_klawoo_create_list").submit( function() {
        
        var list_based_on_products, list_based_on_categories, list_based_on_prod_variations, display_note = '';
        
        if( $("#list_based_on_products").is(':checked') ){
            list_based_on_products = true;
        }
        
        if( $("#list_based_on_prod_variations").is(':checked') ){
            list_based_on_prod_variations = true;
        }
        
        if( $("#list_based_on_categories").is(':checked') ){
            list_based_on_categories = true;
        }

        var data = {
            action: 'klawoo_create_lists',
            list_based_on_products: list_based_on_products,
            list_based_on_categories: list_based_on_categories,
            list_based_on_prod_variations: list_based_on_prod_variations
        };

        request = $.ajax({
            url: ajaxurl,
            type: "post",
            data: data
        });
        
        request.done(function ( response ){

            response = JSON.parse(response);

            if( response.ACK == "Success" ){
                var img_url = klawoo_params.image_url + 'green-tick.png';

                $('#wc_klawoo_create_list_msg').html('<img src="' + img_url + '"alt="Complete" height="16" width="16" class="alignleft"> <h3 style = "margin-left:20px;"> Lists created successfully! </h3>');
                $('#wc_klawoo_create_list_msg').show();
                $('#wc_klawoo_import_option').show();

                display_note = get_create_list_display_note ();

                if (display_note != "") {
                    $('#create_list_note_append').html(display_note);   
                }
                
            }

        });

        return false;
    });
    
    var batch_limit = 0;
    var total_order_count = 0;
    var remmaining_count = 0;

    var batch_data_sync = function( start_limit ) {

        var data = {
            action: 'bulk_subscribe',
            start_limit: start_limit
        };

        request = $.ajax({
            url: ajaxurl,
            type: "post",
            data: data
        });
        
        request.done(function ( response ){

            response = JSON.parse(response);
            // if( response.ACK == "Success" ){

                if ( start_limit == "initial" ) {
                    batch_limit = response.batch_limit;
                    total_order_count = remmaining_count = response.order_total_count;
                    start_limit = bulk_subscribe_failed_count = 0;
                } else {

                    $("#wc_klawoo_import_progressbar").show();
                    $('#wc_klawoo_import_progress_label').addClass('wc_klawoo_import_progressbar_label');
                    $('#wc_klawoo_import_progressbar').addClass('wc_klawoo_import_progressbar');

                    if ( remmaining_count == total_order_count ) {
                        $('#wc_klawoo_import_progress_label').empty();
                    } else {
                        per_orders_sent = Math.round(( total_order_count - remmaining_count ) / total_order_count * 100);
                        progress( per_orders_sent, $('#wc_klawoo_import_progressbar') );
                    }

                    start_limit = start_limit + batch_limit;
                    remmaining_count = remmaining_count - batch_limit;

                    bulk_subscribe_failed_count += response.bulk_subscribe_failed_count;
                }

                if ( (remmaining_count <= batch_limit && remmaining_count > 0) || remmaining_count > 0 ) {
                    batch_data_sync (start_limit);
                }

                if ( remmaining_count <= 0 ) {
                    progress( 100, $('#wc_klawoo_import_progressbar') );
                    msg = total_order_count +' customer(s) synced successfully! ';

                    //Code to display the failed subscribers count
                    if (bulk_subscribe_failed_count > 0) {
                        msg += bulk_subscribe_failed_count+' customer(s) failed to import';
                    }
                    
                    show_data_loaded_msg(msg);
                }

            // }
        });

    };

    $("form#wc_klawoo_import_option").submit( function() {

        var start_limit = 'initial';
        var resp = batch_data_sync(start_limit);        
        return false;
    });
    

    function progress(percent, $element) {
                    
        var progressBarWidth = percent * $element.width() / 100;
        $element.find('div').css({ width: percent + '%' }).html(percent + "%&nbsp;");

    }

    function show_data_loaded_msg( msg ){
                    
        var img_url = klawoo_params.image_url + 'green-tick.png';
        
        setTimeout( function() {
            
                $('#wc_klawoo_import_progress_label').removeClass('wc_klawoo_import_progressbar_label');
                $('#wc_klawoo_import_progressbar').removeClass('wc_klawoo_import_progressbar');
                
                // $('#wc_klawoo_import_progress_label').html('<img src="' + img_url + '"alt="Complete" height="16" width="16" class="alignleft"><h3>'+ msg +'</h3><p>New orders will sync automatically.</p>');
                $('#wc_klawoo_import_progress_label').html('<img src="' + img_url + '"alt="Complete" height="16" width="16" class="alignleft"> <h3 style = "margin-left:20px;"> '+ msg +'</h3>');
            }, 300 );
    }
    
});