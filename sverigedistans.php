<?php
/*
	Plugin Name: SverigeDistans plugin
	Version: 1.0
	Plugin URI: http://dev.laapotti.com/sverigedistans.tv/
	Description: Plugin f&ouml;r att lista SverigeDistans s&auml;ndningar som TV-tabl&aring;
	Author: Fredrik Laapotti
	Author URI: http://dev.laapotti.com
*/

add_action('widgets_init', 'svd_widget_init');
function svd_widget_init() {
	register_widget('svd_widget');
}

class svd_widget extends WP_Widget {
	public function __construct() {
		$widget_options = array(
			'classname'	=> 'svd_widget',
			'description'	=> 'SverigeDistans widget'
		);
		parent::__construct('svd_widget', 'SverigeDistans', $widget_options);
	}

	public function form($instance) {
		$instance = wp_parse_args((array)$instance, array('title' => ''));
		$title = $instance['title'];
		$listing = $instance['listing'];
		?>

		<p>
		<label for="<?php echo $this->get_field_id('title'); ?>">Title:
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo attribute_escape($title); ?>"/>
		</label>
		</p>

		<p>
		<label for="<?php echo $this->get_field_id('text'); ?>">Listing:
			<select class="widefat" id="<?php echo $this->get_field_id('listing'); ?>" name=<?php echo $this->get_field_name('listing'); ?> type="text">
				<option value="Today" <?php echo ($listing == 'Today') ? 'selected' : ''; ?>>Today</option>
				<option value="Week5" <?php echo ($listing == 'Week5') ? 'selected' : ''; ?>>Week (5 days)</option>
				<option value="Month" <?php echo ($listing == 'Month') ? 'selected' : ''; ?>>Month</option>
			</select>
		</label>
		</p>

		<?php
	}

	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];
		$instance['listing'] = $new_instance['listing'];
		return $instance;
	}

	public function widget($args, $instance) {
		extract($args, EXTR_SKIP);
		$title		= empty($instance['title'])	? '' : apply_filters('widget_title', $instance['title']);
		$listing	= empty($instance['listing'])	? '' : $instance['listing'];

		// Begin database queries
		$temp = $wp_query;
		$wp_query = null;
		$wp_query = new WP_Query();
		$wp_query->query(array(
			'post_type'		=> 'post',
			'meta_query'		=> array(
				'_svd_sandning_date'	=> array(
					'key'	=> '_svd_sandning_date',
				),
				'_svd_sandning_start'	=> array(
					'key'	=> '_svd_sandning_start',
				),
			),
			'orderby'		=> array(
				'_svd_sandning_date'	=> 'ASC',
				'_svd_sandning_start'	=> 'ASC',
			)
		));
		// End database queries

		// Begin date logic
		switch($listing) {
			case "Today":
				$end_listing = 1;
				break;
			case "Week5":
				$end_listing = 5;
				break;
			case "Month":
				$end_listing = 31;
				break;
		};

		$one_day = 86400;
		$today = date("Y-m-d", time() + $one_day * 0);
		$end_date = date("Y-m-d", time() + $one_day * $end_listing);

		$begin = new DateTime($today);
		$end = new DateTime($end_date);
		$interval = DateInterval::createFromDateString('1 day');
		$period = new DatePeriod($begin, $interval, $end);
		// End date logic

		echo (isset($before_widget) ? $before_widget : '');
		echo "Your listing: " . $listing . "<br/>";

		// Begin display loop
		foreach($period as $dt) {
			if($wp_query->have_posts()) {
				while($wp_query->have_posts()) {
					$wp_query->the_post();
					$postdate = get_post_meta(get_the_ID(), '_svd_sandning_date', true);
					if($postdate == $dt->format("Y-m-d")) {
						echo "Date: " . $postdate . " - " . "<a href=" . get_the_permalink() . ">" . get_the_title() . "</a><br/>";
					}
				}
			}
		}
		// End display loop

		echo (isset($after_widget) ? $after_widget : '');
	}
}

// Admin box
add_action( 'add_meta_boxes', 'sverigedistans_add_custom_box' );
function sverigedistans_add_custom_box() {
	$screens = array( 'post', 'my_cpt' );
	foreach ( $screens as $screen ) {
		add_meta_box(
			'sverigedistans_box_id',            // Unique ID
			'V&auml;lj datum f&ouml;r s&auml;ndning',      // Box title
			'sverigedistans_inner_custom_box',  // Content callback
			$screen                      // post type
		);
	}
}

// Inner admin box
function sverigedistans_inner_custom_box( $post ) {
	$svd_date = get_post_meta($post->ID, '_svd_sandning_date', true);
	$svd_start = get_post_meta($post->ID, '_svd_sandning_start', true);
	$svd_end = get_post_meta($post->ID, '_svd_sandning_end', true);
?>
	<label for="sverigedistans_date">Datum:</label><br/>
	<input type="date" name="sverigedistans_date" value="<?php echo $svd_date; ?>"/><br/>

	<label for="sverigedistans_start"><?php echo $svd_start; ?>Starttid:</label><br/>
	<input type="time" name="sverigedistans_start" value="<?php echo $svd_start; ?>"/><br/>
	
	<label for="sverigedistans_stop">Sluttid:</label><br/>
	<input type="time" name="sverigedistans_end" value="<?php echo $svd_end; ?>"/>
<?php
}

// Saving post
add_action( 'save_post', 'sverigedistans_save_postdata' );
function sverigedistans_save_postdata( $post_id ) {
//	if ( array_key_exists('sverigedistans_field', $_POST ) ) {
		update_post_meta( $post_id,
			'_svd_sandning_date',
			$_POST['sverigedistans_date']
		);
		update_post_meta( $post_id,
			'_svd_sandning_start',
			$_POST['sverigedistans_start']
		);
		update_post_meta( $post_id,
			'_svd_sandning_end',
			$_POST['sverigedistans_end']
		);
//	}
}

?>
