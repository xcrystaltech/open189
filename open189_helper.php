<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
require_once("HTTP/Request2.php");

class open189
{
	var $urlAuthorize = "https://oauth.api.189.cn/emp/oauth2/v2/authorize";

	var $urlAccessToken = "https://oauth.api.189.cn/emp/oauth2/v2/access_token";

	var $urlRandCode = "http://api.189.cn/v2/dm/randcode/token";

	var $urlRandSend = "http://api.189.cn/v2/dm/randcode/send";

	var $rand_code;
	var $rand_identifier;

	var $app_id;
	var $app_secret;
	var $callbackUrl;
	var $access_token = 'e5f64c2e3f9f50befa7fb25711a4d3261373873429610';

	var $last_error;

	public function __construct($params)
	{

		$this->app_id = $params['app_id'];
		$this->app_secret = $params['app_secret'];
		$this->callbackUrl = $params['callback_url'];
	}

	public function randIdentifier()
	{
		return $this->rand_identifier;
	}

	public function randCode()
	{
		return $this->rand_code;
	}
	public function accessToken()
	{
		return $this->access_token;
	}

	public function setAccessToken($access_token)
	{
		$this->access_token = $access_token;
	}

	public function authorize($options = array())
	{
		$params = array(
				'app_id' => $this->app_id,
				'redirect_uri' => $this->callbackUrl,
				'response_type' => 'code'
		);

		header('Location: ' .$this->urlAuthorize.'?'.http_build_query($params));
	}

	public function _sign($params)
	{

		$strToSign = "";
		$strArr = array();
		foreach($params as $key=>$val)
		{
			$strArr[] = "$key=$val";
		}
		sort($strArr);
		$strToSign = implode("&", $strArr);
		$sign = hash_hmac("sha1", $strToSign, $this->app_secret, true);

		return base64_encode($sign);
	}
	
	//to accept 189 callback, on your $code_notify_url, you should do follow
	/*
	public function code_notify()
	{
		if(isset($_POST)&&isset($_POST['rand_code'])&&isset($_POST['identifier']))
		{
			$this->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file'));
			
			$randcode = $_POST['rand_code'];
			$identifier = $_POST['identifier'];
			$this->cache->save("code", $randcode, 300);
			$this->cache->save("ident", $identifier, 300);
			header("Cache-Control:no-stroe,no-cache,must-revalidate,post-check=0,pre-check=0");
			header("Pragma:no-cache");
			echo json_encode(array('res_code'=>0));
			exit();
		}
	}
	*/
	public function SendSms($phone, $code_notify_url)
	{
		if(empty($this->rand_code))
		{
			$this->getRandCode();
		}
		if(empty($this->rand_code))
		{
			return false;
		}
		$params = array(
				'app_id' => $this->app_id,
				'access_token' =>  $this->getMyAccessToken(),
				'token'    => $this->rand_code,
				'phone' => $phone,
				'url' => $code_notify_url,
				'exp_time' => 5,
				'timestamp' => date("Y-m-d H:i:s",time())
		);
		$sign = $this->_sign($params);
		$params['sign'] = $sign;

		$request = new HTTP_Request2($this->urlRandSend, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
		foreach($params as $key=>$value)
		{
			$request->addPostParameter($key, $value);
		}
		try {
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$result = json_decode($response->getBody(), true);
				if (isset($result['identifier'])){
					$this->rand_identifier = $result['identifier'];
					return true;
				}else{
					$this->last_error = $result['res_message'];
				}
			}
		} catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}
	public function getRandCode()
	{
		$access_token = $this->getMyAccessToken();
		if(empty($access_token))
		{
			return false;
		}
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'timestamp'    => date("Y-m-d H:i:s",time())
		);
		$params['sign'] = $this->_sign($params);

