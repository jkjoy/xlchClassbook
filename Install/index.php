<?php
error_reporting(0);
header("Content-Type: text/html; charset=UTF-8");
header("Powered-By: Xlch-AdminPHP");
setcookie("xlch_token", '', time()-3600, '/');

function root_path($path = ''){
	return dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
}
function install_path($path = ''){
	return __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
}
function write_install_file($relativePath, $content, $displayPath){
	$path = root_path($relativePath);
	$dir = dirname($path);
	if(!is_dir($dir)){
		Logg('写入文件['.$displayPath.']失败，目录不存在：'.$dir);
		return false;
	}
	if(file_put_contents($path, $content) === FALSE){
		$firstError = error_get_last();
		if(is_file($path)){
			@chmod($path, 0666);
		}else{
			@chmod($dir, 0777);
		}
		if(file_put_contents($path, $content) === FALSE){
			$tmpPath = $path . '.tmp.' . md5(uniqid('', true));
			if(file_put_contents($tmpPath, $content) !== FALSE && @rename($tmpPath, $path)){
				return true;
			}
			if(is_file($tmpPath)){
				@unlink($tmpPath);
			}
			$error = error_get_last();
			Logg('写入文件['.$displayPath.']失败，目标路径：'.$path.($error ? '，'.$error['message'] : ($firstError ? '，'.$firstError['message'] : '，请检查权限')));
			return false;
		}
	}
	return true;
}

if(version_compare('7.0', PHP_VERSION, ">")) {
	die('请使用PHP 7.0 或更高的版本运行本程序！');
}
session_start();
$IsInstalled=is_file('Install.lock');
if($IsInstalled){
	if($_GET['step'] == 6 && $_SESSION['Tmp_Username'])
		$Step=6;
	else
		$Step=-1;
}else{
	$Step=isset($_GET['step']) ? (int)$_GET['step'] : 0;
}
function statusIcon($step){
	global $Step;
	if($step == $Step){
		echo 'fa-cog fa-spin';
	}else if($step < $Step){
		echo 'fa-check c-green';
	}else if($Step == -1){
		echo 'fa-close c-red';
	}else{
		echo 'fa-hourglass-half c-cyan';
	}
}
function checkfunc($f,$m = false) {
	if (function_exists($f)) {
		return '<font color="green">可用</font>';
	} else {
		if ($m == false) {
			return '<font color="black">不支持</font>';
		} else {
			return '<font color="red">不支持</font>';
		}
	}
}

