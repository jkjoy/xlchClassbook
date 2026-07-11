<?php
if(!defined("AdminPHP")) exit('<h1 style="color:red">Bad Reuest!</h1> <hr /> Powered By Xlch-AdminPHP');
?>
<?php include(T('_Common/Header'));?><div class="container">
	<div class="block-header">
		<h2>关于</h2>
	</div>
	<div class="card">
		<div class="card-header bgm-cyan">
			<h2>本站信息</h2>
		</div>
		<div class="card-body card-padding row">
			<div class="col-md-12">
				<div class="page-header">
					<h1>本站信息</h1>
				</div>
				以下是本站管理员信息以及班级交流群。				<div class="table-responsive">
					<table class="table table-condensed">
						<tbody>
							<tr>
								<th scope="row">本站站长</th>
								<td><i class="fa fa-qq fa-fw"></i> <?=$WebConfig['AdminInfo']['QQ']?> <a target="_blank" href="http://wpa.qq.com/msgrd?v=3&uin=<?=$WebConfig['AdminInfo']['QQ']?>&site=qq&menu=yes"><img border="0" src="http://wpa.qq.com/pa?p=2:<?=$WebConfig['AdminInfo']['QQ']?>:51" alt="点击这里给我发消息" title="点击这里给我发消息"></a></td>
								<td><i class="fa fa-envelope fa-fw"></i> <?=$WebConfig['AdminInfo']['Email']?></td>
								<td><i class="fa fa-wechat fa-fw"></i> <?=$WebConfig['AdminInfo']['WeChat']?></td>
							</tr>
							<tr>
								<th scope="row">本站交流群</th>
								<td><i class="fa fa-qq fa-fw"></i> <?=$WebConfig['Group']['QQ']?></td>
								<td><i class="fa fa-link fa-fw"></i> <a href="<?=$WebConfig['Group']['QQUrl']?>">点击加群</a></td>
							</tr>
							<?php foreach($I['AdminList'] as $row){ ?>
							<tr>
								<th scope="row">[管理员] <?=$row['Username']?></th>
								<td><i class="fa fa-qq fa-fw"></i> <?=$row['UserData']['SocialAccount']['QQ']?> <a target="_blank" href="http://wpa.qq.com/msgrd?v=3&uin=<?=$row['UserData']['SocialAccount']['QQ']?>&site=qq&menu=yes"><img border="0" src="http://wpa.qq.com/pa?p=2:<?=$row['UserData']['SocialAccount']['QQ']?>:51" alt="点击这里给我发消息" title="点击这里给我发消息"></a></td>
								<td><i class="fa fa-envelope fa-fw"></i> <?=$row['UserData']['ContactMe']['Email']?></td>
								<td><i class="fa fa-wechat fa-fw"></i> <?=$row['UserData']['SocialAccount']['WeChat']?></td>
							</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
<?php include(T('_Common/Footer'));?>
