<?php
// Variables: $email (string), $found (bool)
if ( ! defined( 'ABSPATH' ) ) { exit; }
$store_name = get_option( 'sts_ac_sender_name', get_bloginfo( 'name' ) );
$logo_url   = get_option( 'sts_ac_logo_url', home_url( '/wp-content/uploads/2025/12webp/Logo-pearl.webp' ) );
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>הסרה מרשימת התזכורות — <?php echo esc_html( $store_name ); ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #f5f0e8; font-family: Arial, sans-serif; direction: rtl; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  .card { background: #fffcf5; border: 1px solid #e8e0d0; border-radius: 12px; padding: 48px 40px; max-width: 460px; width: 100%; text-align: center; }
  .logo { max-height: 52px; max-width: 180px; margin: 0 auto 28px; display: block; }
  .check { font-size: 48px; margin-bottom: 16px; }
  h1 { font-size: 22px; color: #2c2c2c; margin-bottom: 12px; }
  p { font-size: 14px; color: #666; line-height: 1.7; margin-bottom: 20px; }
  a.btn { display: inline-block; background: #2c2c2c; color: #fffcf5; text-decoration: none; padding: 12px 32px; border-radius: 6px; font-size: 14px; font-weight: 700; }
</style>
</head>
<body>
<div class="card">
  <?php if ( $logo_url ) : ?>
  <img class="logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $store_name ); ?>">
  <?php endif; ?>

  <div class="check">✓</div>

  <h1>הוסרתם מרשימת התזכורות</h1>

  <?php if ( $email ) : ?>
  <p>
    הכתובת <strong><?php echo esc_html( $email ); ?></strong> הוסרה מרשימת תזכורות העגלה הנטושה של <?php echo esc_html( $store_name ); ?>.
    לא תקבלו יותר הודעות אלה.
  </p>
  <?php else : ?>
  <p>לא תקבלו יותר תזכורות עגלה נטושה מ<?php echo esc_html( $store_name ); ?>.</p>
  <?php endif; ?>

  <a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">חזרה לחנות</a>
</div>
</body>
</html>
