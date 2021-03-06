<?php
/**
 * Created by PhpStorm.
 * User: overnic
 * Date: 2018/1/2
 * Time: 17:52
 */

namespace OverNick\Sms\Client;

use InvalidArgumentException;
use OverNick\Sms\Config\Repository as Config;
use OverNick\Sms\Abstracts\SmsClientAbstract;

/**
 * 腾讯云短信服务
 *
 * Class TencentClient
 * @package OverNick\Dm\Client
 */
class TencentDmClient extends SmsClientAbstract
{
    /**
     * 验证初始化参数
     */
    protected function setConfig($config)
    {
        if(!isset($config['app_id']) || !isset($config['app_key'])){
            throw new InvalidArgumentException("Configure app_id or app_key not found.");
        }

        $this->config = [
            'app_id' => $config['app_id'],
            'app_key' => $config['app_key']
        ];
    }

    /**
     * 获取参数
     *
     * @param Config $params
     * @return Config
     */
    protected function getParams(Config $params)
    {
        if(!(isset($params['tpl']) &&
            isset($params['sign']) &&
            isset($params['params'])))
        {
            throw new InvalidArgumentException("params is empty.");
        }

        return $params;
    }

    /**
     * 单发短信
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function sendOnce()
    {
        // 单发短信接口
        $url = 'https://yun.tim.qq.com/v5/tlssmssvr/sendsms';

        $tel_params = [
            'nationcode' => '86',
            'mobile' => $this->params['to'],
        ];

        return $this->sendSms($url,$tel_params);
    }

    /**
     * 群发短信
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    protected function sendMulti()
    {
        // 群发短信接口
        $url = 'https://yun.tim.qq.com/v5/tlssmssvr/sendmultisms2';

        $tel_params = [];

        foreach($this->params['to'] as $mobile){
            array_push($tel_params,[
                'nationcode' => '86',
                'mobile' => $mobile,
            ]);
        }

        return $this->sendSms($url, $tel_params);
    }

    /**
     * 发送短信
     *
     * @param $url
     * @param $mobile_params
     * @return mixed
     */
    protected function sendSms($url, $mobile_params)
    {
        // 随机码
        $rand_code = $this->getRandom();

        // 时间
        $time = time();

        // 获取请求的url
        $url = $this->getUrl($url,$rand_code);

        // 提交数据
        $postData = [
            'tel' => $mobile_params,
            'type' => "0",
            'sig' => $this->getSign($rand_code,$time),
            'time' => $time,
            'extend' => '',
            'ext' => ''
        ];

        // 如果没有传入模版id,并且提交参数不是一个数组，则默认为普通发送，而不是模版发送
        if($this->isTemplate()){
            array_merge($postData,[
                'tpl_id' => $this->params['params'],
                'params' => $this->params['tpl'],
                'sign' => $this->params['sign']
            ]);
        }else{
            $postData['msg'] = $this->params['params'];
        }

        // 参数利用完成后清除参数
        $this->clearArgs();

        $result =  $this->client->post($url,[
            'verify' => false,
            'json' => json_encode($postData),
            'http_errors' => false
        ]);


        // 得到接口返回结果, 并解析成数组
        $body = json_decode($result->getBody(), true);

        $this->result->setOrigin($body);

        // 接口请求错误
        if(isset($body['ActionStatus'])){
            return $this->result
                ->setState(false)
                ->setCode($body['ErrorCode'])
                ->setMessage($body['ErrorInfo'])
                ->setOrigin($body);
        }

        $this->result->setCode($body['result'])
            ->setMessage($body['errmsg']);

        // 业务请求错误
        if($body['result'] == 0){
            $this->result->setState(true);
        }else{
            $this->result->setState(false);
        }

        return $this->result;
    }

    /**
     * 生成随机数
     *
     * @return int 随机数结果
     */
    public function getRandom()
    {
        return rand(100000, 999999);
    }

    /**
     * 获取签名
     *
     * @param $random
     * @param $time
     * @return string
     */
    private function getSign($random,$time)
    {
        // 获取接收者
        $mobile = $this->params['to'];

        if(is_string($mobile)){
            $mobile = [$mobile];
        }

        $data = [
            'appkey' => $this->config['app_key'],
            'random' => $random,
            'time' => $time,
            'mobile' => implode(',',$mobile)
        ];

        // 返回签名
        return hash('sha256',http_build_query($data),false);
    }

    /**
     * 获取拼接的post url
     *
     * @param $url
     * @param $rand_code
     * @return string
     */
    private function getUrl($url,$rand_code)
    {
        return $url.'?'.http_build_query(['sdkappid' => $this->config['app_id'],'random' => $rand_code]);
    }

    /**
     * 是否使用了模版发信
     *
     * @return bool
     */
    private function isTemplate()
    {
        return !empty($this->params['tpl']) && is_array($this->params['params']) ? true : false;
    }

    /**
     * 清除参数
     */
    private function clearArgs()
    {
        $this->params = [];
    }
}