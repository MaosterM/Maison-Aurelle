</main>
<footer class="site-footer">
  <p>&copy; <?= date('Y') ?> <?= e(HOTEL_NAME) ?>. A fictional hotel and personal learning project: no real bookings are made.</p>
  <nav aria-label="Footer"><a href="rooms.php">Rooms</a><a href="experiences.php">Experiences</a><a href="manage.php">Manage reservation</a><a href="contact.php">Contact</a><a href="login.php">Staff sign in</a></nav>
  <button type="button" id="motion-toggle" aria-live="polite">Motion: Signature</button>
</footer>
<?php if (empty($hideChat)) include __DIR__ . '/chat-widget.php'; ?>
<script type="module" src="assets/js/main.js"></script>
<?php if (empty($hideChat)): ?><script type="module" src="assets/js/chat.js"></script><?php endif; ?>
<?php if (!empty($js)): ?><script type="module" src="<?= e($js) ?>"></script><?php endif; ?>
</body>
</html>
