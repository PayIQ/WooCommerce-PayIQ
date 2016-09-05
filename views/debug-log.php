<?php

?>
<h3>Debug log</h3>

		<br/>
<textarea class="debug_log_view" disabled><?php echo PayIQ::get_debug_log(); ?></textarea>
<style>
    .debug_log_view[disabled] {
        verflow: auto;
        height: 80vh;
        max-height: 100vh;
        width: 95%;
        padding: 20px 2%;
        color: #000;
    }
</style>
<script>
    jQuery('.debug_log_view').css('height', jQuery( window ).height() - (jQuery('.debug_log_view').offset().top + 100));
</script>