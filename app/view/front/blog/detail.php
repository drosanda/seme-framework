<div id="page-content">
	<div class="content-header">
		<div class="header-section">
			<h1>
				<i class="gi gi-show_big_thumbnails"></i> Static Content Example
                <br>
                <small>This is the default content in app/view/front/blog/detail.php</small>
			</h1>
		</div>
	</div>
	<ul class="breadcrumb breadcrumb-top">
		<li><a href="<?=base_url()?>">Home</a></li>
		<li><a href="<?=base_url('blog')?>">Blog</a></li>
		<li><a href="#">Detail</a></li>
	</ul>
	<div class="block full block-alt-noborder">
		<h3 class="sub-header text-center">
            <strong>Article Detail Example for ID: <?=$id?></strong>
        </h3>
		<div class="row">
			<div class="col-md-10 col-md-offset-1 col-lg-8 col-lg-offset-2">
				<article>
					<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas ultrices, justo vel imperdiet gravida, urna ligula hendrerit nibh, ac cursus nibh sapien in purus.</p>
				</article>
			</div>
		</div>
	</div>
</div>