<?php
/**
 * Plugin Name: Force User Email Update
 * Description: Forces Users to Update their Email.
 * Version: 1.0
 * Author: Muhammad Haris
 */

// -----------------------------
// PLUGIN ACTIVATION HOOK
// -----------------------------
register_activation_hook(__FILE__, 'hrs_plugin_activate');

function hrs_plugin_activate() {
    // Example: Add default options or create database tables
    if (!get_option('hrs_plugin_options')) {
        $default_options = [
            'force_email_update' => true,
            'email_update_page'  => '', // you can store a page ID for redirect
        ];
        add_option('hrs_plugin_options', $default_options);
    }

    // Example: Log activation
    error_log('HRS Email Update plugin activated on ' . date('Y-m-d H:i:s'));
}

// -----------------------------
// PLUGIN DEACTIVATION HOOK
// -----------------------------
register_deactivation_hook(__FILE__, 'hrs_plugin_deactivate');

function hrs_plugin_deactivate() {
    // Example: Cleanup tasks
    // You may choose to remove options or just leave them
    // delete_option('hrs_plugin_options');

    // Example: Log deactivation
    error_log('HRS Email Update plugin deactivated on ' . date('Y-m-d H:i:s'));
}


// Constants

/**
 * Cutoff date: Only apply redirect logic for users registered after this date.
 * Format: YYYY-MM-DD HH:MM:SS
 */
define('EMAIL_REDIRECT_CUTOFF', '2025-11-07 00:00:00');




/**
 * Shortcode: [email_update_form password="yes|no" old_email="yes|no"]
 * Merged flexible form generator with optional password & old-email fields.
 */

function hrs_email_update_form($atts) {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to update your email.</p>';
    }

    // Shortcode parameters
    $atts = shortcode_atts([
        'password'  => 'no',
        'old_email' => 'no',
    ], $atts);

    $require_password  = ($atts['password'] === 'yes');
    $show_old_email    = ($atts['old_email'] === 'yes');

    $user = wp_get_current_user();
    $message = '';

    // Handle form POST
    if (isset($_POST['hrs_submit']) && isset($_POST['hrs_nonce']) && wp_verify_nonce($_POST['hrs_nonce'], 'hrs_update_email')) {

        $new_email = sanitize_email($_POST['hrs_new_email']);

        if (empty($new_email)) {
            $message = '<p style="color:red;">Bitte geben Sie eine gültige E-Mail ein.</p>';

        } elseif (!is_email($new_email)) {
            $message = '<p style="color:red;">Die eingegebene E-Mail Adresse ist ungültig.</p>';

        } elseif (email_exists($new_email)) {
            $message = '<p style="color:red;">Diese E-Mail Adresse wird bereits verwendet.</p>';

        } elseif ($require_password && !wp_check_password($_POST['hrs_password'], $user->user_pass, $user->ID)) {
            $message = '<p style="color:red;">Das Passwort ist falsch.</p>';

        } else {

            // Update email
            $result = wp_update_user([
                'ID'         => $user->ID,
                'user_email' => $new_email
            ]);

            if (is_wp_error($result)) {
                $message = '<p style="color:red;">Fehler beim Aktualisieren der E-Mail Adresse.</p>';

            } else {

                // SUCCESS MESSAGE (Your requested text)
                $message = '
				<style>
					.hrs-success .hrs-heading
					{
						text-align: center;
						font: 700 40px/1.1 "Barlow";
						color: #ECCA7E;
					}
						.hrs-success .hrs-heading+p{
						text-align:center;
			}
				</style>
                <div class="hrs-success">
                    <h2 class="hrs-heading">Vielen Dank</h2>
                    <p>Sie erhalten in den nächsten Minuten eine Bestätigung zur E-Mail Aktualisierung 
                    an die von Ihnen neu hinterlegte E-Mail Adresse. Bitte prüfen Sie auch Ihren Spam Ordner.</p>
                </div>';

                hrs_send_email_update_confirmation($user->ID, $new_email);


                // Return only success message
                return $message;
            }
        }
    }

    // Render form
    ob_start();
    ?>
	<style>
		.hrs-email-update-form label {
			display: none;
		}
		.hrs-email-update-form .hrs-heading{
			text-align: center;
			font: 700 40px/1.1 "Barlow";
			color: #ECCA7E;
		}
		.hrs-email-update-form button[type="submit"] {
			width: 100%;
			border-color: #ECCA7E;
			background: #ECCA7E;
			color: #fff;
			font-size: 18px;
			font-weight: 700;
		}


	</style>
    <div class="hrs-email-update-form">
        <?php echo $message; ?>

        <form method="POST">
            <?php wp_nonce_field('hrs_update_email', 'hrs_nonce'); ?>
			<h3 class="hrs-heading">
				E-Mail aktualisieren
			</h3>
            <?php if ($show_old_email): ?>
                <p>
                    <label>Aktuelle E-Mail:</label><br>
                    <input type="text" value="<?php echo esc_attr($user->user_email); ?>" disabled />
                </p>
            <?php endif; ?>

            <p>
                <label>Neue E-Mail:</label><br>
                <input type="email" name="hrs_new_email" required placeholder="E-Mail" />
            </p>

            <?php if ($require_password): ?>
                <p>
                    <label>Passwort:</label><br>
                    <input type="password" name="hrs_password" required placeholder="Passwort" />
                </p>
            <?php endif; ?>

            <p>
                <button type="submit" name="hrs_submit">Weiter</button>
            </p>

        </form>
    </div>

    <?php
    return ob_get_clean();
}

