<?php
if(!defined("AdminPHP")) exit('<h1 style="color:red">Bad Request!</h1> <hr /> Powered By Xlch-AdminPHP');

if(!IsLogin()){
	returnResult([
		'Code'=>-9,
		'Message'=>'未登录！'
	]);
}
if(!$DirInfo=$Mysql->get_row('select * from xlch_image_dir where ID = "'.daddslashes(getArgs('DirId')).'"')){
	returnResult([
		'Code'=>-6,
		'Message'=>'目录不存在！'
	]);
}
if(!$DirInfo['AnybodyUpload'] && $DirInfo['CreaterId'] != $UserInfo['ID']){
	returnResult([
		'Code'=>-2,
		'Message'=>'你没有权限上传。'
	]);
}
if($Type == 'File' || $Type == 'S3'){
	if ($_FILES["file"]["error"] == UPLOAD_ERR_OK){
		if (!(
			($_FILES["file"]["type"] == "image/gif") || 
			($_FILES["file"]["type"] == "image/jpeg") || 
			($_FILES["file"]["type"] == "image/pjpeg") || 
			($_FILES["file"]["type"] == "image/png") || 
			($_FILES["file"]["type"] == "image/bmp")
		)){
			returnResult([
				'Code'=>-52,
				'Message'=>'图片格式错误！'
			]);
		}else if ($WebConfig['FuckRobot']['Image']['Open'] && $UserGroup['Type'] != 'Admin' && $_FILES["file"]["size"] > ($WebConfig['FuckRobot']['Image']['Size']*1024)){
			returnResult([
				'Code'=>-51,
				'Message'=>'大小超过限制！'
			]);
		}else{
			$g=explode('.',$_FILES["file"]["name"]);
			$b=$g[count($g)-1];
			unset($g[count($g)-1]);
			$h=implode('.',$g);
			
			if($Type == 'S3'){
				list($usec, $sec) = explode(" ", microtime());
				$filename = 'Upload/'.date('Y-m-d').'/Classbook_'.((float)$usec + (float)$sec).'_'.md5(RandString(2048)).'.'.$b;
				$s3Config = isset($WebConfig['Option']['S3']) ? $WebConfig['Option']['S3'] : [];
				list($success, $url, $error) = s3_upload_file($s3Config, $filename, $_FILES["file"]["tmp_name"], $_FILES["file"]["type"]);

				if($success){
					$Mysql->query("INSERT INTO `xlch_image` set `DirId` = '".$DirInfo['ID']."', `Url`='".daddslashes($url)."', `Name`='".daddslashes(htmlspecialchars($h))."', `UploadId`='".$UserInfo['ID']."', `AddDate` = '".date($DatetimeFormat)."'");
					returnResult([
						'Code'=>1,
						'Message'=>'上传成功'
					]);
				}else{
					returnResult([
						'Code'=>-3,
						'Message'=>'上传到 S3 兼容存储失败：'.$error
					]);
				}
			}else if($Type == 'File'){
				mkdir(RootDir.'/Upload/'.date('Y-m-d'),0777,true);
				
				list($usec, $sec) = explode(" ", microtime());
				$filename='/Upload/'.date('Y-m-d').'/Flandre-Studio.cn_'.((float)$usec + (float)$sec).'_'.md5(RandString(2048)).'.'.$b;
				
				if(move_uploaded_file($_FILES["file"]["tmp_name"],RootDir.$filename)){
					$Mysql->query("INSERT INTO `xlch_image` set `DirId` = '".$DirInfo['ID']."', `Url`='".daddslashes($filename)."', `Name`='".daddslashes(htmlspecialchars($h))."', `UploadId`='".$UserInfo['ID']."', `AddDate` = '".date($DatetimeFormat)."'");
					returnResult([
						'Code'=>1,
						'Message'=>'上传成功'
					]);
				}else{
					returnResult([
						'Code'=>-3,
						'Message'=>'由于服务器原因上传失败。'
					]);
				}
			}
		}
	}else{
		returnResult([
			'Code'=>-53,
			'Message'=>'上传错误：'.$_FILES["file"]["error"]
		]);
	}
}else if($Type == 'Url'){
	$Urls = $_POST['Urls'];
	
	if(!$Urls or !is_array($Urls)){
		returnResult([
			'Code'=>-52,
			'Message'=>'没有选择要上传的文件。'
		]);
	}
	$sql=[];
	foreach($Urls as $i => $row){
		if(substr($row,0,7) != 'http://' && substr($row,0,8) != 'https://' ){
			returnResult([
				'Code'=>-53,
				'Message'=>'Url地址错误！'.htmlspecialchars($row)
			]);
		}
		$sql[]="INSERT INTO `xlch_image` set `DirId` = '".$DirInfo['ID']."', `Url`='".daddslashes($row)."', `Name`='".daddslashes(date($DatetimeFormat))."', `UploadId`='".$UserInfo['ID']."', `AddDate` = '".date($DatetimeFormat)."'";
	}
	foreach($sql as $sql_){
		$Mysql->query($sql_);
	}
	returnResult([
		'Code'=>1,
		'Message'=>'上传成功'
	]);
}else{
	returnResult([
		'Code'=>-51,
		'Message'=>'没有选择要上传的文件。'
	]);
}
function returnResult($json){
	if($json['Code'] < 1) header('HTTP/1.1 500'); 
	exit(json_encode($json));
}
function s3_upload_file($config, $key, $file, $contentType){
	$endpoint = isset($config['endpoint']) ? trim($config['endpoint']) : '';
	$region = isset($config['region']) && $config['region'] !== '' ? trim($config['region']) : 'auto';
	$accessKey = isset($config['accessKey']) ? trim($config['accessKey']) : '';
	$secretKey = isset($config['secretKey']) ? trim($config['secretKey']) : '';
	$bucket = isset($config['bucket']) ? trim($config['bucket']) : '';
	$domain = isset($config['domain']) ? trim($config['domain']) : '';
	$pathStyle = !empty($config['pathStyle']);

	if($endpoint == '' || $accessKey == '' || $secretKey == '' || $bucket == ''){
		return [false, '', '请先在后台配置 Endpoint、Access Key、Secret Key 和 Bucket'];
	}
	if(substr($endpoint, 0, 7) != 'http://' && substr($endpoint, 0, 8) != 'https://'){
		$endpoint = 'https://' . $endpoint;
	}
	$endpoint = rtrim($endpoint, '/');
	$parts = parse_url($endpoint);
	if(!$parts || empty($parts['scheme']) || empty($parts['host'])){
		return [false, '', 'Endpoint 格式错误'];
	}

	$host = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
	$basePath = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
	$encodedKey = s3_uri_encode($key);
	if($pathStyle){
		$requestHost = $host;
		$canonicalUri = $basePath . '/' . rawurlencode($bucket) . '/' . $encodedKey;
		$url = $parts['scheme'] . '://' . $requestHost . $canonicalUri;
	}else{
		$requestHost = $bucket . '.' . $host;
		$canonicalUri = $basePath . '/' . $encodedKey;
		$url = $parts['scheme'] . '://' . $requestHost . $canonicalUri;
	}

	$payloadHash = hash_file('sha256', $file);
	$amzDate = gmdate('Ymd\THis\Z');
	$date = gmdate('Ymd');
	$scope = $date . '/' . $region . '/s3/aws4_request';
	$canonicalHeaders = 'host:' . $requestHost . "\n" . 'x-amz-content-sha256:' . $payloadHash . "\n" . 'x-amz-date:' . $amzDate . "\n";
	$signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
	$canonicalRequest = "PUT\n" . $canonicalUri . "\n\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
	$stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
	$signingKey = s3_signature_key($secretKey, $date, $region, 's3');
	$signature = hash_hmac('sha256', $stringToSign, $signingKey);
	$authorization = 'AWS4-HMAC-SHA256 Credential=' . $accessKey . '/' . $scope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

	$fp = fopen($file, 'rb');
	if(!$fp){
		return [false, '', '无法读取临时文件'];
	}
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_UPLOAD, true);
	curl_setopt($ch, CURLOPT_INFILE, $fp);
	curl_setopt($ch, CURLOPT_INFILESIZE, filesize($file));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Authorization: ' . $authorization,
		'Content-Type: ' . $contentType,
		'Host: ' . $requestHost,
		'x-amz-content-sha256: ' . $payloadHash,
		'x-amz-date: ' . $amzDate
	]);
	$response = curl_exec($ch);
	$errno = curl_errno($ch);
	$error = curl_error($ch);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	fclose($fp);

	if($errno){
		return [false, '', $error];
	}
	if($status < 200 || $status >= 300){
		return [false, '', 'HTTP '.$status.' '.$response];
	}

	$publicUrl = $domain ? rtrim($domain, '/') . '/' . $encodedKey : $url;
	return [true, $publicUrl, ''];
}
function s3_uri_encode($value){
	$parts = explode('/', $value);
	foreach($parts as $i=>$part){
		$parts[$i] = rawurlencode($part);
	}
	return implode('/', $parts);
}
function s3_signature_key($key, $date, $region, $service){
	$kDate = hash_hmac('sha256', $date, 'AWS4' . $key, true);
	$kRegion = hash_hmac('sha256', $region, $kDate, true);
	$kService = hash_hmac('sha256', $service, $kRegion, true);
	return hash_hmac('sha256', 'aws4_request', $kService, true);
}
