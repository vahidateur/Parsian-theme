<?php
/**
 * Admin: Assistant & Messaging section, migration screen, operations.
 *
 * Extends the existing theme admin (pm-settings / pm-notifications /
 * pm-ai-knowledge) with links instead of duplicating it.
 *
 * @package Parsian_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'pa_admin_menu', 30 );

/**
 * Register submenu pages.
 */
function pa_admin_menu() {
	$parent = function_exists( 'pm_render_settings_page' ) ? 'pm-settings' : 'options-general.php';
	add_submenu_page(
		$parent,
		'دستیار و پیام‌رسان',
		'دستیار و پیام‌رسان',
		'manage_options',
		'pa-assistant',
		'pa_admin_page'
	);
	add_submenu_page(
		$parent,
		'مهاجرت تنظیمات',
		'مهاجرت تنظیمات',
		'manage_options',
		'pa-migration',
		'pa_migration_page'
	);
}

add_action( 'admin_post_pa_assistant_action', 'pa_admin_action' );

/**
 * Handle assistant ops: poll updates, set webhook, rotate secret, retry info.
 */
function pa_admin_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز', 'خطا', array( 'response' => 403 ) );
	}
	check_admin_referer( 'pa_assistant_action' );
	$action = isset( $_POST['pa_action'] ) ? sanitize_key( wp_unslash( $_POST['pa_action'] ) ) : '';
	$result = array( 'success' => false, 'message' => 'عملیات نامشخص.' );
	$adapter = new PA_Bale_Adapter();

	if ( 'poll' === $action ) {
		$offset = (int) get_option( 'pa_bale_offset', 0 );
		$res = $adapter->get_updates( $offset ? $offset + 1 : 0 );
		$count = 0;
		if ( ! empty( $res['success'] ) ) {
			foreach ( array_slice( (array) $res['body']['result'], 0, 20 ) as $update ) {
				$parsed = $adapter->parse_incoming( $update );
				if ( ! $parsed ) {
					continue;
				}
				if ( pa_claim_update( $parsed['update_id'] ) ) {
					pa_ingest_bale_update( $parsed );
					$count++;
				}
				update_option( 'pa_bale_offset', (int) $parsed['update_id'], false );
			}
			update_option( 'pa_bale_last_poll', current_time( 'mysql' ), false );
			pa_bale_remember_identity( $adapter->get_me() );
			$result = array( 'success' => true, 'message' => 'دریافت شد: ' . $count . ' پیام جدید.' );
		} else {
			update_option( 'pa_bale_last_error', $res['message'], false );
			$result = array( 'success' => false, 'message' => 'خطا: ' . $res['message'] );
		}
	} elseif ( 'webhook' === $action ) {
		$res = $adapter->set_webhook( pa_webhook_url() );
		if ( ! empty( $res['success'] ) ) {
			update_option( 'pa_bale_webhook_set', current_time( 'mysql' ), false );
			pa_bale_remember_identity( $adapter->get_me() );
			$result = array( 'success' => true, 'message' => 'وب‌هوک ثبت شد.' );
		} else {
			update_option( 'pa_bale_last_error', $res['message'], false );
			$result = array( 'success' => false, 'message' => 'خطا: ' . $res['message'] );
		}
	} elseif ( 'refresh' === $action ) {
		$status = pa_bale_status_refresh();
		$result = array(
			'success' => ! empty( $status['getme_ok'] ),
			'message' => ! empty( $status['getme_ok'] )
				? 'اتصال بررسی شد' . ( ! empty( $status['username'] ) ? ' — @' . $status['username'] : '' ) . '.'
				: 'بررسی اتصال ناموفق بود: ' . ( isset( $status['getme_message'] ) ? $status['getme_message'] : 'unknown' ),
		);
	} elseif ( 'rotate' === $action ) {
		delete_option( 'pa_bale_webhook_secret' );
		pa_webhook_secret();
		$result = array( 'success' => true, 'message' => 'مسیر محرمانهٔ وب‌هوک تازه‌سازی شد؛ وب‌هوک را دوباره ثبت کنید.' );
	} elseif ( 'recover' === $action ) {
		// 0 = include just-persisted items; queued workers are still skipped
		// by the queue check, so this only drains genuinely stuck work.
		$recovered = pa_recover_pending( 0, 20 );
		$result = array( 'success' => true, 'message' => 'بازیابی شد: ' . $recovered . ' مورد.' );
	} elseif ( 'unlink' === $action && ! empty( $_POST['chat'] ) ) {
		require_once PA_DIR . 'inc/linking.php';
		pa_link_remove( sanitize_text_field( wp_unslash( $_POST['chat'] ) ) );
		$result = array( 'success' => true, 'message' => 'پیوند لغو شد.' );
	}

	set_transient( 'pa_admin_result_' . get_current_user_id(), $result, 60 );
	wp_safe_redirect( admin_url( 'admin.php?page=pa-assistant' ) );
	exit;
}