add_shortcode('email_update_form', 'hrs_email_update_form');

// Function for Sending Mail
function hrs_send_email_update_confirmation($user_id, $new_email) {

    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    $username = $user->user_login;

    // Email subject
    $subject = "Bestätigung der E-Mail Aktualisierung";

    // HTML Email body
    $body  = '<!DOCTYPE html>
				<html>
				<head>
				<meta charset="UTF-8">
				<title>Email Aktualisierung</title>
				</head>
					<body style="font-family:Arial,sans-serif;line-height:1.5;color:#333;">
					<div style="max-width:600px;margin:0 auto;padding:20px;border:1px solid #ddd;border-radius:5px;">
						<h2 style="color:#2c3e50;">Vielen Dank, ' . esc_html($username) . '!</h2>
						<p>Sie erhalten in den nächsten Minuten eine Bestätigung zur E-Mail Aktualisierung an die von Ihnen neu hinterlegte E-Mail Adresse. Bitte prüfen Sie auch Ihren Spam Ordner.</p>
						<hr style="border:none;border-top:1px solid #eee;margin:20px 0;">
						<p><strong>Benutzername:</strong> ' . esc_html($username) . '</p>
						<p>Unter der folgenden Adresse kannst du dein Passwort festlegen:</p>
						<p><a href="https://forstbetriebsgemeinschaft-nuernbergerland.de/login-form" target="_blank" style="color:#2980b9;">Passwort zurücksetzen</a></p>
						<p><strong>Neue E-Mail Adresse:</strong> ' . esc_html($new_email) . '</p>
						<p>Viele Grüße,<br>Forstbetriebsgemeinschaft Nürnberger Land</p>
					</div>
					</body>	
				</html>';

    // Headers for HTML email
    $headers = [
        'Content-Type: text/html; charset=UTF-8'
    ];

    // WordPress mail
    return wp_mail($new_email, $subject, $body, $headers);
}



//  Other Hooks
/**
 * Smart Redirect System for Unlimited Elements Login Form
 * Works with /redirect-check/ page and shortcode [redirect_check]
 * Applies only to users registered after a specific cutoff date.
 */




/**
 * Add 'email_changed' meta for new users registered after cutoff
 */
add_action('user_register', function($user_id) {
    $user = get_userdata($user_id);
    if (!$user) return;

    if (strtotime($user->user_registered) >= strtotime(EMAIL_REDIRECT_CUTOFF)) {
        add_user_meta($user_id, 'email_changed', '0', true);
        error_log("User {$user_id} registered after cutoff → email_changed = 0");
    }
});


/**
 * Detect email change and mark it in meta
 */
add_action('profile_update', function($user_id, $old_user_data) {
    $user = get_userdata($user_id);
    if (!$user) return;

    // Only for users after cutoff
    if (strtotime($user->user_registered) < strtotime(EMAIL_REDIRECT_CUTOFF)) return;

    if ($user->user_email !== $old_user_data->user_email) {
        update_user_meta($user_id, 'email_changed', '1');
        error_log("User {$user_id} changed email → email_changed = 1");
    }
}, 10, 2);


/**
 * Template Redirect Logic
 * This runs BEFORE the page content, so headers can still be sent.
 */
add_action('template_redirect', function() {
    if (!is_page('redirect-check')) return;

    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/'));
        exit;
    }

    $user = wp_get_current_user();
    if (!$user) {
        wp_safe_redirect(home_url('/'));
        exit;
    }

    // Only apply for new users
    if (strtotime($user->user_registered) < strtotime(EMAIL_REDIRECT_CUTOFF)) {
        wp_safe_redirect(home_url('/'));
        exit;
    }

    $email_changed = get_user_meta($user->ID, 'email_changed', true);
    $email_changed = ($email_changed === '1') ? '1' : '0';

    $redirect_url = ($email_changed === '1')
        ? home_url('/downloads/')
        : home_url('/e-mail-aktualisieren/');

    error_log("redirect-check: user {$user->ID} → email_changed = {$email_changed}, redirecting to {$redirect_url}");

    wp_safe_redirect($redirect_url);
    exit;
});


/**
 * Optional shortcode (for compatibility, not required)
 */
add_shortcode('redirect_check', function() {
    // In case redirect failed (rare), show message or fallback link
    return '<p style="text-align:center;padding:2em;">Redirecting... <a href="' . esc_url(home_url('/')) . '">Click here if not redirected.</a></p>';
});


/**
 * Redirect to homepage on logout
 */
add_action('wp_logout', function() {
    wp_safe_redirect(home_url('/'));
    exit;
});


?>