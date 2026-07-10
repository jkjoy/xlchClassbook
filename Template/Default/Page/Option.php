<?php
if(!defined("AdminPHP")) exit('<h1 style="color:red">Bad Reuest!</h1> <hr /> Powered By Xlch-AdminPHP');
?>
<?php include(T('Page/Header'));?>
<div class="pmb-block">
	<form action="<?=U('Page','Option',$Type,'Save')?>" method="post" id="OptionFrom" class="form-horizontal">
		<!-- 暂时弃用 <div class="form-group">
			<label class="col-sm-2 control-label">主页特效</label>
			<div class="col-sm-10">
				<?php foreach(['关闭','流星','樱花','下雪','下雨'] as $x=>$row){ ?>
				<label class="radio radio-inline m-r-20">
					<input type="radio" name="PageJS" <?=($UInfo['UserData']['Public']['PageJs'] == $x ? 'checked' : '')?> value="<?=$x?>">
					<i class="input-helper"></i>
					<?=$row?>
				</label>
				<?php } ?>
			</div>
		</div>-->
		<div class="form-group">
			<label class="col-sm-2 control-label">资料卡背景</label>
			<div class="col-sm-10">
				<?php foreach($UserCardBg as $x=>$row){ ?>
				<div class="radio m-b-15">
					<label>
						<input type="radio" <?=($UInfo['UserData']['Public']['CardBg'] == $x ? 'checked' : '')?> name="CardBg" value="<?=$x?>">
						<i class="input-helper"></i>
						<img src="<?=$row?>" class="img-responsive">
					</label>
				</div>
				<?php } ?>
			</div>
		</div>
		<div class="form-group">
			<div class="col-sm-offset-2 col-sm-10">
				<button type="submit" class="btn btn-primary btn-lg btn-block waves-effect">保存</button>
			</div>
		</div>
	</form>
</div>
<?php include(T('Page/Footer'));?>
