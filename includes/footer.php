</main>
<footer class="site-footer">
  <p>&copy; <?= date('Y') ?> <?= e(HOTEL_NAME) ?></p>
  <nav aria-label="Footer"><a href="rooms.php">Rooms</a><a href="experiences.php">Experiences</a><a href="manage.php">Manage reservation</a><a href="contact.php">Contact</a></nav>
  <button type="button" id="motion-toggle" aria-live="polite">Motion: Signature</button>
</footer>
<script type="module" src="assets/js/main.js"></script>
<?php if (!empty($js)): ?><script type="module" src="<?= e($js) ?>"></script><?php endif; ?>
</body>
</html>
