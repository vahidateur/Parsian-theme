<?php
/**
 * Admin branding: login screen and dashboard.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Login page styles.
 */
function pm_login_assets() {
	wp_enqueue_style(
		'pm-login-font',
		'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'pm-login',
		PM_URI . '/assets/css/login.css',
		array( 'pm-login-font' ),
		pm_asset_version( 'assets/css/login.css' )
	);

	$background = pm_get_option( 'login_bg', '' );
	$background = $background ? $background : pm_img( 'hero.jpg' );
	$background = pm_media_url( $background );

	wp_add_inline_style(
		'pm-login',
		'body.login{--pm-login-bg:url("' . esc_url( $background ) . '");}'
	);
}
add_action( 'login_enqueue_scripts', 'pm_login_assets' );

/**
 * Print the icon sprite before the login form.
 */
add_action( 'login_header', 'pm_sprite' );

/**
 * Login logo link.
 *
 * @return string
 */
function pm_login_header_url() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'pm_login_header_url' );

/**
 * Login logo text.
 *
 * @return string
 */
function pm_login_header_text() {
	return pm_brand_name();
}
add_filter( 'login_headertext', 'pm_login_header_text' );

/**
 * Login page title.
 *
 * @return string
 */
function pm_login_title() {
	return 'ورود به پنل مدیریت — ' . pm_brand_name();
}
add_filter( 'login_title', 'pm_login_title' );

/**
 * Brand block and welcome copy above the login form.
 *
 * @param string $message Existing message.
 * @return string
 */
function pm_login_message( $message ) {
	$text  = pm_get_option( 'login_message', 'برای مدیریت محتوا، اساتید و تنظیمات قالب وارد شوید.' );
	$extra = $message ? '<div class="pm-login-plugin-message">' . wp_kses_post( $message ) . '</div>' : '';

	ob_start();
	?>
	<div class="pm-login-brand">
		<span class="pm-login-brand__mark">
			<?php echo pm_icon( 'wave' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</span>
		<span class="pm-login-brand__text">
			<strong><?php echo esc_html( pm_brand_name() ); ?></strong>
			<small><?php echo esc_html( pm_brand_subtitle() ); ?></small>
		</span>
	</div>
	<div class="pm-login-intro">
		<h1>ورود به پنل مدیریت</h1>
		<p><?php echo esc_html( $text ); ?></p>
	</div>
	<?php
	return ob_get_clean() . $extra;
}
add_filter( 'login_message', 'pm_login_message' );

/**
 * Footer credit on the login page.
 */
function pm_login_footer() {
	?>
	<p class="pm-login-credit">
		<?php echo esc_html( pm_fa( gmdate( 'Y' ) ) ); ?> © <?php echo esc_html( pm_brand_name() ); ?>
	</p>
	<?php
}
add_action( 'login_footer', 'pm_login_footer' );

/**
 * Body class for the custom login screen.
 *
 * @param array $classes Body classes.
 * @return array
 */
function pm_login_body_class( $classes ) {
	$classes[] = 'pm-login-page';
	return $classes;
}
add_filter( 'login_body_class', 'pm_login_body_class' );

/**
 * Build the dashboard experience.
 */
function pm_dashboard_setup() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	remove_action( 'welcome_panel', 'wp_welcome_panel' );
	remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
	remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
	remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
	remove_meta_box( 'dashboard_php_nag', 'dashboard', 'normal' );

	// Keep the dashboard focused on the academy, not plugin onboarding widgets.
	$plugin_widgets = array(
		'wpp_dashboard_primary',
		'wp_mail_smtp_reports_widget_lite',
		'wordfence_activity_report_widget',
	);
	foreach ( $plugin_widgets as $widget_id ) {
		remove_meta_box( $widget_id, 'dashboard', 'normal' );
		remove_meta_box( $widget_id, 'dashboard', 'side' );
	}

	wp_add_dashboard_widget( 'pm_dashboard_welcome', 'نمای کلی پارسیان', 'pm_dashboard_welcome_widget', null, null, 'normal', 'high' );
	wp_add_dashboard_widget( 'pm_dashboard_bookings', 'آخرین درخواست‌های مشاوره', 'pm_dashboard_bookings_widget', null, null, 'normal', 'high' );
	wp_add_dashboard_widget( 'pm_dashboard_shortcuts', 'دسترسی سریع', 'pm_dashboard_shortcuts_widget', null, null, 'side', 'high' );
}
add_action( 'wp_dashboard_setup', 'pm_dashboard_setup' );

