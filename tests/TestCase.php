<?php
/**
 * Created by PhpStorm.
 * User: overnic
 * Date: 2018/3/20
 * Time: 18:58
 */

use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{

    public function testSmsSend()
    {
        // 引用配置文件
        $config = require_once TEST_ROOT.'/../config/sms.php';

        // 实例化程序
        $sms = new \OverNick\Dm\DmManage($config);

        // 参数
        $params = new OverNick\Dm\Config\DmConfig();
        $params->setTo('13100000000');
        $params->setTpl('SMS_00000000');
        $params->setSign('签名');
        $params->setParams([
            "code" => "123456",
            "product" => "001"
        ]);

        // 执行发送
        $result = $sms->send($params);

        $this->assertTrue($result->getState(), '请求失败：'. $result->getMessage());
    }
}