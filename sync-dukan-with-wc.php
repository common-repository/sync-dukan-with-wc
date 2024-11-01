<?php

/*
 * Plugin Name: Sync Dukan with WC
 * Version: 1.0
 * Description: Syncs dukan.pk!
 * Author: Dukan.pk <dukan.playstore@gmail.com>
 * Author URI: https://www.dukan.pk/about
 * Plugin URI:  https://www.dukan.pk/plugins/sync-dukan
 */

if (!class_exists('SyncDukan')) {
    class SyncDukan
    {
        /**
         * Constructor
         */
        public function __construct()
        {
            if(empty(get_option('dukan_access_code'))) {
                $dukan_access_code = md5(time());
                add_option( 'dukan_access_code', $dukan_access_code);
            }

            add_action('admin_menu', array($this, 'registerMenuItems'));
            // Add ajax function that will receive the call back for logged in users
            add_action('wp_ajax_sync_dukan_action', array($this, 'syncDukanWithApi'));
            add_action('wp_ajax_sync_dukan_disconnect', array($this, 'syncDukanDisconnect'));
            add_action('wp_ajax_get_dukan_products', array($this, 'getDukanProducts'));
            add_action('wp_ajax_get_dukan_orders', array($this, 'getDukanOrders'));

            $this->endpoints();

//            if(get_option('dukan_cron') < (time() - 600)) {
//                $this->crons();
//            }
        }

        public function crons()
        {
            if (get_option('dukan_connection') == 1) {
                $this->productCron();
//
                $this->orderCron();

                add_option( 'dukan_cron', time());
            }
        }

        private function productCron()
        {
            $this->getDukanProducts(0, time(), 1);
            return 1;
        }

        public function cronTimings()
        {
            echo esc_html( __('Here, You set the CRON timings.', 'text_domain' ) );
            return 1;
        }

        private function orderCron()
        {
            $this->getDukanOrders(time());
            return 1;
        }

        public function endpoints()
        {
            add_action( 'rest_api_init', function () {
                register_rest_route( 'sync-dukan/v1', '/products', array(
                    'methods' => 'GET',
                    'callback' => [$this, 'getWpProducts'],
                ) );
            } );
            add_action( 'rest_api_init', function () {
                register_rest_route( 'sync-dukan/v1', '/orders', array(
                    'methods' => 'GET',
                    'callback' => [$this, 'getWpOrders'],
                ) );
            } );
            add_action( 'rest_api_init', function () {
                register_rest_route( 'sync-dukan/v1', '/sync', array(
                    'methods' => 'GET',
                    'callback' => [$this, 'sync'],
                ) );
            } );
        }

        function sync()
        {
            global $wpdb;
            $table = $wpdb->prefix . 'options';

            if(!isset($_GET['dukan_token'])) {

                return json_encode(array(
                    'status' => 'false',
                    'message' => 'Dukan token is required.'
                ));

            } else {

                $post_fields = json_encode(array(
                    'dukan_token'   => sanitize_text_field($_REQUEST['dukan_token']),
                    'store_url'     => sanitize_text_field($_SERVER['HTTP_HOST']),
                    'store_token'   => sanitize_text_field($_REQUEST['store_token']),
                    'email'         => sanitize_text_field($_REQUEST['email'])
                ));

                $http_header = array(
                    'appKey'        => '329a01fddb5a552265170b02c579c85f',
                    'appId'         => '7',
                    'Content-Type'  => 'application/json'
                );

                $data = wp_remote_post( 'https://www.dukan.pk/beta/rest/api/shopify/auth',
                    array(
                        'headers'   => $http_header,
                        'body'      => $post_fields
                    ) );

                $data = json_decode($data['body']);

                if ($data->success != 'Y') {
                    return json_encode(array(
                        'status' => 'false',
                        'message' => $data
                    ));
                }
            }

            if(get_option('dukan_access_code') == $_GET['token']) {

                $wpdb->insert($table, array(
                    'option_name'   => 'dukan_connection',
                    'option_value'  => 1
                ));

                $wpdb->delete($table, array('option_name' => 'dukan_token'));
                $wpdb->insert($table, array(
                    'option_name' => 'dukan_token',
                    'option_value' => sanitize_text_field($_REQUEST['dukan_token'])
                ));

//                $this->getDukanProducts();
//                $this->getDukanOrders();

                return json_encode(array(
                    'status' => 'true',
                    'message' => 'Connected.'
                ));

            } else {

                $wpdb->delete($table, array('option_name' => 'dukan_token'));
                $wpdb->delete($table, array('option_name' => 'dukan_connection'));

                return json_encode(array(
                    'status' => 'false',
                    'message' => 'Disconnected.'
                ));

            }
        }

        function verifyApi()
        {
            $this->eligibility();

            if(get_option('dukan_access_code') == sanitize_text_field($_GET['token'])) {
                return;
            }

            wp_die('You are not eligible to proceed.');
            return;
        }

        public function getWpProducts()
        {
            $this->verifyApi();

            $per_page = 5;

            if(isset($_GET['per_page'])) {
                $per_page = sanitize_text_field($_GET['per_page']);
            }

            $offset = 0;

            if(isset($_GET['page'])) {
                if($_GET['page'] != 1) {
                    $offset = $per_page * sanitize_text_field($_GET['page']);
                }
            }

            $args = array(
                'post_type'         => 'product',
                'posts_per_page'    => $per_page,
                'offset'            => $offset,
            );

            if(isset($_GET['after'])) {
                $args['date_query'] = [
                    'column' => 'post_modified',
                    'after'  => [
                        'year'  => (int) date('Y', (int) sanitize_text_field($_GET['after'])),
                        'month' => (int) date('m', (int) sanitize_text_field($_GET['after'])),
                        'day'   => (int) date('d', (int) sanitize_text_field($_GET['after'])),
                    ]
                ];
            }

            if(isset($_GET['before'])) {
                $args['date_query'] = [
                    'column' => 'post_modified',
                    'before'  => [
                        'year'  => (int) date('Y', (int) sanitize_text_field($_GET['before'])),
                        'month' => (int) date('m', (int) sanitize_text_field($_GET['before'])),
                        'day'   => (int) date('d', (int) sanitize_text_field($_GET['before'])),
                    ]
                ];
            }

            if(isset($_GET['product'])) {
                $args['p'] = sanitize_text_field($_GET['product']);
            }

            $loop = new WP_Query( $args );

            $d_products = array();

            while ( $loop->have_posts() ) : $loop->the_post();
                global $product;
                $d_product = array();
                $d_product['id']                = $product->get_id();
                $d_product['name']              = get_the_title();
                $d_product['description']       = get_the_content();
                $d_product['category']          = @get_the_terms( $product->get_id(), 'product_cat' )[0]->name;
                $d_product['sub_category']      = @get_the_terms( $product->get_id(), 'product_cat' )[1]->name;
                $d_product['image']             = get_the_post_thumbnail_url();
                $d_product['price']             = $product->get_regular_price();
                $d_product['permalink']         = get_permalink();
                $d_product['sku']               = $product->get_sku();
                $d_products[] = $d_product;
            endwhile;

            wp_reset_query();

            $count_posts = wp_count_posts( 'product' );

            if(isset($_GET['product'])) {
                return array(
                    'product'   => $d_products,
                    'status'    => 1
                );
            }

            return array(
                'products'  => $d_products,
                'total'     => $count_posts->publish,
                'status'    => 1
            );

        }

        public function getWpOrders()
        {
            $this->verifyApi();

            $per_page = 5;

            if(isset($_GET['per_page'])) {
                $per_page = sanitize_text_field($_GET['per_page']);
            }

            $offset = 0;

            if(isset($_GET['page'])) {
                if($_GET['page'] != 1) {
                    $offset = $per_page * sanitize_text_field($_GET['page']);
                }
            }

            global $wpdb;
            // https://www.codegrepper.com/code-examples/typescript/wp_query+woocommerce+orders

            $sql = "
        SELECT posts.ID, posts.post_status, order_stats.customer_id, customer_lookup.first_name,
         customer_lookup.last_name, customer_lookup.email,
         (select meta_value from {$wpdb->postmeta} as postmeta where post_id = posts.ID
          AND postmeta.meta_key = '_billing_phone') as phone, customer_lookup.country, 
          customer_lookup.postcode, customer_lookup.city, customer_lookup.state, posts.post_date,
         posts.post_title, order_stats.net_total
         
        FROM {$wpdb->posts} AS posts 
        LEFT JOIN {$wpdb->prefix}wc_order_stats AS order_stats ON order_stats.order_id = posts.ID
        LEFT JOIN {$wpdb->prefix}wc_customer_lookup AS customer_lookup ON customer_lookup.customer_id = order_stats.customer_id
          
        WHERE posts.post_type = 'shop_order'
        ";

            if(isset($_GET['before'])) {

                $before = date('Y-m-d 00:00:00', (int) sanitize_text_field($_GET['before']));
                $sql .= " AND posts.post_modified_gmt < '" . $before . "'";

            }

            if(isset($_GET['after'])) {

                $after = date('Y-m-d 00:00:00', (int) sanitize_text_field($_GET['after']));
                $sql .= " AND posts.post_modified_gmt < '" . $after . "'";

            }

            if(isset($_GET['order'])) {
                $sql .= " AND posts.ID = '" . sanitize_text_field($_GET['order']) . "'";
            }

            $sql .=" GROUP BY posts.ID
        ORDER BY posts.ID DESC
        LIMIT ".$per_page."
        OFFSET ".$offset."
    ";
            $results = $wpdb->get_results($wpdb->prepare($sql));
            $orders = array();
            if($results) {
                foreach ($results as $key => $order) {

                    $query_prepared = $wpdb->prepare("select order_item_id AS item from `{$wpdb->prefix}woocommerce_order_items`
                    WHERE order_id = '%s'", $order->ID);

                    $items = $wpdb->get_results($query_prepared);

                    $items_array = array();

                    foreach ($items as $key => $item) {
                        $product_id = wc_get_order_item_meta($item->item,'_product_id');
                        $product = new WC_Product($product_id);
                        $items_array[] = array(
                            'product_id'        => $product_id,
                            'sku'               => $product->get_sku(),
                            'variation_id'      => wc_get_order_item_meta($item->item,'_variation_id'),
                            'qty'               => wc_get_order_item_meta($item->item,'_qty'),
                            'discount_amount'   => wc_get_order_item_meta($item->item,'discount_amount'),
                            'coupon_data'       => wc_get_order_item_meta($item->item,'coupon_data'),
                            'price'             => wc_get_order_item_meta($item->item,'_line_subtotal'),
                        );
                    }

                    $order_array = array(
                        'id'        => $order->ID,
                        'date_time' => $order->post_date,
                        'status'    => $order->post_status,
                        'customer'  => array(
                            'id'            => $order->customer_id,
                            'first_name'    => $order->first_name,
                            'last_name'     => $order->last_name,
                            'country'       => $order->country,
                            'email'         => $order->email,
                            'address'       => '',
                            'city'          => $order->city,
                            'state'         => $order->state,
                            'postcode'      => $order->postcode,
                            'phone'         => $order->phone,
                        ),
                        'items'     => $items_array,
                        'net_total'     => $order->net_total
                    );

                    $orders[] = $order_array;
                }
            }

            $count_posts = wp_count_posts( 'shop_order' );

            $total = 0;

            if ($count_posts) {
                foreach ($count_posts as $key => $value) {
                    $total = $total + $value;
                }
            }

            if(isset($_GET['order'])) {
                return array(
                    'order'     => $orders
                );
            }

            return array(
                'orders'    => $orders,
                'total'     => $total
            );
        }

        private function getDukanProductsApiCall($id = 0, $from_date, $cron)
        {
            $token      = get_option('dukan_token');

            $post_fields = '{
                    "token": "'.$token.'",
                    "dtype": "product"';

            if($from_date > 0) {
                $post_fields .= ',
                "from_date": "'. date('Y-m-d') .'"';
            }

            $post_fields .= '
                }';

            if($id != 0) {
                $post_fields = '{
                    "token": "'.$token.'",
                    "dtype": "product",
                    "product_id": "'.$id.'"
                }';
            }

            $http_header = array(
                'appKey'    => '329a01fddb5a552265170b02c579c85f',
                'appId'     => '7'
            );

            $data = wp_remote_post( 'https://api.dukan.pk/beta/rest/api/shopify/getData'
                ,
                array(
                    'headers'   => $http_header
                ,
                    'body'      => json_decode($post_fields, true)
                )
            );
//
            $data = json_decode($data['body']);

            if($data->success == 'Y') {
                return $data->response->data;
            } else {
                if($cron == 1) {
                    return 'No products to fetch';
                } else {
                    echo esc_html( __('<h2>No products available at dukan</h2>', 'text_domain' ) );
                }
            }
        }

        private function getProductIdBySKU($id)
        {
            $sku = (string) 'dukan_' . $id;

            global $wpdb;
            $check_existance = "select post_id from $wpdb->postmeta where meta_key = '_sku' AND meta_value = '$sku' limit 1";

            return (int) $wpdb->get_var($check_existance);
        }

        public function getDukanProducts($product_id = 0, $from_date = 0, $cron = 0)
        {
            $d_products = $this->getDukanProductsApiCall($product_id, $from_date, $cron);

//            echo '<pre>';
//            print_r($d_products);
//            echo '</pre>';
//
//            wp_die();

            $added = 0;
            $skipped = array();
            $updated = 0;

            if(!is_array($d_products)) {
                return;
            }

            if(count($d_products) > 0) {
                foreach($d_products as $d_product) {

                    if(empty($d_product->product_id)) {

                        $skipped[] = array(
                            'product_id'        => 0,
                            'product_name'      => $d_product->product_name,
                            'description'       => $d_product->description,
                            'category'          => $d_product->category,
                            'sub_category'      => $d_product->sub_category,
                        );

                        continue;
                        // No product will be imported if dukan id is not provided
                    }

                    $wp_product_id = $this->getProductIdBySKU($d_product->product_id);

                    if((int) $wp_product_id < 1) {

                        $post_id = wp_insert_post( array(
                            'post_title'    =>  wp_strip_all_tags($d_product->product_name),
                            'post_name'     =>  $d_product->product_name,
                            'post_content'  =>  $d_product->description,
                            'post_status'   => 'publish',
                            'post_type'     => 'product',
                            'post_author'   => '',
                            'post_parent'   => '',
                        ) );

                        if (!$post_id) // If there is no post id something has gone wrong so don't proceed
                        {
                            return false;
                        }

                        $added++;

                        if(!empty($d_product->variations)) {
                            wp_set_object_terms( $post_id, 'variable', 'product_type' );
                        } else {
                            wp_set_object_terms( $post_id, 'simple', 'product_type' );
                        }

                        wp_set_object_terms($post_id, $d_product->category, 'product_cat');
                        wp_set_object_terms($post_id, $d_product->sub_category, 'product_cat');

                        if(!empty($d_product->product_picture)) {
                            $this->setPostThumbnail($post_id, $d_product->product_picture);
                        }

                        update_post_meta( $post_id, '_visibility', 'visible');
                        update_post_meta( $post_id, '_stock', intval($d_product->quantity_available));
                        update_post_meta( $post_id, '_regular_price', $d_product->regular_price );
                        update_post_meta( $post_id, '_price', $d_product->sale_price);

                        if(intval($d_product->quantity_available) > 0) {
                            update_post_meta( $post_id, '_stock_status', 'instock');
                        } else {
                            if(empty($d_product->variations)) {
                                update_post_meta( $post_id, '_stock_status', 'outofstock');
                            } else {
                                update_post_meta( $post_id, '_stock_status', 'instock');
                            }
                        }
//
                        update_post_meta( $post_id, '_sku', 'dukan_'.$d_product->product_id);

                        if(!empty($d_product->variations)) {

                            $product = wc_get_product($post_id);
                            $product = new WC_Product_Variable($product);

                            foreach ($d_product->variations as $variation) {
                                // Create main product

                                $attr_array = array();

                                foreach ($variation->attr as $attr => $value) {
                                    // Create the attribute object
                                    $attribute = new WC_Product_Attribute();

                                    // pa_size tax id
                                    $attribute->set_id( 0 ); // -> SET to 0

                                    // pa_size slug
                                    $attribute->set_name( strtolower($attr) ); // -> removed 'pa_' prefix

                                    // Set terms slugs
                                    $attribute->set_options( array($value) );

                                    $attribute->set_position( 0 );

                                    // If enabled
                                    $attribute->set_visible( 1 );

                                    // If we are going to use attribute in order to generate variations
                                    $attribute->set_variation( 1 );
                                    $attr_array[] = $attribute;
                                }

                                // Save main product to get its id
                                $product->set_attributes($attr_array);
                                $id = $product->save();

                                $var_obj = new WC_Product_Variation();
                                $var_obj->set_sale_price($variation->sale_price);
                                $var_obj->set_regular_price(10);
                                $var_obj->set_parent_id($id);

                                // Set attributes requires a key/value containing
                                // Tax and term slug
                                $var_obj->save();
                            }
                        }

//                            break;
                    } else {
                        wp_update_post( wp_slash( array(
                            'ID'            =>  $wp_product_id,
                            'post_title'    =>  wp_strip_all_tags($d_product->product_name),
                            'post_name'     =>  $d_product->product_name,
                            'post_content'  =>  $d_product->description,
                        ) ) );
                        $updated++;
                    }
                }
                if($from_date == 0) {
                    echo esc_html( __('Total: ' . count($d_products), 'text_domain' ) );
                    echo '<br>';
                    echo '<br>';
                    echo 'Added: ' . $added;
                    echo '<br>';
                    echo '<br>';
                    echo 'Updated: ' . $updated;
                    echo '<br>';
                    echo '<br>';
                    echo esc_html( __('All the above mentioned products were imported successfully.', 'text_domain' ) );
                    echo '<br>';
                    echo esc_html( __('Rest were in complete or malformed.', 'text_domain' ) );

                    if(!empty($skipped)) {
                        echo '<br>';
                        echo '<br>';
                        echo 'Here are the details of skipped products';
                        echo '<br>';
                        echo '<br>';
                        echo '<table border="1"><tr><td>ID</td><td>Name</td><td>Description</td><td>Category</td><td>Sub Category</td></tr>';
                        foreach ($skipped as $key => $skip) {
                            echo '<tr>';
                            echo '<td>'. $skip['product_id'] .'</td>';
                            echo '<td>'. $skip['product_name'] .'</td>';
                            echo '<td>'. $skip['description'] .'</td>';
                            echo '<td>'. $skip['category'] .'</td>';
                            echo '<td>'. $skip['sub_category'] .'</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    }

                    echo '<br>';
                    echo '<br>';
                    exit;
                }
            }
            return;
        }

        public function setPostThumbnail($post_id, $image_url)
        {
            $image_name       = time() . '-featured.png';
            $upload_dir       = wp_upload_dir(); // Set upload folder
            $image_data       = file_get_contents($image_url); // Get image data
            $unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name ); // Generate unique name
            $filename         = basename( $unique_file_name ); // Create image file name

            // Check folder permission and define file location
            if( wp_mkdir_p( $upload_dir['path'] ) ) {
                $file = $upload_dir['path'] . '/' . $filename;
            } else {
                $file = $upload_dir['basedir'] . '/' . $filename;
            }

            // Create the image  file on the server
            file_put_contents( $file, $image_data );

            // Check image file type
            $wp_filetype = wp_check_filetype( $filename, null );

            // Set attachment data
            $attachment = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_title'     => sanitize_file_name( $filename ),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );

            // Create the attachment
            $attach_id = wp_insert_attachment( $attachment, $file, $post_id );

            // Include image.php
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            // Define attachment metadata
            $attach_data = wp_generate_attachment_metadata( $attach_id, $file );

            // Assign metadata to attachment
            wp_update_attachment_metadata( $attach_id, $attach_data );

            // And finally assign featured image to post
            set_post_thumbnail( $post_id, $attach_id );
        }

        public function getDukanOrders($from_date = 0)
        {
            $token      = get_option('dukan_token');

            $request_headers = array(
                'Content-Type: application/json',
                "appId:" . "7",
                "appKey:" . "329a01fddb5a552265170b02c579c85f"
            );

            $url = "https://api.dukan.pk/beta/rest/api/shopify/getData/";

            $ch = curl_init();
            $data_send = array(
                "token" => $token,
                "dtype" => "order"
            );
            $postdata = json_encode($data_send);

            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);

            $season_data = curl_exec($ch);
            if (curl_errno($ch))
            {
                print "Error: " . curl_error($ch);
                var_dump("Error: " . curl_error($ch));
                exit();
            }
            curl_close($ch);
            $data = json_decode($season_data, true);

            $added = 0;
            $skipped = array();

            if($data['success'] == 'Y') {
                $d_orders = $data['response']['data'];

                if(count($d_orders) > 0) {

                    foreach($d_orders as $d_order) {

                        if((int) $d_order['order_id'] < 1) {

                            $skipped[] = array(
                                'first_name' => $d_order['customer_name'],
                                'last_name'  => $d_order['customer_name'],
                                'phone'      => $d_order['user_order_mobile'],
                                'address_1'  => $d_order['customer_address'],
                                'address_2'  => $d_order['order_customer_address'],
                                'city'       => $d_order['city']
                            );

                            continue;
                        }

                        global $wpdb;

                        $query_prepared = $wpdb->prepare("select post_id from `$wpdb->postmeta` where meta_value = '%s'
                             AND meta_key = '_dukan_order_id' order by post_id desc limit 1", $d_order->order_id);

                        $inserted = $wpdb->get_var($query_prepared);

                        if((int) $inserted > 0) {
                            // IF THE ORDER IS ALREADY INSERTED
                            continue;
                        }

                        $address = array(
                            'first_name' => $d_order['customer_name'],
                            'last_name'  => $d_order['customer_name'],
                            'company'    => 'Dukan.pk',
                            'email'      => time() . '@dukan.pk',
                            'phone'      => $d_order['user_order_mobile'],
                            'address_1'  => $d_order['customer_address'],
                            'address_2'  => $d_order['order_customer_address'],
                            'city'       => $d_order['city'],
                            'state'      => '-',
                            'postcode'   => '-',
                            'country'    => 'PK'
                        );

                        // Now we create the order
                        $order = wc_create_order();

                        $added++;

                        update_post_meta( $order->get_id(), '_dukan_order_id', $d_order['order_id']);

                        // The add_product() function below is located in /plugins/woocommerce/includes/abstracts/abstract_wc_order.php
                        $order->set_address( $address, 'billing' );
                        //
                        $order->calculate_totals();
                        $order->update_status($d_order->order_status_name, 'Imported order', TRUE);

                        foreach ($d_order['order_items'] as $item) {
                            $order_item_id = $this->getProductIdBySKU($item['product_id']);

                            if($order_item_id < 0) {
                                $this->getDukanProducts($item['product_id']);
                                $order_item_id = $this->getProductIdBySKU($item['product_id']);
                            }

                            $order->add_product(wc_get_product($order_item_id), $item['quantity']);
                        }
                    }
                } else {
                    echo 'There are no orders to import';
                }
                if($from_date == 0) {
                    echo esc_html( __('TOTAL: ' . count($d_orders), 'text_domain' ) );
                    echo '<br>';
                    echo '<br>';
                    echo 'Added: ' . $added;
                    echo '<br>';
                    echo '<br>';
                    echo esc_html( __('All the above mentioned products were imported successfully.', 'text_domain' ) );

                    echo '<br>';
                    echo '<br>';

                    if(!empty($skipped)) {
                        echo '<br>';
                        echo esc_html( __('Rest were in complete or malformed.', 'text_domain' ) );
                        echo '<br>';
                        echo '<br>';
                        echo 'Here are the details of skipped orders';
                        echo '<br>';
                        echo '<br>';
                        echo '<table border="1"><tr><td>Name</td><td>Mobile</td><td>Address</td><td>City</td></tr>';
                        foreach ($skipped as $key => $skip) {
                            echo '<tr>';
                            echo '<td>'. $skip['customer_name'] .'</td>';
                            echo '<td>'. $skip['user_order_mobile'] .'</td>';
                            echo '<td>'. $skip['customer_address'] .'</td>';
                            echo '<td>'. $skip['order_customer_address'] .'</td>';
                            echo '<td>'. $skip['city'] .'</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    }
                    exit;
                }
            } else {
                if($from_date == 0) {
                    echo 'An empty response from the API was observed.';
                    exit;
                }
            }

            return true;
        }

        public function syncDukanWithApi()
        {
            $post_fields = json_encode(array(
                'dukan_token'   => sanitize_text_field($_REQUEST['dukan_token']),
                'store_url'     => sanitize_text_field($_SERVER['HTTP_HOST']),
                'store_token'   => sanitize_text_field($_REQUEST['store_token']),
                'email'         => sanitize_text_field($_REQUEST['email'])
            ));

            $http_header = array(
                'appKey: 329a01fddb5a552265170b02c579c85f',
                'appId: 7',
                'Content-Type: application/json'
            );

            $data = wp_remote_post( 'https://www.dukan.pk/beta/rest/api/shopify/auth',
                array(
                    'headers'   => $http_header,
                    'body'      => $post_fields
                ) );

            $data = json_decode($data['body']);

            if ($data->success != 'Y') {
                echo $data->response->msg;
                exit;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'options';
            $wpdb->delete($table, array('option_name' => 'dukan_token'));
            $wpdb->delete($table, array('option_name' => 'store_url'));
            $wpdb->delete($table, array('option_name' => 'store_token'));
            $wpdb->delete($table, array('option_name' => 'dukan_email'));
            $wpdb->delete($table, array('option_name' => 'shopify_token'));
            $wpdb->delete($table, array('option_name' => 'app_key'));
            $wpdb->delete($table, array('option_name' => 'app_id'));
            $wpdb->delete($table, array('option_name' => 'dukan_connection'));

            $wpdb->insert($table, array(
                'option_name' => 'dukan_token',
                'option_value' => sanitize_text_field($_REQUEST['dukan_token'])
            ));

            $wpdb->insert($table, array(
                'option_name' => 'store_url',
                'option_value' => sanitize_text_field($_REQUEST['store_url'])
            ));

            $wpdb->insert($table, array(
                'option_name' => 'store_token',
                'option_value' => sanitize_text_field($_REQUEST['store_token'])
            ));

            $wpdb->insert($table, array(
                'option_name' => 'dukan_email',
                'option_value' => sanitize_text_field($_REQUEST['email'])
            ));

            $wpdb->insert($table, array(
                'option_name' => 'shopify_token',
                'option_value' => sanitize_text_field($_REQUEST['shopify_token'])
            ));

            $wpdb->insert($table, array(
                'option_name' => 'app_key',
                'option_value' => sanitize_text_field($_REQUEST['app_key'])
            ));

            $wpdb->insert($table, array(
                'option_name' => 'app_id',
                'option_value' => sanitize_text_field($_REQUEST['app_id'])
            ));

            $wpdb->insert($table, array(
                'option_name' => 'dukan_connection',
                'option_value' => 1
            ));

            echo $data->response->msg . ", Please reload the page.";
            exit;
        }

        public function syncDukanDisconnect()
        {

            global $wpdb;
            $table = $wpdb->prefix . 'options';
            $wpdb->delete($table, array('option_name' => 'dukan_connection'));
            exit;
        }

        public function enqueue()
        {
            wp_enqueue_script('admin_scripts', plugins_url('js/main_sd.js', __FILE__), array('jquery'));
            wp_localize_script(
                'admin_scripts',
                'ajax_object',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('ajax-nonce')
                )
            );
        }

        public function registerMenuItems()
        {
            // Add Javascript and CSS for front-end display
            add_action('admin_enqueue_scripts', array($this, 'enqueue'));

            add_menu_page('Sync Dukan', 'Sync Dukan', 'manage_options',
                'sync-dukan', array($this, 'pluginStatus'), '', 60);
            add_submenu_page('sync-dukan', 'Products', 'Products', 'manage_options',
                'sync-dukan-products', array($this, 'products'));
            add_submenu_page('sync-dukan', 'Orders', 'Orders', 'manage_options',
                'sync-dukan-orders', array($this, 'orders'));
            add_submenu_page('sync-dukan', 'CRON Jobs', 'CRON Jobs', 'manage_options',
                'sync-dukan-cron', array($this, 'cronTimings'));
        }

        public function pluginStatus()
        {
            if ($this->checkWooCommerceInstallation() == false) {
                echo esc_html( __("Please install (or activate if
 installed) WooCommerce before proceeding", "text_domain" ) );
                wp_die();
            }

            $d_access_code = get_option('dukan_access_code');
            $dukan_connection = get_option('dukan_connection');

            include_once 'views/status.php';

            if(get_option('dukan_cron') && get_option('dukan_connection')) {
                echo esc_html_e( __('The last CRON was run at: '. date('d-m-Y h:i a', get_option('dukan_cron') + (5 * 60 * 60)), 'socialize' ) );
            }
        }

        public function checkDukanConnection()
        {
            if (get_option('dukan_connection') == 2) {
                echo "<div class='success notice'>Your Dukan is successfully connected.</div>";
            } else {
                echo "<div class='warning notice'>Please connect your Dukan to proceed.</div>";
                //include_once 'views/connect_dukan.php';
                $this->eligibility();
                wp_die();
            }
        }

        private function eligibility()
        {
            if (get_option('dukan_connection') != 1) {

                if(empty(get_option('dukan_access_code'))) {
                    $dukan_access_code = md5(time());
                    add_option( 'dukan_access_code', $dukan_access_code);
                }

                echo esc_html( __('Your dukan access code: ' . get_option('dukan_access_code'), 'text_domain' ) );

                wp_die('You are not eligible to proceed.');
            }
            return 1;
        }

        public function orders()
        {
            $this->eligibility();
            include_once 'views/orders.php';
            wp_die();
        }

        public function products()
        {
            $this->eligibility();
            include_once 'views/products.php';
            wp_die();
        }

        public function checkWooCommerceInstallation()
        {
            if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                return true;
            } else {
                return false;
            }
        }

    }
    // instantiate the plugin class
    $wp_plugin_template = new SyncDukan();
}
