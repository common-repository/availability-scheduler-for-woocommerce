<?php
/*
  Plugin Name: Availability Scheduler for WooCommerce
  Description: WooCommerce Availability Scheduler
  Author: azexo
  Author URI: http://azexo.com
  Version: 1.27.8
  Text Domain: azm
 */

add_action('plugins_loaded', 'azm_was_plugins_loaded');

function azm_was_plugins_loaded() {
    load_plugin_textdomain('azm', FALSE, basename(dirname(__FILE__)) . '/languages/');
}

add_action('admin_notices', 'azm_was_admin_notices');

function azm_was_admin_notices() {
    if (!defined('AZM_VERSION')) {
        $plugin_data = get_plugin_data(__FILE__);
        print '<div class="updated notice error is-dismissible"><p>' . $plugin_data['Name'] . ': ' . __('please install <a href="https://codecanyon.net/item/marketing-automation-by-azexo/21402648">Marketing Automation by AZEXO</a> plugin.', 'azm') . '</p><button class="notice-dismiss" type="button"><span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'azm') . '</span></button></div>';
    }
}

add_filter('azr_settings', 'azm_was_settings');

function azm_was_settings($azr) {
    $azr['actions']['hide_product'] = array(
        'name' => __('Hide product', 'azm'),
        'description' => __('Set "Any" place in "Visit" event settings. Use "Products filter" conditions for specify products for this action', 'azm'),
        'group' => __('Product', 'azm'),
        'event_dependency' => array('visit'),
        'required_context' => array('visitors'),
        'set_context' => array('products' => true),
    );
    $azr['actions']['product_not_purchasable'] = array(
        'name' => __('Product not purchasable', 'azm'),
        'description' => __('Set "Any" place in "Visit" event settings. Use "Products filter" conditions for specify products for this action', 'azm'),
        'group' => __('Product', 'azm'),
        'event_dependency' => array('visit'),
        'required_context' => array('visitors'),
        'set_context' => array('products' => true),
    );
    $azr['actions']['countdown_timer'] = array(
        'name' => __('Show countdown timer', 'azm'),
        'description' => __('End date will take from rule performing conditions. Set "Any" place in "Visit" event settings. Use "Products filter" conditions for specify products for this action', 'azm'),
        'group' => __('Product', 'azm'),
        'event_dependency' => array('visit'),
        'condition_dependency' => array('performing_period', 'performing_months', 'performing_week_days', 'performing_hours'),
        'required_context' => array('visitors'),
        'parameters' => array(
            'position' => array(
                'type' => 'dropdown',
                'label' => __('Position', 'azm'),
                'required' => true,
                'options' => array(
                    'woocommerce_after_shop_loop_item_title|9' => __('Loop product - Price - Before', 'azm'),
                    'woocommerce_after_shop_loop_item_title|11' => __('Loop product - Price - After', 'azm'),
                    'woocommerce_after_shop_loop_item|9' => __('Loop product - Add to cart - Before', 'azm'),
                    'woocommerce_after_shop_loop_item|11' => __('Loop product - Add to cart - After', 'azm'),
                    'woocommerce_single_product_summary|9' => __('Single product - Price - Before', 'azm'),
                    'woocommerce_single_product_summary|11' => __('Single product - Price - After', 'azm'),
                    'woocommerce_before_add_to_cart_form|10' => __('Single product - Add to cart - Before', 'azm'),
                    'woocommerce_after_add_to_cart_form|10' => __('Single product - Add to cart - After', 'azm'),
                    'woocommerce_product_meta_start|10' => __('Single product - Product meta - Before', 'azm'),
                    'woocommerce_product_meta_end|10' => __('Single product - Product meta - After', 'azm'),
                    'woocommerce_single_product_summary|19' => __('Single product - Product summary - Before', 'azm'),
                    'woocommerce_single_product_summary|21' => __('Single product - Product summary - After', 'azm'),
                ),
                'default' => 'woocommerce_before_add_to_cart_form|10',
            ),
            'label' => array(
                'type' => 'text',
                'label' => __('Label', 'azm'),
            ),
        ),
    );
    return $azr;
}

