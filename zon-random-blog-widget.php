<?php
/*
Plugin Name: ZON Kennen Sie schon...?
Description: Ein zufälliges Blog oder ein selbst ausgewähltes Blog in der Sidebar anzeigen.
Version: 0.2
Author: Arne Seemann
*/

add_action( 'widgets_init', array( 'ZON_Random_Blog_Widget', 'init' ) );

class ZON_Random_Blog_Widget extends WP_Widget {

	// -- Constructor --
	function __construct() {
		//Initialisiert das Plugin für Design->Widget.

		$control_options = array(
			'width' => 300,
			'height' => 350,
			'id_base' => 'random-widget'
		);

		//[Titel] und [Beschreibung] werden in Design->Widget Uebersicht angeezeigt
		parent::__construct(
			'random-widget',
			'ZON Kennen Sie schon...?',
			array(
				'classname' => 'ZONRandomBlog', // Muss nicht(!) identisch zur eigentlichen Class sein
				'description' => 'Ein zufälliges Blog oder ein selbst ausgewähltes Blog in der Sidebar anzeigen.'
			),
			$control_options
		);
	}

	public static function init() {
		if ( is_multisite() ) {
			register_widget( __CLASS__ );
		}
	}

	// -- Update / Speichern / Validieren --
	function update( $new_instance, $old_instance ) {
		//Validierungen hier einfuegen und in $new_instance speichern
		$instance = $old_instance;

		/* Strip tags (if needed) and update the widget settings. */
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['blog_id'] = strip_tags( $new_instance['blog_id'] );
		$instance['random_blog'] = $new_instance['random_blog'];

		/* Alle Transients löschen, sonst ist im Frontend das Ergebnis nicht sofort sichtbar */
		delete_transient('zon_random_blog_id');
		delete_transient('zon_random_blog_triple');

		return $instance;
	}

	// -- Admin (Backend) Form --
	function form( $instance ) {

		/* Set up some default widget settings. */
		$defaults = array(
			'title' => 'Kennen Sie schon dieses Blog?',
			'blog_id' => '',
			'random_blog' => true
			);
		$instance = wp_parse_args( (array) $instance, $defaults );
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">Titel:</label>
			<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" style="width:100%;" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'blog_id' ); ?>">Blog:<?php if($instance['random_blog']) echo " <em>(derzeit wird ein zufälliges Blog angezeigt)</em>"?></label>
			<select id="<?php echo $this->get_field_id( 'blog_id' ); ?>" name="<?php echo $this->get_field_name( 'blog_id' ); ?>" class="widefat" style="width:100%;">
				<!-- <option>  -->
				<?php $a_blogs = get_blog_list(0, 'all');
					foreach ($a_blogs as $blog) {
						$blog_id   = $blog['blog_id'];
						$blog_name = get_blog_details($blog_id);
						$blog_name = $blog_name->blogname;

						if ( $instance['blog_id'] != $blog_id ) {
							echo '<option value="'.$blog_id.'">'.$blog_id.'-'.$blog_name.'</option>';
						} else {
							echo '<option value="'.$blog_id.'" selected="selected">'.$blog_id.'-'.$blog_name.'</option>';
						}
					}
				?>
				</select>
			</p>
			<p>
				<input class="checkbox" type="checkbox" <?php checked($instance['random_blog'], 'on'); ?> id="<?php echo $this->get_field_id( 'random_blog' ); ?>" name="<?php echo $this->get_field_name( 'random_blog' ); ?>" />
				<label for="<?php echo $this->get_field_id( 'random_blog' ); ?>">Zufälliges Blog anzeigen?</label>
			</p>
			<?php
	}


	// -- Widget (Frontend) Ausgabe --
	function widget( $args, $instance ) {
		extract( $args );

		/* User-selected settings. */
		$title = apply_filters('widget_title', $instance['title'] );
		$blog_id = $instance['blog_id'];
		$random_blog = isset( $instance['random_blog'] ) ? $instance['random_blog'] : false;

		/* Before widget (defined by themes). */
		echo $before_widget;

		/* Title of widget (before and after defined by themes). */
		if ( $title )
			echo $before_title . $title . $after_title;

		/* Halbarkeit des Caches in Sekunden definieren  */
		$cache_time = 3600;


		/* Option für Random-Blog gesetzt */

		if ( $random_blog ) {

			if (!$the_blog = get_transient('zon_random_blog_id')) {
				$the_blog = get_random_mu_blog_id();
				set_transient('zon_random_blog_id', $the_blog, $cache_time);
			}
			// echo "Das Blog hat die ID $the_blog. ";

			if (!$the_posts = get_transient('zon_random_blog_triple')) {
				$the_posts = fetch_mu_posts($the_blog);
				set_transient('zon_random_blog_triple', $the_posts, $cache_time);
			}
		}
		/* Falls "random" nicht ausgewählt wurde, dafür aber ein Blog ausgewählt wurde: */
		else if ( $blog_id ) {
			if (!$the_posts = get_transient('zon_random_blog_triple')) {
				// echo "Caching festes Blog. ";
				$the_posts = fetch_mu_posts($blog_id);
				set_transient('zon_random_blog_triple', $the_posts, $cache_time);
			}
		}
		echo $the_posts;

		/* After widget (defined by themes). */
		echo $after_widget;

	}

}

