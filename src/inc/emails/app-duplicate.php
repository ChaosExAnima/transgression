<p>Hi there <?php echo esc_html( $params['user']->display_name ); ?>,</p>
<p>We got your application... but you were already approved!</p>
<p>Unless there is an evil clone situation going down, you can score your tickets here:</p>
<p><button href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">Tickets</button></p>