add_action( 'admin_post_pa_migration_action', 'pa_migration_admin_action' );

/**
 * Handle migration preview/run/restore.
 */
function pa_migration_admin_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز', 'خطا', array( 'response' => 403 ) );
	}
	check_admin_referer( 'pa_migration_action' );
	$action = isset( $_POST['pa_action'] ) ? sanitize_key( wp_unslash( $_POST['pa_action'] ) ) : '';
	$source = isset( $_POST['source'] ) ? pa_sanitize_slug( wp_unslash( $_POST['source'] ) ) : '';
	$result = array( 'success' => false, 'message' => 'عملیات نامشخص.' );

	if ( 'preview' === $action && $source ) {
		$preview = pa_migration_preview( $source );
		if ( is_wp_error( $preview ) ) {
			$result = array( 'success' => false, 'message' => $preview->get_error_message() );
		} else {
			set_transient( 'pa_migration_preview_' . get_current_user_id(), $preview, 300 );
			wp_safe_redirect( admin_url( 'admin.php?page=pa-migration&source=' . rawurlencode( $source ) ) );
			exit;
		}
	} elseif ( 'run' === $action && $source ) {
		$report = pa_migration_run( $source, ! empty( $_POST['force'] ) );
		if ( is_wp_error( $report ) ) {
			$result = array( 'success' => false, 'message' => $report->get_error_message() );
		} else {
			$message = ! empty( $report['idempotent'] ) ? 'قبلاً مهاجرت شده بود؛ تغییری لازم نبود.' : sprintf(
				'مهاجرت شد: %d کلید، %d تعارض (دست‌نخورده)، %d ناشناخته (مهاجرت نشد).',
				count( $report['migrated'] ),
				count( $report['skipped_conflicts'] ),
				count( $report['preserved_unsupported'] )
			);
			if ( ! empty( $report['secrets_skipped'] ) ) {
				$message .= ' اعتبارنامه‌ها در پشتیبان ذخیره نشدند و باید از مسیر امن تنظیمات وارد شوند: ' . implode( '، ', array_map( 'sanitize_key', (array) $report['secrets_skipped'] ) ) . '.';
			}
			$result = array( 'success' => ! empty( $report['completed'] ), 'message' => $message . ' پشتیبان: ' . $report['backup'] );
		}
	} elseif ( 'restore' === $action && ! empty( $_POST['backup'] ) ) {
		$restored = pa_migration_restore( sanitize_key( wp_unslash( $_POST['backup'] ) ) );
		$result = is_wp_error( $restored )
			? array( 'success' => false, 'message' => $restored->get_error_message() )
			: array( 'success' => true, 'message' => 'پشتیبان بازیابی شد.' );
	}

	set_transient( 'pa_admin_result_' . get_current_user_id(), $result, 60 );
	wp_safe_redirect( admin_url( 'admin.php?page=pa-migration' ) );
	exit;
}

/**
 * Render the assistant dashboard.
 */
