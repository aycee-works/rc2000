<footer class="site-footer">
  <div class="footer-main">
    <div class="footer-identity">
      <a class="footer-brand" href="<?= e(app_url('public/home.php')) ?>" aria-label="Reading Club 2000 home">
        <img class="brand-logo" src="<?= e(app_url('public/assets/images/icons/logo.png')) ?>" alt="" width="38" height="38">
        <span><strong>Reading Club 2000</strong><small>A neighborhood library built around sharing stories.</small></span>
      </a>
      <div class="footer-connect">
        <nav aria-label="Footer navigation">
          <a href="<?= e(app_url('public/about.php')) ?>">About the Club</a>
          <a href="<?= e(app_url('public/contact.php')) ?>">Contact Us</a>
          <a href="<?= e(app_url('public/inquiry.php')) ?>">Inquiries</a>
          <a href="<?= e(app_url('public/home.php')) ?>#announcements">Announcements</a>
          <a class="footer-facebook" href="https://www.facebook.com/ReadingClub2000" target="_blank" rel="noopener noreferrer">
            <img src="<?= e(app_url('public/assets/images/icons/homefb_icon.png')) ?>" alt="" width="22" height="22"> Facebook
          </a>
        </nav>
      </div>
    </div>
    <section class="footer-contact" aria-labelledby="footer-feedback-title">
      <h2 id="footer-feedback-title">Give Us a Feedback</h2>
      <p>Tell us what you think about our website. Your feedback goes straight to our admins.</p>
      <button type="button" class="button button-gold" data-feedback-open>Give Us a Feedback</button>
    </section>
    <div class="footer-bottom">
      <small>© <?= date('Y') ?> Reading Club 2000 Mang Nanie. All rights reserved. No part of this website may be copied, reproduced, or redistributed without written permission.</small>
      <details class="footer-terms">
        <summary>Terms and Conditions</summary>
        <ol>
          <li>Use this website respectfully and provide accurate information when submitting forms.</li>
          <li>All website content belongs to Reading Club 2000 or its respective contributors.</li>
          <li>Submitted feedback and inquiries may be reviewed by the Reading Club 2000 administrators.</li>
        </ol>
      </details>
      <span class="footer-copyright"><img src="<?= e(app_url('public/assets/images/icons/copyright.png')) ?>" alt="" aria-hidden="true" width="16" height="16"><span>Copyright</span></span>
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
<script src="<?= e(app_url('public/assets/js/site.js?v=4')) ?>"></script>
</body>
</html>
