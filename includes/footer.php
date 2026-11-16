<footer class="site-footer">
  <div class="footer-brand">
    <img class="brand-logo footer-logo" src="<?= e(app_url('public/assets/images/logo.png')) ?>" alt="" width="28" height="28">
    <div>
      <strong>Reading Club 2000</strong>
      <p>A neighborhood library built around sharing stories.</p>
    </div>
  </div>
  <div class="footer-contact">
    <h2>Give Us a Feedback</h2>
    <p>Tell us what you think about our website. Your feedback goes straight to our admins.</p>
    <button type="button" class="button button-gold" data-feedback-open>Give Us a Feedback</button>
    <div class="socials" aria-label="Social links">
      <a href="mailto:hello@readingclub2000.local" aria-label="Email">✉</a>
    </div>
  </div>
</footer>
<dialog class="feedback-dialog" id="feedback-dialog" aria-labelledby="feedback-title">
  <div class="feedback-body">
    <div class="feedback-head">
      <div>
        <h2 id="feedback-title">Give Us a Feedback</h2>
        <p>How is your experience with our website? Tell us about the design and how easy it is to use.</p>
      </div>
      <button type="button" class="feedback-close" data-feedback-close aria-label="Close">×</button>
    </div>
    <p class="feedback-status" id="feedback-status" role="status" hidden></p>
    <form id="feedback-form" action="/rc2000/public/feedback.php" method="post">
      <?= csrf_field() ?>
      <label>Name
        <input name="name" required maxlength="120">
      </label>
      <label>Email
        <input type="email" name="email" required maxlength="150">
      </label>
      <label>Your feedback
        <textarea name="message" rows="5" required minlength="10" maxlength="5000"></textarea>
      </label>
      <button class="button wide" type="submit">Send feedback</button>
    </form>
  </div>
</dialog>
<script src="<?= e(app_url('public/assets/js/site.js?v=3')) ?>"></script>
</body>
</html>