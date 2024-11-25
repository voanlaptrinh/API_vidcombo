<?php



class Config {
     public $banks = array(
        //Taif khoanr stripe: khanh3092002@gmail.com /// khanh30920023092002
        'stripe2' => array(
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
        'stripe1' => array(
            'api_key' => 'sk_test_51OeDsPIXbeKO1uxjfGZLmBaoVYMdmbThMwRHSrNa6Zigu0FnQYuAatgfPEodv9suuRFROdNRHux5vUhDp7jC6nca00GbHqdk1Y',
            'secret_key' => 'whsec_QOwtxMmtBnwpsF0PprLXE7es1WZWmhQE',
            'product_ids' => array(
                'vidcombo' => array(
                    'plan1' => 'price_1PV2QfIXbeKO1uxjVvaZPb8p',
                    'plan2' => 'price_1PV2VjIXbeKO1uxjHlOtM0oL',
                    'plan3' => 'price_1PV2USIXbeKO1uxjnL1w3qPC',
                ),
                'vidobo' => array(
                    'plan1' => 'price_1PV2QfIXbeKO1uxjVvaZPb8p',
                    'plan2' => 'price_1PV2VjIXbeKO1uxjHlOtM0oL',
                    'plan3' => 'price_1PV2USIXbeKO1uxjnL1w3qPC',
                )
            ),
        ),
    
        'paypal1' => array(
            'client_id' => 'AS60eEYQCjGcGoDxA-qlsg3Zn16NC1xeeoCMLh6oITy7mySUJPNAvtcpu-vgxPn9T7ONYq0CBagdVp8u',
            'client_secret' => 'EKPI6LSey0sNF5baWqtDZHGxEiGY-nrPZhup6f7xGQmtbDl_m2jezwfRGUFpkwT2wbl8mHyByIEwocDA',
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
        'paypal2' => array(
            'client_id' => 'ATAwcPBvJAz5zlqv2tILRRyzOF1VkBC6yio-PmjeFvmX0HVZFjAi3fECgC7MkFknb-nAGSgUk_we0d8p',
            'client_secret' => 'EFpmH487Fi-ZHq6jOmhpSHGJ2o_KEn8EyRGzOUU4mz1u8GPgtC0eSN9KQUROJNZDhxY2HS7vMcmVcX0u',
            'product_ids' => array(
                'vidcombo' => array(
                    'plan1' => 'P-2G114147RC878780TM45K4IQ',
                    'plan2' => 'P-49F52473KR9692531M45K35I',
                    'plan3' => 'P-93K89385870045805M45K2NQ',
                ),
                'vidobo' => array(
                    'plan1' => 'P-2G114147RC878780TM45K4IQ',
                    'plan2' => 'P-49F52473KR9692531M45K35I',
                    'plan3' => 'P-93K89385870045805M45K2NQ',
                )
            ),
        ),
    );
    
    public $apps = array(
        'vidcombo' => array(
            'stripe' => 'stripe1',
            'paypal' => 'paypal2',
        ),
        'vidobo' => array(
            'stripe' => 'stripe2',
            'paypal' => 'paypal2',
        ),
    );
    
}