<?php

class JSSDK
{
  private $appId;
  private $appSecret;

  public function __construct($appId, $appSecret)
  {
    $this->appId = $appId;
    $this->appSecret = $appSecret;
  }

  public function getSignPackage()
  {
    $jsapiTicket = $this->getJsApiTicket();

    // 注意 URL 一定要动态获取，不能 hardcode.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    $timestamp = time();
    $nonceStr = $this->createNonceStr();

    // 这里参数的顺序要按照 key 值 ASCII 码升序排序
    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

    $signature = sha1($string);

    $signPackage = array(
        "appId" => $this->appId,
        "nonceStr" => $nonceStr,
        "timestamp" => $timestamp,
        "url" => $url,
        "signature" => $signature,
        "rawString" => $string
    );
    return $signPackage;
  }

  private function createNonceStr($length = 16)
  {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    for ($i = 0; $i < $length; $i++) {
      $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
  }

  private function getJsApiTicket()
  {
    // jsapi_ticket 应该全局存储与更新
    //$con = mysqli_connect("rdsb9b5rofnqqivjhrehfpublic.mysql.rds.aliyuncs.com", "hrzdba", "hrz23886", "haorizi");
	$con = mysqli_connect("rdscpfa2k7c1s019l2wjo.mysql.rds.aliyuncs.com", "ddim_test", "DDimTEst+2015", "db_test");
    $query = "SELECT token_content,expire_time FROM temp_token WHERE token_name='jsapi_ticket'";
    $result = mysqli_query($con, $query);
    $rowContent = mysqli_fetch_array($result);
    $expTime = $rowContent['expire_time'];

    if (time() > $expTime) {
      $accessToken = $this->getAccessToken();

      $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
      $res = json_decode($this->httpGet($url));
      $ticket = $res->ticket;
      if ($ticket) {
        $expTime = time() + 7000;
        $query = "UPDATE temp_token set token_content= '{$ticket}', expire_time={$expTime} WHERE token_name='jsapi_ticket'";
        mysqli_query($con, $query);
      }
    } else {
      $ticket = $rowContent['token_content'];
    }
    mysqli_close($con);
    return $ticket;
  }

  public function getAccessToken()
  {
    // access_token 应该全局存储与更新
    //$con = mysqli_connect("rdsb9b5rofnqqivjhrehfpublic.mysql.rds.aliyuncs.com", "hrzdba", "hrz23886", "haorizi");
	$con = mysqli_connect("rdscpfa2k7c1s019l2wjo.mysql.rds.aliyuncs.com", "ddim_test", "DDimTEst+2015", "db_test");
    $query = "SELECT token_content,expire_time FROM temp_token WHERE token_name='access_token'";
    $result = mysqli_query($con, $query);
    $rowContent = mysqli_fetch_array($result);
    $expTime = $rowContent['expire_time'];

    if ($expTime < time()) {
      $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->appId}&secret={$this->appSecret}";

      $res = json_decode($this->httpGet_simple($url));

      //echo "<!--";
      //var_dump($res);
      //echo "-->";

      $access_token = $res->access_token;
      if ($access_token) {
        $expTime = time() + 7000;
        $query = "UPDATE temp_token set token_content= '{$access_token}', expire_time={$expTime},reviser='heart_1607_ja' WHERE token_name='access_token'";
        mysqli_query($con, $query);
      }
    } else {
      $access_token = $rowContent['token_content'];
    }
    mysqli_close($con);
    return $access_token;
  }
  //insert a function to check the subscribe status of this openid
  //update by Cerberus 2016年7月25日
  //subscribe control
  public function subscribes($openid=null){
    if(!$openid)return null;
    $access_token = $this->getAccessToken();

    $userifo_url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$access_token."&openid=".$openid;
    $userifo = json_decode(file_get_contents($userifo_url));
    // echo "<!--";
    // var_dump($userifo);
    // echo "-->";
    // var_dump($userifo);
    $res = $userifo->subscribe ? 1 : 2;
    return $res;
  }

  private function httpGet_simple($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_URL, $url);

    $res = curl_exec($curl);
    curl_close($curl);

    return $res;
  }

  function httpGet($url)
  {
    $cacert = getcwd() . '\cacert.pem'; //CA根证书
    $SSL = substr($url, 0, 8) == "https://" ? true : false;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout - 2);
    if ($SSL && $CA) {
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);   // 只信任CA颁布的证书
      curl_setopt($ch, CURLOPT_CAINFO, $cacert); // CA根证书（用来验证的网站证书是否是CA颁布）
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // 检查证书中是否设置域名，并且是否与提供的主机名匹配
    } else if ($SSL && !$CA) {
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 信任任何证书
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1); // 检查证书中是否设置域名
    }
    // 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
    // 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
  }

  /**
   * curl POST
   *
   * @param    string  url
   * @param    array   数据
   * @param    int     请求超时时间
   * @param    bool    HTTPS时是否进行严格认证
   * @return    string
   */
  function curlPost($url, $data, $timeout = 120, $CA = true)
  {

    $cacert = getcwd() . '\cacert.pem'; //CA根证书
    $SSL = substr($url, 0, 8) == "https://" ? true : false;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout - 2);
    if ($SSL && $CA) {
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);   // 只信任CA颁布的证书
      curl_setopt($ch, CURLOPT_CAINFO, $cacert); // CA根证书（用来验证的网站证书是否是CA颁布）
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // 检查证书中是否设置域名，并且是否与提供的主机名匹配
    } else if ($SSL && !$CA) {
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 信任任何证书
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1); // 检查证书中是否设置域名
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, FALSE);
//	    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:')); //避免data数据过长问题
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data))
    );
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    //curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); //data with URLEncode

    $ret = curl_exec($ch);
    echo curl_error($ch);  //查看报错信

    curl_close($ch);
    return $ret;
  }
}


