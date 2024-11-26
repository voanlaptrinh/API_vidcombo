<?php
namespace App\Models;

use App\Common;
use App\Models\DB;

require_once 'DB.php';
class Subscription extends DB
{
    public $table = 'subscriptions';
    public function insert_subscription($dataInsert){
        $this->insertFields($dataInsert);
        
    }
}
