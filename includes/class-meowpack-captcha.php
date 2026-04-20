<?php
/**
 * Simple Captcha — Math equation + Honeypot anti-spam.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_Captcha
 *
 * Provides:
 *  1. Math captcha (e.g.  "Berapa 12 + 5?  [___]")
 *  2. Honeypot hidden field (invisible to real users, filled by bots)
 *
 * Hookable locations:
 *  - WordPress comment form
 *  - WordPress login / registration / lost-password forms
 *  - WooCommerce checkout (if active)
 *
 * Settings:
 *  enable_captcha           : '0'|'1'
 *  captcha_type             : 'math'|'honeypot'|'both'
 *  captcha_on_comments      : '0'|'1'
 *  captcha_on_login         : '0'|'1'
 *  captcha_on_register      : '0'|'1'
 *  captcha_on_lostpassword  : '0'|'1'
 */
class MeowPack_Captcha {

	/** @var string Transient prefix for captcha answers. */
	const TRANSIENT_PREFIX = 'meowpack_captcha_';

	/** @var int Seconds a captcha answer is valid. */
	const TTL = 600; // 10 minutes.

	/**
	 * Constructor — register hooks based on settings.
	 */
	public function __construct() {
		if ( '1' !== MeowPack_Database::get_setting( 'enable_captcha', '0' ) ) {
			return;
		}

		// Comments.
		if ( '1' === MeowPack_Database::get_setting( 'captcha_on_comments', '1' ) ) {
			add_action( 'comment_form_after_fields',     array( $this, 'render_field' ) );
			add_action( 'comment_form_logged_in_after',  array( $this, 'render_field' ) );
			add_filter( 'preprocess_comment',            array( $this, 'validate_comment' ) );
		}

		// Login.
		if ( '1' === MeowPack_Database::get_setting( 'captcha_on_login', '0' ) ) {
			add_action( 'login_form',                    array( $this, 'render_field' ) );
			add_filter( 'authenticate',                  array( $this, 'validate_login' ), 30, 1 );
		}

		// Registration.
		if ( '1' === MeowPack_Database::get_setting( 'captcha_on_register', '0' ) ) {
			add_action( 'register_form',                 array( $this, 'render_field' ) );
			add_filter( 'registration_errors',           array( $this, 'validate_registration' ), 10, 1 );
		}

		// Lost password.
		if ( '1' === MeowPack_Database::get_setting( 'captcha_on_lostpassword', '0' ) ) {
			add_action( 'lostpassword_form',             array( $this, 'render_field' ) );
			add_action( 'lostpassword_post',             array( $this, 'validate_lostpassword' ) );
		}

		// WooCommerce checkout.
		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_field' ) );
			add_action( 'woocommerce_checkout_process',            array( $this, 'validate_woo_checkout' ) );
		}

		// Enqueue CSS for the captcha widget.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	// -----------------------------------------------------------------------
	// Captcha Generation
	// -----------------------------------------------------------------------

	/**
	 * Generate a new math question and store the answer in a transient.
	 *
	 * @return array{ token: string, question: string, answer: int }
	 */
	public static function generate() {
		$a  = wp_rand( 2, 19 );
		$b  = wp_rand( 1, $a ); // Ensure $b<=a so subtraction is always positive.
		$op = wp_rand( 0, 1 ) ? '+' : '-';

		$answer = ( '+' === $op ) ? ( $a + $b ) : ( $a - $b );
		/* translators: %d and %s are numbers/operator in a math captcha question */
		$question = sprintf( __( 'Berapa %1$d %2$s %3$d?', 'meowpack' ), $a, $op, $b );

		// Unique token per form render.
		$token = wp_generate_uuid4();
		set_transient( self::TRANSIENT_PREFIX . $token, $answer, self::TTL );

		return array(
			'token'    => $token,
			'question' => $question,
			'answer'   => $answer,
		);
	}

	/**
	 * Verify a submitted math captcha answer.
	 *
	 * @param string $token  Token from hidden field.
	 * @param string $answer User-submitted answer.
	 * @return bool
	 */
	public static function verify( $token, $answer ) {
		$token   = sanitize_text_field( $token );
		$stored  = get_transient( self::TRANSIENT_PREFIX . $token );

		// Delete so it can't be replayed.
		delete_transient( self::TRANSIENT_PREFIX . $token );

		if ( false === $stored ) {
			return false; // Expired or forged token.
		}

		return ( (int) $stored === (int) $answer );
	}

	// -----------------------------------------------------------------------
	// Rendering
	// -----------------------------------------------------------------------

