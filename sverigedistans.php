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
		$future = $instance['future'];
		?>

		<p>
		<label for="<?php echo $this->get_field_id('title'); ?>">Title:
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo attribute_escape($title); ?>"/>
		</label>
		</p>

		<p>
		<label for="<?php echo $this->get_field_id('text'); ?>">Listing:
			<select class="widefat" id="<?php echo $this->get_field_id('listing'); ?>" name=<?php echo $this->get_field_name('listing'); ?> type="text">
				<option value="Day" <?php echo ($listing == 'Day') ? 'selected' : ''; ?>>Day</option>
				<option value="Week" <?php echo ($listing == 'Week') ? 'selected' : ''; ?>>Week</option>
				<option value="Month" <?php echo ($listing == 'Month') ? 'selected' : ''; ?>>Month</option>
			</select>
		</label>
		</p>

		<p>
		<label for="<?php echo $this->get_field_id('text'); ?>">Future:
			<input class="widefat" id="<?php echo $this->get_field_id('future'); ?>" name="<?php echo $this->get_field_name('future'); ?>" type="text" value="<?php echo attribute_escape($future); ?>" style="width:50px;"/>
		</label>
		</p>

		<?php
	}

	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];
		$instance['listing'] = $new_instance['listing'];
		$instance['future'] = $new_instance['future'];
		return $instance;
	}

	public function widget($args, $instance) {
		extract($args, EXTR_SKIP);
		$title		= empty($instance['title'])	? '' : apply_filters('widget_title', $instance['title']);
		$listing	= empty($instance['listing'])	? '' : $instance['listing'];
		$future		= empty($instance['future'])	? '' : $instance['future'];

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
		setlocale(LC_TIME, "swedish");
		$dt_today = new DateTime('today');
		$dt_start = new DateTime();
		$dt_end = new DateTime();

		if($future != 0) {
			$dt_start->modify("+" . $future . " " . $listing);
			$dt_end->modify("+" . $future . " " . $listing);
		} else {
			$dt_start->modify('today');
			$dt_end->modify('today');
		}

		switch($listing) {
			case "Day":
				echo "<h3>" . utf8_encode(strftime("%A", $dt_start->getTimestamp())) . "</h3>";
				break;
			case "Week":
				echo "<h3>Vecka " . utf8_encode(strftime("%V", $dt_start->getTimestamp())) . "</h3>";
				$dt_start->modify("monday this week");
				$dt_end->modify("sunday this week");
				break;
			case "Month":
				echo "<h3>" . utf8_encode(strftime("%B", $dt_start->getTimestamp())) . "</h3>";
				$dt_start->modify("first day of this month");
				$dt_end->modify("last day of this month");
				break;
		};

		$interval = DateInterval::createFromDateString('1 day');
		$period = new DatePeriod($dt_start, $interval, $dt_end);
		// End date logic

		echo (isset($before_widget) ? $before_widget : '');
		//echo "Your listing: " . $listing . "<br/>dt_start: " . $dt_start->format("Y-m-d") . "<br/>dt_end: " . $dt_end->format("Y-m-d") . "<br/><br/>";

		// Begin display loop
		foreach($period as $dt) {
			if($wp_query->have_posts()) {
				while($wp_query->have_posts()) {
					$wp_query->the_post();
					$postdate = get_post_meta(get_the_ID(), '_svd_sandning_date', true);
					if($postdate == $dt->format("Y-m-d")) {
						get_template_part("templates/content", "post-grid");
						//echo "Date: " . $postdate . " - " . "<a href=" . get_the_permalink() . ">" . get_the_title() . "</a><br/>";
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
