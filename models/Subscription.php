<?php
namespace Models;

use App\Common;
require_once 'DB.php';
class Subscription
{
    public $table = 'subscriptions';
    public function test(){
        $cm = new Common();
        echo App\Common::getRealIpAddr();
    }
}

$sub = new Subscription();
$sub->test();