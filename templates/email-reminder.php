<?php
// Variables injected by STS_Abandoned_Cart_Email::render_template():
// $cart_row, $items, $recovery_url, $pixel_url, $footer_html, $logo_url, $store_name, $currency_sym
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?php echo esc_html( $store_name ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f5f0e8;font-family:Arial,sans-serif;direction:rtl;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0e8;padding:30px 0;">
<tr><td align="center">

  <!-- Card -->
  <table width="580" cellpadding="0" cellspacing="0" style="background:#fffcf5;border-radius:12px;overflow:hidden;border:1px solid #e8e0d0;max-width:100%;">

    <!-- Header / Logo -->
    <tr>
      <td style="background:#fffcf5;padding:28px 40px 20px;text-align:center;border-bottom:1px solid #e8e0d0;">
        <?php if ( $logo_url ) : ?>
        <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $store_name ); ?>"
             style="max-height:60px;max-width:200px;display:block;margin:0 auto;">
        <?php else : ?>
        <span style="font-size:22px;font-weight:700;color:#2c2c2c;"><?php echo esc_html( $store_name ); ?></span>
        <?php endif; ?>
      </td>
    </tr>

    <!-- Headline -->
    <tr>
      <td style="padding:32px 40px 16px;text-align:center;">
        <h1 style="margin:0 0 8px;font-size:22px;color:#2c2c2c;line-height:1.3;">
          שכחתם משהו? 🛒
        </h1>
        <p style="margin:0;font-size:15px;color:#555;line-height:1.6;">
          השארתם פריטים יפים בעגלה שלכם ב<?php echo esc_html( $store_name ); ?>.<br>
          העגלה שומרת עליהם — השלימו את ההזמנה לפני שייגמרו.
        </p>
      </td>
    </tr>

    <!-- Cart items -->
    <?php if ( ! empty( $items ) ) : ?>
    <tr>
      <td style="padding:16px 40px;">
        <table width="100%" cellpadding="0" cellspacing="0">
          <?php foreach ( $items as $item ) : ?>
          <tr>
            <td style="padding:10px 0;border-bottom:1px solid #f0ebe0;vertical-align:middle;">
              <?php if ( ! empty( $item['image_url'] ) ) : ?>
              <img src="<?php echo esc_url( $item['image_url'] ); ?>" alt=""
                   style="width:56px;height:56px;object-fit:cover;border-radius:6px;float:right;margin-left:14px;display:block;">
              <?php endif; ?>
              <div style="overflow:hidden;">
                <a href="<?php echo esc_url( $item['permalink'] ?? '#' ); ?>"
                   style="font-size:15px;font-weight:600;color:#2c2c2c;text-decoration:none;display:block;">
                  <?php echo esc_html( $item['name'] ); ?>
                </a>
                <span style="font-size:13px;color:#888;">כמות: <?php echo esc_html( $item['qty'] ); ?></span>
                <?php if ( ! empty( $item['price'] ) && $item['price'] > 0 ) : ?>
                <span style="font-size:13px;color:#555;margin-right:12px;">
                  <?php echo esc_html( $currency_sym . number_format( (float) $item['price'], 2 ) ); ?>
                </span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
      </td>
    </tr>
    <?php endif; ?>

    <!-- Total -->
    <?php if ( ! empty( $cart_row->cart_total ) && (float) $cart_row->cart_total > 0 ) : ?>
    <tr>
      <td style="padding:8px 40px 0;text-align:left;">
        <span style="font-size:15px;font-weight:700;color:#2c2c2c;">
          סה"כ: <?php echo esc_html( $currency_sym . number_format( (float) $cart_row->cart_total, 2 ) ); ?>
        </span>
      </td>
    </tr>
    <?php endif; ?>

    <!-- CTA -->
    <tr>
      <td style="padding:28px 40px;text-align:center;">
        <a href="<?php echo esc_url( $recovery_url ); ?>"
           style="display:inline-block;background:#2c2c2c;color:#fffcf5;text-decoration:none;
                  padding:14px 36px;border-radius:6px;font-size:16px;font-weight:700;letter-spacing:0.5px;">
          השלמת ההזמנה
        </a>
      </td>
    </tr>

    <!-- Footer / compliance -->
    <tr>
      <td style="padding:0 40px 28px;">
        <?php echo $footer_html; ?>
      </td>
    </tr>

  </table>
  <!-- /Card -->

</td></tr>
</table>

<!-- Open tracking pixel (best-effort, often blocked) -->
<img src="<?php echo esc_url( $pixel_url ); ?>" width="1" height="1" alt="" style="display:none;">

</body>
</html>
