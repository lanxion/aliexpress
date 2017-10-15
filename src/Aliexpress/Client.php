<?php

namespace Aliexpress;

use GuzzleHttp\Client as HttpClient;

class Client
{
    const HOST_SERVER = 'https://gw.api.alibaba.com/';
    private $httpClient;
    private $appKey;
    private $secKey;
    private $refreshToken;
    private $accessToken;
    private $expiredAt;

    public function __construct($appKey, $secKey, $refreshToken, $accessToken = '', $expiredAt = 0, $httpClient = null)
    {
        $this->httpClient = $httpClient ? : new HttpClient();
        $this->appKey = $appKey;
        $this->secKey = $secKey;
        $this->refreshToken = $refreshToken;
        $this->setAccessToken($accessToken, $expiredAt);
    }

    public function setAccessToken($accessToken, $expiredAt)
    {
        if (! empty($accessToken) && $expiredAt > 100) {
            $this->accessToken = $accessToken;
            $this->expiredAt = $expiredAt;
        } else {
            $this->refreshToken();
        }
    }

    public function initHttpClient($client = null)
    {
        $this->httpClient = $client ? : new HttpClient();
    }

    public function getAuthUrl($redirectUrl)
    {
        $params = [
            'client_id' => $this->appKey,
            'site' => 'aliexpress',
            'redirect_uri' => $redirectUrl
        ];
        $params['_aop_signature'] = $this->sign('', $params);

        return self::HOST_SERVER.'auth/authorize.htm?'.http_build_query($params);
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

    private function refreshToken()
    {
        $params = [
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
            'client_id' => $this->appKey,
            'client_secret' => $this->secKey,
        ];
        
        $response = $this->request('POST', 'system.oauth2/getToken', $params, false);
        if (! isset($response['access_token'])) {
            throw new Exception('Get access token failed!');
        }
        $this->expiredAt = time() + intval($response['expires_in']);
        $this->accessToken = $response['access_token'];
    }

    private function updateAccessToken()
    {
        if ($this->expiredAt < time()) {
            $this->refreshToken();
        }
    }

    private function request($method, $apiPath, $params = [], $sign = true)
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
                return ['error' => 'error_response', 'error_description' => 'Response content is not Array!'];
            }
        } catch (\Exception $e) {
            return ['error' => 'requset_exception', 'error_description' => $e->getMessage()];
        }
        return $response;
    }

    private function sign($path, $params)
    {
        array_walk($params, function (&$value, $key) {
            $value = $key.$value;
        });
        sort($params);

        return strtoupper(bin2hex(hash_hmac('sha1', $path.implode($params), $this->secKey, true)));
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

    public function qureyWlbDomesticLogisticsCompany()
    {
        $apiPath = 'aliexpress.open/api.qureyWlbDomesticLogisticsCompany';
        return $this->request('POST', $apiPath, []);
    }

    public function findAeProductById($productId)
    {
        $params = [
            'productId' => $productId,
        ];
        $apiPath = 'aliexpress.open/api.findAeProductById';
        return $this->request('POST', $apiPath, $params);
    }

    public function getPostCategoryById($categoryId)
    {
        $params = [
            'cateId' => $categoryId,
        ];
        $apiPath = 'aliexpress.open/api.getPostCategoryById';
        return $this->request('POST', $apiPath, $params);
    }

    public function createWarehouseOrder($params)
    {
        $apiPath = 'aliexpress.open/api.createWarehouseOrder';
        return $this->request('POST', $apiPath, $params);
    }

    public function getPrintInfo($internationalLogisticsId)
    {
        $apiPath = 'aliexpress.open/api.getPrintInfo';
        return $this->request('POST', $apiPath, ['internationalLogisticsId'=>$internationalLogisticsId]);
    }

    public function getLogisticsSellerAddresses()
    {
        $apiPath = 'aliexpress.open/alibaba.ae.api.getLogisticsSellerAddresses';
        $params = [
            'request' => '["sender","pickup","refund"]'
        ];
        return $this->request('POST', $apiPath, $params);
    }

    public function queryMsgRelationList($currentPage = 1, $pageSize = 20, $msgSources = 'message_center')
    {
        $apiPath = 'aliexpress.open/api.queryMsgRelationList';
        $params = [
            'currentPage' => $currentPage,
            'pageSize'    => $pageSize,
            'msgSources'  => $msgSources,
        ];

        return $this->request('POST', $apiPath, $params);
    }

    public function queryMsgDetailList($channelId, $currentPage = 1, $pageSize = 20, $msgSources = 'message_center')
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

    public function addMsg($channelId, $buyerId, $content, $msgSources = 'message_center')
    {
        $apiPath = 'aliexpress.open/api.addMsg';
        $params = [
            'channelId' => $channelId,
            'buyerId' => $buyerId,
            'content' => $content,
            'msgSources' => $msgSources,
        ];

        return $this->request('POST', $apiPath, $params);
    }
}
