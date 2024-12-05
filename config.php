<?php
namespace App;

class Config
{
    public static $web_domain = 'https://www.vidcombo.com/';
    public static $banks = array(
        //Taif khoanr stripe: khanh3092002@gmail.com /// khanh30920023092002
        'Stripe' => array(
            'api_key' => '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',
            'secret_key' => 'whsec_xFaRWzhwBZ800CsllRVX89YHhxqPLja6',
            'product_ids' => array(
                'vidcombo' => array(
                    'plan1' => 'price_1PiultJykwD5LYvpJyb57WJ9',
                    'plan2' => 'price_1Piun4JykwD5LYvpVkpiWzuR',
                    'plan3' => 'price_1PiunkJykwD5LYvp0IGdnFUt',
                ),
                'vidobo' => array(
                    'plan1' => 'price_1QQg6hJykwD5LYvpnxUaR5eK',
                    'plan2' => 'price_1QQg7hJykwD5LYvpzX1B07SX',
                    'plan3' => 'price_1QQg8SJykwD5LYvpctIzCbqm',
                )
            ),
        ),

        'paypal1' => array(
            'api_key' => 'AVaKEVjKl4rgNwHJwx6kjNCX3Vt8IJV64M9HbuKfoHMTXik1a1REykkXiROlhvZARj2gu5ryCWnHeMzw',
            'secret_key' => 'EI_Kllqcw-9fzAPCAYqXf0S8RGK-Ynd3FIrmltyE9G2GmN_TEYNULu0qWmisWwtDn3QgSxqv6cfdybb8',
            'product_ids' => array(
                //productID: PROD-04Y16457Y7904364Y
                'vidcombo' => array(
                    'plan1' => 'P-3D0320107P883293YM5GWBNQ',
                    'plan2' => 'P-8958810140640330LM5GWB2Q',
                    'plan3' => 'P-9WU41427EJ108845WM5GWCEQ',
                ),
                //Product ID: PROD-6VY51654PU6823104
                'vidobo' => array(
                    'plan1' => 'P-7K236902UN905363SM5HHM7I',
                    'plan2' => 'P-9Y496764DB504023GM5HHNSI',
                    'plan3' => 'P-91775789UT122914JM5HHN2I',
                )
            ),
        ),
    );


    public static $apps = array(
        'vidcombo' => array(
            'stripe' => 'Stripe',
            'paypal' => 'paypal1',
        ),
        'vidobo' => array(
            'stripe' => 'Stripe',
            'paypal' => 'paypal1',
        ),
    );
    // public static function getPlanAliasByPlanID($plan_id){
    //     foreach (self::$banks as $bank_info) {
    //         foreach ($bank_info['product_ids'] as $plan_alias => $bank_info_plan_id) {
    //             if($plan_id == $bank_info_plan_id)
    //                 return $plan_alias;
    //         }
    //     }
    //     return '';
    // }
    public static function getPlanAliasByPlanID($plan_id)
    {
        foreach (self::$banks as $bank_info) { //Duyêt qua aray Bank
            foreach ($bank_info['product_ids'] as $product_group => $plans) {
                foreach ($plans as $plan_name => $plan_value) {
                    if ($plan_id === $plan_value) {
                        return $plan_name;  //Lấy ta plan name
                    }
                }
            }
        }
        return ''; 
    }
    

    public static function getKeyByProdID($prodID)
    {
        foreach (self::$banks as $bank_name => $bank_info) {
            foreach ($bank_info['product_ids'] as $app_name => $plans) {
                foreach ($plans as $plan_alias => $plan_id) {
                    if ($prodID == $plan_id)
                        return array(
                            'api_key' => $bank_info['api_key'],
                            'secret_key' => $bank_info['secret_key'],
                        );
                }
            }
        }
        return array();
    }
    public static function getAppNamePlanAliasByPlanID($planID)
    {
        foreach (self::$banks as $bank_name => $bank_info) {
            foreach ($bank_info['product_ids'] as $app_name => $plans) {
                foreach ($plans as $plan_alias => $plan_id) {
                    if ($planID == $plan_id)
                        return array($app_name, $plan_alias);
                }
            }
        }
        return array();
    }
    public static function getPlanIdByAppNamePlanAlias($app_name, $plan_alias)
{
    foreach (self::$banks as $bank_name => $bank_info) {
        if (isset($bank_info['product_ids'][$app_name])) {
            $plans = $bank_info['product_ids'][$app_name];
            if (isset($plans[$plan_alias])) {
                return $plans[$plan_alias];
            }
        }
    }
    return null;
}

}