function azm_was_get_interval_end($options, $items, $current) {
    $sorted_items = array_intersect($options, $items);
    if (count($sorted_items) == count($options)) {
        return false;
    }
    $index = array_search($current, $sorted_items);
    if ($index === false) {
        return false;
    }
    $end = $index;
    do {
        $end++;
        if ($end == count($options)) {
            $end = 0;
        }
    } while (!is_null($sorted_items[$end]));
    return $options[$end];
}

add_filter('azr_process_action', 'azm_was_process_action', 10, 2);

function azm_was_process_action($context, $action) {
    switch ($action['type']) {
        case 'hide_product':
            if (isset($context['visitors'])) {
                global $wpdb;
                $db_query = azr_get_db_query($context['visitors']);
                $visitors = $wpdb->get_results($db_query, ARRAY_A);
                $visitors = array_map(function($value) {
                    return $value['visitor_id'];
                }, $visitors);
                $visitors = array_filter($visitors);
                $visitors = array_unique($visitors);
                foreach ($visitors as $visitor_id) {
                    if ($visitor_id == $context['visitor_id']) {
                        if (isset($context['products'])) {
                            add_filter('posts_where', function ($where, $query) use($context) {
                                $post_type = $query->get('post_type');
                                if (!is_array($post_type)) {
                                    $post_type = array($post_type);
                                }
                                if ((in_array('product', $post_type) && count($post_type) == 1) || $query->get('product_cat') || $query->get('product_tag')) {
                                    global $wpdb;
                                    $db_query = azr_get_db_query($context['products']);
                                    $where .= " AND {$wpdb->posts}.ID NOT IN ($db_query) ";
                                }
                                return $where;
                            }, 10, 2);
                        }
                    }
                }
            }
            break;
        case 'product_not_purchasable':
            global $wpdb;
            $db_query = azr_get_db_query($context['visitors']);
            $visitors = $wpdb->get_results($db_query, ARRAY_A);
            $visitors = array_map(function($value) {
                return $value['visitor_id'];
            }, $visitors);
            $visitors = array_filter($visitors);
            $visitors = array_unique($visitors);
            foreach ($visitors as $visitor_id) {
                if ($visitor_id == $context['visitor_id']) {
                    if (isset($context['products'])) {
                        add_filter('woocommerce_is_purchasable', function($purchasable, $product) use ($context) {
                            global $product;
                            global $wpdb;
                            static $products = array();
                            if (is_object($product) && $product instanceof WC_Product) {
                                if (!isset($products[(int) $product->get_id()])) {
                                    global $wpdb;
                                    if (is_array($context['products']['where'])) {
                                        foreach ($context['products']['where'] as &$where) {
                                            $where = str_replace('{product_id}', $product->get_id(), $where);
                                        }
                                    }
                                    $db_query = azr_get_db_query($context['products']);
                                    $results = $wpdb->get_results($db_query, ARRAY_A);
                                    $results = array_map(function($value) {
                                        return (int) $value['ID'];
                                    }, $results);
                                    $results = array_filter($results);
                                    foreach ($results as $id) {
                                        $products[$id] = true;
                                    }
                                }
                                if (isset($products[(int) $product->get_id()]) && $products[(int) $product->get_id()]) {
                                    $purchasable = false;
                                }
                            }
                            return $purchasable;
                        }, 20, 2);
                    }
                }
            }
            break;
        case 'countdown_timer':
            global $wpdb;
            $db_query = azr_get_db_query($context['visitors']);
            $visitors = $wpdb->get_results($db_query, ARRAY_A);
            $visitors = array_map(function($value) {
                return $value['visitor_id'];
            }, $visitors);
            $visitors = array_filter($visitors);
            $visitors = array_unique($visitors);
            foreach ($visitors as $visitor_id) {
                if ($visitor_id == $context['visitor_id']) {
                    $position = explode('|', $action['position']);
                    add_action($position[0], function () use($action, $context) {
                        global $product;
                        static $products = array();
                        if (is_object($product) && $product instanceof WC_Product) {
                            if (isset($context['products']) && !isset($products[(int) $product->get_id()])) {
                                global $wpdb;
                                if (is_array($context['products']['where'])) {
                                    foreach ($context['products']['where'] as &$where) {
                                        $where = str_replace('{product_id}', $product->get_id(), $where);
                                    }
                                }
                                $db_query = azr_get_db_query($context['products']);
                                $results = $wpdb->get_results($db_query, ARRAY_A);
                                $results = array_map(function($value) {
                                    return (int) $value['ID'];
                                }, $results);
                                $results = array_filter($results);
                                foreach ($results as $id) {
                                    $products[$id] = true;
                                }
                            }
                            if (!empty($products) && (isset($products[(int) $product->get_id()]) && $products[(int) $product->get_id()])) {
                                $settings = azr_get_settings();
                                $date_to = false;
                                if (isset($context['performing_hours']) && $context['performing_hours']) {
                                    $interval_end = azm_was_get_interval_end(array_keys($settings['conditions']['performing_hours']['parameters']['performing_hours']['options']), $context['performing_hours'], date('G', time() + get_option('gmt_offset') * HOUR_IN_SECONDS));
                                    $d = DateTime::createFromFormat('G', $interval_end, azm_timezone());
                                    $date_to = $d->getTimestamp();
                                }
                                if (isset($context['performing_week_days']) && $context['performing_week_days']) {
                                    $interval_end = azm_was_get_interval_end(array_keys($settings['conditions']['performing_week_days']['parameters']['performing_week_days']['options']), $context['performing_week_days'], date('w', time() + get_option('gmt_offset') * HOUR_IN_SECONDS));
                                    $d = DateTime::createFromFormat('w', $interval_end, azm_timezone());
                                    $date_to = $d->getTimestamp();
                                }
                                if (isset($context['performing_months']) && $context['performing_months']) {
                                    $interval_end = azm_was_get_interval_end(array_keys($settings['conditions']['performing_months']['parameters']['performing_months']['options']), $context['performing_months'], date('m', time() + get_option('gmt_offset') * HOUR_IN_SECONDS));
                                    $d = DateTime::createFromFormat('m', $interval_end, azm_timezone());
                                    $date_to = $d->getTimestamp();
                                }
                                if (isset($context['performing_to_date']) && $context['performing_to_date']) {
                                    $d = new DateTime($context['performing_to_date'], azm_timezone());
                                    $date_to = $d->getTimestamp();
                                }
                                if ($date_to) {
                                    $expire = $date_to - current_time('timestamp');
                                    if ($expire < 0) {
                                        $expire = 0;
                                    }
                                    $days = floor($expire / 60 / 60 / 24);
                                    $hours = floor(($expire - $days * 60 * 60 * 24) / 60 / 60);
                                    $minutes = floor(($expire - $days * 60 * 60 * 24 - $hours * 60 * 60) / 60);
                                    $seconds = $expire - $days * 60 * 60 * 24 - $hours * 60 * 60 - $minutes * 60;
                                    wp_enqueue_script('countdown', plugins_url('js/jquery.countdown.min.js', __FILE__), array('jquery'), false, true);
                                    wp_enqueue_script('azm-wsc-frontend', plugins_url('js/frontend.js', __FILE__), array('jquery'), false, true);
                                    wp_enqueue_style('azm-wsc-frontend', plugins_url('css/frontend.css', __FILE__), false, false, true);
                                    ?>
                                    <div class="azm-time-left">
                                        <?php print (empty($action['label']) ? '' : '<div class="azm-label">' . esc_html($action['label']) . '</div>'); ?>
                                        <div class="azm-time" data-time="<?php print date('Y/m/d H:i:s', $date_to); ?>Z">
                                            <span class="azm-days"><strong class="azm-count"><?php print $days; ?></strong> <span class="azm-title"><?php print esc_html__('days', 'azm'); ?></span></span>
                                            <span class="azm-hours"><strong class="azm-count"><?php print $hours; ?></strong> <span class="azm-title"><?php print esc_html__('hrs', 'azm'); ?></span></span>
                                            <span class="azm-minutes"><strong class="azm-count"><?php print $minutes; ?></strong> <span class="azm-title"><?php print esc_html__('min', 'azm'); ?></span></span>
                                            <span class="azm-seconds"><strong class="azm-count"><?php print $seconds; ?></strong> <span class="azm-title"><?php print esc_html__('sec', 'azm'); ?></span></span>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                        }
                    }, $position[1]);
                }
            }
            break;
    }
    return $context;
}
