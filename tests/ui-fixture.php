<?php
/** Disposable Docker browser fixture. NOT included in release ZIP. */
namespace Y2KWebs\Lafise {
    // Localhost is a browser secure context. Override only this plugin's HTTPS check for the isolated HTTP fixture.
    function is_ssl(): bool { return getenv('Y2K_LAFISE_LOCAL_TEST') === '1' ? true : \is_ssl(); }
}
namespace {
    if (getenv('Y2K_LAFISE_LOCAL_TEST') !== '1') { return; }
    define('Y2K_LAFISE_PRIVATE', ['username'=>'fixture','password'=>'fixture','merchant_pay_id'=>'fixture-key',
        'merchant_id'=>'FIXTUREM','terminal_id'=>'FIXTURET','type_request'=>'TEST','currency'=>'USD','pilot_limit_minor'=>100,
        'evidence'=>['USD'=>['three_ds'=>'LOCAL-SYNTHETIC-ONLY']]]);
    add_filter('pre_wp_mail','__return_true');
    add_filter('pre_http_request', static function($pre,$args,$url) {
        if (!str_starts_with($url,'https://lafise-cr.portalpos.com/')) { return $pre; }
        if (str_ends_with($url,'/tokenex')) {
            $key=openssl_pkey_new(['private_key_bits'=>2048]);
            $body=['access_token'=>'fixture','serverTransportKey'=>base64_encode(openssl_pkey_get_details($key)['key'])];
        } else {
            // Unknown bank result deliberately exercises the safe retention path. No real bank request.
            return new \WP_Error('fixture_timeout','Synthetic timeout');
        }
        return ['response'=>['code'=>200,'message'=>'OK'],'headers'=>[],'body'=>wp_json_encode($body),'cookies'=>[]];
    },10,3);
}
