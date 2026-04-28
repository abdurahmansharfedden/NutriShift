<?php
// =============================================================================
// footer.php — Shared HTML Footer
// =============================================================================
?>
</main><!-- /main-content -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> NutriShift &mdash; Track by cycle, not the clock.</p>
</footer>

<!-- TEACHING NOTE: We load JavaScript at the BOTTOM of the body (before </body>)
     so that the browser can render the HTML first without being blocked by JS.
     This improves perceived page load speed. -->
<script src="assets/js/main.js"></script>
</body>
</html>
