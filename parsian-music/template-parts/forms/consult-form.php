<?php
/**
 * Consultation form.
 *
 * @package Parsian_Music
 *
 * @var array $args Form arguments.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_id    = isset( $args['id'] ) ? sanitize_html_class( $args['id'] ) : 'pm-consult';
$compact    = ! empty( $args['compact'] );
$sent_form  = isset( $_GET['pm_form'] ) ? sanitize_html_class( wp_unslash( $_GET['pm_form'] ) ) : '';
$sent       = isset( $_GET['pm_sent'] ) && ( '' === $sent_form || $sent_form === $form_id );
$error      = isset( $_GET['pm_error'] ) ? sanitize_text_field( wp_unslash( $_GET['pm_error'] ) ) : '';
if ( '' !== $sent_form && $sent_form !== $form_id ) {
	$error = '';
}
$show_tabs  = isset( $args['show_tabs'] ) ? (bool) $args['show_tabs'] : true;
$teacher_id = isset( $args['teacher_id'] ) ? absint( $args['teacher_id'] ) : 0;

$pm_name_label        = pm_get_option( 'consult_name_label', 'نام و نام خانوادگی' );
$pm_name_placeholder  = pm_get_option( 'consult_name_placeholder', $pm_name_label );
$pm_name_required     = '1' === pm_get_option( 'consult_name_required', '1' );
$pm_phone_label       = pm_get_option( 'consult_phone_label', 'شمارهٔ موبایل' );
$pm_phone_placeholder = pm_get_option( 'consult_phone_placeholder', $pm_phone_label );
$pm_phone_required    = '1' === pm_get_option( 'consult_phone_required', '1' );
$pm_instrument_label  = pm_get_option( 'consult_instrument_label', 'ساز مورد علاقه' );
$pm_instrument_req    = '1' === pm_get_option( 'consult_instrument_required', '1' );
$pm_topic_label       = pm_get_option( 'consult_topic_label', 'موضوع مشاوره' );
$pm_message_label     = pm_get_option( 'consult_message_label', 'توضیحات' );
$pm_message_ph        = pm_get_option( 'consult_message_placeholder', 'توضیحات (اختیاری)' );
$pm_message_enabled   = '1' === pm_get_option( 'consult_message_enabled', '1' );
$pm_phone_tab         = '1' === pm_get_option( 'consult_phone_tab_enabled', '1' );
$pm_phone_number      = pm_get_option( 'consult_phone_number', '' );
$pm_phone_number      = $pm_phone_number ? $pm_phone_number : pm_get_option( 'phone', '۰۲۱-۱۲۳۴۵۶۷۸' );
$pm_phone_note        = pm_get_option( 'consult_phone_note', 'برای رزرو مشاورهٔ تلفنی با آکادمی تماس بگیرید.' );
$pm_phone_hours       = pm_get_option( 'consult_phone_hours', 'شنبه تا پنجشنبه، ۱۰ تا ۲۰' );
$pm_show_tabs         = $show_tabs && $pm_phone_tab;
?>
<form class="pm-form<?php echo $compact ? ' pm-form--compact' : ''; ?>" id="<?php echo esc_attr( $form_id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-pm-form novalidate>
	<?php if ( ! empty( $args['title'] ) ) : ?>
		<p class="pm-form__title"><?php echo esc_html( $args['title'] ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $args['description'] ) ) : ?>
		<p class="pm-form__desc"><?php echo esc_html( $args['description'] ); ?></p>
	<?php endif; ?>

	<?php if ( $sent ) : ?>
		<div class="pm-notice pm-notice--success" role="status">
			<?php echo pm_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span>درخواست شما ثبت شد؛ برای هماهنگی نهایی با شما تماس می‌گیریم.</span>
		</div>
	<?php endif; ?>

	<?php if ( $error ) : ?>
		<div class="pm-notice pm-notice--error" role="alert">
			<?php echo pm_icon( 'help' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span><?php echo esc_html( $error ); ?></span>
		</div>
	<?php endif; ?>

	<?php if ( $pm_show_tabs ) : ?>
		<div class="pm-tabs" role="tablist" data-pm-tabs>
			<button type="button" class="pm-tab is-on" role="tab" aria-selected="true" data-pm-type="inperson">
				<?php echo pm_icon( 'radio' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				مشاوره حضوری
			</button>
			<button type="button" class="pm-tab" role="tab" aria-selected="false" data-pm-type="phone">
				<?php echo pm_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				مشاوره تلفنی
			</button>
		</div>
	<?php endif; ?>

	<input type="hidden" name="action" value="pm_consult">
	<input type="hidden" name="pm_form" value="<?php echo esc_attr( $form_id ); ?>">
	<input type="hidden" name="pm_redirect" value="<?php echo esc_url( $args['redirect'] ); ?>">
	<input type="hidden" name="pm_teacher" value="<?php echo esc_attr( $teacher_id ); ?>">
	<input type="hidden" name="pm_type" value="inperson" data-pm-type-input>
	<?php wp_nonce_field( 'pm_consult', 'pm_nonce' ); ?>

	<div class="pm-hp" aria-hidden="true">
		<label>وب‌سایت<input type="text" name="pm_website" tabindex="-1" autocomplete="off"></label>
	</div>

	<div class="pm-form__panel" data-pm-form-panel="inperson">
		<div class="pm-field">
			<label class="pm-sr-only" for="<?php echo esc_attr( $form_id ); ?>-name"><?php echo esc_html( $pm_name_label ); ?></label>
			<?php echo pm_icon( 'user', 'pm-field__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input id="<?php echo esc_attr( $form_id ); ?>-name" name="pm_name" type="text" placeholder="<?php echo esc_attr( $pm_name_placeholder ); ?>" autocomplete="name" aria-describedby="<?php echo esc_attr( $form_id ); ?>-name-error"<?php echo $pm_name_required ? ' required' : ''; ?>>
			<span class="pm-error" id="<?php echo esc_attr( $form_id ); ?>-name-error" data-pm-error="name"></span>
		</div>

		<div class="pm-field">
			<label class="pm-sr-only" for="<?php echo esc_attr( $form_id ); ?>-phone"><?php echo esc_html( $pm_phone_label ); ?></label>
			<?php echo pm_icon( 'mobile', 'pm-field__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input id="<?php echo esc_attr( $form_id ); ?>-phone" name="pm_phone" type="tel" inputmode="numeric" maxlength="11" placeholder="<?php echo esc_attr( $pm_phone_placeholder ); ?>" autocomplete="tel" aria-describedby="<?php echo esc_attr( $form_id ); ?>-phone-error"<?php echo $pm_phone_required ? ' required' : ''; ?>>
			<span class="pm-error" id="<?php echo esc_attr( $form_id ); ?>-phone-error" data-pm-error="phone"></span>
		</div>

		<div class="pm-field">
			<label class="pm-sr-only" for="<?php echo esc_attr( $form_id ); ?>-instrument"><?php echo esc_html( $pm_instrument_label ); ?></label>
			<?php echo pm_icon( 'music', 'pm-field__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<select id="<?php echo esc_attr( $form_id ); ?>-instrument" name="pm_instrument" aria-describedby="<?php echo esc_attr( $form_id ); ?>-instrument-error"<?php echo $pm_instrument_req ? ' required' : ''; ?>>
				<option value=""><?php echo esc_html( $pm_instrument_label ); ?></option>
				<?php foreach ( pm_instrument_options() as $instrument ) : ?>
					<option value="<?php echo esc_attr( $instrument ); ?>"><?php echo esc_html( $instrument ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php echo pm_icon( 'chev-down', 'pm-field__chev' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span class="pm-error" id="<?php echo esc_attr( $form_id ); ?>-instrument-error" data-pm-error="instrument"></span>
		</div>

		<div class="pm-field pm-field--topic">
			<label class="pm-sr-only" for="<?php echo esc_attr( $form_id ); ?>-topic"><?php echo esc_html( $pm_topic_label ); ?></label>
			<?php echo pm_icon( 'target', 'pm-field__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<select id="<?php echo esc_attr( $form_id ); ?>-topic" name="pm_topic">
				<?php foreach ( pm_topic_options() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php echo pm_icon( 'chev-down', 'pm-field__chev' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<?php if ( $pm_message_enabled ) : ?>
			<div class="pm-field pm-field--message">
				<label class="pm-sr-only" for="<?php echo esc_attr( $form_id ); ?>-message"><?php echo esc_html( $pm_message_label ); ?></label>
				<?php echo pm_icon( 'edit', 'pm-field__icon pm-field__icon--top' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<textarea id="<?php echo esc_attr( $form_id ); ?>-message" name="pm_message" rows="2" placeholder="<?php echo esc_attr( $pm_message_ph ); ?>"></textarea>
			</div>
		<?php endif; ?>

		<button class="pm-btn pm-btn--gold pm-form__submit" type="submit">
			<span class="pm-spinner" aria-hidden="true"></span>
			<span class="pm-form__copy"><?php echo esc_html( $args['button'] ); ?></span>
			<?php echo pm_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>
	</div>

	<div class="pm-form__panel pm-form__panel--phone" data-pm-form-panel="phone" hidden>
		<div class="pm-call-card">
			<span class="pm-call-card__icon"><?php echo pm_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="pm-call-card__eyebrow">مشاورهٔ تلفنی</span>
			<strong class="pm-call-card__number" dir="ltr"><?php echo esc_html( $pm_phone_number ); ?></strong>
			<p><?php echo esc_html( $pm_phone_note ); ?></p>
			<a class="pm-btn pm-btn--gold pm-call-card__btn" href="tel:<?php echo esc_attr( pm_to_en_digits( $pm_phone_number ) ); ?>">
				<?php echo pm_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				تماس با آکادمی
			</a>
			<small><?php echo esc_html( $pm_phone_hours ); ?></small>
		</div>
	</div>

	<?php if ( ! empty( $args['show_benefits'] ) ) : ?>
		<div class="pm-form__benefits">
			<span><?php echo pm_icon( 'headset' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> مشاورهٔ مستقیم با استاد</span>
			<span><?php echo pm_icon( 'level' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> ارزیابی تخصصی سطح</span>
			<span><?php echo pm_icon( 'road' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> مسیر آموزشی شخصی‌سازی‌شده</span>
		</div>
	<?php endif; ?>
</form>
