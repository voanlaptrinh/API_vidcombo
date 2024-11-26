<?php
namespace App\Models;
class Licensekey extends DB
{
    public $table = 'licensekey';
    function findSubscriptionIdByLicenseKey($licenseKey)
    {
        if (!$licenseKey || strlen($licenseKey) != 32)
            return null;

        // Chuẩn bị và thực hiện truy vấn
        $row = $this->selectRow('subscription_id', array('license_key'=>$licenseKey));
        return isset($row['subscription_id'])?$row['subscription_id']:'';
    }
}