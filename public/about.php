<?php
require_once __DIR__ . '/../includes/functions.php';

// Replace this filename with an image placed in public/assets/images/.
$founderImage = 'circlephoto.jpg';
$founderImagePath = __DIR__ . '/assets/images/' . $founderImage;
page_start('About');
?>
<main>
  <section class="page-hero red">
    <div class="narrow">
      <h1>History of Reading Club 2000</h1>
      <p>Reading Club 2000 is an informal library set up by Hernando Guanlao outside his ancestral home in central Makati, Philippines. Hernando Guanlao, known by his nickname Nanie, wanted to encourage his local community to share his joy of reading.</p>
      <a class="button button-gold" href="<?= e(app_url('public/about.php#founder')) ?>">Read More</a>
    </div>
  </section>

  <section class="section about-person" id="founder" data-reveal>
    <?php if (is_file($founderImagePath)): ?>
      <img class="founder-portrait" src="<?= e(app_url('public/assets/images/' . $founderImage)) ?>" alt="Hernando “Mang Nanie” Guanlao" data-reveal>
    <?php else: ?>
      <div class="portrait-placeholder" aria-hidden="true" data-reveal>NG</div>
    <?php endif; ?>
    <div>
      <h2>Hernando “Mang Nanie” Guanlao</h2>
      <p class="founder-description">Mang Nanie is a former tax accountant, ice cream salesman and government employee. He placed books outside his door to see if anyone would borrow them. Many people did, and they donated other books in return, which grew the collection.</p>
      <p class="founder-description">His main purpose was to honor his parents, who passed on their habit of reading. He shares used and donated books at no cost and promotes education through literature. The collection has grown through the support of donors and now holds thousands of books.</p>
      <p class="founder-description">He also takes part in “book walking,” bringing storybooks to children. Today, Mang Nanie and his family continue welcoming readers and sharing books with the community.</p>
    </div>
  </section>

  <section class="section rc-history" id="history" data-reveal>
    <div class="history-heading">
      <span class="eyebrow">A COMMUNITY LIBRARY IN MAKATI</span>
      <h2>The story of RC2000</h2>
      <p>From a small collection outside a family home to an open invitation to read, Reading Club 2000 has grown through the generosity of its community.</p>
    </div>
    <div class="history-timeline">
      <article class="history-milestone">
        <span class="history-year">2000</span>
        <div><h3>A library begins at home</h3><p>Mang Nanie placed books outside his home on Balagtas Street in La Paz, Makati, making them freely available to neighbors and passersby.</p></div>
      </article>
      <article class="history-milestone">
        <span class="history-year">A growing collection</span>
        <div><h3>Readers become donors</h3><p>People borrowed books and brought more to share. Over time, community support turned the small collection into thousands of books.</p></div>
      </article>
      <article class="history-milestone">
        <span class="history-year">Today</span>
        <div><h3>Reading stays open to all</h3><p>Reading Club 2000 continues to welcome readers and share books at no cost, carrying forward Mang Nanie’s belief that everyone should have access to reading.</p></div>
      </article>
    </div>
  </section>

  <section class="section mission-vision" aria-label="Our mission and vision">
    <article class="info-card" data-reveal>
      <h2>Mission</h2>
      <p>The Reading Club is a community-based reading club focused on promoting literacy and free exchange of knowledge. To make books available to anyone who wants them; to save and give access to the true history of those who serve humanity through learning; and to lead young people into the love of learning that is purposeful, original, and upright. In keeping with this principle, we make every effort to present content that is accurate, informative, and respectful of all persons and sources that are included.</p>
    </article>
    <article class="info-card navy" data-reveal>
      <h2>Vision</h2>
      <p>The Reading Club hopes to see a society where books are not just a privilege but a common resource to be freely read and be a source of growth for all Filipinos irrespective of their age and background in knowledge, character and compassion. We hope that our readers of the future will see learning as a means to earn a diploma, but also as a way to add meaning and value to their life and to others.</p>
    </article>
  </section>

  <section class="section watch-section" data-reveal>
    <h2>Watch Us Here!</h2>
    <p>Get to know more about us through our videos.</p>
    <div class="video-wrapper">
        <div class="video-card">
            <iframe
                src="https://www.youtube.com/embed/SPi3Men4B5E"
                title="Video 1"
                allowfullscreen>
            </iframe>
        </div>
        <div class="video-card">
            <iframe
                src="https://www.youtube.com/embed/Z0ZyFZ40Cv8?start=3"
                title="Video 2"
                allowfullscreen>
            </iframe>
        </div>
    </div>
</section>

<section class="section visit-section" data-reveal>
  <div class="section-heading">
    <div><h2>Visit Us</h2><p>Drop by or find us on the map.</p></div>
  </div>
  <div class="visit-grid">
    <div class="visit-info-card">
      <p class="contact-detail">
        <img src="<?= e(app_url('public/assets/images/icons/contactpin_icon.png')) ?>" width="32" height="32" alt="">
        <span>1454 Balagtas St., Barangay La Paz<br>1204 Makati</span>
      </p>
      <p class="contact-detail">
        <img src="<?= e(app_url('public/assets/images/icons/contactcall_icon.png')) ?>" width="32" height="32" alt="">
        <a href="tel:+639176176486">0977 617 0486</a>
      </p>
      <div class="visit-socials">
        <span>Follow us:</span>
        <a href="#" aria-label="Facebook"><img src="<?= e(app_url('public/assets/images/icons/contactfb_icon.png')) ?>" width="32" height="32" alt=""></a>
      </div>
    </div>
    <div class="visit-map-card">
      <iframe title="Reading Club 2000 map" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=1454%20Balagtas%20St%20La%20Paz%20Makati&t=&z=16&ie=UTF8&iwloc=&output=embed"></iframe>
      <a class="button button-map-overlay" href="https://maps.google.com/?q=1454+Balagtas+St+La+Paz+Makati" target="_blank" rel="noopener">Open in Maps</a>
    </div>
  </div>
</section>

</main>
<?php page_end(); ?>
