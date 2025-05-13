<!DOCTYPE html>
<html class="no-js" lang="en">
	<?php $this->getThemeElement("page/html/head",$__forward); ?>
	<body>
		<div id="page-wrapper" class="page-loading">
			<div class="preloader themed-background">
				<h1 class="push-top-bottom text-light text-center" >
                    <strong>Seme Framework</strong>
                    <br>
                    <small>Loading...</small>
                </h1>
				<div class="inner">
					<h3 class="text-light visible-lt-ie10"><strong>Loading..</strong></h3>
					<div class="preloader-spinner hidden-lt-ie10"></div>
				</div>
			</div>
			
            <div id="page-container" class="sidebar-mini sidebar-visible-lg-mini">
				<div id="main-container">
					<?php $this->getThemeContent(); ?>
					<?php $this->getThemeElement("page/html/footer",$__forward); ?>
				</div>
			</div>
		</div>
		<div id="modal-preloader" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
			<div class="modal-dialog slideInDown animated">
				<div class="modal-content" style="background-color: #000;color: #fff;">
					<div class="modal-header text-center" style="border: none;">
						<h2 class="modal-title"><i class="fa fa-spin fa-refresh"></i> Loading...</h2>
					</div>
				</div>
			</div>
		</div>
		<?php $this->getJsFooter(); ?>
		<script>
			$(document).ready(function(e){
				<?php $this->getJsReady(); ?>
			});
			<?php $this->getJsContent(); ?>
		</script>
	</body>
</html>