	/**
	 * Render the captcha widget HTML (math field + honeypot).
	 */
	public function render_field() {
		$type = MeowPack_Database::get_setting( 'captcha_type', 'math' );
		echo '<div class="meowpack-captcha-wrap">';

		if ( in_array( $type, array( 'math', 'both' ), true ) ) {
			$captcha = self::generate();
			echo '<p class="meowpack-captcha-math">';
			echo '<label for="meowpack_captcha_answer">'
				. esc_html( $captcha['question'] )
				. ' <span class="required" aria-hidden="true">*</span></label>';
			echo '<input type="text" id="meowpack_captcha_answer" name="meowpack_captcha_answer"'
				. ' inputmode="numeric" autocomplete="off" required'
				. ' aria-required="true" aria-label="' . esc_attr__( 'Jawab pertanyaan verifikasi', 'meowpack' ) . '"'
				. ' style="max-width:80px;" />';
			echo '<input type="hidden" name="meowpack_captcha_token" value="' . esc_attr( $captcha['token'] ) . '" />';
			echo '</p>';
		}

		if ( in_array( $type, array( 'honeypot', 'both' ), true ) ) {
			// Hidden honeypot — bots fill this, humans don't see it.
			echo '<p class="meowpack-hp" aria-hidden="true" style="display:none!important;position:absolute;left:-9999px;">';
			echo '<label for="meowpack_hp_field">' . esc_html__( 'Leave this field empty', 'meowpack' ) . '</label>';
			echo '<input type="text" id="meowpack_hp_field" name="meowpack_hp_field" tabindex="-1" autocomplete="off" />';
			echo '</p>';
		}

		echo '</div>';
	}

	// -----------------------------------------------------------------------
	// Validation Helpers
	// -----------------------------------------------------------------------

	/**
	 * Common validation logic shared across form types.
	 *
	 * @return string|true Error message or true on success.
	 */
	private function validate_submission() {
		$type = MeowPack_Database::get_setting( 'captcha_type', 'math' );

		// Honeypot check.
		if ( in_array( $type, array( 'honeypot', 'both' ), true ) ) {
			$hp = isset( $_POST['meowpack_hp_field'] ) ? sanitize_text_field( wp_unslash( $_POST['meowpack_hp_field'] ) ) : '';
			if ( ! empty( $hp ) ) {
				return __( 'Spam terdeteksi. Silakan coba lagi.', 'meowpack' );
			}
		}

		// Math captcha check.
		if ( in_array( $type, array( 'math', 'both' ), true ) ) {
			$token  = isset( $_POST['meowpack_captcha_token'] )  ? sanitize_text_field( wp_unslash( $_POST['meowpack_captcha_token'] ) )  : '';
			$answer = isset( $_POST['meowpack_captcha_answer'] ) ? sanitize_text_field( wp_unslash( $_POST['meowpack_captcha_answer'] ) ) : '';

			if ( empty( $token ) || empty( $answer ) ) {
				return __( 'Silakan jawab pertanyaan verifikasi.', 'meowpack' );
			}

			if ( ! self::verify( $token, $answer ) ) {
				return __( 'Jawaban verifikasi salah. Silakan coba lagi.', 'meowpack' );
			}
		}

		return true;
	}

	/**
	 * Validate comment form captcha.
	 *
	 * @param array $commentdata Comment data array.
	 * @return array
	 */
	public function validate_comment( $commentdata ) {
		// Skip for logged-in users submitting from the admin (XMLRPC etc.).
		if ( is_user_logged_in() && current_user_can( 'moderate_comments' ) ) {
			return $commentdata;
		}

		$result = $this->validate_submission();
		if ( true !== $result ) {
			wp_die( esc_html( $result ), esc_html__( 'Verifikasi gagal', 'meowpack' ), array( 'back_link' => true ) );
		}

		return $commentdata;
	}

	/**
	 * Validate login form captcha.
	 *
	 * @param WP_User|WP_Error|null $user Current authentication result.
	 * @return WP_User|WP_Error|null
	 */
	public function validate_login( $user ) {
		// Only validate on actual POST.
		if ( empty( $_POST['log'] ) ) {
			return $user;
		}

		$result = $this->validate_submission();
		if ( true !== $result ) {
			return new WP_Error( 'meowpack_captcha', esc_html( $result ) );
		}

		return $user;
	}

	/**
	 * Validate registration form.
	 *
	 * @param WP_Error $errors Registration errors object.
	 * @return WP_Error
	 */
	public function validate_registration( $errors ) {
		$result = $this->validate_submission();
		if ( true !== $result ) {
			$errors->add( 'meowpack_captcha', esc_html( $result ) );
		}
		return $errors;
	}

	/**
	 * Validate lost password form.
	 *
	 * @param WP_Error $errors Lost password errors.
	 */
	public function validate_lostpassword( $errors ) {
		$result = $this->validate_submission();
		if ( true !== $result ) {
			$errors->add( 'meowpack_captcha', esc_html( $result ) );
		}
	}

	/**
	 * Validate WooCommerce checkout.
	 */
	public function validate_woo_checkout() {
		$result = $this->validate_submission();
		if ( true !== $result ) {
			wc_add_notice( esc_html( $result ), 'error' );
		}
	}

	// -----------------------------------------------------------------------
	// Styles
	// -----------------------------------------------------------------------

	/**
	 * Enqueue minimal captcha styles.
	 */
	public function enqueue_styles() {
		$css = '
		.meowpack-captcha-wrap { margin: 12px 0; }
		.meowpack-captcha-math label { display: block; font-weight: 600; margin-bottom: 4px; }
		.meowpack-captcha-math input[type="text"] {
			border: 2px solid #8aadf4; border-radius: 6px; padding: 6px 10px;
			font-size: 1rem; width: 80px;
		}
		.meowpack-hp { display: none !important; }
		';
		wp_register_style( 'meowpack-captcha', false, array(), MEOWPACK_VERSION ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'meowpack-captcha' );
		wp_add_inline_style( 'meowpack-captcha', $css );
	}
}