		try
		{
			$request = new HTTP_Request2($this->urlRandCode."?".http_build_query($params), HTTP_Request2::METHOD_GET);
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$result = json_decode($response->getBody(), true);
				if(isset($result["token"]))
				{
					$this->rand_code = $result['token'];
					return $result["token"];
				}else{
					$this->last_error = $result;
				}
			}
		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
	}
	public function getAccessToken($grant = 'authorization_code', $params = array())
	{
		$params = array(
				'app_id' => $this->app_id,
				'redirect_uri' => $this->callbackUrl,
				'grant_type'    => $grant,
				'code' => $params['code'],
				'app_secret' => $this->app_secret
		);

		$request = new HTTP_Request2($this->urlAccessToken, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
		foreach($params as $key=>$value)
		{
			$request->addPostParameter($key, $value);
		}
		try {
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$result = json_decode($response->getBody(), true);
				if ($result['res_code'] == 0){
					$this->access_token = $result["access_token"];
					return true;
				}
			}
		} catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}
	/**
	 * 获取登陆者电话
	 * @return string|mixed
	 */
	public function getPhone()
	{
		$url = "http://api.189.cn/upc/real/cellphone_and_province";
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $this->access_token,
				'type' => 'json'
		);
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET);
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$result = json_decode($response->getBody(), true);
				if(isset($result["error"]))
				{
					return "";
				}else{
					return $result["cellphone"];
				}
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	public function getMyAccessToken()
	{
		$CI =& get_instance();
		$CI->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file'));
		$access_token = $CI->cache->get("my_access_token");
		if(!$access_token)
		{
			try {
				$params = array(
						'app_id' => $this->app_id,
						'app_secret' => $this->app_secret,
						'grant_type'    => "client_credentials"
				);
				$request = new HTTP_Request2($this->urlAccessToken, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
				foreach($params as $key=>$value)
				{
					$request->addPostParameter($key, $value);
				}
				$response = $request->send();
				if (200 == $response->getStatus()) {
					$result = json_decode($response->getBody(), true);
					if ($result['res_code'] == 0){
						$CI->cache->save("my_access_token",$result["access_token"]);
						return $result["access_token"];
					}
				}
			}catch (HTTP_Request2_Exception $e) {
				$this->last_error = $e->getMessage();
			}
			return "";
		}
		return $access_token;
	}
	/**
	 * 获取城市天气信息
	 * @param long $cityId
	 * @return string
	 */
	public function getWeather($cityId)
	{
		$access_token = $this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = "http://api.189.cn/huafeng/api/getforecast24";
		$params = array(
				'app_id' => $this->app_id,
				'access_token'=> $access_token,
				'city_id' => $cityId
		);
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$data = $response->getBody();
				$xml = simplexml_load_string($data);
				$forecast = $xml->City->forecast;
				return $xml->City["name"]." ".$forecast["DATE"]." 天气:".$forecast["WEA"]." 风向:".$forecast["WIND"]."  最高气温:".$forecast["TMAX"]."摄氏度  "."最低气温:".$forecast["TMIN"]."摄氏度";
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 获取定位信息
	 * @param string $address
	 * @return string
	 */
	public function getAddrPositionInfo($address)
	{
		$access_token = $this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/besttone/getAddrPositionInfo';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'timestamp'	=> date('Y-m-d H:i:s',time()),
				'address' => $address,
				'encode' => "UTF-8"
		);
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach($params as $key=>$value)
			{
				$request->addPostParameter($key, $value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return $json->response->list;
				else
					return "";
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 获取交叉路口位置信息
	 * @param string $city
	 * @param string $roadName1
	 * @param string $roadName2
	 * @return string
	 */
	public function getCrossRoadPositionInfo($city,$roadName1,$roadName2)
	{
		$access_token = $this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/besttone/getCrossRoadPositionInfo';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'city' => $city,
				'roadName1' => $roadName1,
				'roadName2' => $roadName2,
				'timestamp'	=> date('Y-m-d H:i:s',time()),
				'encode' => "UTF-8"
		);
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach($params as $key=>$value)
			{
				$request->addPostParameter($key, $value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return $json->response->list;
				else
					return "";
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 获取用户基本信息
	 * @return string|multitype:NULL
	 */
	public function getCloudUserInfo()
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/getUserInfo.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token
		);
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return array(
							"capacity" => $json->capacity,
							"available" => $json->available,
							"maxFilesize" => $json->maxFilesize
					);
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 获取文件夹信息
	 * @return string|multitype:NULL
	 */

	public function getCloudFolderInfo(array $options=null)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/getFolderInfo.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token
		);
		if(isset($options['folderId']))
			$params['folderId'] = $options['folderId'];
		if(isset($options['folderPath']))
			$params['folderPath'] = $options['folderPath'];
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return array(
							"id" => $json->id,
							"path" => $json->path,
							"name" => $json->name,
							"createDate" => $json->createDate,
							"lastOpTime" => $json->lastOpTime,
							"rev" => $json->rev

					);
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}

	/**
	 * 获取文件列表
	 * @param arry $options
	 * 			$options['orderBy'],$options['pageNum'],$options['pageSize'] is required
	 * @return array|string
	 */

	public function getCloudFileList(array $options=null)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/listFiles.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'orderBy' => $options['orderBy'],
				'pageNum' => $options['pageNum'],
				'pageSize' => $options['pageSize']
		);
		if(isset($options['folderId']))
			$params['folderId'] = $options['folderId'];
		if(isset($options['fileType']))
			$params['fileType'] = $options['fileType'];
		if(isset($options['mediaType']))
			$params['mediaType'] = $options['mediaType'];
		if(isset($options['mediaAttr']))
			$params['mediaAttr'] = $options['mediaAttr'];
		if(isset($options['iconOption']))
			$params['iconOption'] = $options['iconOption'];
		if(isset($options['descending']))
			$params['descending'] = $options['descending'];
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return 	$json->fileList;
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 创建文件夹
	 * @param array $options
	 * @return string|multitype:NULL
	 */
	public function createCloudFolder(array $options=null)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/createFolder.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'folderName' => $options['folderName']
		);
		if(isset($options['parentFolderId']))
			$params['parentFolderId'] = $options['parentFolderId'];
		if(isset($options['relativePath']))
			$params['relativePath'] = $options['relativePath'];
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return 	array(
							'id' => $json->id,
							'name' => $json->name,
							'createDate' => $json->createDate,
							'lastOpTime' => $json->lastOpTime
					);
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 获取文件信息
	 * @param array $options
	 * @return string|multitype:NULL
	 */
	public function getCloudFileInfo(array $options=null)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/getFileInfo.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token
		);
		if(isset($options['fileId']))
			$params['fileId'] = $options['fileId'];
		if(isset($options['filePath']))
			$params['filePath'] = $options['filePath'];
		if(isset($options['mediaAttr']))
			$params['mediaAttr'] = $options['mediaAttr'];
		if(isset($options['iconOption']))
			$params['iconOption'] = $options['iconOption'];
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					$data = array(
							"id" => $json->id,
							'parentFolderId' => $json->parentFolderId,
							"path" => $json->path,
							"name" => $json->name,
							'size' => $json->size,
							"createDate" => $json->createDate,
							"lastOpTime" => $json->lastOpTime,
							"mediaType" => $json->mediaType
					);
					if(isset($options['mediaAttr']) && $options['mediaAttr'] == 1 && isset($json->mediaAttr))
					{
						$data['mediaAttr'] = $json->mediaAttr;
					}
					return $data;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 生成文件分享链接
	 * @param int $fileId
	 * @return string|multitype:NULL
	 */
	public function createCloudShareLink($fileId)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/createShareLink.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'fileId' => $fileId
		);
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return $json->shareLink;
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
			return $this->last_error;
		}
		return "";
	}
	/**
	 * 获取文件下载地址
	 * @param array $options
	 * @return string
	 */
	public function getCloudFileDownloadUrl(array $options=null)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/getFileDownloadUrl.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'fileId' => $options['fileId']
		);
		if (isset($options['short']))
			$params['short'] = $options['short'];
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return $json->fileDownloadUrl;
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 文件搜索
	 * @param array $options
	 * @return string
	 */
	public function searchCloudFiles(array $options=null)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/searchFiles.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token
		);
		$params = array_merge($params,$options);
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach($params as $key=>$value)
			{
				$request->addPostParameter($key, $value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return array(
							'count'	=> $json->count,
							'folder' => $json->folder,
							'file' => $json->file
					);
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 批量获取文件下载地址
	 * @param array $options
	 * @return string
	 */
	public function batchGetFileDownloadUrl(array $options=null)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/batchGetFileDownloadUrl.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token
		);
		if(isset($options['short']))
			$params['short'] = $options['short'];
		$fileString = '';
		foreach ($options['fileId'] as $key=>$value)
		{
			$fileString .= '&fileId='.$value;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params).$fileString, HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return $json->downloadUrl;
				else
					$this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}
	/**
	 * 重命名文件夹
	 * @param array $options
	 * @return string|multitype:NULL
	 */
	public function renameCloudFolder(array $options=null)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/renameFolder.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'folderId' => $options['folderId'],
				'destFolderName' => $options['destFolderName']
		);
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach($params as $key=>$value)
			{
				$request->addPostParameter($key, $value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return array(
							'id' => $json->id,
							'name' => $json->name,
							'createDate' => $json->createDate,
							'lastOpTime' => $json->lastOpTime
					);
				else
					$this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}

	/**
	 * 重命名文件
	 * @param array $options
	 * @return string|multitype:NULL
	 */
	public function renameCloudFile(array $options=null)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/renameFile.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'fileId' => $options['fileId'],
				'destFileName' => $options['destFileName']
		);
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach($params as $key=>$value)
			{
				$request->addPostParameter($key, $value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return array(
							'id' => $json->id,
							'name' => $json->name,
							'size' => $json->size,
							'md5' => $json->md5,
							'createDate' => $json->createDate
					);
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 复制文件
	 * @param array $options
	 * @return string|multitype:NULL
	 */
	public function copyCloudFile(array $options=null)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/copyFile.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'fileId' => $options['fileId'],
				'destFileName' => $options['destFileName']
		);
		if (isset($options['destParentFolderId']))
			$params['destParentFolderId'] = $options['destParentFolderId'];
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach($params as $key=>$value)
			{
				$request->addPostParameter($key, $value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return array(
							'id' => $json->id,
							'name' => $json->name,
							'size' => $json->size,
							'md5' => $json->md5,
							'createDate' => $json->createDate
					);
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 移动文件夹
	 * @param array $options
	 * @return string|multitype:NULL
	 */
	public function moveCloudFolder(array $options=null)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/moveFolder.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'folderId' => $options['folderId'],
				'destParentFolderId' => $options['destParentFolderId']
		);
		if (isset($options['destFolderName']))
			$params['destFolderName'] = $options['destFolderName'];
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach($params as $key=>$value)
			{
				$request->addPostParameter($key, $value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return array(
							'id' => $json->id,
							'name' => $json->name,
							'createDate' => $json->createDate,
							'lastOpTime' => $json->lastOpTime
					);
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 移动文件
	 * @param array $options
	 * @return string|multitype:NULL
	 */
	public function moveCloudFile(array $options=null)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/moveFile.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'fileId' => $options['fileId'],
				'destFileName' => $options['destFileName']
		);
		if (isset($options['destParentFolderId']))
			$params['destParentFolderId'] = $options['destParentFolderId'];
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach($params as $key=>$value)
			{
				$request->addPostParameter($key, $value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return array(
							'id' => $json->id,
							'name' => $json->name,
							'size' => $json->size,
							'md5' => $json->md5,
							'createDate' => $json->createDate
					);
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 删除文件夹
	 * @param long $folderId
	 * @return string|boolean
	 */
	public function deleteCloudFolder($folderId)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/deleteFolder.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'folderId' => $folderId
		);
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach($params as $key=>$value)
			{
				$request->addPostParameter($key, $value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return true;
				else
					$this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}
	/**
	 * 删除文件
	 * @param long $fileId
	 * @return string|boolean
	 */
	public function deleteCloudFile($fileId)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/deleteFile.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'fileId' => $fileId
		);
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach($params as $key=>$value)
			{
				$request->addPostParameter($key, $value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return true;
				else
					$this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}
	/**
	 * 获取文件上传地址
	 * @return string 应用使用接口返回的地址并把app_id和access_token拼接在url后面进行文件上传
	 */
	public function getFileUploadUrl()
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/getFileUploadUrl.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token
		);
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return $json->FileUploadUrl;
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 获取文件上传地址(断点续传)
	 * @param array $options
	 * 		filename,size,md5,lastWrite,localPath is required
	 * 		parentFolderId,baseFileId is optional
	 * @return array
	 */
	public function createUploadFile(array $options=null)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/createUploadFile.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token
		);
		$params = array_merge($params,$options);
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach($params as $key=>$value)
			{
				$request->addPostParameter($key, $value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return array(
							'uploadFileId' => $json->uploadFileId,
							'fileUploadUrl' => $json->fileUploadUrl,
							'fileCommitUrl' => $json->fileCommitUrl,
							'fileDataExists' => $json->fileDataExists
					);
				else
					$this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}
	/**
	 * 获取上传文件状态（断点续传）
	 * @param long $uploadFileId
	 * @return array
	 */
	public function getUploadFileStatus($uploadFileId)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/ChinaTelecom/getUploadFileStatus.action';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'uploadFileId' => $uploadFileId
		);
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
					return array(
							'uploadFileId' => $json->uploadFileId,
							'size' => $json->size,
							'fileUploadUrl' => $json->fileUploadUrl,
							'fileCommitUrl' => $json->fileCommitUrl,
							'fileDataExists' => $json->fileDataExists
					);
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 *
	 * @param string $uploadUrl 文件上传地址，通过getFileUploadUrl()取得
	 * @param long $parentFolderId 上传的目的文件夹id，通过getCloudFileList()取得,默认为主文件夹
	 * @param file $file 上传文件流
	 * @return string|boolean
	 */
	public function doFileUpload($uploadUrl,$parentFolderId,$uploadFileId=0,$filename,$fileStream)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token
		);
		try
		{
			$request = new HTTP_Request2($uploadUrl.'&'.http_build_query($params), HTTP_Request2::METHOD_PUT, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$request->setHeader("Content-Transfer-Encoding:binary");
			$request->setHeader('Edrive-ParentFolderId:'.$parentFolderId);
			$request->setHeader('Edrive-FileName:'.urlencode($filename));
			if($uploadFileId)
			{
				$request->setHeader('Edrive-UploadFileId:'.$uploadFileId);
			}
			$request->setBody($fileStream);
			$response = $request->send();
			if (200 == $response->getStatus()) {
				return true;
			}
		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}
	/**
	 * 获取榜单列表
	 * @return array 榜单列表
	 */
	public function queryBillboardList()
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/content/contentbillboardservice/querybillboardlist';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->queryBillboardListResponse->billboard_list->billboard;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 榜单歌曲查询
	 * @param long $billboard_id 榜单ID
	 * @param int $count 单页返回记录数 默认为10
	 * @param int $page 查询记录的开始数, 默认为 1
	 * @return string 榜单歌曲列表
	 */
	public function queryContentBillboard($billboard_id,$count=10,$page=1)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/content/contentbillboardservice/querycontentbillboard';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'billboard_id' => $billboard_id,
				'count' => $count,
				'page' => $page,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->QueryContentBillboardResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 歌曲信息查询
	 * @param long $id
	 * @param int $type
	 * @return string
	 */
	public function querySongInfo($id,$type)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/product/productquery/querysonginfo';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'id' => $id,
				'type' => $type,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->QueryContentBillboardResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 查询产品全部信息
	 * @param long $content_id
	 * @return string|mixed
	 */
	public function queryAllProduct($content_id,array $options=null)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/product/productquery/queryallproduct';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'content_id'=> $content_id,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if(count($options))
		{
			$params = array_merge($params,$options);
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->ProductInfoListJTResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 彩铃详情查询
	 * @param long $resource_id
	 * @param string $sign
	 * @return string|mixed
	 */
	public function queryCrbtInfo($resource_id,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/product/productquery/querycrbtinfo';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'resource_id'=> $resource_id,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->musicProductInfo;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 振铃详情查询
	 * @param long $resource_id
	 * @param string $sign
	 * @return string|mixed
	 */
	public function queryRingToneInfo($resource_id,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/product/productquery/queryringtoneinfo';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'resource_id'=> $resource_id,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->musicProductInfo;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 全曲详情查询
	 * @param long $resource_id
	 * @param string $sign
	 * @return string|mixed
	 */
	public function queryFullTrackInfo($resource_id,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/product/productquery/queryfulltrackinfo';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'resource_id'=> $resource_id,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->musicProductInfo;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 铃音盒详情查询
	 * @param long $box_fee_id
	 * @param string $sign
	 * @return string|mixed
	 */
	public function queryRingBoxInfo($box_fee_id,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/product/productquery/queryringboxinfo';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'box_fee_id'=> $box_fee_id,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->RingBoxInfo;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 铃音盒中产品查询
	 * @param long $box_fee_id
	 * @param string $sign
	 * @return string|mixed
	 */
	public function queryRingBoxBizInfo($box_fee_id,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/product/productquery/queryringboxbizinfo';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'box_fee_id'=> $box_fee_id,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->ringboxbizlist;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 彩铃试听查询
	 * @param unknown_type $resource_id
	 * @param unknown_type $sign
	 * @return string|mixed
	 */
	public function queryCrbt($resource_id,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/audio/iaudiomanager/querycrbt';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'resource_id'=> $resource_id,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->audioFileResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 振铃试听查询
	 * @param long $resource_id
	 * @param string $sign
	 * @param string $format
	 * @return string
	 */
	public function queryRingTone($resource_id,$sign='',$format='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/audio/iaudiomanager/queryringtone';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'resource_id'=> $resource_id,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		if($format != '')
		{
			$params['format'] = $format;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->audioFileResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 全曲试听查询
	 * @param long $id
	 * @param number $id_type,id类型,type=4 全曲id;type=5 内容id
	 * @param string $format
	 * @param string $sign
	 * @return string
	 */
	public function queryFullTrackById($id,$id_type,$format,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/audio/iaudiomanager/queryfulltrackbyid';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'id'=> $id,
				'id_type' => $id_type,
				'format' => $format,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->audioFileResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 查询专辑榜单列表信息(面向深度合作应用）
	 * @param long $album_billboard_id
	 * @param number $count
	 * @param number $page
	 * @param number $sign
	 * @return string
	 */
	public function queryAlbumList($album_billboard_id,$count,$page,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/content/contentbillboardservice/queryalbumlist';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'album_billboard_id'=> $album_billboard_id,
				'count' => $count,
				'page' => $page,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->queryAlbumListResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 获取热门歌手列表(面向深度合作应用)
	 * @param long $list_id
	 * @param string $sign
	 * @return string
	 */
	public function queryHotSingerList($list_id,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/content/contentbillboardservice/queryhotsingerlist';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'list_id'=> $list_id,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->hotsingerlistResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 查询富榜单接口(面向深度合作应用)
	 * @param long $billboard_id
	 * @param number $start_num
	 * @param number $max_num
	 * @param string $sign
	 * @return string
	 */
	public function queryRichBillboard($billboard_id,$start_num,$max_num,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/content/contentbillboardservice/queryrichbillboard';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'billboard_id'=> $billboard_id,
				'start_num' => $start_num,
				'max_num' => $max_num,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->richBillboardResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 曲库信息-获取分类列表
	 * @param string $sign
	 * @return string
	 */
	public function queryCateList($sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/cate/catemanager/querycatelist';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->queryCateLstResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 查询分类歌手信息
	 * @param long $cate_id
	 * @param number $start_num
	 * @param number $max_num
	 * @param string $order
	 * @param string $sign
	 * @return string|mixed
	 */
	public function queryActorsInfo($cate_id,$start_num,$max_num,$order,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/cate/catemanager/queryactorsinfo';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'cate_id' => $cate_id,
				'start_num' => $start_num,
				'max_num' => $max_num,
				'order' => $order,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				return $json;
				if($json->res_code == 0)
				{
					return $json->queryActorsResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 查询分类歌曲信息
	 * @param long $cate_id
	 * @param number $start_num
	 * @param number $max_num
	 * @param string $order
	 * @param string $sign
	 * @return string
	 */
	public function queryMusicCate($cate_id,$start_num,$max_num,$order,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/cate/catemanager/querymusiccate';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'cate_id' => $cate_id,
				'start_num' => $start_num,
				'max_num' => $max_num,
				'order' => $order,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->queryMusicsResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 歌手图片查询
	 * @param long $id
	 * @param number $id_type
	 * @param string $signer_name
	 * @param string $format
	 * @param string $sign
	 * @return string
	 */
	public function findSingerPic($id,$id_type,$signer_name,$format='',$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/picture/picture/findsingerpic';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'id' => $id,
				'id_type' => $id_type,
				'signer_name' => $signer_name,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		if($format != '')
		{
			$params['format'] = $format;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->queryPicsResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 专辑图片查询
	 * @param long $id
	 * @param number $id_type
	 * @param string $format
	 * @param string $singer
	 * @param string $song
	 * @param string $sign
	 * @return string
	 */
	public function findAlbumPic($id,$id_type,$format='',$singer,$song,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/picture/picture/findalbumpic';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'id' => $id,
				'id_type' => $id_type,
				'singer' => $singer,
				'song' => $song,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		if($format != '')
		{
			$params['format'] = $format;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->queryPicsResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 查询歌词
	 * @param long $id
	 * @param number $id_type
	 * @param string $music_name,optional
	 * @param string $actor_name,optional
	 * @param number $type
	 * @param string $sign,optional
	 * @return string
	 */
	public function queryLyric($id,$id_type,$music_name='',$actor_name='',$type,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/lyric/lyric/querylyric';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'id' => $id,
				'id_type' => $id_type,
				'type' => $type,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		if($music_name != '')
		{
			$params['music_name'] = $music_name;
		}
		if($actor_name != '')
		{
			$params['actor_name'] = $actor_name;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->queryLyricResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 订购彩铃或音乐盒
	 * @param array $options
	 * 		mdn,crbt_id,type,random_key,set_default_crbt,sign
	 * @return string|boolean
	 */
	public function orderCrbtService(array $options=null)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/music/crbtservice/order';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'mdn' => $options['mdn'],
				'crbt_id' => $options['crbt_id'],
				'type' => $options['type'],
				'random_key' => $options['random_key'],
				'set_default_crbt' => $options['set_default_crbt'],
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($options['sign'] != '')
		{
			$params['sign'] = $options['sign'];
		}
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach ($params as $key=>$value)
			{
				$request->addPostParameter($key,value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return true;
				}
				else
				{
					$this->last_error = $json->res_message;
					return false;
				}
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}
	/**
	 * 订购彩铃验证码接口
	 * @param string $mdn 手机号码
	 * @param long $crbt_id 彩铃ID
	 * @param number $type 1为用余额订购彩铃,2为爱乐点订购(不支持订购音乐盒).
	 * @return string|boolean
	 */
	public function sendOrderRandomkey($mdn,$crbt_id,$type)
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/music/crbtservice/sendorderrandomkey';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'mdn' => $options['mdn'],
				'crbt_id' => $options['crbt_id'],
				'type' => $options['type'],
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($options['sign'] != '')
		{
			$params['sign'] = $options['sign'];
		}
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach ($params as $key=>$value)
			{
				$request->addPostParameter($key,value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return true;
				}
				else
				{
					$this->last_error = $json->res_message;
					return false;
				}
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}
	/**
	 * 查询铃音播放模式
	 * @param string $mdn 手机号码
	 * @param number $mdn_type 号码类型。 0:手机号,1:小灵通
	 * @param string $sign
	 * @return string
	 */
	public function queryPlayMode($mdn,$mdn_type,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/music/crbtservice/queryplaymode';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'mdn' => $mdn,
				'mdn_type' => $mdn_type,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->playModeResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 设置铃音播放模式
	 * @param string $mdn
	 * @param number $mdn_type
	 * @param number $play_mode
	 * @param string $sign
	 * @return string|boolean
	 */
	public function setPlayMode($mdn,$mdn_type,$play_mode,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/music/crbtservice/setplaymode';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'mdn' => $mdn,
				'mdn_type' => $mdn_type,
				'play_mode' => $play_mode,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach ($params as $key=>$value)
			{
				$request->addPostParameter($key,$value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return true;
				}
				else
				{
					$this->last_error = $json->res_message;
					return false;
				}
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}
	/**
	 * 查询个人铃音库
	 * @param string $mdn
	 * @param number $count
	 * @param number $page
	 * @param string $sign
	 * @return string
	 */
	public function queryRing($mdn,$count,$page,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/music/crbtservice/queryring';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'mdn' => $mdn,
				'count' => $count,
				'page' => $page,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->queryRingResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 查询默认铃音
	 * @param string $mdn
	 * @param string $sign
	 * @return string
	 */
	public function queryDefaultRing($mdn,$count,$page,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/music/crbtservice/querydefaultring';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'mdn' => $mdn,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->defaultRingResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 设置默认铃音
	 * @param string $mdn
	 * @param number $mdn_type
	 * @param number $crbt_id
	 * @return string|boolean
	 */
	public function setRing($mdn,$crbt_id,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/music/crbtservice/setring';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'mdn' => $mdn,
				'crbt_id' => $crbt_id,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach ($params as $key=>$value)
			{
				$request->addPostParameter($key,$value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return true;
				}
				else
				{
					$this->last_error = $json->res_message;
					return false;
				}
			}
		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}
	/**
	 * 开通彩铃
	 * @param string $mdn
	 * @param string $random_key
	 * @param string $sign
	 * @return string|boolean
	 */
	public function openCrbtService($mdn,$random_key,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/music/crbtservice/open';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'mdn' => $mdn,
				'random_key' => $random_key,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach ($params as $key=>$value)
			{
				$request->addPostParameter($key,$value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return true;
				}
				else
				{
					$this->last_error = $json->res_message;
					return false;
				}
			}
		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}
	/**
	 * 是否彩铃用户
	 * @param string $mdn
	 * @param string $sign
	 * @return string|boolean
	 */
	public function isCrbtUser($mdn,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/music/crbtservice/iscrbtuser';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'mdn' => $mdn,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach ($params as $key=>$value)
			{
				$request->addPostParameter($key,$value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return true;
				}
				else
				{
					$this->last_error = $json->res_message;
					return false;
				}
			}
		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}
	/**
	 * 查询铃音订购页URL
	 * @param string $product_id
	 * @param string $portal_type WAP或者WEB
	 * @param string $mdn,optional
	 * @param string $channel_id,optional
	 * @param string $sign,optional
	 * @return string
	 */
	public function queryOrderPageUrl($product_id,$portal_type,$mdn,$channel_id,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/music/crbtservice/queryorderpageurl';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'product_id' => $product_id,
				'portal_type' => $portal_type,
				'mdn' => $mdn,
				'channel_id' => $channel_id,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->query_order_page_url_response;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 通过手机下载音乐
	 * @param string $mdn
	 * @param string $random_key
	 * @param string $product_id
	 * @param string $sign,optional
	 * @return string|boolean
	 */
	public function wapPush($mdn,$random_key,$product_id,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/music/crbtservice/wappush';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'mdn' => $mdn,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach ($params as $key=>$value)
			{
				$request->addPostParameter($key,$value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return true;
				}
				else
				{
					$this->last_error = $json->res_message;
					return false;
				}
			}
		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}
	/**
	 * 歌曲搜索服务
	 * @param string $key_word
	 * @param string $type,返回歌曲类型,1=结果包含彩铃;2=包含振铃;4=包含全曲;1-2=包含彩铃振铃
	 * @param number $count
	 * @param number $page
	 * @param string $sign
	 * @return string
	 */
	public function searchMusic($key_word,$type,$count,$page,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/searchmusic/search/searchmusic';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'key_work' => $key_word,
				'type' => $type,
				'count' => $count,
				'page' => $page,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->searchSongDataResponse;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 发送短信验证码
	 * @param string $mdn
	 * @param string $sign,optional
	 * @return string|boolean
	 */
	public function sendRandom($mdn,$sign='')
	{
		$access_token = $this->accessToken();//$this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/music/openapi/services/v2/music/crbtservice/sendrandom';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'mdn' => $mdn,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $sign;
		}
		try
		{
			$request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			foreach ($params as $key=>$value)
			{
				$request->addPostParameter($key,$value);
			}
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return true;
				}
				else
				{
					$this->last_error = $json->res_message;
					return false;
				}
			}
		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return false;
	}
	/**
	 * 节目分页查询
	 * @param number $ver
	 * @param number $page
	 * @param number $size
	 * @param string $resource
	 * @param array $options
	 * @return string
	 */
	public function queryProgram($ver,$page,$size,$reqsource,array $options=null)
	{
		$access_token = $this->accessToken(); // $this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/media189/program/query';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'ver' => $ver,
				'page' => $page,
				'size' => $size,
				'reqsource' => $reqsource,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if(count($options))
		{
			$params = array_merge($params,$options);
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->info;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 节目详情查询
	 * @param number $ver
	 * @param string $pid
	 * @param number $reqsource
	 * @param string $sign
	 * @return string
	 */
	public function getProgramInfo($ver,$pid,$reqsource,$sign)
	{
		$access_token = $this->accessToken(); // $this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/media189/program/getInfo';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'ver' => $ver,
				'pid' => $pid,
				'reqsource' => $reqsource,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $options['sign'];
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->info;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 获取节目分类详情
	 * @param number $ver
	 * @param string $cid
	 * @param string $reqsource
	 * @param string $sign
	 * @return string
	 */
	public function getProgramCateInfo($ver,$cid,$reqsource,$sign)
	{
		$access_token = $this->accessToken(); // $this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/media189/category/getInfo';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'ver' => $ver,
				'cid' => $pid,
				'reqsource' => $reqsource,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $options['sign'];
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->info;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
	/**
	 * 查询节目分类信息
	 * @param number $ver
	 * @param string $cid
	 * @param string $reqsource
	 * @param string $sign
	 * @return string
	 */
	public function queryProgramCateInfo($ver,$cid,$reqsource,$sign)
	{
		$access_token = $this->accessToken(); // $this->getMyAccessToken();
		if(empty($access_token))
		{
			return "";
		}
		$url = 'http://api.189.cn/v2/media189/category/query';
		$params = array(
				'app_id' => $this->app_id,
				'access_token' => $access_token,
				'ver' => $ver,
				'cid' => $pid,
				'reqsource' => $reqsource,
				'timestamp'	=> date('Y-m-d H:i:s',time())
		);
		if($sign != '')
		{
			$params['sign'] = $options['sign'];
		}
		try
		{
			$request = new HTTP_Request2($url."?".http_build_query($params), HTTP_Request2::METHOD_GET, array("ssl_verify_peer"=>false,'ssl_verify_host'=> false));
			$response = $request->send();
			if (200 == $response->getStatus()) {
				$json = json_decode($response->getBody());
				if($json->res_code == 0)
				{
					return $json->info;
				}
				else
					return $this->last_error = $json->res_message;
			}

		}catch (HTTP_Request2_Exception $e) {
			$this->last_error = $e->getMessage();
		}
		return "";
	}
}

?>