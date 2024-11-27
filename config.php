<?php
namespace App;

class Config
{
    public static $web_domain = 'https://www.vidcombo.com/';
    public static $banks = array(
        //Taif khoanr stripe: khanh3092002@gmail.com /// khanh30920023092002
        'Stripe' => array(
            'api_key' => 'sk_test_51QNPA2KVaCDurUFdekFsGsZ2f0KKSVSBlt1dSRAAV1GbcdQvDzHf31i1X70GUbFF7l5N2xbr5JO13CYWdd9UB53E0082G7hZky',
            'secret_key' => 'whsec_CU4CDKe2328UzylXU23Hueh8n7geQcB1',
            'product_ids' => array(
                'vidcombo' => array(
                    'plan1' => 'price_1QNPEKKVaCDurUFdR6JzKm20',
                    'plan2' => 'price_1QNPErKVaCDurUFd3gD9wQCN',
                    'plan3' => 'price_1QNPFLKVaCDurUFdoQc2J8GS',
                ),
                'vidobo' => array(
                    'plan1' => 'price_1QNQ7pKVaCDurUFdonWdU3lX',
                    'plan2' => 'price_1QNQ8MKVaCDurUFdTSkybVXk',
                    'plan3' => 'price_1QNQ92KVaCDurUFd9xxrlvOh',
                )
            ),
        ),

        'paypal1' => array(
            'api_key' => 'AS60eEYQCjGcGoDxA-qlsg3Zn16NC1xeeoCMLh6oITy7mySUJPNAvtcpu-vgxPn9T7ONYq0CBagdVp8u',
            'secret_key' => 'EKPI6LSey0sNF5baWqtDZHGxEiGY-nrPZhup6f7xGQmtbDl_m2jezwfRGUFpkwT2wbl8mHyByIEwocDA',
            'product_ids' => array(
                'vidcombo' => array(
                    'plan1' => 'P-2N046189W34174607M4ZR6PA',
                    'plan2' => 'P-63F91990FB938624MM4ZR6WQ',
                    'plan3' => 'P-3MV286946S8608236M4ZSAPA',
                ),
                'vidobo' => array(
                    'plan1' =>  'P-88V598096T5182056M47NQNY',
                    'plan2' => 'P-6D86645935592440AM47NQWA',
                    'plan3' => 'P-3VM89144TK032961PM47NQ4Q',
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
}
