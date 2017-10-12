<?php

namespace Aliexpress;

use GuzzleHttp\Client as HttpClient;

class Client
{
    private $httpClient;
    private $appKey;
    private $secKey;
    private $hostServer = 'gw.api.alibaba.com';
    private $refreshToken;
    private $accessToken;
    private $accessTokenExpired = 0;

    public function __construct($appKey = '', $secKey = '', $refreshToken = '', $accessToken = '', $client = null)
    {
        $this->setHttpClient($client);
        $this->setAppKey($appKey);
        $this->setSecKey($secKey);
        $this->setRefreshToken($refreshToken);
        if ($accessToken) {
            $this->refreshToken();
        } else {
            $this->setAccessToken($accessToken);
        }
        
    }

    public function getAccessTokenExpired()
    {
        return $this->accessTokenExpired;
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }

    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    public function setHttpClient($client)
    {
        $this->httpClient = $client ? : new HttpClient(['debug'=> false]);
    }

    public function setAppKey($appKey)
    {
        $this->appKey = $appKey;
    }

    public function setSecKey($secKey)
    {
        $this->secKey = $secKey;
    }

    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;
    }

    public function getAuthUrl($redirectUrl)
    {
        $params = [
            'client_id' => $this->appKey,
            'site' => 'aliexpress',
            'redirect_uri' => $redirectUrl
        ];
        $params['_aop_signature'] = $this->sign('', $params);

        return 'http://gw.api.alibaba.com/auth/authorize.htm?'.http_build_query($params);
    }

    public function getToken($code, $redirectUrl = 'default')
    {
        $params = [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'need_refresh_token' => 'true',
            'client_id' => $this->appKey,
            'client_secret' => $this->secKey,
            'redirect_uri' => $redirectUrl,
        ];

        return $this->request('POST', 'system.oauth2/getToken', $params, fasle);
    }

    public function refreshToken()
    {
        $params = [
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
            'client_id' => $this->appKey,
            'client_secret' => $this->secKey,
        ];
        
        $response = $this->request('POST', 'system.oauth2/getToken', $params, false);
        if (isset($response['access_token'])) {
            $this->accessToken = $response['access_token'];
            $this->accessTokenExpired = time() + intval($response['expires_in']);
        }
        return false;
    }

    public function updateAccessToken()
    {
        if ($this->accessTokenExpired < time()) {
            $this->refreshToken();
        }
    }

    public function findOrderListQuery($page = 1, $pageSize = 50, $createDateStart = '', $createDateEnd = '', $orderStatus = '')
    {
        $params = [
            'page' => $page,
            'pageSize' => $pageSize,
            'createDateStart' => $createDateStart,
            'createDateEnd' => $createDateEnd,
            'orderStatus' => $orderStatus
        ];
        $apiPath = 'aliexpress.open/api.findOrderListQuery';
        return $this->request('POST', $apiPath, $params);
    }

    public function findOrderById($orderId)
    {
        $params = [
            'orderId' => $orderId
        ];
        $apiPath = 'aliexpress.open/api.findOrderById';
        return $this->request('POST', $apiPath, $params);
    }

    public function getOnlineLogisticsServiceListByOrderId($orderId)
    {
        $params = [
            'orderId' => $orderId
        ];
        $apiPath = 'aliexpress.open/api.getOnlineLogisticsServiceListByOrderId';
        return $this->request('POST', $apiPath, $params);
    }

    public function getOnlineLogisticsInfo($orderId)
    {
        $params = [
            'orderId' => $orderId
        ];
        $apiPath = 'aliexpress.open/api.getOnlineLogisticsInfo';
        return $this->request('POST', $apiPath, $params);
    }

    public function listLogisticsService()
    {
        $apiPath = 'aliexpress.open/api.listLogisticsService';
        return $this->request('POST', $apiPath);
    }

    public function sellerShipment($serviceName, $logisticsNo, $sendType, $outRef)
    {
        $params = [
            'serviceName' => $serviceName,
            'logisticsNo' => $logisticsNo,
            'sendType' => $sendType,
            'outRef' => $outRef,
        ];
        $apiPath = 'aliexpress.open/api.sellerShipment';
        return $this->request('POST', $apiPath, $params);
    }

    public function findOrderListSimpleQuery($page = 1, $pageSize = 20, $createDateStart = '', $createDateEnd = '', $orderStatus = '')
    {
        $params = [
            'page' => $page,
            'pageSize' => $pageSize,
            'createDateStart' => $createDateStart,
            'createDateEnd' => $createDateEnd,
            'orderStatus' => $orderStatus
        ];
        $apiPath = 'aliexpress.open/api.findOrderListSimpleQuery';
        return $this->request('POST', $apiPath, $params);
    }

    public function request($method, $apiPath, $params = [], $sign = true)
    {
        $params = array_filter($params);
        if ($sign) {
            $this->updateAccessToken();
            $params['access_token'] = $this->accessToken;
            $params['_aop_signature'] = $this->sign('param2/1/'.$apiPath.'/'.$this->appKey, $params);
        }
        $queryString = http_build_query($params);
        $uri = 'https://'.$this->hostServer.'/openapi/param2/1/'.$apiPath.'/'.$this->appKey.'?'.$queryString;
        try {
            $response = $this->httpClient->request($method, $uri, $params);
            $response = json_decode($response->getBody()->getContents(), true);
            if (! is_array($response)) {
                return ['error' => false];
            }
        } catch (\Exception $e) {
            logger('smt request exception:'.$e->getMessage());
            return ['error' => false, 'message' => $e->getMessage()];
        }
        return $response;
    }

    public function sign($path, $params)
    {
        array_walk($params, function (&$value, $key) {
            $value = $key.$value;
        });
        sort($params);

        return strtoupper(bin2hex(hash_hmac('sha1', $path.implode($params), $this->secKey, true)));
    }

    //根据订单id 获取可用的线上物流方式
    public function apiGetOnlineLogisticsServiceListByOrderId($orderId)
    {
        $params = [
            'orderId' => $orderId,
        ];
        $apiPath = 'aliexpress.open/api.getOnlineLogisticsServiceListByOrderId';
        return $this->request('POST', $apiPath, $params);
    }

    //获取第三方物流公司及其列表
    public function apiQureyWlbDomesticLogisticsCompany()
    {
        $apiPath = 'aliexpress.open/api.qureyWlbDomesticLogisticsCompany';
        return $this->request('POST', $apiPath, []);
    }

    //根据产品ID 获取产品详细信息
    public function apiFindAeProductById($productId)
    {
        $params = [
            'productId' => $productId,
        ];
        $apiPath = 'aliexpress.open/api.findAeProductById';
        return $this->request('POST', $apiPath, $params);
    }

    //根据分类id获取 分类详细信息
    public function apiGetPostCategoryById($categoryId)
    {
        $params = [
            'cateId' => $categoryId,
        ];
        $apiPath = 'aliexpress.open/api.getPostCategoryById';
        return $this->request('POST', $apiPath, $params);
    }

    //根据分类id获取 分类详细信息
    public function apiCreateWarehouseOrder($params)
    {
        $apiPath = 'aliexpress.open/api.createWarehouseOrder';
        return $this->request('POST', $apiPath, $params);
    }

    //打印标签
    public function apiGetPrintInfo($internationalLogisticsId)
    {
        $apiPath = 'aliexpress.open/api.getPrintInfo';
        return $this->request('POST', $apiPath, ['internationalLogisticsId'=>$internationalLogisticsId]);
    }

    //获取卖家地址列表  郑州的 refund addressId 59720379
    public function apiGetLogisticsSellerAddresses()
    {
        $apiPath = 'aliexpress.open/alibaba.ae.api.getLogisticsSellerAddresses';
        $params = [
            'request' => '["sender","pickup","refund"]'
        ];
        return $this->request('POST', $apiPath, $params);
    }

    //获取当前用户下与当前用户建立消息关系的列表
    public function apiQueryMsgRelationList($currentPage = 1, $pageSize = 20, $msgSources = 'message_center')
    {
        $apiPath = 'aliexpress.open/api.queryMsgRelationList';
        $params = [
            'currentPage' => $currentPage,
            'pageSize'    => $pageSize,
            'msgSources'  => $msgSources,
        ];

        return $this->request('POST', $apiPath, $params);
    }

    //站内信/订单留言查询详情列表
    public function apiQueryMsgDetailList($channelId, $currentPage = 1, $pageSize = 20, $msgSources = 'message_center')
    {
        $apiPath = 'aliexpress.open/api.queryMsgDetailList';
        $params = [
            'channelId'   => $channelId,
            'currentPage' => $currentPage,
            'pageSize'    => $pageSize,
            'msgSources'  => $msgSources,
        ];

        return $this->request('POST', $apiPath, $params);
    }


    //速卖通发信
    public function apiAddMsg($channelId, $buyerId, $content, $msgSources)
    {
        $apiPath = 'aliexpress.open/api.addMsg';
        $params = [
            'channelId' => $channelId,
            'buyerId' => $buyerId,
            'content' => $content,
            'msgSources' => 'message_center',
        ];

        return $this->request('POST', $apiPath, $params);
    }
}