/**
 * Welcome dashboard widget.
 */
function pm_dashboard_welcome_widget() {
	pm_sprite();

	$teachers = wp_count_posts( 'pm_teacher' );
	$bookings = wp_count_posts( 'pm_booking' );
	$posts    = wp_count_posts( 'post' );
	$pages    = wp_count_posts( 'page' );
	$notice   = pm_get_option( 'dashboard_notice', '' );
	$stats    = array(
		array( 'value' => isset( $teachers->publish ) ? $teachers->publish : 0, 'label' => 'استاد فعال', 'icon' => 'users' ),
		array( 'value' => isset( $bookings->private ) ? $bookings->private : 0, 'label' => 'درخواست مشاوره', 'icon' => 'mail' ),
		array( 'value' => isset( $posts->publish ) ? $posts->publish : 0, 'label' => 'نوشته', 'icon' => 'edit' ),
		array( 'value' => isset( $pages->publish ) ? $pages->publish : 0, 'label' => 'برگه', 'icon' => 'building' ),
	);
	?>
	<div class="pm-dash-welcome">
		<span class="pm-dash-welcome__glow" aria-hidden="true"></span>
		<div class="pm-dash-welcome__copy">
			<span class="pm-dash-eyebrow">Parsian Music Academy</span>
			<h2><?php echo esc_html( pm_get_option( 'dashboard_title', 'به پنل مدیریت پارسیان خوش آمدید' ) ); ?></h2>
			<p><?php echo esc_html( pm_get_option( 'dashboard_text', 'از اینجا محتوا، اساتید، درخواست‌های مشاوره و ظاهر سایت را مدیریت کنید.' ) ); ?></p>

			<?php if ( $notice ) : ?>
				<div class="pm-dash-notice">
					<?php echo pm_icon( 'sparkles' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php echo esc_html( $notice ); ?></span>
				</div>
			<?php endif; ?>

			<div class="pm-dash-actions">
				<a class="is-gold" href="<?php echo esc_url( pm_settings_url() ); ?>">
					<?php echo pm_icon( 'edit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					تنظیمات قالب
				</a>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">
					<?php echo pm_icon( 'arrow-left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					مشاهدهٔ سایت
				</a>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pm_teacher' ) ); ?>">
					<?php echo pm_icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					افزودن استاد
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=pm_booking' ) ); ?>">
					<?php echo pm_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					درخواست‌ها
				</a>
			</div>
		</div>
		<div class="pm-dash-welcome__mark" aria-hidden="true">
			<span><?php echo pm_icon( 'wave' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
	</div>

	<div class="pm-dash-stats">
		<?php foreach ( $stats as $stat ) : ?>
			<div class="pm-dash-stat">
				<span class="pm-dash-stat__icon"><?php echo pm_icon( $stat['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<strong><?php echo esc_html( pm_fa( $stat['value'] ) ); ?></strong>
				<span><?php echo esc_html( $stat['label'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Latest consultation requests.
 */
function pm_dashboard_bookings_widget() {
	pm_sprite();

	$bookings = get_posts(
		array(
			'post_type'      => 'pm_booking',
			'post_status'    => 'any',
			'posts_per_page' => 6,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	?>
	<?php if ( empty( $bookings ) ) : ?>
		<div class="pm-dash-empty">
			<?php echo pm_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div>
				<strong>هنوز درخواستی ثبت نشده است.</strong>
				<p>درخواست‌های فرم مشاورهٔ سایت در این بخش نمایش داده می‌شوند.</p>
			</div>
		</div>
	<?php else : ?>
		<div class="pm-dash-bookings">
			<table class="widefat striped">
				<thead>
					<tr>
						<th>نام</th>
						<th>موبایل</th>
						<th>ساز</th>
						<th>تاریخ</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $bookings as $booking ) : ?>
						<?php
						$name       = get_post_meta( $booking->ID, '_pm_booking_name', true );
						$phone      = get_post_meta( $booking->ID, '_pm_booking_phone', true );
						$instrument = get_post_meta( $booking->ID, '_pm_booking_instrument', true );
						$name       = $name ? $name : $booking->post_title;
						?>
						<tr>
							<td><strong><?php echo esc_html( $name ); ?></strong></td>
							<td dir="ltr"><?php echo esc_html( $phone ? $phone : '—' ); ?></td>
							<td><?php echo esc_html( $instrument ? $instrument : '—' ); ?></td>
							<td><?php echo esc_html( date_i18n( 'Y/m/d H:i', strtotime( $booking->post_date ) ) ); ?></td>
							<td><a href="<?php echo esc_url( get_edit_post_link( $booking->ID ) ); ?>">مشاهده</a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<a class="pm-dash-more" href="<?php echo esc_url( admin_url( 'edit.php?post_type=pm_booking' ) ); ?>">همهٔ درخواست‌ها <?php echo pm_icon( 'arrow-left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		</div>
	<?php endif; ?>
	<?php
}

/**
 * Quick links dashboard widget.
 */
function pm_dashboard_shortcuts_widget() {
	pm_sprite();

	$links = array(
		array( 'label' => 'تنظیمات قالب', 'url' => pm_settings_url(), 'icon' => 'edit' ),
		array( 'label' => 'اساتید', 'url' => admin_url( 'edit.php?post_type=pm_teacher' ), 'icon' => 'users' ),
		array( 'label' => 'دپارتمان‌ها', 'url' => admin_url( 'edit-tags.php?taxonomy=pm_department&post_type=pm_teacher' ), 'icon' => 'music' ),
		array( 'label' => 'درخواست‌های مشاوره', 'url' => admin_url( 'edit.php?post_type=pm_booking' ), 'icon' => 'mail' ),
		array( 'label' => 'منوها', 'url' => admin_url( 'nav-menus.php' ), 'icon' => 'menu' ),
		array( 'label' => 'رسانه', 'url' => admin_url( 'upload.php' ), 'icon' => 'star' ),
		array( 'label' => 'سفارشی‌ساز', 'url' => admin_url( 'customize.php' ), 'icon' => 'target' ),
		array( 'label' => 'افزونه‌ها', 'url' => admin_url( 'plugins.php' ), 'icon' => 'award' ),
	);
	?>
	<div class="pm-dash-shortcuts">
		<?php foreach ( $links as $link ) : ?>
			<a href="<?php echo esc_url( $link['url'] ); ?>">
				<span><?php echo pm_icon( $link['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<strong><?php echo esc_html( $link['label'] ); ?></strong>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Show the number of new consultation requests next to the menu item.
 */
function pm_booking_menu_bubble() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	global $menu;
	$counts = wp_count_posts( 'pm_booking' );
	$count  = isset( $counts->private ) ? (int) $counts->private : 0;

	if ( ! $count || ! is_array( $menu ) ) {
		return;
	}

	foreach ( $menu as $index => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=pm_booking' === $item[2] ) {
			$menu[ $index ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . esc_html( number_format_i18n( $count ) ) . '</span></span>';
			break;
		}
	}
}
add_action( 'admin_menu', 'pm_booking_menu_bubble', 999 );

/**
 * Branded admin footer.
 *
 * @return string
 */
function pm_admin_footer_text() {
	return 'قالب پارسیان — طراحی اختصاصی برای آموزشگاه موسیقی';
}
add_filter( 'admin_footer_text', 'pm_admin_footer_text' );

/**
 * Branded version text in the admin footer.
 *
 * @return string
 */
function pm_admin_update_footer() {
	return 'Parsian Theme 1.6.0';
}
add_filter( 'update_footer', 'pm_admin_update_footer', 11 );