function checkclass($f,$m = false) {
	if (class_exists($f)) {
		return '<font color="green">可用</font>';
	} else {
		if ($m == false) {
			return '<font color="black">不支持</font>';
		} else {
			return '<font color="red">不支持</font>';
		}
	}
}
function is_sqlite_available(){
	return class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers());
}
function random_database_name(){
	if(function_exists('random_bytes')){
		return 'classbook_' . bin2hex(random_bytes(16)) . '.sqlite';
	}
	return 'classbook_' . md5(RandString(128) . microtime(true)) . '.sqlite';
}
function RandString($length){
	$str = null;
	$strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
	$max = strlen($strPol)-1;
	
	for($i=0;$i<$length;$i++){
		$str.=$strPol[rand(0,$max)];
	}
	return $str;
}
function new_is_writeable($file) {
    if (is_dir($file)){
        $dir = $file;
        if ($fp = @fopen("$dir/test.txt", 'w')) {
            @fclose($fp);
            @unlink("$dir/test.txt");
            $writeable = 1;
        } else {
            $writeable = 0;
        }
    } else {
        if ($fp = @fopen($file, 'a+')) {
            @fclose($fp);
            $writeable = 1;
        } else {
            $writeable = 0;
        }
    }
 
    return $writeable;
}
function getServerSoft(){
	$s=strtolower($_SERVER['SERVER_SOFTWARE']);
	if(strpos($s,'iis') !== FALSE){
		return 'iis';
	}else if(strpos($s,'nginx') !== FALSE){
		return 'nginx';
	}else if(strpos($s,'apache') !== FALSE or strpos($s,'kangle') !== FALSE){
		return 'apache/kangle';
	}else{
		return '';
	}
}
function Logg($text,$type = false){
	/*
		2 = 开始进行
		0 = 闭合2
		1 = 普通输出
	*/
	if($type === false){
		if(!$text or strpos($text,'√')!==false){
			echo '<font color=green>'.(strpos($text,'√')!==false ? $text : '√').'</font>';
		}else{
			echo '<font color=red>×:'.$text.'</font>';
		}
		echo '</p>';
	}else if($type === 2){
		echo '<p>'.$text.'... ';
	}else{
		echo '<p><br>'.$text.'：</p>';
	}
}
function Install(){
	$dbType = (isset($_POST['Mysql_Type']) && $_POST['Mysql_Type'] == 'sqlite') ? 'sqlite' : 'mysql';
	Logg('检测环境配置',1);
	$Check=array(
		[
			'Name'=>'文件写入函数',
			'Is'=>function_exists('file_put_contents')
		],
		[
			'Name'=>'文件写入权限',
			'Is'=>(new_is_writeable(root_path('Upload')) && new_is_writeable(root_path()))
		],
		[
			'Name'=>'数据库配置目录写入权限',
			'Is'=>new_is_writeable(root_path('Core/WebApp/Config/Database'))
		],
		[
			'Name'=>'站点配置目录写入权限',
			'Is'=>new_is_writeable(root_path('Core/WebApp/Config/SysConfig'))
		],
		[
			'Name'=>'安装锁目录写入权限',
			'Is'=>new_is_writeable(install_path())
		],
		[
			'Name'=>'文件读取函数',
			'Is'=>function_exists('file_get_contents')
		],
		[
			'Name'=>'Curl网页访问模块',
			'Is'=>function_exists('curl_exec')
		],
		[
			'Name'=>'GD图形处理模块',
			'Is'=>function_exists('imagecreatefromjpeg')
		],
		[
			'Name'=>'数据库功能',
			'Is'=>($dbType == 'sqlite' ? is_sqlite_available() : function_exists('mysqli_connect'))
		]
	);
	foreach($Check as $row){
		Logg($row['Name'],2);
		if(!$row['Is']){
			Logg('功能缺失或未开启');
			return false;
		}else{
			Logg(0);
		}
	}
	
	
	Logg('检测信息配置',1);
	$Check=[
		[
			'Name'=>'站点名称',
			'Is'=>(strlen($_POST['WebConfig_WebName'])>=3 && strlen($_POST['WebConfig_WebName'])<=60),
			'Info'=>'站点名称必填，最短为3个字符，最长为30个字符'
		],
		[
			'Name'=>'班级密码',
			'Is'=>(!isset($_POST['WebConfig_CanRegister']) or (strlen($_POST['WebConfig_RegisterPassword'])>=3 && strlen($_POST['WebConfig_RegisterPassword'])<=60)),
			'Info'=>'班级密码必填，最短为3个字符，最长为30个字符'
		],
		[
			'Name'=>'管理员姓名',
			'Is'=>preg_match('/^[\x7f-\xff]{2,20}$/',$_POST['Admin_Username']),
			'Info'=>'姓名只能是中文。'
		],
		[
			'Name'=>'管理员密码',
			'Is'=>preg_match('/^[a-zA-Z0-9\_\.\!\@\#\$\%\^\&\*\(\)]{6,20}$/',$_POST['Admin_Password']),
			'Info'=>'密码格式错误，只能为数字字母下划线以及英文标点符号且长度在6~20位！'
		]
	];
	foreach($Check as $row){
		Logg($row['Name'],2);
		if(!$row['Is']){
			Logg($row['Info']);
			return false;
		}else{
			Logg(0);
		}
	}
	include(root_path('Core/WebApp/Function/Mysql/db.class.php'));
	
	Logg('写入数据库',1);
	Logg('连接数据库',2);
	$MysqlInfoArray = [
		'Type' => $dbType,
		'Ip' => isset($_POST["Mysql_IP"]) ? $_POST["Mysql_IP"] : '',
		'Port' => isset($_POST["Mysql_Port"]) ? $_POST["Mysql_Port"] : '',
		'Username' => isset($_POST["Mysql_Username"]) ? $_POST["Mysql_Username"] : '',
		'Password' => isset($_POST["Mysql_Password"]) ? $_POST["Mysql_Password"] : '',
		'Database' => isset($_POST["Mysql_Database"]) ? $_POST["Mysql_Database"] : '',
		'QZ' => 'xlch'
	];
	if($dbType == 'sqlite'){
		if(!is_dir(root_path('data'))){
			mkdir(root_path('data'), 0777, true);
		}
		if(!is_file(root_path('data/.htaccess'))){
			file_put_contents(root_path('data/.htaccess'), "Require all denied\r\nDeny from all\r\n");
		}
		if(!is_file(root_path('data/web.config'))){
			file_put_contents(root_path('data/web.config'), "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n<configuration><system.webServer><security><requestFiltering><hiddenSegments><add segment=\"data\" /></hiddenSegments></requestFiltering></security></system.webServer></configuration>");
		}
		$MysqlInfoArray['Ip'] = '';
		$MysqlInfoArray['Port'] = '';
		$MysqlInfoArray['Username'] = '';
		$MysqlInfoArray['Password'] = '';
		$MysqlInfoArray['Database'] = 'data/' . random_database_name();
	}
	$ConnectMysqlInfo = $MysqlInfoArray;
	if($dbType == 'sqlite'){
		$ConnectMysqlInfo['Database'] = root_path($MysqlInfoArray['Database']);
	}
	$Mysql=new DB($ConnectMysqlInfo);
	
	if(!$Mysql->link){
		Logg($Mysql->error());
		return false;
	}else{
		Logg(0);
	}
	
	Logg('安装数据库',1);
	include(root_path('Core/Tool/dbSyncTool.php'));
	$xlchclassbookSyncData = include(install_path('dbSync.data.php'));
	$syncData = $xlchclassbookSyncData['data'];
	$option = $xlchclassbookSyncData['option'];

	$dbSyncTool = new dbSyncTool($Mysql, $syncData, $option);
	$dbSyncTool->fix();
	foreach($dbSyncTool->log as $row){
		Logg('安装数据库',2);
		Logg($row . '√');
	}
	
	Logg('写入配置文件',1);
	Logg('保存数据库信息',2);
	$MysqlInfo="<?php\r\nreturn <<<FlandreStudio_JSON\r\n".json_encode($MysqlInfoArray, JSON_PRETTY_PRINT + JSON_UNESCAPED_UNICODE)."\r\nFlandreStudio_JSON;\r\n?>";
	if(!write_install_file('Core/WebApp/Config/Database/Database.php', $MysqlInfo, 'Core/WebApp/Config/Database/Database.php')){
		return false;
	}else{
		Logg(0);
	}
	
	Logg('保存网站配置文件',2);
	$WebConfig = json_decode(include(root_path('Core/WebApp/Config/SysConfig/Config.php')),true);
	if(!$WebConfig){
		$WebConfig = json_decode(include(root_path('Core/WebApp/Config/SysConfig/Config.php')),true);
	}
	
	$WebConfig['Info']['WebName']=$_POST['WebConfig_WebName'];
	$WebConfig['SEO']['Title']=$_POST['WebConfig_WebName'];
	$WebConfig['Option']['Register']=isset($_POST['WebConfig_CanRegister']);
	$WebConfig['Option']['RegisterPassword']=$_POST['WebConfig_RegisterPassword'];
	$WebConfig['SysCode']=md5(RandString(256));
	
	if(!write_install_file('Core/WebApp/Config/SysConfig/Config.php', "<?php\r\nreturn <<<FlandreStudio_JSON\r\n".json_encode($WebConfig,JSON_PRETTY_PRINT + JSON_UNESCAPED_UNICODE)."\r\nFlandreStudio_JSON;\r\n?>", 'Core/WebApp/Config/SysConfig/Config.php')){
		return false;
	}else{
		Logg(0);
	}
	
	Logg('配置管理员账户',1);
	if($Mysql->get_row('select * from xlch_user where ID = 1')){
		Logg('重置管理员用户名和密码',2);
		if($Mysql->query('update xlch_user set Username = "'.addslashes($_POST['Admin_Username']).'", Password = "'.addslashes($_POST['Admin_Password']).'", `Group` = "Admin" where ID = 1')){
			Logg(0);
		}else{
			Logg($Mysql->error());
			return false;
		}
	}else{
		Logg('创建管理员账户',2);
		
		$DefaultUserData=json_decode(include(root_path('Core/WebApp/Config/SysConfig/DefaultUserData.php')),true);
		
		$sql='INSERT INTO `xlch_user` set
			`Username`="'.addslashes($_POST['Admin_Username']).'" , 
			`Password`="'.addslashes($_POST['Admin_Password']).'", 
			`HeadUrl`="/Upload/Default/Head.png",
			`RegIP`="8.8.8.8", 
			`RegCity`="火星", 
			`Group`="Admin",
			`RegDate`="'.date('Y-m-d H:i:s').'",
			`UserData`="'.addslashes(json_encode($DefaultUserData)).'"';
		if($Mysql->query($sql)){
			Logg(0);
		}else{
			Logg($Mysql->error());
			return false;
		}
	}
	
	$_SESSION['Tmp_Username']=$_POST['Admin_Username'];
	$_SESSION['Tmp_Password']=$_POST['Admin_Password'];
	
	if(!is_dir(root_path('Upload/UserHead'))){
		mkdir(root_path('Upload/UserHead'), 0777, true);
	}
	
	Logg('放置安装锁',2);
	if(file_put_contents(install_path('Install.lock'),"这个文件是安装锁，如果您需要重新安装本程序，请删除该文件。") === FALSE){
		$error = error_get_last();
		Logg('写入文件[Install/Install.lock]失败，目标路径：'.install_path('Install.lock').($error ? '，'.$error['message'] : '，请检查权限'));
		return false;
	}else{
		Logg(0);
	}
	return true;
}
include(root_path('Core/AdminPHP/Config/SysConfig/Version.php'));
?>
<!-- Install Wizard -->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="cn">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=0.9, maximum-scale=0.9" />
		<title>班级同学录安装程序 | 第<?=$Step ?>步</title>
		<!-- Vendor CSS -->
		<link href="//lib.baomitu.com/fullcalendar/3.4.0/fullcalendar.css" rel="stylesheet">
		<link href="//lib.baomitu.com/animate.css/3.5.2/animate.min.css" rel="stylesheet">
		<link href="//lib.baomitu.com/limonte-sweetalert2/6.6.4/sweetalert2.min.css" rel="stylesheet">
		<link href="//lib.baomitu.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
		<link href="/assets/css/jquery.mCustomScrollbar.min.css" rel="stylesheet">
		<link href="//lib.baomitu.com/bootstrap-datetimepicker/4.17.47/css/bootstrap-datetimepicker.min.css" rel="stylesheet">
		<link href="//lib.baomitu.com/lightgallery/1.3.9/css/lightgallery.min.css" rel="stylesheet">
		<link href="//lib.baomitu.com/bootstrap-validator/0.5.3/css/bootstrapValidator.min.css" rel="stylesheet">
		<link href="//lib.baomitu.com/cropper/3.0.0-rc.1/cropper.min.css" rel="stylesheet">
		<!-- CSS -->
		<link href="/assets/css/app_1.min.css" rel="stylesheet">
		<link href="/assets/css/app_2.min.css" rel="stylesheet">
		<link href="/assets/css/loading.css" rel="stylesheet">
		<script src="//lib.baomitu.com/jquery/1.12.4/jquery.js"></script>
	</head>
	<body>
		<div id="loading">
			<div id="loading1">
				<div class="block"></div>
				<div class="block"></div>
				<div class="block"></div>
				<div class="block"></div>
				<div class="section-left"></div>
				<div class="section-right"></div>
			</div>
		</div>
		<section id="page">
			<header id="header" class="clearfix" data-ma-theme="lightblue">
				<ul class="h-inner">
					<li class="hi-trigger ma-trigger" data-ma-action="sidebar-open" data-ma-target="#sidebar">
						<div class="line-wrap">
							<div class="line top"></div>
							<div class="line center"></div>
							<div class="line bottom"></div>
						</div>
					</li>
					<li class="hi-logo">
						<a href="index.html">班级同学录 - 安装程序</a>
					</li>
				</ul>
			</header>
			<section id="main">
				<aside id="sidebar" class="sidebar c-overflow">
					<div class="s-profile">
						<a href="#" data-ma-action="profile-menu-toggle" style="background: #66ccff;">
							<div class="sp-pic">
								<img src="/Upload/Default/Head.png" alt="">
							</div>
							<div class="sp-info">
								班级同学录安装向导
								<i class="fa fa-caret-down"></i>
							</div>
						</a>
						<ul class="main-menu">
							<li>
								<a href="?step=0"><i class="fa fa-home"></i> 安装说明</a>
							</li>
							<li>
								<a href="?step=2"><i class="fa fa-check-square-o"></i> 配置检测</a>
							</li>
							<li>
								<a href="?step=4"><i class="fa fa-cog"></i> 信息配置</a>
							</li>
							<li>
								<a href="/"><i class="fa fa-link"></i> 网站首页</a>
							</li>
						</ul>
					</div>
					<ul class="main-menu">
						<li>
							<a <?=(0 < $Step ? 'href="?step=0"' : '')?>><i class="fa <?=statusIcon(0);?>"></i> 程序说明</a>
						</li>
						<li>
							<a <?=(1 < $Step ? 'href="?step=1"' : '')?>><i class="fa <?=statusIcon(1);?>"></i> 使用协议</a>
						</li>
						<li>
							<a <?=(2 < $Step ? 'href="?step=2"' : '')?>><i class="fa <?=statusIcon(2);?>"></i> 配置检测</a>
						</li>
						<li>
							<a <?=(3 < $Step ? 'href="?step=3"' : '')?>><i class="fa <?=statusIcon(3);?>"></i> 伪静态配置</a>
						</li>
						<li>
							<a <?=(4 < $Step ? 'href="?step=4"' : '')?>><i class="fa <?=statusIcon(4);?>"></i> 信息配置</a>
						</li>
						<li class="">
							<a <?=(5 < $Step ? 'href="?step=5"' : '')?>><i class="fa <?=statusIcon(5);?>"></i> 安装完毕</a>
						</li>
						<li>
							<a href="?"><i class="fa fa-power-off c-red"></i> 取消安装</a>
						</li>
					</ul>
				</aside>
				<section id="content">
					<div class="container">
						<div class="row">
							<div class="col-md-12">
							<?php switch($Step){
								case 0: ?>
								<div class="card">
									<div class="card-header bgm-blue">
										<h2>hello~ 欢迎使用班级同学录！</h2>
									</div>
									<div class="card-body card-padding">
										<p>安装向导将帮助您完成运行环境检测、数据库连接、站点配置和管理员账户创建。</p>
										<br>
										<p>班级同学录提供个人主页、同学录、相册、留言板等功能。</p>
										<p>当前版本支持 PHP 8.x、MySQL 和 SQLite。</p>
										<br>
										<p>同学录包含个人主页、同学录、相册、留言板三大功能。</p>
										<br>
										<p>在【个人主页】您可以填写您的个人信息，定制个性化主页。</p>
										<p>在【同学录】将会展示全班每个人的主页。</p>
										<p>【相册】功能能让您可以自由的上传图片并让同学查看。所有的照片保存期限都是永久的，不像QQ那样过段时间换台手机就没了。</p>
										<p>我们努力将功能做的极简化、精致化，目的是让大家能够更高效的使用，不使部分功能荒废。</p>
										<br>
										<p>安装完成后，您可以进入后台继续调整站点配置。</p>
										<h4 class="c-pink">注：如果您的数据库中已经存有同学录数据，将会沿用旧数据。</h4>
										<br>
										<a href="?step=<?=($Step+1)?>" class="btn btn-block bgm-green">下一步 →</a>
									</div>
								</div>
								<div class="card">
									<div class="card-header bgm-cyan">
										<h2>版本信息 —— 当前版本:<?=$Version ?> (<?=$Version_?>)</h2>
									</div>
									<div class="card-body card-padding">
										<p>请根据 README 文档确认运行环境和部署配置。</p>
									</div>
								</div>
								<?php break; ?>
								<?php case 1: ?>
								<div class="card">
									<div class="card-header bgm-cyan">
										<h2>使用协议</h2>
									</div>
									<div class="card-body card-padding">
										<p>请确认您拥有当前站点目录的写入权限，并已准备好数据库连接信息。</p>
										<p>继续安装表示您同意在当前目录写入配置文件和安装锁文件。</p>
										<a href="?step=<?=($Step+1)?>" class="btn btn-block bgm-green">同意 →</a>
									</div>
								</div>
								<?php break; ?>
								<?php case 2: ?>
								<div class="card">
									<div class="card-header bgm-green">
										<h2>配置检测</h2>
									</div>
									<div class="table-responsive">
										<table class="table table-striped">
											<thead>
												<tr>
													<th style="width:20%">函数检测</th>
													<th style="width:15%">需求</th>
													<th style="width:15%">当前</th>
													<th style="width:50%">用途</th>
												</tr>
											</thead>
											<tbody>
												<tr>
													<td>PHP 7.0或以上</td>
													<td>必须</td>
													<td><?php echo phpversion(); ?></td>
													<td>PHP版本支持</td>
												</tr>
												<tr>
													<td>mysqli_connect()</td>
													<td>MySQL 必须</td>
													<td><?php echo checkfunc('mysqli_connect',true); ?></td>
													<td>连接 MySQL 数据库</td>
												</tr>
												<tr>
													<td>PDO SQLite</td>
													<td>SQLite 必须</td>
													<td><?php echo is_sqlite_available() ? '<font color="green">可用</font>' : '<font color="red">不支持</font>'; ?></td>
													<td>连接 SQLite 数据库</td>
												</tr>
												<tr>
													<td>imagecreatefromjpeg()</td>
													<td>必须</td>
													<td><?php echo checkfunc('imagecreatefromjpeg',true); ?></td>
													<td>处理图片</td>
												</tr>
												<tr>
													<td>curl_exec()</td>
													<td>必须</td>
													<td><?php echo checkfunc('curl_exec',true); ?></td>
													<td>抓取网页</td>
												</tr>
												<tr>
													<td>file_get_contents()</td>
													<td>必须</td>
													<td><?php echo checkfunc('file_get_contents',true); ?></td>
													<td>读取文件</td>
												</tr>
												<tr>
													<td>目录写入权限</td>
													<td>必须</td>
													<td><?php if (new_is_writeable(root_path('Upload')) && new_is_writeable(root_path())) { echo '<font color="green">可用</font>'; } else { echo '<font color="red">不支持</font>'; } ?></td>
													<td>上传图片</td>
												</tr>
												<tr>
													<td>数据库配置目录写入权限</td>
													<td>必须</td>
													<td><?php if (new_is_writeable(root_path('Core/WebApp/Config/Database'))) { echo '<font color="green">可用</font>'; } else { echo '<font color="red">不支持</font>'; } ?></td>
													<td>保存数据库连接信息</td>
												</tr>
												<tr>
													<td>站点配置目录写入权限</td>
													<td>必须</td>
													<td><?php if (new_is_writeable(root_path('Core/WebApp/Config/SysConfig'))) { echo '<font color="green">可用</font>'; } else { echo '<font color="red">不支持</font>'; } ?></td>
													<td>保存网站配置</td>
												</tr>
												<tr>
													<td>安装目录写入权限</td>
													<td>必须</td>
													<td><?php if (new_is_writeable(install_path())) { echo '<font color="green">可用</font>'; } else { echo '<font color="red">不支持</font>'; } ?></td>
													<td>写入安装锁</td>
												</tr>
												<tr>
													<td>file_put_contents()</td>
													<td>必须</td>
													<td><?php echo checkfunc('file_put_contents',true); ?></td>
													<td>写入文件、保存配置等</td>
												</tr>
												<tr>
													<td>fsockopen()</td>
													<td>推荐</td>
													<td><?php echo checkfunc('fsockopen'); ?></td>
													<td>发送邮件</td>
												</tr>
												<tr>
													<td>ZipArchive</td>
													<td>推荐</td>
													<td><?php echo checkclass('ZipArchive'); ?></td>
													<td>Zip 解包和压缩</td>
												</tr>
											</tbody>
										</table>
									</div>
									<div class="card-body card-padding">
										<h4>！ 如果以上有任何一项“<font color=red>必须</font>”项目不支持，可能会导致程序无法使用，建议您更换网站空间或者联系服务商协助解决。</h4>
										<h4>！ 如果以上有任何一项“<font color=blue>推荐</font>”项目不支持，可能会导致程序功能缺失。</h4>
										
										<a href="?step=<?=($Step+1)?>" class="btn btn-block bgm-green">下一步 →</a>
									</div>
								</div>
								<?php break; ?>
								<?php case 3: ?>
								<div class="card">
									<div class="card-header bgm-purple">
										<h2>伪静态配置</h2>
									</div>
									<div class="card-body card-padding">
										<center>
											<h1><i class="fa fa-edit c-lightgreen fa-5x"></i></h1>
											<p>AdminPHP框架支持伪静态，开启后在地址栏显示的地址会更加美观。</p>
											<p>如果您的空间支持伪静态，建议您开启。</p>
										</center>
										<?php include(root_path('Core/AdminPHP/Function/SysFunction/SysFunction.php')); ?>
										<?php include(root_path('Core/AdminPHP/Config/Rewrite/Url.php')); ?>
										<?php include(root_path('Core/AdminPHP/Content/Url/Url.php')); ?>
										<hr></hr>
										<h3>您的服务器软件是：<?=$_SERVER['SERVER_SOFTWARE'];?></h3>
										<h3>伪静态配置状态　：<font id="rewriteStatus"></font><a id="refushRewriteStatus" class="btn bgm-blue">刷新</a></h3>
										<?php
										switch(getServerSoft()){
											case 'iis':
											copy(install_path('rewrite/web.config'), root_path('web.config'));
										?>
											<h4>安装程序已经自动将"web.config"文件放置到根目录。</br><b><font color=red>如果您未安装<a href="https://www.iis.net/downloads/microsoft/url-rewrite" target="_blank">IIS Rewrite(点击下载)</a>扩展，请点击链接进行下载安装。<font color=red></b></h4>
										<?php
											break;
											case 'apache/kangle':
											copy(install_path('rewrite/.htaccess'), root_path('.htaccess'));
										?>
											<h4>安装程序已经自动将".htaccess"文件放置到根目录。一般情况下，伪静态已经成功配置。</h4>
										<?php
											break;
											case 'nginx':
										?>
											<h4>nginx暂不支持自动配置伪静态，请您在下方复制nginx伪静态配置文件进行手动设置。</h4>
										<?php
											break;
										} 
										?>
										<br>
										
										<hr></hr>
										
										<p class="c-purple">如果自动配置失败，或者不支持自动配置，请您手动配置：</p>
										
										<br>
										
										<p class="c-blue">Nginx/Tengine：</p>
										<textarea rows=5 class="form-control"><?=file_get_contents(install_path('rewrite/nginx.txt'))?></textarea>
										<br>
										<br>
										<p class="c-blue">Apache/Kangle (.htaccess)：</p>
										<textarea rows=5 class="form-control"><?=file_get_contents(install_path('rewrite/.htaccess'))?></textarea>
										<br>
										<br>
										<p class="c-blue">IIS (web.config)：</p>
										<textarea rows=5 class="form-control"><?=file_get_contents(install_path('rewrite/web.config'))?></textarea>
										<br>
										<br>
										以上文件您可以在“Install/rewrite”文件夹找到。
										<script>
										var refushRewriteStatus=function(){
											$('#rewriteStatus').attr('class','c-blue').html('刷新中...');
											if(<?=(!$Rewrite ? 'true' : 'false')?>){
												$('#rewriteStatus').attr('class','c-green').html('不使用伪静态');
												$('#stepBuuton').attr('class','btn btn-block bgm-green').html('跳过，不使用伪静态 →');
											}else{
												$.ajax({
													url:'<?=U('func','phpinfo','phpinfo');?>',
													dataType:'text',
													success:function (){
														$('#rewriteStatus').attr('class','c-green').html('配置正确');
														$('#stepBuuton').attr('class','btn btn-block bgm-green').html('下一步 →');
													},
													error:function (){
														$('#rewriteStatus').attr('class','c-red').html('配置错误');
														$('#stepBuuton').attr('class','btn btn-block bgm-orange').attr('href','?step=4&rewrite=disable').html('跳过，不使用伪静态 →');
													}
												});
											}
										}
										$('#refushRewriteStatus').click(refushRewriteStatus);
										refushRewriteStatus();
										</script>
										<a id="stepBuuton" href="?step=<?=($Step+1)?>" class="btn btn-block bgm-white">请稍等...</a>
									</div>
								</div>
								<?php break; ?>
								<?php case 4: 
								if($_GET['rewrite'] == 'disable'){
									$rewriteConfig=<<<Xlch88
<?php
//是否使用了伪静态文件
//如果没有使用的话地址会是这样www.****.com/index.php?s=**********
//如果使用的话地址会是这样www.****.com/*********
\$Rewrite=false;


//URL分隔符
define('Url_Header','');
define('Url_Explode','/');
define('Url_Footer','.html');
Xlch88;
									write_install_file('Core/AdminPHP/Config/Rewrite/Url.php', $rewriteConfig, 'Core/AdminPHP/Config/Rewrite/Url.php');
								}
								?>
								<div class="card">
									<div class="card-header bgm-pink">
										<h2>配置信息</h2>
									</div>
									<div class="card-body card-padding">
										<form class="form-horizontal" method=post action="?step=<?=($Step+1)?>">
											<h3>1.数据库配置</h3>
											<div class="form-group">
												<label for="Mysql_Type" class="col-sm-2 control-label">数据库类型</label>
												<div class="col-sm-10">
													<div class="fg-line">
														<div class="select">
															<select class="form-control" id="Mysql_Type" name="Mysql_Type">
																<option value="mysql">MySQL / MariaDB</option>
																<option value="sqlite">SQLite</option>
															</select>
														</div>
													</div>
													<p class="help-block">选择 SQLite 时，安装程序会自动创建 data 目录并生成随机文件名的数据库。</p>
												</div>
											</div>
											<div class="form-group mysql-config">
												<label for="Mysql_IP" class="col-sm-2 control-label">数据库地址</label>
												<div class="col-sm-10">
													<input type="text" class="form-control" required="required" id="Mysql_IP" name="Mysql_IP" value="localhost">
												</div>
											</div>
											<div class="form-group mysql-config">
												<label for="Mysql_Port" class="col-sm-2 control-label">数据库端口</label>
												<div class="col-sm-10">
													<input type="number" class="form-control" required="required" id="Mysql_Port" name="Mysql_Port" value="3306">
												</div>
											</div>
											<div class="form-group mysql-config">
												<label for="Mysql_Username" class="col-sm-2 control-label">数据库用户名</label>
												<div class="col-sm-10">
													<input type="text" class="form-control" required="required" id="Mysql_Username" name="Mysql_Username" value="">
												</div>
											</div>
											<div class="form-group mysql-config">
												<label for="Mysql_Password" class="col-sm-2 control-label">数据库密码</label>
												<div class="col-sm-10">
													<input type="text" class="form-control" required="required" id="Mysql_Password" name="Mysql_Password" value="">
												</div>
											</div>
											<div class="form-group mysql-config">
												<label for="Mysql_Database" class="col-sm-2 control-label">数据库名</label>
												<div class="col-sm-10">
													<input type="text" class="form-control" required="required" id="Mysql_Database" name="Mysql_Database" value="">
												</div>
											</div>
											<script>
											$(function(){
												var refreshDbType = function(){
													var isSqlite = $('#Mysql_Type').val() == 'sqlite';
													$('.mysql-config').toggle(!isSqlite).find('input').prop('disabled', isSqlite);
												};
												$('#Mysql_Type').on('change', refreshDbType);
												refreshDbType();
											});
											</script>
											<hr></hr>
											<h3>2.网站信息配置</h3>
											<div class="form-group">
												<label for="WebConfig_WebName" class="col-sm-2 control-label">站点名称</label>
												<div class="col-sm-10">
													<input type="text" class="form-control" required="required" name="WebConfig_WebName" id="WebConfig_WebName" value="班级同学录">
												</div>
											</div>
											<div class="form-group">
												<div class="col-sm-offset-2 col-sm-10">
													<div class="checkbox">
														<label>
															<input type="checkbox" value="Checked" name="WebConfig_CanRegister" checked=checked>
															<i class="input-helper"></i>
															启用注册功能（关闭后只能由管理员在后台手动注册）
														</label>
													</div>
												</div>
											</div>
											<div class="form-group">
												<label for="WebConfig_RegisterPassword" class="col-sm-2 control-label">班级密码</label>
												<div class="col-sm-10">
													<div class="fg-line">
														<input type="text" class="form-control input-sm" name="WebConfig_RegisterPassword" id="WebConfig_RegisterPassword" placeholder="注册时需要填写" value="">
													</div>
												</div>
											</div>
											<div class="form-group">
												<div class="col-sm-offset-2 col-sm-10">
													<div class="checkbox">
														更多功能，请在安装完毕后进入后台进行配置。
													</div>
												</div>
											</div>
											<hr></hr>
											<h3>3.管理员信息配置</h3>
											<div class="form-group">
												<label for="Admin_Username" class="col-sm-2 control-label">管理员姓名</label>
												<div class="col-sm-10">
													<div class="fg-line">
														<input type="text" class="form-control input-sm" required="required" name="Admin_Username" id="Admin_Username" value="admin">
													</div>
												</div>
											</div>
											<div class="form-group">
												<label for="Admin_Password" class="col-sm-2 control-label">管理员密码</label>
												<div class="col-sm-10">
													<div class="fg-line">
														<input type="text" class="form-control input-sm" required="required" name="Admin_Password" id="Admin_Password" value="<?=RandString(6);?>">
													</div>
												</div>
											</div>
											<button type="submit" class="btn btn-block bgm-red">开始安装 →</button>
										</form>
									</div>
								</div>
								<?php break; ?>
								<?php case 5: ?>
								<div class="card">
									<div class="card-header bgm-green">
										<h2>开始安装</h2>
									</div>
									<div class="card-body card-padding">
										<?php
										$Install=Install();
										?>
										<?php if($Install){ ?><a href="?step=<?=($Step+1)?>" class="btn btn-block bgm-green">下一步 →</a><?php } ?>
									</div>
								</div>
								<?php break; ?>
								<?php case 6: ?>
								<div class="card">
									<div class="card-header bgm-blue">
										<h2>安装完毕</h2>
									</div>
									<div class="card-body card-padding">
										<center>
											<h1><i class="fa fa-check c-green fa-5x"></i></h1>
											<p>恭喜您，安装完毕！</p>
											<p>感谢您使用本程序！</p>
											<br>
											<p><a href="/" class="btn btn-blue btn-lg bgm-green">访问首页</a></p>
										</center>
										<hr></hr>
										<h3>以下是您刚刚设置的信息：</h3>
										<p>管理员用户名：	<?=$_SESSION['Tmp_Username']?></p>
										<p>管理员密码　：	<?=$_SESSION['Tmp_Password']?></p>
										<?php $_SESSION=[]; ?>
										<p class="c-pink">请妥善保管您的密码，以免发生安全隐患。</p>
										<hr></hr>
										<p>安装完成后请及时删除或限制访问 Install 目录。</p>
									</div>
								</div>
								<?php break; ?>
								<?php case -1: ?>
								<div class="card">
									<div class="card-header bgm-pink">
										<h2>安装锁</h2>
									</div>
									<div class="card-body card-padding text-center">
										<h1><i class="fa fa-lock c-red fa-5x"></i></h1>
										<p>emmmmmm，看上去似乎已经安装过了。</p>
										<p>如果你想重新安装/重置密码，请删除“<font class="c-blue">Install/Install.lock</font>”。</p>
										<p>否则，在安装锁开启的状态下，您无法进行重新安装等操作。</p>
									</div>
								</div>
								<?php break; ?>
								<?php default: ?>
								<div class="card">
									<div class="card-header bgm-red">
										<h2>错误</h2>
									</div>
									<div class="card-body card-padding">
										未定义操作。
									</div>
								</div>
								<?php break; ?>
							<?php } ?>
							</div>
						</div>
					</div>
				</section>
			</section>
			<footer id="footer">
				班级同学录安装程序
				<ul class="f-menu">	
					<li><a href="?step=0">安装说明</a></li>
					<li><a href="?step=2">配置检测</a></li>
				</ul>
			</footer>
		</section>
			<script src="//lib.baomitu.com/twitter-bootstrap/3.3.7/js/bootstrap.min.js"></script>
			<script src="//lib.baomitu.com/moment.js/2.18.1/moment.min.js"></script>	
			<script src="/assets/js/waves.min.js"></script>	
			<script src="/assets/js/bootstrap-growl.min.js"></script>	
			<script src="//lib.baomitu.com/limonte-sweetalert2/6.6.4/sweetalert2.min.js"></script>
			<script src="/assets/js/jquery.mCustomScrollbar.concat.min.js"></script>	
			<script src="//lib.baomitu.com/lightgallery/1.3.9/js/lightgallery.min.js"></script>	
			<script src="/assets/js/bootstrap-datetimepicker.zh-CN.js"></script>	
			<script src="//lib.baomitu.com/bootstrap-datetimepicker/4.17.47/js/bootstrap-datetimepicker.min.js"></script>	
			<script src="//lib.baomitu.com/bootstrap-validator/0.5.3/js/bootstrapValidator.js"></script>
			<script src="//lib.baomitu.com/cropper/3.0.0-rc.1/cropper.min.js"></script>	
			<script src="/assets/js/webuploader.js"></script>			
			<script src="/assets/kindeditor/kindeditor-min.js"></script>	
			<script src="/assets/kindeditor/zh_CN.js"></script>			
			<!-- Placeholder for IE9 -->	
			<!--[if IE 9 ]>			
			<script src="vendors/bower_components/jquery-placeholder/jquery.placeholder.min.js"></script>	
			<![endif]-->	
			<script src="/assets/js/app.min.js"></script>
	</body>
</html>
