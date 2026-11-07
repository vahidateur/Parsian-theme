<?php
/**
 * Custom post types and taxonomies.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the teacher post type, department taxonomy and booking inbox.
 */
function pm_register_content_types() {
	register_post_type(
		'pm_teacher',
		array(
			'labels'       => array(
				'name'               => 'اساتید',
				'singular_name'      => 'استاد',
				'menu_name'          => 'اساتید',
				'name_admin_bar'     => 'استاد',
				'add_new'            => 'افزودن استاد',
				'add_new_item'       => 'افزودن استاد جدید',
				'edit_item'          => 'ویرایش استاد',
				'new_item'           => 'استاد جدید',
				'view_item'          => 'مشاهدهٔ استاد',
				'search_items'       => 'جستجوی اساتید',
				'not_found'          => 'استادی پیدا نشد',
				'not_found_in_trash' => 'استادی در زباله‌دان پیدا نشد',
				'all_items'          => 'همهٔ اساتید',
			),
			'public'       => true,
			'has_archive'  => 'teachers',
			'rewrite'      => array(
				'slug'       => 'teachers',
				'with_front' => false,
			),
			'menu_icon'    => 'dashicons-businessperson',
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
			'show_in_rest' => true,
			'menu_position' => 21,
		)
	);

	register_taxonomy(
		'pm_department',
		array( 'pm_teacher' ),
		array(
			'labels'            => array(
				'name'          => 'دپارتمان‌ها',
				'singular_name' => 'دپارتمان',
				'search_items'  => 'جستجوی دپارتمان',
				'all_items'     => 'همهٔ دپارتمان‌ها',
				'edit_item'     => 'ویرایش دپارتمان',
				'update_item'   => 'به‌روزرسانی دپارتمان',
				'add_new_item'  => 'افزودن دپارتمان',
				'menu_name'     => 'دپارتمان‌ها',
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'       => 'department',
				'with_front' => false,
			),
		)
	);

	register_post_type(
		'pm_booking',
		array(
			'labels'       => array(
				'name'          => 'درخواست‌های مشاوره',
				'singular_name' => 'درخواست مشاوره',
				'menu_name'     => 'درخواست‌ها',
				'edit_item'     => 'مشاهدهٔ درخواست',
				'view_item'     => 'مشاهدهٔ درخواست',
				'search_items'  => 'جستجوی درخواست‌ها',
				'not_found'     => 'درخواستی پیدا نشد',
				'all_items'     => 'همهٔ درخواست‌ها',
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-email-alt',
			'supports'     => array( 'title', 'editor' ),
			'capability_type' => 'post',
			'map_meta_cap' => true,
			'menu_position' => 22,
		)
	);
}
add_action( 'init', 'pm_register_content_types' );

/**
 * Better placeholder for the teacher title field.
 *
 * @param string $text Placeholder.
 * @return string
 */
function pm_teacher_title_placeholder( $text ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'pm_teacher' === $screen->post_type ) {
		return 'نام استاد را وارد کنید';
	}
	return $text;
}
add_filter( 'enter_title_here', 'pm_teacher_title_placeholder' );

/**
 * Teacher admin columns.
 *
 * @param array $columns Columns.
 * @return array
 */
function pm_teacher_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['pm_portrait']    = 'تصویر';
			$new['pm_role']        = 'سمت';
			$new['pm_department']  = 'دپارتمان';
			$new['pm_presence']   = 'روزهای حضور';
		}
	}
	return $new;
}
add_filter( 'manage_pm_teacher_posts_columns', 'pm_teacher_columns' );

/**
 * Render teacher admin columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function pm_teacher_column_content( $column, $post_id ) {
	if ( 'pm_portrait' === $column ) {
		$url = pm_teacher_portrait_url( $post_id );
		if ( $url ) {
			printf( '<img src="%s" alt="" style="width:44px;height:56px;object-fit:cover;border-radius:4px" />', esc_url( $url ) );
		}
	}
	if ( 'pm_role' === $column ) {
		echo esc_html( pm_teacher_meta( 'role', $post_id, '—' ) );
	}
	if ( 'pm_department' === $column ) {
		echo esc_html( pm_teacher_department_name( $post_id, '—' ) );
	}
	if ( 'pm_presence' === $column ) {
		echo esc_html( pm_teacher_meta( 'presence_days', $post_id, '—' ) );
	}
}
add_action( 'manage_pm_teacher_posts_custom_column', 'pm_teacher_column_content', 10, 2 );

/**
 * Filter the teacher archive by the department query string.
 *
 * @param WP_Query $query Main query.
 */
function pm_teacher_archive_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( ! $query->is_post_type_archive( 'pm_teacher' ) ) {
		return;
	}

	$department = isset( $_GET['department'] ) ? sanitize_title( wp_unslash( $_GET['department'] ) ) : '';
	if ( $department ) {
		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy' => 'pm_department',
					'field'    => 'slug',
					'terms'    => $department,
				),
			)
		);
	}

	$query->set( 'posts_per_page', 12 );
	$query->set(
		'orderby',
		array(
			'menu_order' => 'ASC',
			'date'       => 'DESC',
		)
	);
}
add_action( 'pre_get_posts', 'pm_teacher_archive_query' );

/**
 * Booking admin columns.
 *
 * @param array $columns Columns.
 * @return array
 */
function pm_booking_columns( $columns ) {
	return array(
		'cb'            => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
		'title'         => 'درخواست',
		'pm_phone'      => 'موبایل',
		'pm_instrument' => 'ساز',
		'pm_topic'      => 'موضوع',
		'pm_type'       => 'نوع',
		'pm_teacher'    => 'استاد',
		'date'          => 'تاریخ',
	);
}
add_filter( 'manage_pm_booking_posts_columns', 'pm_booking_columns' );

/**
 * Render booking admin columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function pm_booking_column_content( $column, $post_id ) {
	$map = array(
		'pm_phone'      => '_pm_booking_phone',
		'pm_instrument' => '_pm_booking_instrument',
		'pm_topic'      => '_pm_booking_topic',
		'pm_type'       => '_pm_booking_type',
	);
	if ( isset( $map[ $column ] ) ) {
		$value = get_post_meta( $post_id, $map[ $column ], true );
		if ( 'pm_type' === $column ) {
			$value = 'phone' === $value ? 'تلفنی' : 'حضوری';
		}
		echo esc_html( $value ? $value : '—' );
	}
	if ( 'pm_teacher' === $column ) {
		$teacher_id = (int) get_post_meta( $post_id, '_pm_booking_teacher', true );
		echo $teacher_id ? esc_html( get_the_title( $teacher_id ) ) : '—';
	}
}
add_action( 'manage_pm_booking_posts_custom_column', 'pm_booking_column_content', 10, 2 );

/**
 * Keep booking rows out of the block editor REST payloads.
 *
 * @param array  $args      Post type args.
 * @param string $post_type Post type key.
 * @return array
 */
function pm_booking_show_in_rest( $args, $post_type ) {
	if ( 'pm_booking' === $post_type ) {
		$args['show_in_rest'] = false;
	}
	return $args;
}
add_filter( 'register_post_type_args', 'pm_booking_show_in_rest', 10, 2 );