/*  */
/* Wechselt zum angegebenen Blog und returned die gewünschte Menge an posts in einer UL */
function fetch_mu_posts($id, $number_of_posts=3){
	switch_to_blog($id);
	// $posts_to_return = '<a href="'.get_bloginfo('url').'"><strong style="text-transform: uppercase; font-size: 10px">'.get_bloginfo('name').': </strong>&raquo;'.get_bloginfo('description').'&laquo;</a><div>Dort lesen Sie derzeit:</div><ul class="bulletpoints">';
	$posts_to_return = '<ul class="shortteaserlist"><li><a href="'.get_bloginfo('url').'"><strong>'.get_bloginfo('name');
	if (get_bloginfo('description') != "") {
		$posts_to_return .= ':</strong> <span class="shorttitle">"'.get_bloginfo('description').'"</span>';
	} else $posts_to_return .= '</strong>';
	$posts_to_return .= '</a></li></ul><div style="margin-top: 5px">Die aktuellsten Beiträge:</div><ul class="bulletpoints">';
	$number_posts = 'numberposts='.$number_of_posts;
	$lastposts = get_posts($number_posts);
	foreach ($lastposts as $post) {
		$posts_to_return .= "<li>\n".'<a href="'.get_permalink($post->ID).'" alt="'.$post->post_title.'">'.$post->post_title."</a></li>\n";
	}
	$posts_to_return .= "</ul>";
	restore_current_blog();
	return $posts_to_return;
}

function get_random_mu_blog_id() {
	// Wordpress MU Funktion um Array aller Blogs zu erhalten
	$a_all_blogs = get_blog_list(0, 'all'); // von 0 beginnend, alle Blogs
	$ausschluss = array(1, 12, 15, 19, 20, 21, 24, 25, 28, 29, 30, 32, 34, 39, 41, 45, 48, 49, 51, 55, 56);
	$i = 0;
	do {
		$i_tmp  = array_rand($a_all_blogs);
		$a_blog = $a_all_blogs[$i_tmp]; // zufälliges Blog auswählen
		$o_blog_details = get_blog_details($a_blog['blog_id']);
		$i++;
		/* Es wird die Schleife solange durchlaufen, solange noch eine der Bedingungen erfüllt ist:
		1. das zufällig gewählte Blog ist das aktuelle Blog
		2. das zufällig gewählte Blog ist das Main-Blog mit der id 1. Dies wird standardmäßig
		nicht von get_blog_list() returned, da es bei uns *nicht öffentlich* ist, ist aber
		als Sicherheitsabfrage nicht verkehrt. */
} while (get_bloginfo() == $o_blog_details->blogname OR in_array($o_blog_details->blog_id, $ausschluss));
// Anmerkung: Vergleich mit dem Blogname ist vll. unelegant, aber die eigenen Blog-ID würde man nur über eine Global bekommen.

return $o_blog_details->blog_id;
}

/* Derzeit nicht in Verwendung:
Wechselt zum angegebenen Blog und echoed die gewünschte Menge an posts in einer UL */
function render_mu_posts($id, $number_of_posts=3) {
	switch_to_blog($id);
	echo 'Im Blog '.get_bloginfo('name').' lesen Sie derzeit:';
	$number_posts = 'numberposts='.$number_of_posts;
	$lastposts = get_posts($number_posts);
	echo '<ul class="bulletpoints">';
	foreach ($lastposts as $post) {
		echo '<li>';
		echo '<a href="'.get_permalink($post->ID).'" alt="'.$post->post_title.'">'.$post->post_title.'</a>';
		echo '</li>';
	}
	echo '</ul>';
	restore_current_blog();
	return;
}