function pa_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز', 'خطا', array( 'response' => 403 ) );
	}
	if ( function_exists( 'pm_sprite' ) ) {
		pm_sprite();
	}
	$result = get_transient( 'pa_admin_result_' . get_current_user_id() );
	delete_transient( 'pa_admin_result_' . get_current_user_id() );
	$status = pa_bale_status_cached();
	$username = ! empty( $status['username'] ) ? (string) $status['username'] : '';
	$checked_at = (int) $status['checked_at'];
	$operator_queue = function_exists( 'pa_operator_queue' ) ? pa_operator_queue( 20 ) : array();
	$operator_pending = function_exists( 'pa_operator_pending_count' ) ? pa_operator_pending_count() : 0;
	global $wpdb;
	$conversations = $wpdb->get_results(
		"SELECT id, channel, external_key, wp_user_id, status, updated_at FROM " . pa_table( 'conversations' ) . " ORDER BY updated_at DESC LIMIT 50"
	);
	$events = $wpdb->get_results(
		"SELECT conversation_id, event, created_at FROM " . pa_table( 'events' ) . " ORDER BY id DESC LIMIT 50"
	);
	$queue_pending = function_exists( 'pa_pending_count' ) ? pa_pending_count() : 0;
	$queue_recovery = wp_next_scheduled( 'pa_recover_pending' );
	$schedule_failures = $wpdb->get_results(
		"SELECT created_at, payload FROM " . pa_table( 'events' ) . " WHERE event IN ('bale_schedule_failed','bale_delivery_schedule_failed') ORDER BY id DESC LIMIT 5"
	);
	?>
	<div class="wrap" dir="rtl">
		<h1>دستیار و پیام‌رسان</h1>
		<?php if ( $result ) : ?>
			<div class="notice notice-<?php echo ! empty( $result['success'] ) ? 'success' : 'error'; ?>"><p><?php echo esc_html( $result['message'] ); ?></p></div>
		<?php endif; ?>
		<h2>بله</h2>
		<table class="widefat striped"><tbody>
			<tr><th>توکن</th><td><?php echo ! empty( $status['configured'] ) ? 'ثبت شده (نمایش داده نمی‌شود)' : 'ثبت نشده'; ?></td></tr>
			<tr><th>اتصال (getMe)</th><td>
				<?php
				if ( ! $checked_at ) {
					echo 'بررسی نشده — دکمهٔ «بررسی اتصال» را بزنید.';
				} elseif ( ! empty( $status['getme_ok'] ) ) {
					echo 'متصل' . ( $username ? ' — @' . esc_html( $username ) : '' );
				} else {
					echo esc_html( isset( $status['getme_message'] ) ? $status['getme_message'] : 'ناموفق' );
				}
				?>
			</td></tr>
			<tr><th>وب‌هوک</th><td><code dir="ltr"><?php echo esc_html( pa_webhook_url() ); ?></code> <?php echo ! empty( $status['webhook_ok'] ) ? '— ثبت‌شده' : ''; ?></td></tr>
			<tr><th>آخرین بررسی</th><td><?php echo $checked_at ? esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $checked_at ), 'Y-m-d H:i' ) ) : '—'; ?></td></tr>
			<tr><th>آخرین خطا</th><td><?php echo esc_html( ! empty( $status['last_error'] ) ? $status['last_error'] : '—' ); ?></td></tr>
		</tbody></table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0;">
			<input type="hidden" name="action" value="pa_assistant_action">
			<?php wp_nonce_field( 'pa_assistant_action' ); ?>
			<button class="button" type="submit" name="pa_action" value="refresh">بررسی اتصال (getMe)</button>
			<button class="button" type="submit" name="pa_action" value="poll">دریافت پیام‌ها (polling)</button>
			<button class="button" type="submit" name="pa_action" value="webhook">ثبت وب‌هوک</button>
			<button class="button" type="submit" name="pa_action" value="rotate">تازه‌سازی مسیر محرمانه</button>
			<button class="button" type="submit" name="pa_action" value="recover">بازیابی پاسخ‌های معوق (<?php echo esc_html( function_exists( 'pa_pending_count' ) ? pa_pending_count() : 0 ); ?>)</button>
		</form>
		<h2>گفتگوها</h2>
		<table class="widefat striped">
			<thead><tr><th>#</th><th>کانال</th><th>شناسه</th><th>کاربر</th><th>وضعیت</th><th>به‌روزرسانی</th></tr></thead>
			<tbody>
			<?php if ( ! $conversations ) : ?><tr><td colspan="6">گفتگویی ثبت نشده است.</td></tr><?php endif; ?>
			<?php foreach ( (array) $conversations as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row->id ); ?></td>
					<td><?php echo esc_html( $row->channel ); ?></td>
					<td><code dir="ltr"><?php echo esc_html( $row->external_key ); ?></code></td>
					<td>
						<?php echo esc_html( $row->wp_user_id ? get_the_author_meta( 'display_name', $row->wp_user_id ) : '—' ); ?>
						<?php if ( 'bale' === $row->channel && $row->wp_user_id ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<input type="hidden" name="action" value="pa_assistant_action">
								<input type="hidden" name="pa_action" value="unlink">
								<input type="hidden" name="chat" value="<?php echo esc_attr( preg_replace( '/^bale:/', '', $row->external_key ) ); ?>">
								<?php wp_nonce_field( 'pa_assistant_action' ); ?>
								<button class="button button-small" type="submit">لغو پیوند</button>
							</form>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $row->status ); ?></td>
					<td><?php echo esc_html( $row->updated_at ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<h2>رویدادها</h2>
		<table class="widefat striped">
			<thead><tr><th>گفتگو</th><th>رویداد</th><th>زمان</th></tr></thead>
			<tbody>
			<?php if ( ! $events ) : ?><tr><td colspan="3">رویدادی ثبت نشده است.</td></tr><?php endif; ?>
			<?php foreach ( (array) $events as $event ) : ?>
				<tr><td><?php echo esc_html( $event->conversation_id ); ?></td><td><?php echo esc_html( $event->event ); ?></td><td><?php echo esc_html( $event->created_at ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<h2>صف اپراتور (تحویل انسانی)</h2>
		<table class="widefat striped">
			<thead><tr><th>گفتگو</th><th>شناسهٔ پیام</th><th>زمان</th><th>پیام</th></tr></thead>
			<tbody>
			<?php if ( ! $operator_queue ) : ?><tr><td colspan="4">پیام معوقی برای اپراتور نیست.</td></tr><?php endif; ?>
			<?php foreach ( (array) $operator_queue as $job ) : ?>
				<tr>
					<td><?php echo esc_html( $job['conversation_id'] ); ?></td>
					<td><?php echo esc_html( $job['message_id'] ); ?></td>
					<td><?php echo esc_html( $job['created_at'] ); ?></td>
					<td><?php echo esc_html( mb_substr( (string) $job['text'], 0, 160 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<h2>صف پردازش</h2>
		<table class="widefat striped"><tbody>
			<tr><th>پاسخ‌های معوق</th><td><?php echo esc_html( $queue_pending ); ?></td></tr>
			<tr><th>در انتظار اپراتور</th><td><?php echo esc_html( $operator_pending ); ?></td></tr>
			<tr><th>زمان‌بند بازیابی</th><td><?php echo $queue_recovery ? esc_html( 'فعال — اجرای بعدی: ' . get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $queue_recovery ), 'Y-m-d H:i' ) ) : 'غیرفعال — WP-Cron را بررسی کنید'; ?></td></tr>
			<tr>
				<th>خطاهای زمان‌بندی (آخرین ۵ مورد)</th>
				<td>
					<?php if ( ! $schedule_failures ) : ?>
						—
					<?php else : ?>
						<?php foreach ( (array) $schedule_failures as $failure ) : ?>
							<?php
							$payload = json_decode( (string) $failure->payload, true );
							$error = is_array( $payload ) && isset( $payload['error'] ) ? preg_replace( '/[^a-z0-9_]/', '', (string) $payload['error'] ) : 'unknown';
							?>
							<code dir="ltr"><?php echo esc_html( $failure->created_at . ' — ' . $error ); ?></code><br>
						<?php endforeach; ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody></table>
		<p>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=pm-ai-knowledge' ) ); ?>">دانش تأییدشده</a>
			<a class="button" href="<?php echo function_exists( 'pm_settings_url' ) ? esc_url( pm_settings_url() ) : esc_url( admin_url( 'admin.php?page=pm-settings' ) ); ?>">تنظیمات دستیار</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=pa-migration' ) ); ?>">مهاجرت تنظیمات</a>
		</p>
	</div>
	<?php
}

/**
 * Render the migration screen.
 */
function pa_migration_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز', 'خطا', array( 'response' => 403 ) );
	}
	$result = get_transient( 'pa_admin_result_' . get_current_user_id() );
	delete_transient( 'pa_admin_result_' . get_current_user_id() );
	$status = pa_migration_status();
	$selected = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';
	$preview = $selected ? get_transient( 'pa_migration_preview_' . get_current_user_id() ) : null;
	?>
	<div class="wrap" dir="rtl">
		<h1>مهاجرت تنظیمات قالب</h1>
		<p>از نصب‌های v1.6.0 با شناسهٔ تصادفی به شناسهٔ استاندارد <code dir="ltr">parsian-music</code>. منبع هرگز حذف نمی‌شود.</p>
		<?php if ( $result ) : ?>
			<div class="notice notice-<?php echo ! empty( $result['success'] ) ? 'success' : 'error'; ?>"><p><?php echo esc_html( $result['message'] ); ?></p></div>
		<?php endif; ?>
		<?php if ( 'clean' === $status['state'] ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $status['message'] ); ?></p></div>
		<?php else : ?>
			<p><?php echo esc_html( $status['message'] ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pa_migration_action">
				<input type="hidden" name="pa_action" value="preview">
				<?php wp_nonce_field( 'pa_migration_action' ); ?>
				<select name="source">
					<?php foreach ( pa_migration_candidates() as $slug => $candidate ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $selected, $slug ); ?>>
							<?php echo esc_html( $slug . ' (' . $candidate['count'] . ' کلید)' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button class="button" type="submit">پیش‌نمایش</button>
			</form>
		<?php endif; ?>
		<?php if ( $preview && ! is_wp_error( $preview ) ) : ?>
			<h2>پیش‌نمایش: <code dir="ltr"><?php echo esc_html( $preview['source'] ); ?></code> ← <code dir="ltr"><?php echo esc_html( $preview['destination'] ); ?></code></h2>
			<table class="widefat striped"><tbody>
				<tr><th>قابل مهاجرت</th><td><?php echo esc_html( count( $preview['supported'] ) ); ?> کلید</td></tr>
				<tr><th>تعارض (مقصد پر است)</th><td><?php echo $preview['conflicts'] ? esc_html( implode( '، ', $preview['conflicts'] ) ) : '—'; ?></td></tr>
				<tr><th>اعتبارنامه‌ها (مهاجرت نمی‌شوند)</th><td><?php echo ! empty( $preview['secrets'] ) ? esc_html( implode( '، ', $preview['secrets'] ) ) : '—'; ?></td></tr>
				<tr><th>ناشناخته (مهاجرت نمی‌شود)</th><td><?php echo $preview['unsupported'] ? esc_html( implode( '، ', $preview['unsupported'] ) ) : '—'; ?></td></tr>
			</tbody></table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('مهاجرت اجرا شود؟ منبع حذف نمی‌شود.');">
				<input type="hidden" name="action" value="pa_migration_action">
				<input type="hidden" name="pa_action" value="run">
				<input type="hidden" name="source" value="<?php echo esc_attr( $preview['source'] ); ?>">
				<?php wp_nonce_field( 'pa_migration_action' ); ?>
				<button class="button button-primary" type="submit">اجرای مهاجرت (فقط خانه‌های خالی)</button>
			</form>
		<?php endif; ?>
		<h2>بازیابی</h2>
		<p>برای بازگشت، از پشتیبان ذخیره‌شده استفاده کنید (نام گزینه در پیام نتیجه نمایش داده می‌شود):</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="pa_migration_action">
			<input type="hidden" name="pa_action" value="restore">
			<?php wp_nonce_field( 'pa_migration_action' ); ?>
			<input type="text" name="backup" placeholder="pa_migration_backup_…" dir="ltr" style="width:320px">
			<button class="button" type="submit">بازیابی پشتیبان</button>
		</form>
	</div>
	<?php
}
