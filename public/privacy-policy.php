<?php
require_once __DIR__ . '/../includes/functions.php';

page_start('Privacy Policy', ['class' => 'privacy-policy-page']);
?>
<main class="privacy-policy">
  <section class="privacy-policy-hero">
    <span class="eyebrow">Reading Club 2000</span>
    <h1>Privacy Policy</h1>
    <p>This page explains what information this website receives, why it is used, and how to contact us with a privacy question.</p>
    <p class="privacy-policy-updated">Last updated: November 20, 2026</p>
  </section>

  <div class="privacy-policy-layout">
    <nav class="privacy-policy-nav" aria-label="Privacy policy sections">
      <strong>On this page</strong>
      <a href="#information">Information you provide</a>
      <a href="#use">How information is used</a>
      <a href="#facebook">Facebook and external services</a>
      <a href="#storage">Cookies and browser storage</a>
      <a href="#sharing">Access and sharing</a>
      <a href="#retention">Retention and security</a>
      <a href="#requests">Your questions and requests</a>
    </nav>

    <article class="privacy-policy-content">
      <section id="information">
        <h2>Information you provide</h2>
        <p>When you use the website, you may choose to provide information through its forms, including:</p>
        <ul>
          <li><strong>Inquiries:</strong> your name, email address, optional phone number, inquiry category, subject, and message.</li>
          <li><strong>Donation reports:</strong> your name and email address, optional contact details, donation amount and date, payment method, transaction reference, and any payment proof you upload. Book donation forms may also ask for book details and notes.</li>
          <li><strong>Website feedback:</strong> your name, email address, and feedback message.</li>
        </ul>
        <p>Please do not include passwords, full payment-card numbers, or other information that a form does not request.</p>
      </section>

      <section id="use">
        <h2>How information is used</h2>
        <p>Reading Club 2000 administrators use submitted information to respond to inquiries and feedback, issue and track inquiry references, review donation reports, verify donations, and maintain the website and its records.</p>
      </section>

      <section id="facebook">
        <h2>Facebook and external services</h2>
        <p>The website links to and may display public content from the Reading Club 2000 Facebook Page. When a Facebook embed or other third-party content loads, that provider may receive technical information from your browser under its own privacy policy.</p>
        <p>If automatic Facebook post updates are enabled, the website may retrieve and temporarily store publicly available post details—such as text, publication date, image, and link—to show them in Community Updates. The website does not use Facebook Login for visitors and does not request access to private posts or visitors’ Facebook profiles for this purpose. Posts link back to Facebook, where Meta’s terms and privacy policy apply.</p>
        <p>Some pages may also include services such as Google Fonts, YouTube, or Google Maps. Those services handle information according to their own policies.</p>
      </section>

      <section id="storage">
        <h2>Cookies and browser storage</h2>
        <p>The website uses a session cookie for functions such as maintaining a form session and protecting submissions. It also stores your display theme preference in your browser’s local storage. Third-party embeds may use their own browser technologies when loaded.</p>
      </section>

      <section id="sharing">
        <h2>Access and sharing</h2>
        <p>Information submitted through the website is available to authorized Reading Club 2000 administrators who need it to handle the related request or donation report. The website may also rely on service providers for hosting, database, file storage, or email delivery. Information is not sold through this website.</p>
      </section>

      <section id="retention">
        <h2>Retention and security</h2>
        <p>Submitted information and related records are kept for as long as needed to handle requests, review donations, maintain appropriate records, and operate and protect the website. The website uses safeguards intended to protect submitted information, but no online service can guarantee absolute security.</p>
      </section>

      <section id="requests">
        <h2>Your questions and requests</h2>
        <p>For a question about this policy or a request concerning information you submitted, contact Reading Club 2000 through the <a href="<?= e(app_url('public/inquiry.php')) ?>">inquiry form</a> or the <a href="<?= e(app_url('public/contact.php')) ?>">Contact Us</a> page. Please include enough information for the administrators to locate your submission, and do not send passwords or payment-card details.</p>
      </section>

      <section>
        <h2>Changes to this policy</h2>
        <p>This policy may be updated when the website’s features or information practices change. The date at the top of this page indicates when it was last updated.</p>
      </section>
    </article>
  </div>
</main>
<?php page_end(); ?>
