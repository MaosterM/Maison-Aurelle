<?php $chatLive = LLM_API_KEY !== ''; ?>
<noscript><a class="chat-fab chat-fab--link" href="contact.php#inquiry"><span>Contact us</span></a></noscript>
<div class="chat" id="chat" data-csrf="<?= e(csrf_token()) ?>" data-live="<?= $chatLive ? '1' : '0' ?>">
  <button class="chat-fab" type="button" id="chat-open" aria-expanded="false" aria-controls="chat-panel"><span>Concierge</span></button>
  <div class="chat-panel" id="chat-panel" role="dialog" aria-modal="true" aria-labelledby="chat-title" inert>
    <header class="chat-head">
      <div>
        <h2 id="chat-title">Concierge</h2>
        <p><?= $chatLive ? 'AI assistant. Prices are checked live.' : 'Automated answers.' ?></p>
      </div>
      <button class="chat-link" type="button" id="chat-reset">New chat</button>
      <button class="chat-link" type="button" id="chat-close">Close</button>
    </header>
    <div class="chat-log" id="chat-log" role="log" aria-live="polite" aria-label="Conversation" tabindex="0"></div>
    <p class="sr-only" id="chat-status" role="status"></p>
    <div class="chat-chips" id="chat-chips"></div>
    <form class="chat-form" id="chat-form">
      <label class="sr-only" for="chat-input">Your message</label>
      <textarea id="chat-input" rows="1" maxlength="500" placeholder="Ask about your stay" autocomplete="off"></textarea>
      <button class="chat-send" type="submit">Send</button>
    </form>
  </div>
</div>
