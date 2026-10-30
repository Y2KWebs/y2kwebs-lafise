<?php
if (!defined('WP_CLI') || !WP_CLI || home_url() !== 'http://localhost:8087') { exit('Local only.'); }
update_option('woocommerce_y2kwebs_lafise_settings',['enabled'=>'yes','currency'=>'USD','mode'=>'pilot']);
update_option('woocommerce_currency','USD');
update_option('woocommerce_default_country','US:CA');
update_option('woocommerce_store_address','Test street');
update_option('woocommerce_store_city','Test');
update_option('woocommerce_store_postcode','90001');
update_option('woocommerce_ship_to_countries','disabled');
update_option('woocommerce_coming_soon','no');
update_option('blog_public','0');
update_option('permalink_structure','/%postname%/');
$user=get_user_by('login','localtester'); $user->add_cap('y2k_lafise_test');
WC_Install::create_pages();
$p=new WC_Product_Simple();$p->set_name('Demo local — pago simulado');$p->set_regular_price('1.00');$p->set_virtual(true);$p->set_status('publish');$p->save();
$classic=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Classic checkout fixture','post_content'=>'[woocommerce_checkout]']);
flush_rewrite_rules();
echo wp_json_encode(['product'=>$p->get_id(),'classic'=>$classic,'blocks'=>get_option('woocommerce_checkout_page_id')]);
