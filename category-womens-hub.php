
<?php 
	get_header(); 
	$term = get_queried_object();
	$args = array(
		'category_name'=> $term->slug,
		'post_type'         => 'post',
		'posts_per_page'    => 15,
		'paged'             => $paged,
		'orderby'           => 'date',
		'order'             => 'DESC'
	);
	$data = new WP_Query($args);
	$posts = $data->posts;
?>
<div id="show-ads"> </div>
	<section id="category-page">
        <div class="breadcrumb">
            <ul>
                <li><a href="/">Home</a></li>
                <li>></li>
                <li> <?= get_the_archive_title() ?> </li>
            </ul>
        </div>
        <div class="category-upper">

        </div>

        <header>
            <h1> <?= get_the_archive_title(); ?> </h1>
        </header>
       
        <div class="news">
			
		<div class="row">
			<?php
				foreach ($posts as $post): 
				?>
			<div class="col-sm-3"> <a href="<?= get_the_permalink( $post->ID ); ?>"> <?= get_thumbnail(['post_id'=>$post->ID, 'size'=>'pdf_thumbnail']) ?> </a> </div>
			<?php endforeach; ?>
		</div>
			
            <div class="pagination">
                <?php echo paginate_links(['mid_size'=>2, 'total' => $data->max_num_pages, 'next_text'=>'»', 'prev_text'=>'«' ]); ?>
                <!-- <a href=""><i class="fa fa-angle-double-left"></i> Older Posts</a> -->
            </div>
        </div>
    </section>

<?php 
	get_footer();
?>