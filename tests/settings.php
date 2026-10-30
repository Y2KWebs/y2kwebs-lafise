<?php
if (!defined('WP_CLI') || !WP_CLI || home_url() !== 'http://localhost:8087') { exit('Local tests only.'); }
use Y2KWebs\Lafise\{Config, Gateway, Settings, Secrets};
$_SERVER['HTTPS']='on';
$_SERVER['HTTP_HOST']='localhost:8087';
wp_set_current_user(1);
$checks=0;
$assert=static function(bool $ok,string $message) use (&$checks):void {if(!$ok)throw new RuntimeException($message);$checks++;};
$previous=get_option(Settings::OPTION,[]);
$secretBefore=get_option('y2k_lafise_secrets','');
try {
    $fixture=['enabled'=>'yes','mode'=>'live','test_max_amount'=>'1.00','currency'=>'USD','username'=>'fixture','password'=>'abc<>&%slash\\tail',
        'merchant_pay_id'=>'merchant-fixture-key','merchant_id'=>'TESTM','terminal_id'=>'TESTT','type_request'=>'Production'];
    update_option(Settings::OPTION,$fixture);
    $stored=get_option(Settings::OPTION);
    $assert($stored['password']==='' && $stored['merchant_pay_id']==='','Gateway option contains no secrets');
    $assert((new Config())->get('password')===$fixture['password'],'Encrypted password round trip');
    $assert(!str_contains(get_option('y2k_lafise_secrets'),$fixture['password']),'Secret ciphertext');
    $withoutType = $fixture;
    unset($withoutType['type_request']);
    update_option(Settings::OPTION, $withoutType);
    $assert((new Config())->get('type_request') === 'Production', 'Missing request type uses the admin form default');
    $assert((new Config())->allowed(), 'Partial settings do not hide a configured gateway');
    $withoutType['type_request'] = '';
    update_option(Settings::OPTION, $withoutType);
    $assert(!(new Config())->allowed(), 'Explicitly empty request type remains invalid');
    $withoutType['type_request'] = 'TEST';
    update_option(Settings::OPTION, $withoutType);
    $assert((new Config())->get('type_request') === 'TEST', 'Explicit request type is preserved');
    update_option(Settings::OPTION, $fixture);
    wp_set_current_user(0);
    $assert((new Config())->allowed(),'Normal mode accepts guests without certificates');
    wp_set_current_user(1);
    $gateway=new Gateway();
    $adminData=[];
    foreach($fixture as $field=>$value) { $adminData[$gateway->get_field_key($field)] = wp_slash($value); }
    $adminData[$gateway->get_field_key('password')] = wp_slash('admin<>&secret\\tail');
    $gateway->set_post_data($adminData);
    $gateway->process_admin_options();
    $assert((new Config())->get('password')==='admin<>&secret\\tail','Native admin preserves special characters');
    $fixture['password']='admin<>&secret\\tail';
    $stored=get_option(Settings::OPTION);
    $html=$gateway->generate_password_html('password',$gateway->form_fields['password']);
    $assert(!str_contains($html,$fixture['password']) && str_contains($html,'value=""'),'Admin field never contains saved secret');
    $stored['title']='Changed title';update_option(Settings::OPTION,$stored);
    $assert((new Config())->get('password')===$fixture['password'],'Blank preserves secret');
    $request=new WP_REST_Request('POST','/y2k-lafise/v1/settings');
    $request->set_header('content-type','application/json');$request->set_body(wp_json_encode(['password'=>'new<api>&secret']));
    $response=Settings::api($request);
    $assert(!is_wp_error($response) && !isset($response->get_data()['password']),'API redaction');
    $assert((new Config())->get('password')==='new<api>&secret','API preserves special characters');
    $server=rest_get_server();
    wp_set_current_user(0);
    $unauthorized=$server->dispatch(new WP_REST_Request('GET','/y2k-lafise/v1/settings'));
    $assert($unauthorized->get_status()===401 || $unauthorized->get_status()===403,'Settings API denies guests');
    wp_set_current_user(1);
    $stored=get_option(Settings::OPTION);$stored['mode']='test';update_option(Settings::OPTION,$stored);
    wp_set_current_user(0);$assert(!(new Config())->allowed(),'Optional test mode excludes guests');
    wp_set_current_user(1);$assert((new Config())->allowed(),'Test mode allows administrator without custom role setup');
    $stored['clear_secrets']='yes';update_option(Settings::OPTION,$stored);
    $assert((new Config())->get('password')==='' && get_option(Settings::OPTION)['enabled']==='no','Clear credentials disables gateway');
    echo "PASS: $checks settings assertions\n";
} finally {
    update_option(Settings::OPTION,$previous);
    if($secretBefore) update_option('y2k_lafise_secrets',$secretBefore,false); else delete_option('y2k_lafise_secrets');
}
