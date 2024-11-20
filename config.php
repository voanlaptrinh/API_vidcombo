<?php

$banks = array(
    'Stripe' => array(
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
        )
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
        )
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
                'plan1' => 'P-2N046189W34174607M4ZR6PA',
                'plan2' => 'P-63F91990FB938624MM4ZR6WQ',
                'plan3' => 'P-3MV286946S8608236M4ZSAPA',
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
        )
    ),
);

$apps = array(
    'vidcombo' => array(
        'stripe' => 'Stripe',
        'paypal' => 'paypal2',
    ),
    'vidobo' => array(
        'stripe' => 'stripe1',
        'paypal' => 'paypal1',
    ),
);



